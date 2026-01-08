<?php
session_start();
require_once '../conexao.php';

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Não autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Get expenses by category for specific month
$stmt = $pdo->prepare("
    SELECT 
        category,
        SUM(amount) as total,
        COUNT(*) as count
    FROM transactions 
    WHERE user_id = ? 
        AND type = 'expense'
        AND MONTH(transaction_date) = ?
        AND YEAR(transaction_date) = ?
    GROUP BY category
    ORDER BY total DESC
");
$stmt->execute([$user_id, $month, $year]);
$monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total expenses by category (all time)
$stmt2 = $pdo->prepare("
    SELECT 
        category,
        SUM(amount) as total,
        COUNT(*) as count
    FROM transactions 
    WHERE user_id = ? AND type = 'expense'
    GROUP BY category
    ORDER BY total DESC
");
$stmt2->execute([$user_id]);
$totalData = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Get available months
$stmt3 = $pdo->prepare("
    SELECT DISTINCT 
        YEAR(transaction_date) as year,
        MONTH(transaction_date) as month
    FROM transactions 
    WHERE user_id = ?
    ORDER BY year DESC, month DESC
");
$stmt3->execute([$user_id]);
$availableMonths = $stmt3->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$monthlyTotal = array_sum(array_column($monthlyData, 'total'));
$grandTotal = array_sum(array_column($totalData, 'total'));

echo json_encode([
    'status' => 'success',
    'currentMonth' => $month,
    'currentYear' => $year,
    'monthly' => [
        'data' => $monthlyData,
        'total' => $monthlyTotal
    ],
    'allTime' => [
        'data' => $totalData,
        'total' => $grandTotal
    ],
    'availableMonths' => $availableMonths
]);
