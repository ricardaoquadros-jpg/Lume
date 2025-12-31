<?php
session_start();
require_once '../conexao.php';

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
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

try {
    switch ($method) {
        case 'GET':
            // List all recurring transactions
            $stmt = $pdo->prepare("
                SELECT * FROM recurring_transactions 
                WHERE user_id = ? AND active = 1
                ORDER BY day_of_month ASC
            ");
            $stmt->execute([$user_id]);
            $recurring = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['status' => 'success', 'recurring' => $recurring]);
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            $type = $data['type'] ?? 'expense';
            $description = trim($data['description'] ?? '');
            $amount = (float) ($data['amount'] ?? 0);
            $category = $data['category'] ?? 'Outros';
            $frequency = $data['frequency'] ?? 'monthly';
            $day_of_month = (int) ($data['day_of_month'] ?? 1);
            $active = isset($data['active']) ? ($data['active'] ? 1 : 0) : 1;

            if (empty($description)) {
                throw new Exception('Descrição é obrigatória');
            }

            if ($amount <= 0) {
                throw new Exception('Valor deve ser maior que zero');
            }

            $stmt = $pdo->prepare("
                INSERT INTO recurring_transactions 
                (user_id, type, description, amount, category, frequency, day_of_month, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $type, $description, $amount, $category, $frequency, $day_of_month, $active]);

            echo json_encode(['status' => 'success', 'message' => 'Recorrência criada!', 'id' => $pdo->lastInsertId()]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int) ($data['id'] ?? 0);

            if (!$id) {
                throw new Exception('ID inválido');
            }

            // Verify ownership
            $stmt = $pdo->prepare("SELECT id FROM recurring_transactions WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            if (!$stmt->fetch()) {
                throw new Exception('Recorrência não encontrada');
            }

            $type = $data['type'] ?? 'expense';
            $description = trim($data['description'] ?? '');
            $amount = (float) ($data['amount'] ?? 0);
            $category = $data['category'] ?? 'Outros';
            $frequency = $data['frequency'] ?? 'monthly';
            $day_of_month = (int) ($data['day_of_month'] ?? 1);

            if (empty($description)) {
                throw new Exception('Descrição é obrigatória');
            }

            if ($amount <= 0) {
                throw new Exception('Valor deve ser maior que zero');
            }

            $stmt = $pdo->prepare("
                UPDATE recurring_transactions 
                SET type = ?, description = ?, amount = ?, category = ?, frequency = ?, day_of_month = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$type, $description, $amount, $category, $frequency, $day_of_month, $id, $user_id]);

            echo json_encode(['status' => 'success', 'message' => 'Recorrência atualizada!']);
            break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int) ($data['id'] ?? 0);

            if (!$id) {
                throw new Exception('ID inválido');
            }

            // Soft delete
            $stmt = $pdo->prepare("UPDATE recurring_transactions SET active = 0 WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);

            echo json_encode(['status' => 'success', 'message' => 'Recorrência removida']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
