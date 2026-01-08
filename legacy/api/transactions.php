<?php
session_start();
require_once '../conexao.php';

// Disable error display
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Não autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Get transaction ID from query string
$transaction_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

switch ($method) {
    case 'GET':
        // List all transactions for user
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM transactions 
                WHERE user_id = ? 
                ORDER BY transaction_date DESC, created_at DESC
            ");
            $stmt->execute([$user_id]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'status' => 'success',
                'transactions' => $transactions
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Erro ao buscar transações']);
        }
        break;
        
    case 'PUT':
        // Update a transaction
        if (!$transaction_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ID da transação não informado']);
            exit;
        }
        
        // Verify ownership
        $stmt = $pdo->prepare("SELECT id FROM transactions WHERE id = ? AND user_id = ?");
        $stmt->execute([$transaction_id, $user_id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Transação não encontrada']);
            exit;
        }
        
        // Get JSON body
        $input = json_decode(file_get_contents('php://input'), true);
        
        try {
            $stmt = $pdo->prepare("
                UPDATE transactions 
                SET type = ?, description = ?, amount = ?, category = ?, transaction_date = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([
                $input['type'] ?? 'expense',
                $input['description'],
                $input['amount'],
                $input['category'],
                $input['transaction_date'],
                $transaction_id,
                $user_id
            ]);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Transação atualizada com sucesso'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Erro ao atualizar transação']);
        }
        break;
        
    case 'DELETE':
        // Delete a transaction
        if (!$transaction_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ID da transação não informado']);
            exit;
        }
        
        // Verify ownership
        $stmt = $pdo->prepare("SELECT id FROM transactions WHERE id = ? AND user_id = ?");
        $stmt->execute([$transaction_id, $user_id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Transação não encontrada']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
            $stmt->execute([$transaction_id, $user_id]);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Transação removida com sucesso'
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Erro ao remover transação']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
}
