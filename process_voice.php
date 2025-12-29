<?php
session_start();
require_once 'conexao.php';

// Get JSON data from n8n
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate required fields
if (!isset($data['user_id']) || !isset($data['description']) || !isset($data['amount'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

$user_id = (int) $data['user_id'];
$type = $data['type'] ?? 'expense';
$description = $data['description'];
$amount = (float) $data['amount'];
$category = $data['category'] ?? 'Outros';
$transaction_date = $data['transaction_date'] ?? date('Y-m-d');

try {
    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, description, amount, category, transaction_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $type, $description, $amount, $category, $transaction_date]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Transação registrada com sucesso!',
        'data' => [
            'id' => $pdo->lastInsertId(),
            'description' => $description,
            'amount' => $amount,
            'category' => $category,
            'type' => $type
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
