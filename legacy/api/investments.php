<?php
session_start();
require_once '../conexao.php';

header('Content-Type: application/json');

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
            $investment_id = $_GET['id'] ?? null;
            
            if ($investment_id) {
                // Get single investment with transaction history
                $stmt = $pdo->prepare("
                    SELECT * FROM investments 
                    WHERE id = ? AND user_id = ? AND active = 1
                ");
                $stmt->execute([$investment_id, $user_id]);
                $investment = $stmt->fetch();
                
                if (!$investment) {
                    throw new Exception('Investimento não encontrado');
                }
                
                // Get transactions
                $stmt = $pdo->prepare("
                    SELECT * FROM investment_transactions 
                    WHERE investment_id = ?
                    ORDER BY transaction_date DESC, created_at DESC
                ");
                $stmt->execute([$investment_id]);
                $transactions = $stmt->fetchAll();
                
                // Calculate totals
                $total_deposited = 0;
                $total_withdrawn = 0;
                foreach ($transactions as $t) {
                    if ($t['type'] === 'deposit') $total_deposited += $t['amount'];
                    if ($t['type'] === 'withdrawal') $total_withdrawn += $t['amount'];
                }
                
                $net_invested = $total_deposited - $total_withdrawn;
                $current = (float)$investment['current_value'];
                $gain = $current - $net_invested;
                $yield = $net_invested > 0 ? ($gain / $net_invested) * 100 : 0;
                
                $investment['transactions'] = $transactions;
                $investment['total_deposited'] = round($total_deposited, 2);
                $investment['total_withdrawn'] = round($total_withdrawn, 2);
                $investment['net_invested'] = round($net_invested, 2);
                $investment['gain'] = round($gain, 2);
                $investment['yield_percent'] = round($yield, 2);
                
                echo json_encode(['status' => 'success', 'investment' => $investment]);
                
            } else {
                // List all investments with summary
                $stmt = $pdo->prepare("
                    SELECT i.*, 
                           COALESCE(SUM(CASE WHEN t.type = 'deposit' THEN t.amount ELSE 0 END), 0) as total_deposited,
                           COALESCE(SUM(CASE WHEN t.type = 'withdrawal' THEN t.amount ELSE 0 END), 0) as total_withdrawn
                    FROM investments i
                    LEFT JOIN investment_transactions t ON i.id = t.investment_id
                    WHERE i.user_id = ? AND i.active = 1
                    GROUP BY i.id
                    ORDER BY i.created_at DESC
                ");
                $stmt->execute([$user_id]);
                $investments = $stmt->fetchAll();
                
                $total_invested = 0;
                $total_current = 0;
                
                foreach ($investments as &$inv) {
                    $deposited = (float)$inv['total_deposited'];
                    $withdrawn = (float)$inv['total_withdrawn'];
                    $net = $deposited - $withdrawn;
                    $current = (float)$inv['current_value'];
                    $gain = $current - $net;
                    $yield = $net > 0 ? ($gain / $net) * 100 : 0;
                    
                    // Days since first transaction
                    $stmtFirst = $pdo->prepare("
                        SELECT MIN(transaction_date) as first_date 
                        FROM investment_transactions 
                        WHERE investment_id = ?
                    ");
                    $stmtFirst->execute([$inv['id']]);
                    $firstDate = $stmtFirst->fetchColumn();
                    
                    $days = 0;
                    if ($firstDate) {
                        $start = new DateTime($firstDate);
                        $now = new DateTime();
                        $days = $start->diff($now)->days;
                    }
                    
                    $monthly_yield = $days > 0 ? ($yield / $days) * 30 : 0;
                    
                    $inv['net_invested'] = round($net, 2);
                    $inv['gain'] = round($gain, 2);
                    $inv['yield_percent'] = round($yield, 2);
                    $inv['days_elapsed'] = $days;
                    $inv['monthly_projection'] = round($monthly_yield, 2);
                    
                    // Performance
                    if ($monthly_yield >= 1.0) {
                        $inv['performance'] = 'excellent';
                    } elseif ($monthly_yield >= 0.5) {
                        $inv['performance'] = 'good';
                    } elseif ($monthly_yield >= 0) {
                        $inv['performance'] = 'below';
                    } else {
                        $inv['performance'] = 'poor';
                    }
                    
                    $total_invested += $net;
                    $total_current += $current;
                }
                
                $total_gain = $total_current - $total_invested;
                $total_yield = $total_invested > 0 ? ($total_gain / $total_invested) * 100 : 0;
                
                echo json_encode([
                    'status' => 'success',
                    'investments' => $investments,
                    'summary' => [
                        'total_invested' => round($total_invested, 2),
                        'total_current' => round($total_current, 2),
                        'total_gain' => round($total_gain, 2),
                        'total_yield' => round($total_yield, 2)
                    ]
                ]);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) $data = $_POST;
            
            $action = $data['action'] ?? 'create';
            
            if ($action === 'create') {
                // Create new investment
                $name = trim($data['name'] ?? '');
                $type = $data['type'] ?? 'Outro';
                
                if (empty($name)) {
                    throw new Exception('Nome é obrigatório');
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO investments (user_id, name, type, current_value)
                    VALUES (?, ?, ?, 0)
                ");
                $stmt->execute([$user_id, $name, $type]);
                $investment_id = $pdo->lastInsertId();
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Investimento criado!',
                    'id' => $investment_id
                ]);
                
            } elseif ($action === 'deposit' || $action === 'withdrawal') {
                // Add deposit or withdrawal
                $investment_id = (int)$data['investment_id'];
                $amount = (float)$data['amount'];
                $date = $data['date'] ?? date('Y-m-d');
                $notes = $data['notes'] ?? '';
                
                if ($amount <= 0) {
                    throw new Exception('Valor deve ser maior que zero');
                }
                
                // Verify ownership
                $stmt = $pdo->prepare("SELECT id, current_value FROM investments WHERE id = ? AND user_id = ?");
                $stmt->execute([$investment_id, $user_id]);
                $inv = $stmt->fetch();
                if (!$inv) throw new Exception('Investimento não encontrado');
                
                // Calculate new balance
                $current = (float)$inv['current_value'];
                $new_balance = $action === 'deposit' ? $current + $amount : $current - $amount;
                
                // Insert transaction
                $stmt = $pdo->prepare("
                    INSERT INTO investment_transactions (investment_id, type, amount, balance_after, transaction_date, notes)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$investment_id, $action, $amount, $new_balance, $date, $notes]);
                
                // Update current value
                $stmt = $pdo->prepare("UPDATE investments SET current_value = ?, last_update = ? WHERE id = ?");
                $stmt->execute([$new_balance, $date, $investment_id]);
                
                $type_label = $action === 'deposit' ? 'Aporte' : 'Retirada';
                echo json_encode(['status' => 'success', 'message' => "$type_label registrado!"]);
                
            } elseif ($action === 'update_value') {
                // Update current market value (no deposit/withdrawal)
                $investment_id = (int)$data['investment_id'];
                $new_value = (float)$data['new_value'];
                $date = $data['date'] ?? date('Y-m-d');
                
                // Verify ownership
                $stmt = $pdo->prepare("SELECT id FROM investments WHERE id = ? AND user_id = ?");
                $stmt->execute([$investment_id, $user_id]);
                if (!$stmt->fetch()) throw new Exception('Investimento não encontrado');
                
                // Insert update transaction
                $stmt = $pdo->prepare("
                    INSERT INTO investment_transactions (investment_id, type, amount, balance_after, transaction_date)
                    VALUES (?, 'update', 0, ?, ?)
                ");
                $stmt->execute([$investment_id, $new_value, $date]);
                
                // Update current value
                $stmt = $pdo->prepare("UPDATE investments SET current_value = ?, last_update = ? WHERE id = ?");
                $stmt->execute([$new_value, $date, $investment_id]);
                
                echo json_encode(['status' => 'success', 'message' => 'Valor atualizado!']);
                
            } elseif ($action === 'edit_transaction') {
                // Edit an existing transaction
                $transaction_id = (int)$data['transaction_id'];
                $new_amount = (float)$data['amount'];
                $new_date = $data['date'] ?? null;
                
                // Get transaction and verify ownership
                $stmt = $pdo->prepare("
                    SELECT t.*, i.user_id 
                    FROM investment_transactions t
                    JOIN investments i ON t.investment_id = i.id
                    WHERE t.id = ?
                ");
                $stmt->execute([$transaction_id]);
                $trans = $stmt->fetch();
                
                if (!$trans || $trans['user_id'] != $user_id) {
                    throw new Exception('Transação não encontrada');
                }
                
                // Update transaction
                $stmt = $pdo->prepare("
                    UPDATE investment_transactions 
                    SET amount = ?, transaction_date = COALESCE(?, transaction_date)
                    WHERE id = ?
                ");
                $stmt->execute([$new_amount, $new_date, $transaction_id]);
                
                // Recalculate investment current value
                recalculateInvestmentValue($pdo, $trans['investment_id']);
                
                echo json_encode(['status' => 'success', 'message' => 'Transação atualizada!']);
            }
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (isset($data['transaction_id'])) {
                // Delete transaction
                $transaction_id = (int)$data['transaction_id'];
                
                // Get transaction and verify ownership
                $stmt = $pdo->prepare("
                    SELECT t.investment_id, i.user_id 
                    FROM investment_transactions t
                    JOIN investments i ON t.investment_id = i.id
                    WHERE t.id = ?
                ");
                $stmt->execute([$transaction_id]);
                $trans = $stmt->fetch();
                
                if (!$trans || $trans['user_id'] != $user_id) {
                    throw new Exception('Transação não encontrada');
                }
                
                // Delete
                $stmt = $pdo->prepare("DELETE FROM investment_transactions WHERE id = ?");
                $stmt->execute([$transaction_id]);
                
                // Recalculate
                recalculateInvestmentValue($pdo, $trans['investment_id']);
                
                echo json_encode(['status' => 'success', 'message' => 'Transação removida']);
                
            } else {
                // Delete investment
                $investment_id = (int)($data['id'] ?? 0);
                
                $stmt = $pdo->prepare("UPDATE investments SET active = 0 WHERE id = ? AND user_id = ?");
                $stmt->execute([$investment_id, $user_id]);
                
                echo json_encode(['status' => 'success', 'message' => 'Investimento removido']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

// Helper function to recalculate investment value after editing/deleting transactions
function recalculateInvestmentValue($pdo, $investment_id) {
    // Get all transactions ordered by date
    $stmt = $pdo->prepare("
        SELECT type, amount FROM investment_transactions 
        WHERE investment_id = ? 
        ORDER BY transaction_date ASC, created_at ASC
    ");
    $stmt->execute([$investment_id]);
    $transactions = $stmt->fetchAll();
    
    $balance = 0;
    $last_update_value = 0;
    
    foreach ($transactions as $t) {
        if ($t['type'] === 'deposit') {
            $balance += $t['amount'];
        } elseif ($t['type'] === 'withdrawal') {
            $balance -= $t['amount'];
        } elseif ($t['type'] === 'update') {
            // Update sets the absolute value (includes gains)
            $last_update_value = $t['amount'] > 0 ? $t['amount'] : $balance;
        }
    }
    
    // If there was an update, use that as current value
    // Otherwise, use the calculated balance (deposits - withdrawals)
    $current_value = $last_update_value > 0 ? $last_update_value : $balance;
    
    $stmt = $pdo->prepare("UPDATE investments SET current_value = ? WHERE id = ?");
    $stmt->execute([$current_value, $investment_id]);
}
