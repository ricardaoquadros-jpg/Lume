<?php
session_start();
require_once 'env.php';
require_once 'conexao.php';

// Load environment variables
loadEnv(__DIR__ . '/.env');

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

// --- HELPER FUNCTIONS ---

// 1. Get Financial Stats (Balance, Income, Expense, Real-Time Earnings)
function getFinancialStats($pdo, $user_id)
{
    // A. Get Profile & Settings
    $stmt = $pdo->prepare("SELECT * FROM work_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        return ['error' => 'Perfil não encontrado'];
    }

    // Capture the AI Profile Text
    $ai_profile_data = $profile['ai_profile_data'] ?? '';

    $salary = (float) $profile['salary'];
    $initial_balance = (float) $profile['initial_balance'];

    // Work Schedule Logic
    $work_start = $profile['work_start'];
    $work_end = $profile['work_end'];
    $work_days = json_decode($profile['work_days'] ?? '[]', true);

    // Rates Calculation
    $days_worked_month = count($work_days) ?: 20;
    $daily_salary = $salary / $days_worked_month;

    // Calculate Work Hours Total
    $start_mins = (int) strtotime($work_start) / 60;
    $end_mins = (int) strtotime($work_end) / 60;
    $d1 = new DateTime($work_start);
    $d2 = new DateTime($work_end);
    if ($d2 < $d1)
        $d2->modify('+1 day');
    $interval = $d1->diff($d2);
    $work_hours_total = $interval->h + ($interval->i / 60);
    if ($work_hours_total <= 0)
        $work_hours_total = 8;
    $hourly_rate = $daily_salary / $work_hours_total;

    // Calculate "Earned Static"
    $now = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
    $current_day = (int) $now->format('j');
    $days_passed = 0;
    foreach ($work_days as $d) {
        if ($d < $current_day)
            $days_passed++;
    }
    $earned_month_static = $days_passed * $daily_salary;

    // Calculate "Earned Today"
    $earned_today = 0;
    $is_work_day = in_array($current_day, $work_days);
    if ($is_work_day) {
        $start_dt = new DateTime($work_start);
        $end_dt = new DateTime($work_end);
        $start_dt->setDate((int) $now->format('Y'), (int) $now->format('m'), (int) $now->format('d'));
        $end_dt->setDate((int) $now->format('Y'), (int) $now->format('m'), (int) $now->format('d'));
        if ($end_dt < $start_dt)
            $end_dt->modify('+1 day');

        if ($now >= $end_dt) {
            $earned_today = $daily_salary;
        } elseif ($now > $start_dt) {
            $diff = $start_dt->diff($now);
            $mins_worked = ($diff->h * 60) + $diff->i + ($diff->s / 60);
            $mins_total = ($work_hours_total * 60);
            $pct = $mins_worked / $mins_total;
            if ($pct > 1)
                $pct = 1;
            $earned_today = $daily_salary * $pct;
        }
    }
    $total_month_realtime = $earned_month_static + $earned_today;

    // Get DB Totals
    $stmt = $pdo->prepare("SELECT type, SUM(amount) as total FROM transactions WHERE user_id = ? GROUP BY type");
    $stmt->execute([$user_id]);
    $totals = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $total_income_db = $totals['income'] ?? 0;
    $total_expenses_db = $totals['expense'] ?? 0;
    $current_balance = ($initial_balance + $total_income_db) - $total_expenses_db;

    // Monthly DB Flows
    $stmt = $pdo->prepare("
        SELECT type, SUM(amount) as total 
        FROM transactions 
        WHERE user_id = ? 
        AND MONTH(transaction_date) = MONTH(CURRENT_DATE()) 
        AND YEAR(transaction_date) = YEAR(CURRENT_DATE())
        GROUP BY type
    ");
    $stmt->execute([$user_id]);
    $month_totals = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    return [
        'ai_profile_data' => $ai_profile_data, // KEY NEW FIELD
        'balance' => $current_balance, // Saldo Real
        'salary' => $salary,
        'hourly_rate' => $hourly_rate,
        'daily_salary' => $daily_salary,
        'earned_today_realtime' => $earned_today,
        'earned_month_realtime' => $total_month_realtime,
        'month_expenses_db' => $month_totals['expense'] ?? 0,
        'month_income_db' => $month_totals['income'] ?? 0 // Manual extra income
    ];
}

// 2. Get recent transactions
function getRecentTransactions($pdo, $user_id, $limit = 20)
{
    $stmt = $pdo->prepare("
        SELECT id, type, description, amount, category, transaction_date
        FROM transactions 
        WHERE user_id = ? 
        ORDER BY transaction_date DESC, created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 3. Save AI Profile
function saveAIProfile($pdo, $user_id, $profile_text)
{
    try {
        $stmt = $pdo->prepare("UPDATE work_profiles SET ai_profile_data = ? WHERE user_id = ?");
        $stmt->execute([$profile_text, $user_id]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// 4. Send message to AI (Smart Profiling)
function askAI($api_key, $message, $transactions, $stats, $user_id, $pdo = null)
{
    // Format stats
    $bal = number_format($stats['balance'], 2, ',', '.');
    $sal = number_format($stats['salary'], 2, ',', '.');
    $hr = number_format($stats['hourly_rate'], 2, ',', '.');
    $dayRev = number_format($stats['earned_today_realtime'], 2, ',', '.');
    $monthRev = number_format($stats['earned_month_realtime'], 2, ',', '.');
    $monthExp = number_format($stats['month_expenses_db'], 2, ',', '.');

    // Day Progress %
    $dayPct = ($stats['earned_today_realtime'] / $stats['daily_salary']) * 100;
    $dayPctStr = number_format($dayPct, 1, ',', '.');

    // Transactions list
    $tList = "";
    foreach ($transactions as $t) {
        $type = $t['type'] === 'income' ? 'Receita' : 'Despesa';
        $tList .= "[ID:{$t['id']}] {$t['transaction_date']} | {$type} | {$t['category']} | {$t['description']} | R$ " . number_format($t['amount'], 2, ',', '.') . "\n";
    }

    // --- PROFILING LOGIC ---
    $hasProfile = !empty($stats['ai_profile_data']);
    $userProfileText = $stats['ai_profile_data'] ?: "DESCONHECIDO";

    if (!$hasProfile) {
        // --- MODE: INTERVIEWER ---
        $systemPrompt = <<<EOT
Você é o LUME AI. **Sua tarefa agora é APENAS descobrir o perfil do usuário.**
Seja EXTREMAMENTE BREVE. Fale como um amigo em um chat rápido (Whatsapp).

=== MISSÃO ===
Faça perguntas para descobrir: Idade, Profissão, Estado Civil, Objetivo e Perfil de Risco.

=== REGRAS DE OURO ===
1. **PERGUNTE UMA COISA DE CADA VEZ.** (Nunca mande uma lista).
2. **TEXTO CURTO:** Máximo 15 palavras por mensagem.
3. Se já souber tudo, use `save_profile`.

=== JSON OUTPUT ===
1. SAVE: { "action": "save_profile", "message": "Show! Perfil anotado.", "profile_text": "..." }
2. PERGUNTAR: { "action": "reply", "message": "E qual sua idade?" }
EOT;

    } else {
        // --- MODE: ELITE ADVISOR ---
        $systemPrompt = <<<EOT
Você é o LUME AI, um Consultor Financeiro amigo e direto.
Você conhece o usuário. **NÃO SEJA ROBÓTICO.**

=== PERFIL (MEMÓRIA) ===
{$userProfileText}

=== DADOS (TEMPO REAL) ===
💰 Saldo: R$ {$bal}
💸 Despesas Mês: R$ {$monthExp}
⏱️ Ganho Hoje: R$ {$dayRev}

=== TRANSAÇÕES RECENTES ===
{$tList}

=== SUA MISSÃO ===
1. Dê conselhos baseados no PERFIL acima.
2. Gerencie finanças (add/remove) se pedido.

=== REGRAS DE COMPORTAMENTO ===
1. **SEJA CURTO:** Responda em no máximo 2 frases curtas.
2. **DIRETO AO PONTO:** Não enrole. Dê a solução primeiro.
3. **SEM LISTAS LONGAS:** Só dê detalhes se o usuário perguntar "Por que?" ou "Detalhe".
4. **TOM DE VOZ:** Informal, confidente, "Brother".

Exemplo Ruim: "Com base no seu saldo atual de X, eu sugiro que você considere..."
Exemplo Bom: "Tô vendo que sobrou uma grana aqui. Que tal investir no CDB hoje?"

=== JSON OUTPUT ===
Responda APENAS JSON.
1. REPLY: { "action": "reply", "message": "Texto curto aqui..." }
2. ADICIONAR: { "action": "add", "requires_confirmation": true, "message": "Adicionando...", "transactions": [...] }
... (outras actions iguais)
EOT;
    }

    // Call API (OpenRouter)
    $curl = curl_init();
    $payload = json_encode([
        'model' => 'meta-llama/llama-3.3-70b-instruct:free',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $message]
        ],
        'temperature' => 0.4
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
            'X-Title: Lume Finance Expert'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 60
    ]);

    $response = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);

    if ($error)
        return ['action' => 'error', 'message' => 'Erro AI: ' . $error];

    $data = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? '';
    // Clean markdown
    $content = preg_replace('/```json\n?|```/', '', $content);
    $result = json_decode(trim($content), true);

    if (!$result)
        return ['action' => 'reply', 'message' => $content ?: 'Erro de pensamento AI.'];

    // Handle Save Profile Action Internal
    if (isset($result['action']) && $result['action'] === 'save_profile' && $pdo) {
        saveAIProfile($pdo, $user_id, $result['profile_text']);
        // Optional: Recursively call self to give immediate advice? Or just return success msg.
        // For simplicity, return the message. User will ask next q.
    }

    return $result;
}

// 4. Action Executor (Same as before, simplified for this context)
function executeAction($pdo, $user_id, $actionData)
{
    // ... (Existing Logic for Add/Remove/Edit) ...
    // Copying existing logic for stability
    $action = $actionData['action'] ?? '';
    switch ($action) {
        case 'add':
            $transactions = $actionData['transactions'] ?? [];
            $inserted = [];
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, description, amount, category, transaction_date, created_at) VALUES (?, ?, ?, ?, ?, CURDATE(), NOW())");
            foreach ($transactions as $t) {
                $stmt->execute([$user_id, $t['type'] ?? 'expense', $t['description'], $t['amount'], $t['category'] ?? 'Outros']);
                $inserted[] = $pdo->lastInsertId();
            }
            return ['status' => 'success', 'message' => '✅ Transações adicionadas com sucesso!', 'ids' => $inserted];

        case 'remove':
            $ids = $actionData['transaction_ids'] ?? [];
            if (empty($ids))
                return ['status' => 'error', 'message' => 'Nenhum ID'];
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM transactions WHERE id IN ($placeholders) AND user_id = ?");
            $params = array_merge($ids, [$user_id]);
            $stmt->execute($params);
            return ['status' => 'success', 'message' => '✅ Transações removidas!'];

        case 'edit':
            $id = $actionData['transaction_id'] ?? 0;
            $changes = $actionData['changes'] ?? [];
            if (!$id)
                return ['status' => 'error', 'message' => 'ID inválido'];
            $sets = [];
            $params = [];
            foreach ($changes as $k => $v) {
                if (in_array($k, ['description', 'amount', 'category', 'type', 'transaction_date'])) {
                    $sets[] = "$k = ?";
                    $params[] = $v;
                }
            }
            if (empty($sets))
                return ['status' => 'error', 'message' => 'Nada para editar'];
            $params[] = $id;
            $params[] = $user_id;
            $stmt = $pdo->prepare("UPDATE transactions SET " . implode(', ', $sets) . " WHERE id = ? AND user_id = ?");
            $stmt->execute($params);
            return ['status' => 'success', 'message' => '✅ Transação atualizada!'];

        default:
            return ['status' => 'error', 'message' => 'Ação desconhecida'];
    }
}

// === MAIN HANDLER ===

switch ($action) {
    case 'chat':
        // 1. Get Financial Context
        $stats = getFinancialStats($pdo, $user_id);
        $transactions = getRecentTransactions($pdo, $user_id, 20); // More context

        // 2. Ask AI
        $response = askAI($api_key, $message, $transactions, $stats, $user_id, $pdo);

        // 3. Hydrate 'remove' previews
        if ($response['action'] === 'remove' && !empty($response['transaction_ids'])) {
            $ids = $response['transaction_ids'];
            $p = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id IN ($p) AND user_id = ?");
            $stmt->execute(array_merge($ids, [$user_id]));
            $response['transactions_to_remove'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode($response);
        break;

    case 'confirm':
        // Execute pending action
        $pending = $input['pending_action'] ?? null;
        if ($pending) {
            $result = executeAction($pdo, $user_id, $pending);
            echo json_encode($result);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Nenhuma ação']);
        }
        break;

    case 'get_context':
        $transactions = getRecentTransactions($pdo, $user_id, 10);
        echo json_encode(['status' => 'success', 'transactions' => $transactions]);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Ação inválida']);
}
