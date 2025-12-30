<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
date_default_timezone_set('America/Sao_Paulo');

// --- 1. Fetch User & Profile Data ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_transaction') {
    $type = $_POST['type'];
    $desc = $_POST['description'];
    $cat = $_POST['category'];
    $date = $_POST['transaction_date'];

    $amountStr = $_POST['amount'];
    $amountStr = str_replace('.', '', $amountStr);
    $amount = (float) str_replace(',', '.', $amountStr);

    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, description, amount, category, transaction_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $type, $desc, $amount, $cat, $date]);

    header("Location: dashboard.php");
    exit;
}

$stmtUser = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmtUser->execute([$user_id]);
$user = $stmtUser->fetch();
$username = $user['username'] ?? 'Usuário';

$stmt = $pdo->prepare("SELECT * FROM work_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

if (!$profile) {
    header("Location: setup.php");
    exit;
}

// --- 2. Financial Constants & Rates ---
$salary = (float) $profile['salary'];
$payment_type = $profile['payment_type'];
$initial_balance = (float) $profile['initial_balance'];

// Time Config
$work_start = $profile['work_start'];
$work_end = $profile['work_end'];
$interval_active = $profile['has_interval'];
$interval_start = $profile['interval_start'];
$interval_end = $profile['interval_end'];
$work_days = json_decode($profile['work_days'] ?? '[]', true);

// Rates Calculation
$days_worked_month = count($work_days) ?: 20;
$daily_salary = $salary / $days_worked_month;

function timeToMins($time)
{
    list($h, $m) = explode(':', $time);
    return ($h * 60) + $m;
}

$start_mins = timeToMins($work_start);
$end_mins = timeToMins($work_end);
if ($end_mins < $start_mins)
    $end_mins += 1440;

$work_mins_total = $end_mins - $start_mins;

if ($interval_active && $interval_start && $interval_end) {
    $int_start_mins = timeToMins($interval_start);
    $int_end_mins = timeToMins($interval_end);
    if ($int_end_mins < $int_start_mins)
        $int_end_mins += 1440;

    $interval_duration = $int_end_mins - $int_start_mins;
    if ($interval_duration > 0)
        $work_mins_total -= $interval_duration;
}

$work_hours_total = $work_mins_total / 60;
if ($work_hours_total <= 0)
    $work_hours_total = 8;

$hourly_rate = $daily_salary / $work_hours_total;
$minutely_rate = $hourly_rate / 60;
$secondly_rate = $hourly_rate / 3600;
$weekly_salary = $daily_salary * (count($work_days) / 4); // Approx

// --- 3. Current State ---
$now = new DateTime();
$current_day = (int) $now->format('j');
$is_today_work = in_array($current_day, $work_days);

// Earned Static (Past days)
$days_passed = 0;
foreach ($work_days as $d) {
    if ($d < $current_day)
        $days_passed++;
}
$earned_month_static = $days_passed * $daily_salary;

// --- 4. Expenses & Balance Logic ---
$stmt_exp = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ?");
$stmt_exp->execute([$user_id]);
$all_transactions = $stmt_exp->fetchAll();

$total_expenses = 0;
foreach ($all_transactions as $t) {
    if ($t['type'] == 'expense') {
        $total_expenses += $t['amount'];
    }
}

// Current Balance = Initial Balance + Income - Expenses
$total_income = 0;
foreach ($all_transactions as $t) {
    if ($t['type'] == 'income') {
        $total_income += $t['amount'];
    }
}

// User requested Saldo em Conta = Initial. But usually it's Initial + Income - Expense. 
// He said "Without summing monthly earnings (salary)". So manual Transactions should count?
// "Saldo em conta tem que ser o saldo bancario que eu inseri, sem ser somar ele com os ganhos do mes"
// This implies he wants Manual Transactions to affect balance, but NOT the "Pro-rated Salary".
// So: Current Balance = Initial Balance + Manual Income - Manual Expenses.
$current_balance = ($initial_balance + $total_income) - $total_expenses;

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard Lume</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg: #F5F5F7;
            --card: #FFFFFF;
            --text: #1D1D1F;
            --sub: #86868B;
            --accent: #0b3680;
            --danger: #FF3B30;
            --border: #E5E5EA;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background: var(--bg);
            color: var(--text);
            padding-bottom: 40px;
            min-height: 100vh;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 24px;
        }

        /* Header */
        header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
        }

        .welcome h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--text);
        }

        .welcome p {
            color: var(--sub);
            font-size: 14px;
        }

        .header-actions {
            display: flex;
            gap: 8px;
        }

        .btn-header {
            text-decoration: none;
            color: white;
            background: var(--accent);
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: opacity 0.2s;
        }

        .btn-header:hover {
            opacity: 0.9;
        }

        /* Grid Layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Card Styles */
        .card {
            background: var(--card);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 200px;
            position: relative;
            border: 1px solid rgba(0, 0, 0, 0.02);
        }

        .card-title {
            font-size: 14px;
            color: var(--sub);
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .big-value {
            font-size: 42px;
            font-weight: 800;
            color: var(--accent);
            letter-spacing: -1px;
            margin: 16px 0 8px;
        }

        .sub-text {
            font-size: 12px;
            color: var(--sub);
            font-weight: 500;
        }

        /* Progress Bar */
        .progress-container {
            width: 100%;
            background: #F2F2F7;
            height: 10px;
            border-radius: 5px;
            overflow: hidden;
            margin-top: 16px;
        }

        .progress-fill {
            height: 100%;
            background: var(--accent);
            width: 0%;
            transition: width 0.5s ease;
        }

        /* Specific Card: Financial Summary */
        .summary-card {
            align-items: stretch;
            text-align: left;
        }

        .summary-header {
            margin-bottom: 24px;
        }

        .summary-label {
            font-size: 12px;
            color: var(--sub);
            font-weight: 600;
            text-transform: uppercase;
        }

        .summary-total {
            font-size: 32px;
            font-weight: 700;
            color: var(--text);
            margin-top: 4px;
        }

        .summary-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 12px;
        }

        .summary-item:last-child {
            border: none;
        }

        .summary-item span:first-child {
            color: var(--sub);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .summary-item span:last-child {
            font-weight: 600;
            color: var(--text);
        }

        /* Daily Progress */
        .timer-display {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 4px;
        }

        .date-display {
            font-size: 12px;
            color: var(--sub);
            margin-bottom: 24px;
        }

        .time-labels {
            display: flex;
            justify-content: space-between;
            width: 100%;
            font-size: 12px;
            color: var(--sub);
            margin-bottom: 8px;
        }
    </style>
</head>

<body>

    <div class="container">
        <header>
            <div class="welcome">
                <h1>Olá, <?php echo htmlspecialchars($username); ?>!</h1>
                <p>Seu Painel - Visão geral e simplificada.</p>
            </div>
            <div class="header-actions">
                <!-- Edit Button -->
                <a href="setup.php" class="btn-header">
                    <i data-lucide="edit-2" width="14"></i> Editar
                </a>
                <!-- Reset Button -->
                <a href="reset_profile.php" class="btn-header"
                    onclick="return confirm('Tem certeza? Isso apagará todas as configurações.');">
                    Configurar
                </a>
                <a href="logout.php" class="btn-header" style="background:transparent; border:1px solid #3A3A3C;">
                    <i data-lucide="log-out" width="14"></i>
                </a>
            </div>
        </header>

        <div class="dashboard-grid">

            <!-- Card 1: Ganhos do Dia -->
            <div class="card">
                <div class="card-title"><i data-lucide="wallet" width="16"></i> Ganhos do Dia (Tempo Real)</div>
                <div class="big-value">R$ <span id="live-earnings">0,00</span></div>
                <div class="sub-text" id="work-status-text">Fora do expediente.</div>
            </div>

            <!-- Card 2: Ganhos do Mês -->
            <div class="card">
                <div class="card-title"><i data-lucide="calendar" width="16"></i> Ganhos do Mês (Tempo Real)</div>
                <div class="big-value">R$ <span id="month-earnings">0,00</span></div>
                <div class="progress-container">
                    <div id="month-bar" class="progress-fill"></div>
                </div>
                <div class="sub-text" style="margin-top: 8px;"><span id="month-pct">0%</span> do salário mensal</div>
            </div>

            <!-- Card 3: Progresso do Dia -->
            <div class="card">
                <div class="card-title" style="align-self: flex-start;"><i data-lucide="clock" width="16"></i> Progresso
                    do Dia</div>

                <div style="text-align: center; margin-top: 12px;">
                    <div class="timer-display" id="clock-now">--:--:--</div>
                    <div class="date-display">
                        <?php
                        $months = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
                        $days = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
                        $now_dt = new DateTime();
                        echo $days[$now_dt->format('w')] . ', ' . $now_dt->format('d') . ' de ' . $months[$now_dt->format('n') - 1] . ' de ' . $now_dt->format('Y');
                        ?>
                    </div>
                </div>

                <div class="time-labels">
                    <span>Início: <?php echo substr($work_start, 0, 5); ?></span>
                    <span>Fim: <?php echo substr($work_end, 0, 5); ?></span>
                </div>
                <div class="progress-container" style="background: #F2F2F7; height: 8px; margin-top: 0;">
                    <div id="day-bar" class="progress-fill" style="background: var(--accent);"></div>
                </div>
                <div class="sub-text" id="day-pct" style="margin-top: 8px;">0.00%</div>
            </div>

            <!-- Card 4: Resumo Financeiro -->
            <div class="card summary-card">
                <div class="card-title"><i data-lucide="trending-up" width="16"></i> Resumo Financeiro</div>
                <div class="summary-header">
                    <div class="summary-label">Patrimônio Líquido</div>
                    <div class="summary-total">R$ <span
                            id="net-worth"><?php echo number_format($current_balance, 2, ',', '.'); ?></span></div>
                </div>
                <div class="summary-list">
                    <div class="summary-item">
                        <span><i data-lucide="dollar-sign" width="14"></i> Salário</span>
                        <span>R$ <?php echo number_format($salary, 2, ',', '.'); ?></span>
                    </div>
                    <div class="summary-item">
                        <span><i data-lucide="calendar-check" width="14"></i> Ganho Semanal</span>
                        <span>R$ <?php echo number_format($weekly_salary, 2, ',', '.'); ?></span>
                    </div>
                    <div class="summary-item">
                        <span><i data-lucide="sun" width="14"></i> Ganho Diário Total</span>
                        <span>R$ <?php echo number_format($daily_salary, 2, ',', '.'); ?></span>
                    </div>
                    <div class="summary-item">
                        <span><i data-lucide="hourglass" width="14"></i> Ganho por Hora</span>
                        <span>R$ <?php echo number_format($hourly_rate, 2, ',', '.'); ?></span>
                    </div>
                    <div class="summary-item">
                        <span><i data-lucide="landmark" width="14"></i> Saldo em Conta</span>
                        <span>R$ <?php echo number_format($current_balance, 2, ',', '.'); ?></span>
                    </div>
                    <div class="summary-item">
                        <span><i data-lucide="credit-card" width="14"></i> Despesas do Mês</span>
                        <span>R$ <?php echo number_format($total_expenses, 2, ',', '.'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Category Charts Section -->
        <div style="margin-top: 40px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 class="recents-title" style="margin:0;">Despesas por Categoria</h3>
                <div style="display:flex; align-items:center; gap:12px;">
                    <button onclick="changeMonth(-1)"
                        style="background:var(--card); border:1px solid var(--border); padding:6px 10px; border-radius:6px; cursor:pointer;">
                        <i data-lucide="chevron-left" width="16"></i>
                    </button>
                    <span id="current-month-label" style="font-weight:600; min-width:120px; text-align:center;">Dezembro
                        2024</span>
                    <button onclick="changeMonth(1)"
                        style="background:var(--card); border:1px solid var(--border); padding:6px 10px; border-radius:6px; cursor:pointer;">
                        <i data-lucide="chevron-right" width="16"></i>
                    </button>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <!-- Monthly Chart -->
                <div
                    style="background:var(--card); border-radius:12px; padding:20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                        <div style="font-weight:600; font-size:14px; color:var(--text);">Despesas do Mês</div>
                        <div id="monthly-total" style="font-weight:700; font-size:18px; color:var(--danger);">R$ 0,00
                        </div>
                    </div>
                    <div style="height:250px; display:flex; justify-content:center; align-items:center;">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>

                <!-- All Time Chart -->
                <div
                    style="background:var(--card); border-radius:12px; padding:20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                        <div style="font-weight:600; font-size:14px; color:var(--text);">Despesas Totais</div>
                        <div id="alltime-total" style="font-weight:700; font-size:18px; color:var(--danger);">R$ 0,00
                        </div>
                    </div>
                    <div style="height:250px; display:flex; justify-content:center; align-items:center;">
                        <canvas id="alltimeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. Transaction History Section -->
        <div style="margin-top: 40px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 class="recents-title" style="margin:0;">Histórico Financeiro</h3>
                <button onclick="document.getElementById('add-modal').style.display='flex'"
                    style="background:var(--accent); color:white; border:none; padding:8px 12px; border-radius:8px; font-weight:600; font-size:13px; cursor:pointer;">
                    + Nova
                </button>
            </div>

            <div
                style="background:var(--card); border-radius:12px; overflow:hidden; border:1px solid rgba(0,0,0,0.02); box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
                <table style="width:100%; border-collapse:collapse; font-size:13px;">
                    <thead>
                        <tr
                            style="background:#FAFAFA; border-bottom:1px solid var(--border); text-align:left; color:var(--sub);">
                            <th style="padding:12px 16px; font-weight:600; width:30px;"></th>
                            <th style="padding:12px 16px; font-weight:600;">Descrição</th>
                            <th style="padding:12px 16px; font-weight:600;">Categoria</th>
                            <th style="padding:12px 16px; font-weight:600;">Data</th>
                            <th style="padding:12px 16px; font-weight:600;">Valor</th>
                            <th style="padding:12px 16px; font-weight:600; text-align:right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Calculate running balances
                        $stmt_hist = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY transaction_date ASC, created_at ASC");
                        $stmt_hist->execute([$user_id]);
                        $all_trans = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);

                        $running_balance = $initial_balance;
                        $history_rows = [];

                        foreach ($all_trans as $t) {
                            $prev_balance = $running_balance;
                            if ($t['type'] == 'income') {
                                $running_balance += $t['amount'];
                            } else {
                                $running_balance -= $t['amount'];
                            }
                            array_unshift($history_rows, [
                                'data' => $t,
                                'prev_balance' => $prev_balance
                            ]);
                        }

                        if (empty($history_rows)): ?>
                            <tr>
                                <td colspan="6" style="padding:24px; text-align:center; color:var(--sub);">Nenhuma
                                    movimentação registrada.</td>
                            </tr>
                        <?php else:
                            foreach ($history_rows as $row):
                                $t = $row['data'];
                                $is_income = $t['type'] == 'income';
                                $color = $is_income ? 'var(--accent)' : 'var(--danger)';
                                $sign = $is_income ? '+' : '-';
                                $transcription = htmlspecialchars($t['transcription'] ?? '');
                                $hasTranscription = !empty($t['transcription']);
                                ?>
                                <tr class="transaction-row" data-id="<?php echo $t['id']; ?>"
                                    style="border-bottom:1px solid var(--border); cursor:pointer;"
                                    onclick="toggleTransactionDetail(<?php echo $t['id']; ?>)">
                                    <td style="padding:12px 8px 12px 16px; color:var(--sub);">
                                        <i data-lucide="<?php echo $hasTranscription ? 'chevron-down' : 'minus'; ?>" width="16"
                                            class="expand-icon-<?php echo $t['id']; ?>"></i>
                                    </td>
                                    <td style="padding:12px 16px; font-weight:500; color:var(--text);">
                                        <?php echo htmlspecialchars($t['description']); ?>
                                    </td>
                                    <td style="padding:12px 16px; color:var(--sub);"><span
                                            style="background:#F2F2F7; padding:4px 8px; border-radius:4px; font-size:11px;"><?php echo htmlspecialchars($t['category'] ?? 'Geral'); ?></span>
                                    </td>
                                    <td style="padding:12px 16px; color:var(--sub);">
                                        <?php echo date('d/m/Y', strtotime($t['transaction_date'])); ?>
                                    </td>
                                    <td style="padding:12px 16px; font-weight:600; color:<?php echo $color; ?>;">
                                        <?php echo $sign . ' R$ ' . number_format($t['amount'], 2, ',', '.'); ?>
                                    </td>
                                    <td style="padding:12px 16px; text-align:right;" onclick="event.stopPropagation();">
                                        <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($t)); ?>)"
                                            style="background:transparent; border:1px solid var(--border); padding:6px 10px; border-radius:6px; cursor:pointer; margin-right:4px;"
                                            title="Editar">
                                            <i data-lucide="edit-2" width="14"></i>
                                        </button>
                                        <button onclick="deleteTransaction(<?php echo $t['id']; ?>)"
                                            style="background:transparent; border:1px solid var(--danger); color:var(--danger); padding:6px 10px; border-radius:6px; cursor:pointer;"
                                            title="Remover">
                                            <i data-lucide="trash-2" width="14"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php if ($hasTranscription): ?>
                                    <tr class="transaction-detail" id="detail-<?php echo $t['id']; ?>"
                                        style="display:none; background:#FAFAFA;">
                                        <td colspan="6" style="padding:16px 16px 16px 48px;">
                                            <div style="display:flex; align-items:flex-start; gap:12px;">
                                                <i data-lucide="mic" width="18"
                                                    style="color:var(--accent); flex-shrink:0; margin-top:2px;"></i>
                                                <div>
                                                    <div
                                                        style="font-size:11px; color:var(--sub); font-weight:600; margin-bottom:4px;">
                                                        TRANSCRIÇÃO DO ÁUDIO</div>
                                                    <div style="font-size:13px; color:var(--text); font-style:italic;">
                                                        "<?php echo $transcription; ?>"</div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add Modal -->
        <div id="add-modal"
            style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:200; align-items:center; justify-content:center;">
            <div style="background:white; padding:24px; border-radius:16px; width:90%; max-width:400px;">
                <h3 style="margin-bottom:16px;">Nova Movimentação</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_transaction">

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">TIPO</label>
                    <div style="display:flex; gap:10px; margin-bottom:12px;">
                        <label style="flex:1; cursor:pointer;">
                            <input type="radio" name="type" value="income"> Entrada
                        </label>
                        <label style="flex:1; cursor:pointer;">
                            <input type="radio" name="type" value="expense" checked> Saída
                        </label>
                    </div>

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">DESCRIÇÃO</label>
                    <input type="text" name="description" required
                        style="width:100%; padding:10px; margin-bottom:12px; border:1px solid var(--border); border-radius:8px;">

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">VALOR (R$)</label>
                    <input type="text" name="amount" required class="currency-field" placeholder="0,00"
                        style="width:100%; padding:10px; margin-bottom:12px; border:1px solid var(--border); border-radius:8px;">

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">CATEGORIA</label>
                    <select name="category"
                        style="width:100%; padding:10px; margin-bottom:12px; border:1px solid var(--border); border-radius:8px;">
                        <option value="Alimentação">🍔 Alimentação</option>
                        <option value="Mercado">🛒 Mercado</option>
                        <option value="Transporte">🚗 Transporte</option>
                        <option value="Lazer">🎮 Lazer</option>
                        <option value="Roupas">👕 Roupas</option>
                        <option value="Jogos">🎯 Jogos</option>
                        <option value="Saúde">💊 Saúde</option>
                        <option value="Esportes">⚽ Esportes</option>
                        <option value="Investimento">📈 Investimento</option>
                        <option value="Educação">📚 Educação</option>
                        <option value="Moradia">🏠 Moradia</option>
                        <option value="Contas">📄 Contas</option>
                        <option value="Assinaturas">📺 Assinaturas</option>
                        <option value="Beleza">💇 Beleza</option>
                        <option value="Pets">🐕 Pets</option>
                        <option value="Viagem">✈️ Viagem</option>
                        <option value="Presentes">🎁 Presentes</option>
                        <option value="Salário">💰 Salário</option>
                        <option value="Extra">💵 Extra</option>
                        <option value="Outros">📦 Outros</option>
                    </select>

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">DATA</label>
                    <input type="date" name="transaction_date" required value="<?php echo date('Y-m-d'); ?>"
                        style="width:100%; padding:10px; margin-bottom:24px; border:1px solid var(--border); border-radius:8px;">

                    <div style="display:flex; gap:10px;">
                        <button type="button" onclick="document.getElementById('add-modal').style.display='none'"
                            style="flex:1; padding:12px; border:none; background:#F2F2F7; border-radius:8px; font-weight:600; cursor:pointer;">Cancelar</button>
                        <button type="submit"
                            style="flex:1; padding:12px; border:none; background:var(--accent); color:white; border-radius:8px; font-weight:600; cursor:pointer;">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Modal -->
        <div id="edit-modal"
            style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:200; align-items:center; justify-content:center;">
            <div style="background:white; padding:24px; border-radius:16px; width:90%; max-width:400px;">
                <h3 style="margin-bottom:16px;">Editar Transação</h3>
                <form id="edit-form" onsubmit="saveEdit(event)">
                    <input type="hidden" id="edit-id">

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">TIPO</label>
                    <div style="display:flex; gap:10px; margin-bottom:12px;">
                        <label style="flex:1; cursor:pointer;">
                            <input type="radio" name="edit-type" id="edit-type-income" value="income"> Entrada
                        </label>
                        <label style="flex:1; cursor:pointer;">
                            <input type="radio" name="edit-type" id="edit-type-expense" value="expense" checked> Saída
                        </label>
                    </div>

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">DESCRIÇÃO</label>
                    <input type="text" id="edit-description" required
                        style="width:100%; padding:10px; margin-bottom:12px; border:1px solid var(--border); border-radius:8px;">

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">VALOR (R$)</label>
                    <input type="number" step="0.01" id="edit-amount" required
                        style="width:100%; padding:10px; margin-bottom:12px; border:1px solid var(--border); border-radius:8px;">

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">CATEGORIA</label>
                    <select id="edit-category"
                        style="width:100%; padding:10px; margin-bottom:12px; border:1px solid var(--border); border-radius:8px;">
                        <option value="Alimentação">🍔 Alimentação</option>
                        <option value="Mercado">🛒 Mercado</option>
                        <option value="Transporte">🚗 Transporte</option>
                        <option value="Lazer">🎮 Lazer</option>
                        <option value="Roupas">👕 Roupas</option>
                        <option value="Jogos">🎯 Jogos</option>
                        <option value="Saúde">💊 Saúde</option>
                        <option value="Esportes">⚽ Esportes</option>
                        <option value="Investimento">📈 Investimento</option>
                        <option value="Educação">📚 Educação</option>
                        <option value="Moradia">🏠 Moradia</option>
                        <option value="Contas">📄 Contas</option>
                        <option value="Assinaturas">📺 Assinaturas</option>
                        <option value="Beleza">💇 Beleza</option>
                        <option value="Pets">🐕 Pets</option>
                        <option value="Viagem">✈️ Viagem</option>
                        <option value="Presentes">🎁 Presentes</option>
                        <option value="Salário">💰 Salário</option>
                        <option value="Extra">💵 Extra</option>
                        <option value="Outros">📦 Outros</option>
                    </select>

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">DATA</label>
                    <input type="date" id="edit-date" required
                        style="width:100%; padding:10px; margin-bottom:24px; border:1px solid var(--border); border-radius:8px;">

                    <div style="display:flex; gap:10px;">
                        <button type="button" onclick="document.getElementById('edit-modal').style.display='none'"
                            style="flex:1; padding:12px; border:none; background:#F2F2F7; border-radius:8px; font-weight:600; cursor:pointer;">Cancelar</button>
                        <button type="submit"
                            style="flex:1; padding:12px; border:none; background:var(--accent); color:white; border-radius:8px; font-weight:600; cursor:pointer;">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Voice Recording Button (Floating) -->
        <button id="voice-btn"
            style="position:fixed; bottom:24px; right:24px; width:64px; height:64px; border-radius:50%; background:var(--accent); border:none; box-shadow:0 4px 12px rgba(11,54,128,0.3); cursor:pointer; display:flex; align-items:center; justify-content:center; transition:transform 0.2s;">
            <i data-lucide="mic" width="28" style="color:white;"></i>
        </button>
        <div id="recording-indicator"
            style="display:none; position:fixed; bottom:100px; right:24px; background:white; padding:12px 20px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1); font-size:13px; font-weight:600; color:var(--accent);">
            🎙️ Gravando...
        </div>

        <!-- AI Assistant Button (Floating) -->
        <a href="chat.php" id="ai-assistant-btn"
            style="position:fixed; bottom:24px; left:24px; width:56px; height:56px; border-radius:50%; background:var(--accent); border:none; box-shadow:0 4px 16px rgba(11,54,128,0.4); cursor:pointer; display:flex; align-items:center; justify-content:center; transition:transform 0.2s; z-index:1000; text-decoration: none;">
            <i data-lucide="bot" width="28" style="color:white;"></i>
        </a>
        <!-- Modal removed in favor of chat.php -->

        <!-- AI Chat Modal (Draggable Sidebar Style) -->
        <div id="ai-chat-modal"
            style="display:none; position:fixed; bottom:100px; left:24px; width:380px; height:600px; max-height:80vh; background:white; border-radius:12px; box-shadow:0 8px 40px rgba(0,0,0,0.25); z-index:1001; display:flex; flex-direction:column; overflow:hidden; border:1px solid rgba(0,0,0,0.1);">

            <!-- Draggable Header -->
            <div id="ai-chat-header"
                style="background:var(--accent); padding:14px 16px; display:flex; justify-content:space-between; align-items:center; cursor:grab; user-select:none;">
                <div style="display:flex; align-items:center; gap:10px; pointer-events:none;">
                    <div
                        style="width:8px; height:8px; background:#00ff88; border-radius:50%; box-shadow:0 0 8px #00ff88;">
                    </div>
                    <span style="color:white; font-weight:600; font-size:14px; letter-spacing:0.5px;">Lume AI
                        Consultant</span>
                </div>
                <div style="display:flex; gap:12px;">
                    <button onclick="toggleExpand()" title="Expandir"
                        style="background:none; border:none; cursor:pointer; opacity:0.8; color:white;">
                        <i data-lucide="maximize-2" width="16"></i>
                    </button>
                    <button onclick="toggleChat()" title="Fechar"
                        style="background:none; border:none; cursor:pointer; opacity:0.8; color:white;">
                        <i data-lucide="x" width="18"></i>
                    </button>
                </div>
            </div>

            <!-- Chat Content (ChatGPT Style) -->
            <div id="chat-messages"
                style="flex:1; overflow-y:auto; padding:0; display:flex; flex-direction:column; background:#FFFFFF;">
                <div class="message-block ai-block">
                    <div class="avatar ai-avatar"><i data-lucide="bot" width="16"></i></div>
                    <div class="message-content">
                        Olá! Sou o <strong>Lume</strong>.
                        <br><br>
                        Estou conectado aos seus dados. Posso analisar seus gastos ou sugerir investimentos. Como posso
                        ajudar?
                    </div>
                </div>
            </div>

            <!-- Review Action Card -->
            <div id="pending-action"
                style="display:none; background:#F9FAFB; padding:12px 16px; border-top:1px solid #E5E7EB;">
                <div id="pending-action-text"
                    style="font-size:13px; color:#374151; margin-bottom:10px; font-weight:500;"></div>
                <div style="display:flex; gap:8px;">
                    <button onclick="confirmAction()"
                        style="flex:1; padding:8px; background:#10B981; color:white; border:none; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer;">Confirmar</button>
                    <button onclick="cancelAction()"
                        style="flex:1; padding:8px; background:#E5E7EB; color:#374151; border:none; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer;">Cancelar</button>
                </div>
            </div>

            <!-- Input Area -->
            <div style="padding:16px; background:white; border-top:1px solid #F3F4F6;">
                <div
                    style="display:flex; align-items:flex-end; gap:8px; background:#F3F4F6; border-radius:12px; padding:8px 12px; border:1px solid transparent; transition:border 0.2s;">
                    <textarea id="chat-input" rows="1" placeholder="Pergunte algo..."
                        style="flex:1; background:transparent; border:none; resize:none; font-size:14px; max-height:100px; outline:none; line-height:1.5;"
                        oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'"
                        onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault(); sendChatMessage();}"></textarea>
                    <button onclick="sendChatMessage()"
                        style="background:var(--accent); border:none; width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0;">
                        <i data-lucide="arrow-up" width="16" style="color:white;"></i>
                    </button>
                </div>
            </div>
        </div>

        <style>
            /* ChatGPT-like Internal Styling */
            .message-block {
                display: flex;
                gap: 12px;
                padding: 20px 16px;
                border-bottom: 1px solid rgba(0, 0, 0, 0.03);
                font-size: 14px;
                line-height: 1.6;
                color: #1f2937;
            }

            .ai-block {
                background: #FFFFFF;
            }

            .user-block {
                background: #F9FAFB;
            }

            .avatar {
                width: 28px;
                height: 28px;
                border-radius: 6px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                margin-top: 2px;
            }

            .ai-avatar {
                background: var(--accent);
                color: white;
            }

            .user-avatar {
                background: #4B5563;
                color: white;
            }

            .message-content {
                flex: 1;
                word-break: break-word;
            }

            .message-content strong {
                font-weight: 600;
                color: #111;
            }

            /* Draggable/Resizable Classes */
            .modal-expanded {
                width: 600px !important;
                height: 80vh !important;
            }
        </style>

        <script>
            // Draggable Logic
            const modal = document.getElementById('ai-chat-modal');
            const header = document.getElementById('ai-chat-header');

            let isDragging = false;
            let startX, startY, initialLeft, initialTop;

            header.onmousedown = function (e) {
                e.preventDefault();
                isDragging = true;
                header.style.cursor = 'grabbing';

                // Get mouse start position
                startX = e.clientX;
                startY = e.clientY;

                // Get element start position
                const rect = modal.getBoundingClientRect();
                initialLeft = rect.left;
                initialTop = rect.top;

                // Remove bottom/right constraints if set (switch to top/left positioning)
                modal.style.bottom = 'auto';
                modal.style.right = 'auto';
                modal.style.left = initialLeft + 'px';
                modal.style.top = initialTop + 'px';
            };

            document.onmouseup = function () {
                isDragging = false;
                header.style.cursor = 'grab';
            };

            document.onmousemove = function (e) {
                if (!isDragging) return;

                const dx = e.clientX - startX;
                const dy = e.clientY - startY;

                modal.style.left = (initialLeft + dx) + 'px';
                modal.style.top = (initialTop + dy) + 'px';
            };

            // Toggle Expand
            function toggleExpand() {
                modal.classList.toggle('modal-expanded');
                // Re-center if expanded? Or just let it grow.
            }

            // Previous JS Logic (Chat)...
            // (Ensure sendChatMessage and other functions are still valid below)
        </script>

        <script>
            lucide.createIcons();

            const CONFIG = {
                salary: <?php echo $salary; ?>,
                dailySalary: <?php echo $daily_salary; ?>,
                workStart: "<?php echo $work_start; ?>",
                workEnd: "<?php echo $work_end; ?>",
                isTodayWork: <?php echo $is_today_work ? 'true' : 'false'; ?>,
                earnedStatic: <?php echo $earned_month_static; ?>,
                currentBalance: <?php echo $current_balance; ?>
            };

            function formatMoney(val) {
                return val.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function update() {
                const now = new Date();
                document.getElementById('clock-now').innerText = now.toLocaleTimeString('pt-BR');

                let earnedToday = 0;
                let diffPct = 0;
                let statusText = "Fora do expediente.";

                if (CONFIG.isTodayWork) {
                    // Robust Time Parsing & Shift Logic
                    const [sH, sM] = CONFIG.workStart.split(':').map(Number);
                    const [eH, eM] = CONFIG.workEnd.split(':').map(Number);

                    const start = new Date(now);
                    start.setHours(sH, sM, 0, 0);

                    const end = new Date(now);
                    end.setHours(eH, eM, 0, 0);

                    // Determine Shift Type
                    const isNightShift = end < start;

                    let startTime, endTime;
                    let inShift = false;

                    if (isNightShift) {
                        // Shift spans across midnight (e.g. 22:00 to 06:00)
                        // We have two possible windows relevant to 'now':
                        // Window A: yesterday 22:00 to today 06:00
                        // Window B: today 22:00 to tomorrow 06:00

                        const startA = new Date(start); startA.setDate(startA.getDate() - 1);
                        const endA = new Date(end); // today morning

                        const startB = new Date(start); // today night
                        const endB = new Date(end); endB.setDate(endB.getDate() + 1);

                        if (now >= startA && now <= endA) {
                            inShift = true;
                            startTime = startA;
                            endTime = endA;
                        } else if (now >= startB && now <= endB) {
                            inShift = true;
                            startTime = startB;
                            endTime = endB;
                        }
                    } else {
                        // Day shift (e.g. 09:00 to 18:00)
                        if (now >= start && now <= end) {
                            inShift = true;
                            startTime = start;
                            endTime = end;
                        }
                    }

                    if (inShift) {
                        const totalMs = endTime - startTime;
                        const elapsed = now - startTime;
                        diffPct = elapsed / totalMs;
                        earnedToday = diffPct * CONFIG.dailySalary;
                        statusText = "Em expediente... 🟢";
                    } else {
                        // Check if finished or waiting
                        if (isNightShift) {
                            // Complex logic for status text, stick to simple for now
                            statusText = "Fora do expediente.";
                        } else {
                            if (now > end) {
                                statusText = "Expediente finalizado.";
                                earnedToday = CONFIG.dailySalary;
                                diffPct = 1;
                            } else {
                                statusText = "Aguardando início.";
                            }
                        }
                    }
                }

                // Clip 0-1
                if (diffPct < 0) diffPct = 0;
                if (diffPct > 1) diffPct = 1;

                // 1. Day Earnings
                document.getElementById('live-earnings').innerText = formatMoney(earnedToday);
                // Force update status text color
                const statusEl = document.getElementById('work-status-text');
                statusEl.innerText = statusText;
                statusEl.style.color = (statusText.includes('🟢')) ? 'var(--accent)' : 'var(--sub)';

                // 2. Month Earnings
                const totalMonth = CONFIG.earnedStatic + earnedToday;
                document.getElementById('month-earnings').innerText = formatMoney(totalMonth);
                const monthPct = (totalMonth / CONFIG.salary) * 100;
                document.getElementById('month-bar').style.width = Math.min(monthPct, 100) + '%';
                document.getElementById('month-pct').innerText = monthPct.toFixed(2) + '%';

                // 3. Day Progress
                document.getElementById('day-bar').style.width = (diffPct * 100) + '%';
                document.getElementById('day-pct').innerText = (diffPct * 100).toFixed(2) + '%';

                // 4. Net Worth (Patrimônio Líquido = Saldo em Conta)
                const netWorth = CONFIG.currentBalance;
                document.getElementById('net-worth').innerText = formatMoney(netWorth);
            }
            setInterval(update, 100);
            update();

            // Voice Recording Logic
            let mediaRecorder;
            let audioChunks = [];
            const voiceBtn = document.getElementById('voice-btn');
            const recordingIndicator = document.getElementById('recording-indicator');
            const N8N_WEBHOOK_URL = '/voice_proxy.php'; // Using PHP proxy to avoid CORS

            voiceBtn.addEventListener('click', async () => {
                if (!mediaRecorder || mediaRecorder.state === 'inactive') {
                    // Start recording
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        mediaRecorder = new MediaRecorder(stream);
                        audioChunks = [];

                        mediaRecorder.ondataavailable = (event) => {
                            audioChunks.push(event.data);
                        };

                        mediaRecorder.onstop = async () => {
                            const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                            await sendToN8N(audioBlob);
                            stream.getTracks().forEach(track => track.stop());
                        };

                        mediaRecorder.start();
                        voiceBtn.style.background = 'var(--danger)';
                        recordingIndicator.style.display = 'block';
                    } catch (error) {
                        alert('Erro ao acessar microfone: ' + error.message);
                    }
                } else {
                    // Stop recording
                    mediaRecorder.stop();
                    voiceBtn.style.background = 'var(--accent)';
                    recordingIndicator.style.display = 'none';
                }
            });

            async function sendToN8N(audioBlob) {
                const formData = new FormData();
                formData.append('audio', audioBlob, 'recording.webm');
                formData.append('user_id', <?php echo $user_id; ?>);

                try {
                    const response = await fetch(N8N_WEBHOOK_URL, {
                        method: 'POST',
                        body: formData
                    });

                    // Log response details for debugging
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);

                    const responseText = await response.text();
                    console.log('Response text:', responseText);

                    // Try to parse as JSON
                    let result;
                    try {
                        result = JSON.parse(responseText);
                    } catch (e) {
                        alert('❌ Erro: n8n retornou resposta inválida.\n\nVerifique:\n1. OpenAI API Key está configurada no n8n\n2. Workflow está ativo\n3. Console (F12) para mais detalhes');
                        console.error('Parse error:', e);
                        console.error('Raw response:', responseText);
                        return;
                    }

                    if (result.status === 'success') {
                        const count = result.count || 1;
                        const transactions = result.transactions || [];

                        if (count === 1 && transactions.length > 0) {
                            const t = transactions[0];
                            const typeText = t.type === 'income' ? 'Entrada' : 'Despesa';
                            alert('✅ ' + typeText + ' registrada: ' + t.description + ' - R$ ' + parseFloat(t.amount).toFixed(2));
                        } else if (count > 1) {
                            let summary = '✅ ' + count + ' transações registradas:\n\n';
                            transactions.forEach((t, i) => {
                                const typeText = t.type === 'income' ? '+' : '-';
                                summary += (i + 1) + '. ' + t.description + ' ' + typeText + ' R$ ' + parseFloat(t.amount).toFixed(2) + '\n';
                            });
                            alert(summary);
                        } else {
                            alert('✅ ' + result.message);
                        }
                        location.reload();
                    } else {
                        alert('❌ Erro: ' + (result.message || 'Não foi possível processar'));
                    }
                } catch (error) {
                    alert('❌ Erro de conexão: ' + error.message + '\n\nVerifique o Console (F12) para detalhes');
                    console.error('Fetch error:', error);
                }
            }

            // Transaction Management Functions
            function toggleTransactionDetail(id) {
                const detailRow = document.getElementById('detail-' + id);
                if (detailRow) {
                    const isVisible = detailRow.style.display !== 'none';
                    detailRow.style.display = isVisible ? 'none' : 'table-row';
                }
            }

            function openEditModal(transaction) {
                document.getElementById('edit-id').value = transaction.id;
                document.getElementById('edit-description').value = transaction.description;
                document.getElementById('edit-amount').value = parseFloat(transaction.amount);
                document.getElementById('edit-category').value = transaction.category || 'Outros';
                document.getElementById('edit-date').value = transaction.transaction_date;

                if (transaction.type === 'income') {
                    document.getElementById('edit-type-income').checked = true;
                } else {
                    document.getElementById('edit-type-expense').checked = true;
                }

                document.getElementById('edit-modal').style.display = 'flex';
            }

            async function saveEdit(event) {
                event.preventDefault();

                const id = document.getElementById('edit-id').value;
                const type = document.querySelector('input[name="edit-type"]:checked').value;
                const description = document.getElementById('edit-description').value;
                const amount = document.getElementById('edit-amount').value;
                const category = document.getElementById('edit-category').value;
                const transaction_date = document.getElementById('edit-date').value;

                try {
                    const response = await fetch('/api/transactions.php?id=' + id, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ type, description, amount, category, transaction_date })
                    });

                    const result = await response.json();

                    if (result.status === 'success') {
                        alert('✅ Transação atualizada com sucesso!');
                        location.reload();
                    } else {
                        alert('❌ Erro: ' + result.message);
                    }
                } catch (error) {
                    alert('❌ Erro ao salvar: ' + error.message);
                }
            }

            async function deleteTransaction(id) {
                if (!confirm('Tem certeza que deseja remover esta transação?')) {
                    return;
                }

                try {
                    const response = await fetch('/api/transactions.php?id=' + id, {
                        method: 'DELETE'
                    });

                    const result = await response.json();

                    if (result.status === 'success') {
                        alert('✅ Transação removida com sucesso!');
                        location.reload();
                    } else {
                        alert('❌ Erro: ' + result.message);
                    }
                } catch (error) {
                    alert('❌ Erro ao remover: ' + error.message);
                }
            }

            // ==================== AI ASSISTANT CHAT ====================
            let pendingAction = null;
            let chatVisible = false;

            function toggleChat() {
                const modal = document.getElementById('ai-chat-modal');
                chatVisible = !chatVisible;
                modal.style.display = chatVisible ? 'flex' : 'none';
                if (chatVisible) {
                    document.getElementById('chat-input').focus();
                    lucide.createIcons();
                }
            }

            function addMessage(content, isUser) {
                const messagesDiv = document.getElementById('chat-messages');
                const msgDiv = document.createElement('div');
                msgDiv.style.cssText = isUser
                    ? 'background:var(--accent); color:white; padding:12px 16px; border-radius:12px 12px 0 12px; max-width:85%; font-size:13px; align-self:flex-end;'
                    : 'background:#F2F2F7; padding:12px 16px; border-radius:12px 12px 12px 0; max-width:85%; font-size:13px; line-height:1.5;';
                msgDiv.innerHTML = content;
                messagesDiv.appendChild(msgDiv);
                messagesDiv.scrollTop = messagesDiv.scrollHeight;
            }

            function showPendingAction(response) {
                pendingAction = response;
                const pendingDiv = document.getElementById('pending-action');
                const textDiv = document.getElementById('pending-action-text');

                let previewText = '';

                if (response.action === 'remove' && response.transactions_to_remove) {
                    previewText = '<strong>Transações a remover:</strong><br>';
                    response.transactions_to_remove.forEach(t => {
                        previewText += `• ${t.description} - R$ ${parseFloat(t.amount).toFixed(2)}<br>`;
                    });
                } else if (response.action === 'add' && response.transactions) {
                    previewText = '<strong>Transações a adicionar:</strong><br>';
                    response.transactions.forEach(t => {
                        previewText += `• ${t.description} - R$ ${parseFloat(t.amount).toFixed(2)} (${t.category})<br>`;
                    });
                } else if (response.action === 'edit') {
                    previewText = `<strong>Editar transação #${response.transaction_id}:</strong><br>`;
                    for (const [key, val] of Object.entries(response.changes || {})) {
                        previewText += `• ${key}: ${val}<br>`;
                    }
                }

                textDiv.innerHTML = previewText;
                pendingDiv.style.display = 'block';
            }

            function hidePendingAction() {
                pendingAction = null;
                document.getElementById('pending-action').style.display = 'none';
            }

            async function sendChatMessage() {
                const input = document.getElementById('chat-input');
                const message = input.value.trim();
                if (!message) return;

                input.value = '';
                addMessage(message, true);

                // Show typing indicator
                addMessage('⏳ Pensando...', false);

                try {
                    const response = await fetch('/ai_assistant.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'chat', message: message })
                    });

                    const result = await response.json();

                    // Remove typing indicator
                    const msgs = document.getElementById('chat-messages');
                    msgs.removeChild(msgs.lastChild);

                    // Add AI response
                    addMessage(result.message || 'Desculpe, não entendi.', false);

                    // Show confirmation if needed
                    if (result.requires_confirmation && result.action !== 'reply' && result.action !== 'clarify') {
                        showPendingAction(result);
                    }

                } catch (error) {
                    const msgs = document.getElementById('chat-messages');
                    msgs.removeChild(msgs.lastChild);
                    addMessage('❌ Erro ao conectar com o assistente.', false);
                    console.error(error);
                }
            }

            async function confirmAction() {
                if (!pendingAction) return;

                addMessage('✓ Confirmado!', true);

                try {
                    const response = await fetch('/ai_assistant.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'confirm', pending_action: pendingAction })
                    });

                    const result = await response.json();
                    addMessage(result.message || '✅ Ação executada!', false);
                    hidePendingAction();

                    // Reload after a short delay to show the message
                    setTimeout(() => location.reload(), 1500);

                } catch (error) {
                    addMessage('❌ Erro ao executar ação.', false);
                    console.error(error);
                }
            }

            function cancelAction() {
                hidePendingAction();
                addMessage('❌ Ação cancelada.', false);
            }

            // ==================== CATEGORY CHARTS ====================
            const categoryColors = {
                'Alimentação': '#FF6384',
                'Mercado': '#36A2EB',
                'Transporte': '#FFCE56',
                'Lazer': '#4BC0C0',
                'Roupas': '#9966FF',
                'Jogos': '#FF9F40',
                'Saúde': '#FF6384',
                'Esportes': '#4BC0C0',
                'Investimento': '#36A2EB',
                'Educação': '#9966FF',
                'Moradia': '#FFCE56',
                'Contas': '#FF9F40',
                'Assinaturas': '#C9CBCF',
                'Beleza': '#FF6384',
                'Pets': '#4BC0C0',
                'Viagem': '#36A2EB',
                'Presentes': '#9966FF',
                'Salário': '#00D084',
                'Extra': '#00D084',
                'Outros': '#C9CBCF'
            };

            const monthNames = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

            let currentMonth = new Date().getMonth() + 1;
            let currentYear = new Date().getFullYear();
            let monthlyChartInstance = null;
            let alltimeChartInstance = null;

            function formatCurrency(value) {
                return 'R$ ' + parseFloat(value).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function createPieChart(canvasId, data, existingChart) {
                const ctx = document.getElementById(canvasId);
                if (!ctx) return null;

                if (existingChart) {
                    existingChart.destroy();
                }

                if (!data || data.length === 0) {
                    return null;
                }

                const labels = data.map(d => d.category || 'Outros');
                const values = data.map(d => parseFloat(d.total));
                const colors = labels.map(l => categoryColors[l] || '#C9CBCF');

                return new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: colors,
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    boxWidth: 12,
                                    padding: 8,
                                    font: { size: 11 }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        const value = context.parsed;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const pct = ((value / total) * 100).toFixed(1);
                                        return context.label + ': ' + formatCurrency(value) + ' (' + pct + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }

            async function loadCategoryData() {
                try {
                    const response = await fetch(`/api/categories.php?month=${currentMonth}&year=${currentYear}`);
                    const data = await response.json();

                    if (data.status === 'success') {
                        // Update month label
                        document.getElementById('current-month-label').textContent =
                            monthNames[currentMonth - 1] + ' ' + currentYear;

                        // Update totals
                        document.getElementById('monthly-total').textContent = formatCurrency(data.monthly.total);
                        document.getElementById('alltime-total').textContent = formatCurrency(data.allTime.total);

                        // Create/update charts
                        monthlyChartInstance = createPieChart('monthlyChart', data.monthly.data, monthlyChartInstance);
                        alltimeChartInstance = createPieChart('alltimeChart', data.allTime.data, alltimeChartInstance);
                    }
                } catch (error) {
                    console.error('Error loading category data:', error);
                }
            }

            function changeMonth(delta) {
                currentMonth += delta;
                if (currentMonth > 12) {
                    currentMonth = 1;
                    currentYear++;
                } else if (currentMonth < 1) {
                    currentMonth = 12;
                    currentYear--;
                }
                loadCategoryData();
            }

            // Load charts on page load
            document.addEventListener('DOMContentLoaded', function () {
                loadCategoryData();
            });
        </script>
</body>

</html>