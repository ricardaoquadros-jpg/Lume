<?php
session_start();
require_once 'env.php';
require_once 'conexao.php';

// Disable error display
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Não autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get request body
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$message = $input['message'] ?? '';

// OpenRouter API Key from environment
$api_key = env('OPENROUTER_API_KEY');

// Get recent transactions for context
function getRecentTransactions($pdo, $user_id, $limit = 10) {
    $stmt = $pdo->prepare("
        SELECT id, type, description, amount, category, transaction_date, transcription
        FROM transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Send message to MiMo (via OpenRouter)
function askAI($api_key, $message, $transactions, $user_id) {
    // Format transactions for context
    $transactionList = "";
    foreach ($transactions as $i => $t) {
        $type = $t['type'] === 'income' ? 'Receita' : 'Despesa';
        $transactionList .= "ID:{$t['id']} | {$type} | {$t['description']} | R$ " . number_format($t['amount'], 2, ',', '.') . " | {$t['category']} | {$t['transaction_date']}\n";
    }
    
    $systemPrompt = <<<EOT
Você é o Lume, um assistente financeiro inteligente. Você ajuda o usuário a gerenciar suas finanças.

CONTEXTO - Últimas transações do usuário:
$transactionList

SUAS CAPACIDADES:
1. ADICIONAR transações (despesas ou receitas)
2. REMOVER transações existentes (use os IDs acima)
3. EDITAR transações existentes
4. RESPONDER perguntas sobre as finanças

REGRAS DE RESPOSTA:
- Sempre responda em JSON válido
- Use o campo "action" para indicar o que fazer
- Use "requires_confirmation" = true quando for fazer alterações
- Seja amigável e use emojis

FORMATOS DE RESPOSTA:

1. Para ADICIONAR transação(ões):
{
  "action": "add",
  "requires_confirmation": true,
  "message": "Entendi! Vou adicionar:",
  "transactions": [{"description": "...", "amount": 0.00, "category": "...", "type": "expense"}]
}

2. Para REMOVER transação(ões):
{
  "action": "remove",
  "requires_confirmation": true,
  "message": "Vou remover estas transações:",
  "transaction_ids": [1, 2, 3]
}

3. Para EDITAR transação:
{
  "action": "edit",
  "requires_confirmation": true,
  "message": "Vou alterar a transação:",
  "transaction_id": 1,
  "changes": {"amount": 50.00, "description": "novo nome"}
}

4. Para apenas RESPONDER (sem ação):
{
  "action": "reply",
  "requires_confirmation": false,
  "message": "Sua resposta aqui..."
}

5. Quando NÃO ENTENDER:
{
  "action": "clarify",
  "requires_confirmation": false,
  "message": "Não entendi bem. Você quer adicionar uma despesa, remover algo, ou apenas conversar?"
}

Responda APENAS com JSON válido, sem texto adicional.
EOT;

    $curl = curl_init();
    
    $payload = json_encode([
        'model' => 'meta-llama/llama-3.3-70b-instruct:free',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $message]
        ],
        'temperature' => 0.3
    ]);
    
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://openrouter.ai/api/v1/chat/completions',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json',
            'HTTP-Referer: http://localhost:8000',
            'X-Title: Lume Finance'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 60
    ]);
    
    $response = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($error) {
        return ['action' => 'error', 'message' => 'Erro ao conectar com AI: ' . $error];
    }
    
    $data = json_decode($response, true);
    
    // Debug: log the response
    error_log("MiMo Response: " . substr($response, 0, 500));
    
    $content = $data['choices'][0]['message']['content'] ?? '';
    
    // Parse JSON from response
    $content = preg_replace('/```json\n?|```/', '', $content);
    $result = json_decode(trim($content), true);
    
    if (!$result) {
        // If JSON parsing fails, return as reply
        return ['action' => 'reply', 'message' => $content ?: 'Desculpe, não consegui processar a resposta.'];
    }
    
    return $result;
}

// Execute confirmed action
function executeAction($pdo, $user_id, $actionData) {
    $action = $actionData['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $transactions = $actionData['transactions'] ?? [];
            $inserted = [];
            $stmt = $pdo->prepare("
                INSERT INTO transactions (user_id, type, description, amount, category, transaction_date)
                VALUES (?, ?, ?, ?, ?, CURDATE())
            ");
            foreach ($transactions as $t) {
                $stmt->execute([
                    $user_id,
                    $t['type'] ?? 'expense',
                    $t['description'],
                    $t['amount'],
                    $t['category'] ?? 'Outros'
                ]);
                $inserted[] = $pdo->lastInsertId();
            }
            return ['status' => 'success', 'message' => '✅ ' . count($inserted) . ' transação(ões) adicionada(s)!', 'ids' => $inserted];
            
        case 'remove':
            $ids = $actionData['transaction_ids'] ?? [];
            if (empty($ids)) {
                return ['status' => 'error', 'message' => 'Nenhum ID para remover'];
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM transactions WHERE id IN ($placeholders) AND user_id = ?");
            $params = array_merge($ids, [$user_id]);
            $stmt->execute($params);
            return ['status' => 'success', 'message' => '✅ ' . count($ids) . ' transação(ões) removida(s)!'];
            
        case 'edit':
            $id = $actionData['transaction_id'] ?? 0;
            $changes = $actionData['changes'] ?? [];
            if (!$id || empty($changes)) {
                return ['status' => 'error', 'message' => 'Dados inválidos para edição'];
            }
            
            $sets = [];
            $params = [];
            foreach ($changes as $field => $value) {
                if (in_array($field, ['description', 'amount', 'category', 'type', 'transaction_date'])) {
                    $sets[] = "$field = ?";
                    $params[] = $value;
                }
            }
            if (empty($sets)) {
                return ['status' => 'error', 'message' => 'Nenhum campo válido para editar'];
            }
            
            $params[] = $id;
            $params[] = $user_id;
            $sql = "UPDATE transactions SET " . implode(', ', $sets) . " WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return ['status' => 'success', 'message' => '✅ Transação atualizada!'];
            
        default:
            return ['status' => 'error', 'message' => 'Ação não suportada'];
    }
}

// Handle different actions
switch ($action) {
    case 'chat':
        // Get AI response
        $transactions = getRecentTransactions($pdo, $user_id);
        $response = askAI($api_key, $message, $transactions, $user_id);
        
        // Enrich response with transaction details for preview
        if ($response['action'] === 'remove' && isset($response['transaction_ids'])) {
            $ids = $response['transaction_ids'];
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id IN ($placeholders) AND user_id = ?");
            $params = array_merge($ids, [$user_id]);
            $stmt->execute($params);
            $response['transactions_to_remove'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode($response);
        break;
        
    case 'confirm':
        // Execute the confirmed action
        $pendingAction = $input['pending_action'] ?? null;
        if (!$pendingAction) {
            echo json_encode(['status' => 'error', 'message' => 'Nenhuma ação pendente']);
            exit;
        }
        
        $result = executeAction($pdo, $user_id, $pendingAction);
        echo json_encode($result);
        break;
        
    case 'get_context':
        // Return recent transactions for display
        $transactions = getRecentTransactions($pdo, $user_id, 5);
        echo json_encode(['status' => 'success', 'transactions' => $transactions]);
        break;
        
    default:
        echo json_encode(['status' => 'error', 'message' => 'Ação inválida']);
}
