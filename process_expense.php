<?php
session_start();
require_once 'conexao.php';

// Simulate reading JSON input
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

$user_id = $_SESSION['user_id'];

// MOCK AI LOGIC: Randomly generate an expense
$descriptions = ['Café na padaria', 'Uber para o centro', 'Almoço no shopping', 'Mercado semanal'];
$mock_desc = $descriptions[array_rand($descriptions)];
$mock_amount = rand(500, 5000) / 100; // 5.00 to 50.00

$stmt = $pdo->prepare("INSERT INTO expenses (user_id, description, amount) VALUES (?, ?, ?)");
$stmt->execute([$user_id, $mock_desc, $mock_amount]);

echo json_encode([
    'success' => true,
    'data' => [
        'description' => $mock_desc,
        'amount' => $mock_amount
    ]
]);
?>