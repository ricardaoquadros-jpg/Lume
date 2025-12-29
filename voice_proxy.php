<?php
session_start();
require_once 'conexao.php';

// Disable error display to prevent HTML in JSON response
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Não autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Receive audio from frontend
if (!isset($_FILES['audio'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Arquivo de áudio não enviado']);
    exit;
}

$audioFile = $_FILES['audio']['tmp_name'];
$audioName = $_FILES['audio']['name'];

// Forward to n8n webhook (OpenAI version)
$n8nUrl = 'https://ricardoquadross.app.n8n.cloud/webhook-test/lume-voice-openai';

$curl = curl_init();

$postData = [
    'audio' => new CURLFile($audioFile, 'audio/webm', $audioName),
    'user_id' => $user_id
];

curl_setopt_array($curl, [
    CURLOPT_URL => $n8nUrl,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);

// Log for debugging
error_log("n8n Response Code: " . $httpCode);
error_log("n8n Response: " . substr($response ?: '', 0, 500));

if ($error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao conectar com n8n: ' . $error]);
    exit;
}

if ($httpCode !== 200) {
    http_response_code($httpCode ?: 500);
    echo json_encode(['status' => 'error', 'message' => 'n8n retornou erro: ' . $httpCode, 'response' => $response]);
    exit;
}

if (empty($response)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'n8n retornou resposta vazia']);
    exit;
}

// Parse n8n response
$data = json_decode($response, true);

if (!$data || !isset($data['status']) || $data['status'] !== 'success') {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Resposta inválida do n8n', 'response' => $response]);
    exit;
}

// Handle multiple transactions
$transactions = $data['transactions'] ?? [];

if (empty($transactions)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Nenhuma transação identificada no áudio']);
    exit;
}

$insertedTransactions = [];

try {
    $stmt = $pdo->prepare("
        INSERT INTO transactions (user_id, type, description, amount, category, transaction_date, transcription)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($transactions as $t) {
        $stmt->execute([
            $user_id,
            $t['type'] ?? 'expense',
            $t['description'],
            $t['amount'],
            $t['category'] ?? 'Outros',
            $t['transaction_date'],
            $t['transcription'] ?? null
        ]);
        
        $insertedTransactions[] = [
            'id' => $pdo->lastInsertId(),
            'type' => $t['type'] ?? 'expense',
            'description' => $t['description'],
            'amount' => $t['amount'],
            'category' => $t['category'] ?? 'Outros',
            'transaction_date' => $t['transaction_date'],
            'transcription' => $t['transcription'] ?? null
        ];
    }
    
    $count = count($insertedTransactions);
    $message = $count === 1 
        ? 'Transação registrada com sucesso!' 
        : $count . ' transações registradas com sucesso!';
    
    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'count' => $count,
        'transactions' => $insertedTransactions
    ]);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar no banco de dados: ' . $e->getMessage()]);
}
