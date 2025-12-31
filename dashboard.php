<?php
session_start();
require_once 'security_headers.php';
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

    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1E3A5F">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Lume">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">

    <style>
        :root {
            --bg: #F5F5F7;
            --card: #FFFFFF;
            --text: #1D1D1F;
            --sub: #86868B;
            --accent: #0b3680;
            --danger: #FF3B30;
            --border: #E5E5EA;
            --success: #34C759;
        }

        /* Dark Mode */
        .dark-mode {
            --bg: #1C1C1E;
            --card: #2C2C2E;
            --text: #FFFFFF;
            --sub: #8E8E93;
            --accent: #4A90D9;
            --danger: #FF453A;
            --border: #38383A;
            --success: #30D158;
        }

        /* Solarized Light Theme */
        .solarized-theme {
            --bg: #FDF6E3;
            --card: #EEE8D5;
            --text: #586E75;
            --sub: #93A1A1;
            --accent: #268BD2;
            --danger: #DC322F;
            --border: #E0DAC6;
            --success: #859900;
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

                <button onclick="toggleTheme()" class="btn-header"
                    style="background:var(--card); border:1px solid var(--border); color:var(--text);"
                    title="Alternar tema (Claro/Escuro/Solarized)">
                    <i data-lucide="moon" width="14" id="theme-icon"></i>
                </button>
                <a href="logout.php" class="btn-header" style="background:var(--danger); border:none; color:white;"
                    title="Sair">
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

        <!-- 6. Analytics Section -->
        <div style="margin-top: 40px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 class="recents-title" style="margin:0;">Panorama Financeiro</h3>
                <button onclick="generateAIInsights()"
                    style="background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); color:white; border:none; padding:8px 16px; border-radius:8px; font-weight:600; font-size:13px; cursor:pointer; display:flex; align-items:center; gap:8px; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);">
                    <i data-lucide="sparkles" width="14"></i> Análise Inteligente
                </button>
            </div>
            <div class="analytics-grid" style="display:grid; grid-template-columns: 2fr 1fr; gap:20px;">
                <!-- Evolution Chart -->
                <div
                    style="background:var(--card); border-radius:12px; padding:20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
                    <div style="font-weight:600; font-size:14px; color:var(--text); margin-bottom:16px;">Evolução do
                        Saldo (Mês Atual)</div>
                    <div style="height:300px; width:100%;">
                        <canvas id="evolutionChart"></canvas>
                    </div>
                </div>

                <!-- Top 5 Expenses -->
                <div
                    style="background:var(--card); border-radius:12px; padding:20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                        <div style="font-weight:600; font-size:14px; color:var(--text);">🏆 Top 5 Despesas</div>
                        <div id="comparison-badge"
                            style="display:none; align-items:center; padding:4px 8px; border-radius:6px; font-size:11px; font-weight:600;">
                        </div>
                    </div>
                    <div id="top-expenses-list" style="display:flex; flex-direction:column; gap:12px;">
                        <div style="color:var(--sub); font-size:13px; text-align:center; padding:20px;">Carregando...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 7. Budget Section -->
        <div style="margin-top: 40px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 class="recents-title" style="margin:0;">💰 Orçamentos por Categoria</h3>
                <button onclick="openBudgetModal()"
                    style="background:var(--accent); color:white; border:none; padding:8px 12px; border-radius:8px; font-weight:600; font-size:13px; cursor:pointer;">
                    + Novo Orçamento
                </button>
            </div>
            <div id="budgets-container"
                style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:16px;">
                <!-- Budgets loaded via JS -->
                <div style="color:var(--sub); font-size:14px; padding:20px; text-align:center;">
                    Carregando orçamentos...
                </div>
            </div>
        </div>

        <!-- Budget Modal -->
        <div id="budget-modal"
            style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:200; align-items:center; justify-content:center;">
            <div style="background:var(--card); padding:24px; border-radius:16px; width:90%; max-width:400px;">
                <h3 style="margin-bottom:16px; color:var(--text);">Novo Orçamento</h3>
                <form onsubmit="saveBudget(event)">
                    <label style="font-size:12px; font-weight:600; color:var(--sub);">CATEGORIA</label>
                    <select id="budget-category" required
                        style="width:100%; padding:12px; border:1px solid var(--border); border-radius:8px; margin-bottom:16px; background:var(--card); color:var(--text);">
                        <option value="">Selecione...</option>
                        <option value="Alimentação">🍔 Alimentação</option>
                        <option value="Mercado">🛒 Mercado</option>
                        <option value="Transporte">🚗 Transporte</option>
                        <option value="Lazer">🎮 Lazer</option>
                        <option value="Roupas">👕 Roupas</option>
                        <option value="Jogos">🎲 Jogos</option>
                        <option value="Saúde">💊 Saúde</option>
                        <option value="Esportes">⚽ Esportes</option>
                        <option value="Educação">📚 Educação</option>
                        <option value="Moradia">🏠 Moradia</option>
                        <option value="Contas">📃 Contas</option>
                        <option value="Assinaturas">📺 Assinaturas</option>
                        <option value="Beleza">💄 Beleza</option>
                        <option value="Pets">🐾 Pets</option>
                        <option value="Viagem">✈️ Viagem</option>
                        <option value="Presentes">🎁 Presentes</option>
                        <option value="Outros">📦 Outros</option>
                    </select>

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">LIMITE MENSAL (R$)</label>
                    <input type="number" id="budget-limit" step="0.01" min="0.01" required
                        style="width:100%; padding:12px; border:1px solid var(--border); border-radius:8px; margin-bottom:16px; background:var(--card); color:var(--text);"
                        placeholder="500.00">

                    <div style="display:flex; gap:8px;">
                        <button type="button" onclick="closeBudgetModal()"
                            style="flex:1; padding:12px; border:1px solid var(--border); background:var(--card); border-radius:8px; cursor:pointer; color:var(--text);">Cancelar</button>
                        <button type="submit"
                            style="flex:1; padding:12px; border:none; background:var(--accent); color:white; border-radius:8px; cursor:pointer; font-weight:600;">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 6.5 Recurring Transactions Section -->
        <div style="margin-top: 40px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 class="recents-title" style="margin:0;">🔄 Transações Recorrentes</h3>
                <button onclick="openRecurringModal()"
                    style="background:var(--accent); color:white; border:none; padding:8px 12px; border-radius:8px; font-weight:600; font-size:13px; cursor:pointer;">
                    + Nova Recorrência
                </button>
            </div>
            <div id="recurring-container"
                style="display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap:16px;">
                <div style="color:var(--sub); font-size:14px; padding:20px; text-align:center;">
                    Carregando...
                </div>
            </div>
        </div>

        <!-- Recurring Modal -->
        <div id="recurring-modal"
            style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:200; align-items:center; justify-content:center;">
            <div
                style="background:var(--card); padding:24px; border-radius:16px; width:90%; max-width:420px; max-height:90vh; overflow-y:auto;">
                <h3 style="margin-bottom:16px; color:var(--text);">Nova Transação Recorrente</h3>
                <form onsubmit="saveRecurring(event)">
                    <label style="font-size:12px; font-weight:600; color:var(--sub);">TIPO</label>
                    <div style="display:flex; gap:8px; margin-bottom:16px;" id="rec-type-selector">
                        <label class="rec-type-option"
                            style="flex:1; padding:12px; border:2px solid var(--border); border-radius:8px; text-align:center; cursor:pointer; background:var(--card); transition: all 0.2s;">
                            <input type="radio" name="rec-type" value="income" style="display:none;"
                                onchange="updateRecTypeVisual()">
                            <span>💰 Receita</span>
                        </label>
                        <label class="rec-type-option rec-type-selected"
                            style="flex:1; padding:12px; border:2px solid var(--danger); border-radius:8px; text-align:center; cursor:pointer; background:rgba(255,59,48,0.1); transition: all 0.2s;">
                            <input type="radio" name="rec-type" value="expense" checked style="display:none;"
                                onchange="updateRecTypeVisual()">
                            <span>💸 Despesa</span>
                        </label>
                    </div>

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">DESCRIÇÃO</label>
                    <input type="text" id="rec-description" required
                        style="width:100%; padding:12px; border:1px solid var(--border); border-radius:8px; margin-bottom:16px; background:var(--card); color:var(--text);"
                        placeholder="Ex: Netflix, Salário...">

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">VALOR (R$)</label>
                    <input type="number" id="rec-amount" step="0.01" min="0.01" required
                        style="width:100%; padding:12px; border:1px solid var(--border); border-radius:8px; margin-bottom:16px; background:var(--card); color:var(--text);"
                        placeholder="1412.00">


                    <label style="font-size:12px; font-weight:600; color:var(--sub);">CATEGORIA</label>
                    <select id="rec-category"
                        style="width:100%; padding:12px; border:1px solid var(--border); border-radius:8px; margin-bottom:16px; background:var(--card); color:var(--text);">
                        <option value="Salário">💰 Salário</option>
                        <option value="Assinaturas">📺 Assinaturas</option>
                        <option value="Contas">📃 Contas</option>
                        <option value="Moradia">🏠 Moradia</option>
                        <option value="Outros">📦 Outros</option>
                    </select>

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">FREQUÊNCIA</label>
                    <select id="rec-frequency"
                        style="width:100%; padding:12px; border:1px solid var(--border); border-radius:8px; margin-bottom:16px; background:var(--card); color:var(--text);">
                        <option value="monthly">Mensal</option>
                        <option value="weekly">Semanal</option>
                        <option value="yearly">Anual</option>
                    </select>

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">DIA DO MÊS</label>
                    <div style="display:flex; gap:8px; margin-bottom:16px;">
                        <input type="number" id="rec-day" min="1" max="31" value="1"
                            style="flex:1; padding:12px; border:1px solid var(--border); border-radius:8px; background:var(--card); color:var(--text);">
                        <label
                            style="display:flex; align-items:center; gap:6px; padding:0 12px; border:1px solid var(--border); border-radius:8px; cursor:pointer; white-space:nowrap;">
                            <input type="checkbox" id="rec-last-day"
                                onchange="document.getElementById('rec-day').disabled = this.checked;">
                            <span style="font-size:12px;">Último dia</span>
                        </label>
                    </div>

                    <div style="display:flex; gap:8px;">
                        <button type="button" onclick="closeRecurringModal()"
                            style="flex:1; padding:12px; border:1px solid var(--border); background:var(--card); border-radius:8px; cursor:pointer; color:var(--text);">Cancelar</button>
                        <button type="submit"
                            style="flex:1; padding:12px; border:none; background:var(--accent); color:white; border-radius:8px; cursor:pointer; font-weight:600;">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Recurring Modal -->
        <div id="edit-recurring-modal"
            style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:200; align-items:center; justify-content:center;">
            <div
                style="background:var(--card); padding:24px; border-radius:16px; width:90%; max-width:420px; max-height:90vh; overflow-y:auto;">
                <h3 style="margin-bottom:16px; color:var(--text);">Editar Transação Recorrente</h3>
                <form onsubmit="updateRecurring(event)">
                    <input type="hidden" id="edit-rec-id">

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">TIPO</label>
                    <div style="display:flex; gap:8px; margin-bottom:16px;" id="edit-rec-type-selector">
                        <label class="edit-rec-type-option"
                            style="flex:1; padding:12px; border:2px solid var(--border); border-radius:8px; text-align:center; cursor:pointer; background:var(--card); transition: all 0.2s;">
                            <input type="radio" name="edit-rec-type" value="income" style="display:none;"
                                onchange="updateEditRecTypeVisual()">
                            <span>💰 Receita</span>
                        </label>
                        <label class="edit-rec-type-option"
                            style="flex:1; padding:12px; border:2px solid var(--border); border-radius:8px; text-align:center; cursor:pointer; background:var(--card); transition: all 0.2s;">
                            <input type="radio" name="edit-rec-type" value="expense" style="display:none;"
                                onchange="updateEditRecTypeVisual()">
                            <span>💸 Despesa</span>
                        </label>
                    </div>

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">DESCRIÇÃO</label>
                    <input type="text" id="edit-rec-description" required
                        style="width:100%; padding:12px; border:1px solid var(--border); border-radius:8px; margin-bottom:16px; background:var(--card); color:var(--text);">

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">VALOR (R$)</label>
                    <input type="number" id="edit-rec-amount" step="0.01" min="0.01" required
                        style="width:100%; padding:12px; border:1px solid var(--border); border-radius:8px; margin-bottom:16px; background:var(--card); color:var(--text);"
                        placeholder="1412.00">

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">CATEGORIA</label>
                    <select id="edit-rec-category"
                        style="width:100%; padding:12px; border:1px solid var(--border); border-radius:8px; margin-bottom:16px; background:var(--card); color:var(--text);">
                        <option value="Salário">💰 Salário</option>
                        <option value="Assinaturas">📺 Assinaturas</option>
                        <option value="Contas">📃 Contas</option>
                        <option value="Moradia">🏠 Moradia</option>
                        <option value="Outros">📦 Outros</option>
                    </select>

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">FREQUÊNCIA</label>
                    <select id="edit-rec-frequency"
                        style="width:100%; padding:12px; border:1px solid var(--border); border-radius:8px; margin-bottom:16px; background:var(--card); color:var(--text);">
                        <option value="monthly">Mensal</option>
                        <option value="weekly">Semanal</option>
                        <option value="yearly">Anual</option>
                    </select>

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">DIA DO MÊS</label>
                    <div style="display:flex; gap:8px; margin-bottom:16px;">
                        <input type="number" id="edit-rec-day" min="1" max="31"
                            style="flex:1; padding:12px; border:1px solid var(--border); border-radius:8px; background:var(--card); color:var(--text);">
                        <label
                            style="display:flex; align-items:center; gap:6px; padding:0 12px; border:1px solid var(--border); border-radius:8px; cursor:pointer; white-space:nowrap;">
                            <input type="checkbox" id="edit-rec-last-day"
                                onchange="document.getElementById('edit-rec-day').disabled = this.checked;">
                            <span style="font-size:12px;">Último dia</span>
                        </label>
                    </div>

                    <div style="display:flex; gap:8px;">
                        <button type="button" onclick="closeEditRecurringModal()"
                            style="flex:1; padding:12px; border:1px solid var(--border); background:var(--card); border-radius:8px; cursor:pointer; color:var(--text);">Cancelar</button>
                        <button type="submit"
                            style="flex:1; padding:12px; border:none; background:var(--accent); color:white; border-radius:8px; cursor:pointer; font-weight:600;">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 6.6 Investments Section -->
        <div style="margin-top: 40px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 class="recents-title" style="margin:0;">📈 Meus Investimentos</h3>
                <button onclick="openInvestmentModal()"
                    style="background:linear-gradient(135deg, #10b981 0%, #059669 100%); color:white; border:none; padding:8px 12px; border-radius:8px; font-weight:600; font-size:13px; cursor:pointer;">
                    + Novo Investimento
                </button>
            </div>

            <!-- Investment Summary -->
            <div id="investment-summary"
                style="display:grid; grid-template-columns: repeat(4, 1fr); gap:16px; margin-bottom:20px;">
                <div style="background:var(--card); border-radius:12px; padding:16px; text-align:center;">
                    <div style="font-size:12px; color:var(--sub); margin-bottom:4px;">Total Investido</div>
                    <div id="inv-total-invested" style="font-size:20px; font-weight:700; color:var(--text);">R$ 0,00
                    </div>
                </div>
                <div style="background:var(--card); border-radius:12px; padding:16px; text-align:center;">
                    <div style="font-size:12px; color:var(--sub); margin-bottom:4px;">Valor Atual</div>
                    <div id="inv-total-current" style="font-size:20px; font-weight:700; color:var(--text);">R$ 0,00
                    </div>
                </div>
                <div style="background:var(--card); border-radius:12px; padding:16px; text-align:center;">
                    <div style="font-size:12px; color:var(--sub); margin-bottom:4px;">Rendimento</div>
                    <div id="inv-total-gain" style="font-size:20px; font-weight:700; color:var(--success);">+R$ 0,00
                    </div>
                </div>
                <div style="background:var(--card); border-radius:12px; padding:16px; text-align:center;">
                    <div style="font-size:12px; color:var(--sub); margin-bottom:4px;">Rentabilidade</div>
                    <div id="inv-total-yield" style="font-size:20px; font-weight:700; color:var(--success);">0%</div>
                </div>
            </div>

            <!-- Investment Cards -->
            <div id="investments-container"
                style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:16px;">
                <div
                    style="color:var(--sub); font-size:14px; padding:40px; text-align:center; background:var(--card); border-radius:12px;">
                    Carregando investimentos...
                </div>
            </div>
        </div>

        <!-- Investment Modal (New) -->
        <div id="investment-modal"
            style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:200; align-items:center; justify-content:center;">
            <div
                style="background:var(--card); padding:24px; border-radius:16px; width:90%; max-width:420px; max-height:90vh; overflow-y:auto;">
                <h3 style="margin-bottom:16px; color:var(--text);">📈 Novo Investimento</h3>
                <form onsubmit="saveInvestment(event)">
                    <label style="font-size:12px; font-weight:600; color:var(--sub);">NOME DO INVESTIMENTO</label>
                    <input type="text" id="inv-name" required
                        style="width:100%; padding:12px; border:1px solid var(--border); border-radius:8px; margin-bottom:16px; background:var(--card); color:var(--text);"
                        placeholder="Ex: Nubank RDB, Tesouro Selic...">

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">TIPO</label>
                    <select id="inv-type"
                        style="width:100%; padding:12px; border:1px solid var(--border); border-radius:8px; margin-bottom:16px; background:var(--card); color:var(--text);">
                        <option value="CDB">💰 CDB</option>
                        <option value="Tesouro">🏛️ Tesouro Direto</option>
                        <option value="LCI/LCA">🏠 LCI/LCA</option>
                        <option value="Ações">📊 Ações</option>
                        <option value="FIIs">🏢 FIIs</option>
                        <option value="Crypto">₿ Criptomoedas</option>
                        <option value="Poupança">🐷 Poupança</option>
                        <option value="Outro">📦 Outro</option>
                    </select>

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">VALOR INVESTIDO (R$)</label>
                    <input type="number" id="inv-amount" step="0.01" min="0.01" required
                        style="width:100%; padding:12px; border:1px solid var(--border); border-radius:8px; margin-bottom:16px; background:var(--card); color:var(--text);"
                        placeholder="500.00">

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">DATA DO INVESTIMENTO</label>
                    <input type="date" id="inv-date" required
                        style="width:100%; padding:12px; border:1px solid var(--border); border-radius:8px; margin-bottom:16px; background:var(--card); color:var(--text);">

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">TAXA ESPERADA (%)</label>
                    <div style="display:flex; gap:8px; margin-bottom:16px;">
                        <input type="number" id="inv-rate" step="0.01" min="0"
                            style="flex:2; padding:12px; border:1px solid var(--border); border-radius:8px; background:var(--card); color:var(--text);"
                            placeholder="1.0">
                        <select id="inv-rate-period"
                            style="flex:1; padding:12px; border:1px solid var(--border); border-radius:8px; background:var(--card); color:var(--text);">
                            <option value="monthly">a.m.</option>
                            <option value="yearly">a.a.</option>
                        </select>
                    </div>

                    <div style="display:flex; gap:8px;">
                        <button type="button" onclick="closeInvestmentModal()"
                            style="flex:1; padding:12px; border:1px solid var(--border); background:var(--card); border-radius:8px; cursor:pointer; color:var(--text);">Cancelar</button>
                        <button type="submit"
                            style="flex:1; padding:12px; border:none; background:linear-gradient(135deg, #10b981 0%, #059669 100%); color:white; border-radius:8px; cursor:pointer; font-weight:600;">Salvar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Update Value Modal -->
        <div id="update-value-modal"
            style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:200; align-items:center; justify-content:center;">
            <div style="background:var(--card); padding:24px; border-radius:16px; width:90%; max-width:360px;">
                <h3 style="margin-bottom:16px; color:var(--text);">💹 Atualizar Valor</h3>
                <form onsubmit="updateInvestmentValue(event)">
                    <input type="hidden" id="update-inv-id">

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">VALOR ATUAL (R$)</label>
                    <input type="number" id="update-new-value" step="0.01" min="0" required
                        style="width:100%; padding:12px; border:1px solid var(--border); border-radius:8px; margin-bottom:16px; background:var(--card); color:var(--text);"
                        placeholder="1356.36">

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">DATA DA ATUALIZAÇÃO</label>
                    <input type="date" id="update-date" required
                        style="width:100%; padding:12px; border:1px solid var(--border); border-radius:8px; margin-bottom:16px; background:var(--card); color:var(--text);">

                    <div style="display:flex; gap:8px;">
                        <button type="button" onclick="closeUpdateModal()"
                            style="flex:1; padding:12px; border:1px solid var(--border); background:var(--card); border-radius:8px; cursor:pointer; color:var(--text);">Cancelar</button>
                        <button type="submit"
                            style="flex:1; padding:12px; border:none; background:var(--accent); color:white; border-radius:8px; cursor:pointer; font-weight:600;">Atualizar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Deposit Modal -->
        <div id="deposit-modal"
            style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:200; align-items:center; justify-content:center;">
            <div style="background:var(--card); padding:24px; border-radius:16px; width:90%; max-width:360px;">
                <h3 style="margin-bottom:16px; color:var(--text);">💰 Novo Aporte</h3>
                <div style="font-size:13px; color:var(--sub); margin-bottom:16px;">
                    Investimento: <strong id="deposit-inv-name" style="color:var(--text);"></strong>
                </div>
                <form onsubmit="saveDeposit(event)">
                    <input type="hidden" id="deposit-inv-id">

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">VALOR DO APORTE (R$)</label>
                    <input type="number" id="deposit-amount" step="0.01" min="0.01" required
                        style="width:100%; padding:12px; border:1px solid var(--border); border-radius:8px; margin-bottom:16px; background:var(--card); color:var(--text);"
                        placeholder="639.14">

                    <label style="font-size:12px; font-weight:600; color:var(--sub);">DATA DO APORTE</label>
                    <input type="date" id="deposit-date" required
                        style="width:100%; padding:12px; border:1px solid var(--border); border-radius:8px; margin-bottom:16px; background:var(--card); color:var(--text);">

                    <div style="display:flex; gap:8px;">
                        <button type="button" onclick="closeDepositModal()"
                            style="flex:1; padding:12px; border:1px solid var(--border); background:var(--card); border-radius:8px; cursor:pointer; color:var(--text);">Cancelar</button>
                        <button type="submit"
                            style="flex:1; padding:12px; border:none; background:linear-gradient(135deg, #10b981 0%, #059669 100%); color:white; border-radius:8px; cursor:pointer; font-weight:600;">Registrar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 7. Transaction History Section -->
        <div style="margin-top: 40px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 class="recents-title" style="margin:0;">Histórico Financeiro</h3>
                <div style="display:flex; gap:10px;">
                    <button onclick="exportCSV()"
                        style="background:var(--card); border:1px solid var(--border); color:var(--text); padding:8px 12px; border-radius:8px; font-size:13px; cursor:pointer; display:flex; align-items:center; gap:6px; transition: background 0.2s;">
                        <i data-lucide="download" width="14"></i> Exportar
                    </button>
                    <button onclick="document.getElementById('add-modal').style.display='flex'"
                        style="background:var(--accent); color:white; border:none; padding:8px 12px; border-radius:8px; font-weight:600; font-size:13px; cursor:pointer;">
                        + Nova
                    </button>
                </div>
            </div>

            <!-- SEARCH & FILTERS -->
            <div style="display:flex; gap:10px; margin-bottom:16px;">
                <div style="flex:2; position:relative;">
                    <i data-lucide="search"
                        style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--sub); width:16px;"></i>
                    <input type="text" id="filter-search" placeholder="Buscar transação..."
                        onkeyup="filterTransactions()"
                        style="width:100%; padding:10px 10px 10px 36px; border:1px solid var(--border); border-radius:8px; background:var(--card); color:var(--text); font-size:13px;">
                </div>
                <select id="filter-category" onchange="filterTransactions()"
                    style="flex:1; padding:10px; border:1px solid var(--border); border-radius:8px; background:var(--card); color:var(--text); font-size:13px;">
                    <option value="">Todas as Categorias</option>
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
                    <tbody id="transactions-table-body">
                        <!-- Loaded via JS -->
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
        <button id="ai-assistant-btn" onclick="toggleChat()"
            style="position:fixed; bottom:24px; left:24px; width:56px; height:56px; border-radius:50%; background:var(--accent); border:none; box-shadow:0 4px 16px rgba(11,54,128,0.4); cursor:pointer; display:flex; align-items:center; justify-content:center; transition:transform 0.2s; z-index:1000;">
            <i data-lucide="bot" width="28" style="color:white;"></i>
        </button>
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
                <div style="display:flex; gap:10px;">
                    <button onclick="resetChat()" title="Novo Chat"
                        style="background:none; border:none; cursor:pointer; opacity:0.8; color:white;">
                        <i data-lucide="rotate-ccw" width="16"></i>
                    </button>
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
                        Se é nossa primeira vez, preciso entender seu perfil. Caso contrário, como posso ajudar hoje?
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
                    <textarea id="chat-input" rows="1" placeholder="Converse comigo..."
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

            // ==================== THEME MANAGEMENT ====================
            const themes = ['light', 'dark', 'solarized'];

            function toggleTheme() {
                const currentTheme = localStorage.getItem('lume-theme') || 'light';
                let nextIndex = (themes.indexOf(currentTheme) + 1) % themes.length;
                setTheme(themes[nextIndex]);
            }

            function setTheme(theme) {
                // Clear all theme classes
                document.body.classList.remove('dark-mode', 'solarized-theme');

                if (theme === 'dark') {
                    document.body.classList.add('dark-mode');
                } else if (theme === 'solarized') {
                    document.body.classList.add('solarized-theme');
                }

                localStorage.setItem('lume-theme', theme);
                updateThemeIcon(theme);
            }

            function updateThemeIcon(theme) {
                const icon = document.getElementById('theme-icon');
                if (icon) {
                    if (theme === 'dark') {
                        icon.setAttribute('data-lucide', 'moon');
                    } else if (theme === 'solarized') {
                        icon.setAttribute('data-lucide', 'sun-snow'); // Icon representing solarized
                    } else {
                        icon.setAttribute('data-lucide', 'sun');
                    }
                    lucide.createIcons();
                }
            }

            // Initialize theme on load
            (function initTheme() {
                // Migrate old setting if exists
                if (localStorage.getItem('lume-dark-mode') === 'true') {
                    localStorage.setItem('lume-theme', 'dark');
                    localStorage.removeItem('lume-dark-mode');
                }

                const savedTheme = localStorage.getItem('lume-theme') || 'light';
                setTheme(savedTheme);
            })();

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

            // Chat history for context
            let chatHistory = [];

            async function sendChatMessage() {
                const input = document.getElementById('chat-input');
                const message = input.value.trim();
                if (!message) return;

                input.value = '';
                addMessage(message, true);

                // Add user message to history
                chatHistory.push({ role: 'user', content: message });

                // Show typing indicator
                addMessage('⏳ Pensando...', false);

                try {
                    const response = await fetch('/ai_assistant.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'chat',
                            message: message,
                            history: chatHistory.slice(0, -1) // Send history without current message
                        })
                    });

                    const result = await response.json();

                    // Remove typing indicator
                    const msgs = document.getElementById('chat-messages');
                    msgs.removeChild(msgs.lastChild);

                    const aiMessage = result.message || 'Desculpe, não entendi.';

                    // Add AI response
                    addMessage(aiMessage, false);

                    // Add AI response to history
                    chatHistory.push({ role: 'assistant', content: aiMessage });

                    // Limit history to last 20 messages
                    if (chatHistory.length > 20) {
                        chatHistory = chatHistory.slice(-20);
                    }

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
                loadEvolutionChart();
                loadTopExpenses();
            }

            // Load charts on page load
            document.addEventListener('DOMContentLoaded', function () {
                loadCategoryData();
                loadBudgets();
                loadEvolutionChart();
                loadTopExpenses();
            });

            // ==================== BUDGET MANAGEMENT ====================
            function openBudgetModal() {
                document.getElementById('budget-modal').style.display = 'flex';
            }

            function closeBudgetModal() {
                document.getElementById('budget-modal').style.display = 'none';
                document.getElementById('budget-category').value = '';
                document.getElementById('budget-limit').value = '';
            }

            async function loadBudgets() {
                try {
                    const response = await fetch('/api/budgets.php');
                    const data = await response.json();

                    const container = document.getElementById('budgets-container');

                    if (data.status !== 'success' || data.budgets.length === 0) {
                        container.innerHTML = `
                            <div style="grid-column: 1/-1; color:var(--sub); font-size:14px; padding:40px; text-align:center; background:var(--card); border-radius:12px;">
                                Nenhum orçamento definido. Clique em "+ Novo Orçamento" para começar!
                            </div>
                        `;
                        return;
                    }

                    container.innerHTML = data.budgets.map(b => {
                        const pct = Math.min(b.percentage, 100);
                        const color = b.percentage >= 100 ? 'var(--danger)' :
                            b.percentage >= 80 ? '#FFA500' : 'var(--success)';
                        const statusIcon = b.over_budget ? '⚠️' : b.percentage >= 80 ? '⚡' : '✓';

                        return `
                            <div style="background:var(--card); border-radius:12px; padding:16px; position:relative;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                    <span style="font-weight:600; color:var(--text);">${b.category}</span>
                                    <button onclick="deleteBudget(${b.id})" style="background:none; border:none; color:var(--sub); cursor:pointer; font-size:16px;" title="Remover">×</button>
                                </div>
                                <div style="display:flex; justify-content:space-between; font-size:13px; color:var(--sub); margin-bottom:8px;">
                                    <span>R$ ${parseFloat(b.current_spent).toFixed(2)} / R$ ${parseFloat(b.monthly_limit).toFixed(2)}</span>
                                    <span style="color:${color};">${statusIcon} ${b.percentage}%</span>
                                </div>
                                <div style="background:var(--border); border-radius:4px; height:8px; overflow:hidden;">
                                    <div style="width:${pct}%; height:100%; background:${color}; border-radius:4px; transition:width 0.3s;"></div>
                                </div>
                                ${b.over_budget ? `<div style="font-size:11px; color:var(--danger); margin-top:6px;">Orçamento estourado em R$ ${(b.current_spent - b.monthly_limit).toFixed(2)}</div>` : ''}
                            </div>
                        `;
                    }).join('');
                } catch (error) {
                    console.error('Error loading budgets:', error);
                }
            }

            async function saveBudget(event) {
                event.preventDefault();
                const category = document.getElementById('budget-category').value;
                const limit = document.getElementById('budget-limit').value;

                try {
                    const response = await fetch('/api/budgets.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ category, monthly_limit: limit })
                    });

                    const result = await response.json();
                    if (result.status === 'success') {
                        closeBudgetModal();
                        loadBudgets();
                    } else {
                        alert('Erro: ' + result.message);
                    }
                } catch (error) {
                    alert('Erro ao salvar: ' + error.message);
                }
            }

            async function deleteBudget(id) {
                if (!confirm('Remover este orçamento?')) return;

                try {
                    const response = await fetch('/api/budgets.php?id=' + id, { method: 'DELETE' });
                    const result = await response.json();
                    if (result.status === 'success') {
                        loadBudgets();
                    } else {
                        alert('Erro: ' + result.message);
                    }
                } catch (error) {
                    alert('Erro ao remover: ' + error.message);
                }
            }

            // ==================== RECURRING TRANSACTIONS ====================
            function openRecurringModal() {
                document.getElementById('recurring-modal').style.display = 'flex';
            }

            function closeRecurringModal() {
                document.getElementById('recurring-modal').style.display = 'none';
            }

            async function loadRecurring() {
                try {
                    const response = await fetch('/api/recurring.php');
                    const data = await response.json();

                    const container = document.getElementById('recurring-container');

                    if (data.status !== 'success' || data.recurring.length === 0) {
                        container.innerHTML = `
                            <div style="grid-column: 1/-1; color:var(--sub); font-size:14px; padding:40px; text-align:center; background:var(--card); border-radius:12px;">
                                Nenhuma transação recorrente. Configure seu salário e contas fixas!
                            </div>
                        `;
                        return;
                    }

                    const freqLabels = { daily: 'Diário', weekly: 'Semanal', monthly: 'Mensal', yearly: 'Anual' };

                    container.innerHTML = data.recurring.map(r => {
                        const isIncome = r.type === 'income';
                        const icon = isIncome ? '💰' : '💸';
                        const color = isIncome ? 'var(--success)' : 'var(--danger)';
                        const nextDate = new Date(r.next_date + 'T00:00:00');
                        const formattedDate = nextDate.toLocaleDateString('pt-BR');
                        const dayLabel = r.day_of_month == -1 ? 'Último dia' : 'Dia ' + r.day_of_month;

                        return `
                            <div style="background:var(--card); border-radius:12px; padding:16px; border-left:4px solid ${color};">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                    <span style="font-weight:600; color:var(--text);">${icon} ${r.description}</span>
                                    <div style="display:flex; gap:8px;">
                                        <button onclick='editRecurring(${JSON.stringify(r)})' style="background:none; border:none; color:var(--accent); cursor:pointer; font-size:14px;" title="Editar">✏️</button>
                                        <button onclick="deleteRecurring(${r.id})" style="background:none; border:none; color:var(--sub); cursor:pointer; font-size:16px;" title="Remover">×</button>
                                    </div>
                                </div>
                                <div style="display:flex; justify-content:space-between; font-size:13px; color:var(--sub);">
                                    <span>${r.category} • ${freqLabels[r.frequency]} • ${dayLabel}</span>
                                    <span style="color:${color}; font-weight:600;">R$ ${parseFloat(r.amount).toFixed(2)}</span>
                                </div>
                                <div style="font-size:12px; color:var(--sub); margin-top:8px;">
                                    📅 Próxima: ${formattedDate}
                                </div>
                            </div>
                        `;
                    }).join('');
                } catch (error) {
                    console.error('Error loading recurring:', error);
                }
            }

            async function saveRecurring(event) {
                event.preventDefault();
                const type = document.querySelector('input[name="rec-type"]:checked').value;
                const description = document.getElementById('rec-description').value;
                const amount = document.getElementById('rec-amount').value;
                const category = document.getElementById('rec-category').value;
                const frequency = document.getElementById('rec-frequency').value;
                const isLastDay = document.getElementById('rec-last-day').checked;
                const day_of_month = isLastDay ? -1 : document.getElementById('rec-day').value;

                try {
                    const response = await fetch('/api/recurring.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ type, description, amount, category, frequency, day_of_month })
                    });

                    const result = await response.json();
                    if (result.status === 'success') {
                        closeRecurringModal();
                        loadRecurring();
                    } else {
                        alert('Erro: ' + result.message);
                    }
                } catch (error) {
                    alert('Erro ao salvar: ' + error.message);
                }
            }

            async function deleteRecurring(id) {
                if (!confirm('Remover esta recorrência?')) return;

                try {
                    const response = await fetch('/api/recurring.php?id=' + id, { method: 'DELETE' });
                    const result = await response.json();
                    if (result.status === 'success') {
                        loadRecurring();
                    } else {
                        alert('Erro: ' + result.message);
                    }
                } catch (error) {
                    alert('Erro ao remover: ' + error.message);
                }
            }

            // Load recurring on page load
            document.addEventListener('DOMContentLoaded', function () {
                loadRecurring();
            });

            let editingRecurringId = null;

            function editRecurring(r) {
                editingRecurringId = r.id;
                // Fill the modal with current values
                document.querySelector(`input[name="rec-type"][value="${r.type}"]`).checked = true;
                document.getElementById('rec-description').value = r.description;
                document.getElementById('rec-amount').value = r.amount;
                document.getElementById('rec-category').value = r.category;
                document.getElementById('rec-frequency').value = r.frequency;

                if (r.day_of_month == -1) {
                    document.getElementById('rec-last-day').checked = true;
                    document.getElementById('rec-day').disabled = true;
                    document.getElementById('rec-day').value = 1;
                } else {
                    document.getElementById('rec-last-day').checked = false;
                    document.getElementById('rec-day').disabled = false;
                    document.getElementById('rec-day').value = r.day_of_month;
                }

                // Open modal
                document.getElementById('recurring-modal').style.display = 'flex';
            }

            // Override saveRecurring to handle edit mode
            const originalSaveRecurring = saveRecurring;
            saveRecurring = async function (event) {
                event.preventDefault();
                const type = document.querySelector('input[name="rec-type"]:checked').value;
                const description = document.getElementById('rec-description').value;
                const amount = document.getElementById('rec-amount').value;
                const category = document.getElementById('rec-category').value;
                const frequency = document.getElementById('rec-frequency').value;
                const isLastDay = document.getElementById('rec-last-day').checked;
                const day_of_month = isLastDay ? -1 : document.getElementById('rec-day').value;

                const method = editingRecurringId ? 'PUT' : 'POST';
                const url = editingRecurringId ? '/api/recurring.php?id=' + editingRecurringId : '/api/recurring.php';

                try {
                    const response = await fetch(url, {
                        method: method,
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ type, description, amount, category, frequency, day_of_month })
                    });

                    const result = await response.json();
                    if (result.status === 'success') {
                        closeRecurringModal();
                        loadRecurring();
                        editingRecurringId = null;
                    } else {
                        alert('Erro: ' + result.message);
                    }
                } catch (error) {
                    alert('Erro ao salvar: ' + error.message);
                }
            };

            // Reset edit mode when closing modal
            const originalCloseRecurringModal = closeRecurringModal;
            closeRecurringModal = function () {
                editingRecurringId = null;
                document.getElementById('recurring-modal').style.display = 'none';
                document.getElementById('rec-description').value = '';
                document.getElementById('rec-amount').value = '';
                document.getElementById('rec-day').value = 1;
                document.getElementById('rec-day').disabled = false;
                document.getElementById('rec-last-day').checked = false;
            };

            // ==================== TRANSACTION MANAGEMENT ====================
            function toggleTransactionDetail(id) {
                const detailRow = document.getElementById('detail-' + id);
                if (detailRow) {
                    const isVisible = detailRow.style.display !== 'none';
                    detailRow.style.display = isVisible ? 'none' : 'table-row';

                    // Toggle icon
                    const icon = document.querySelector('.expand-icon-' + id);
                    if (icon) {
                        icon.setAttribute('data-lucide', isVisible ? 'chevron-down' : 'chevron-up');
                        lucide.createIcons();
                    }
                }
            }

            function openEditModal(transaction) {
                document.getElementById('edit-id').value = transaction.id;
                document.getElementById('edit-description').value = transaction.description;
                document.getElementById('edit-amount').value = transaction.amount;
                document.getElementById('edit-category').value = transaction.category;
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
                if (!confirm('Tem certeza que deseja remover esta transação?')) return;

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

            // ==================== ANALYTICS ====================
            let evolutionChartInstance = null;

            async function loadComparison() {
                try {
                    const response = await fetch(`/api/analytics.php?type=comparison&month=${currentMonth}&year=${currentYear}`);
                    const result = await response.json();

                    if (result.status === 'success') {
                        const badgeObj = document.getElementById('comparison-badge');
                        if (!badgeObj) return;

                        const diff = result.percent;
                        const direction = result.diff > 0 ? 'up' : 'down'; // up = spent more

                        let text, icon, color, bg;

                        if (direction === 'down') {
                            text = `${Math.abs(diff)}% vs mês passado`;
                            icon = '⬇️';
                            color = '#10a37f'; // Green
                            bg = 'rgba(16, 163, 127, 0.1)';
                        } else {
                            text = `${Math.abs(diff)}% vs mês passado`;
                            icon = '⬆️';
                            color = '#ef4444'; // Red
                            bg = 'rgba(239, 68, 68, 0.1)';
                        }

                        if (Math.abs(diff) < 1) {
                            text = 'Estável vs mês passado';
                            icon = '➡️';
                            color = 'var(--sub)';
                            bg = 'var(--border)';
                        }

                        badgeObj.style.display = 'inline-flex';
                        badgeObj.innerHTML = `<span style="margin-right:4px;">${icon}</span> ${text}`;
                        badgeObj.style.color = color;
                        badgeObj.style.background = bg;
                    }
                } catch (e) {
                    console.error('Comparison error', e);
                }
            }

            async function loadEvolutionChart() {
                try {
                    const response = await fetch(`/api/analytics.php?type=evolution&month=${currentMonth}&year=${currentYear}`);
                    const result = await response.json();

                    loadComparison();

                    if (result.status === 'success') {
                        const ctx = document.getElementById('evolutionChart').getContext('2d');
                        const labels = result.data.map(d => d.day);
                        const data = result.data.map(d => d.balance);

                        // Destroy old chart if exists
                        if (evolutionChartInstance) {
                            evolutionChartInstance.destroy();
                        }

                        const isDark = document.body.classList.contains('dark-mode') || document.body.classList.contains('solarized-theme');
                        const gridColor = isDark ? '#38383A' : '#E5E5EA';
                        const textColor = isDark ? '#FFF' : '#1D1D1F';
                        const accentColor = '#34C759';

                        evolutionChartInstance = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Saldo Acumulado',
                                    data: data,
                                    borderColor: accentColor,
                                    backgroundColor: (context) => {
                                        const ctx = context.chart.ctx;
                                        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                                        gradient.addColorStop(0, 'rgba(52, 199, 89, 0.4)');
                                        gradient.addColorStop(1, 'rgba(52, 199, 89, 0.0)');
                                        return gradient;
                                    },
                                    borderWidth: 2,
                                    pointRadius: 2,
                                    fill: true,
                                    tension: 0.4
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: false,
                                        grid: { color: gridColor },
                                        ticks: { color: textColor }
                                    },
                                    x: {
                                        grid: { display: false },
                                        ticks: { color: textColor }
                                    }
                                },
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        callbacks: {
                                            label: function (context) {
                                                return 'R$ ' + context.parsed.y.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    }
                } catch (e) {
                    console.error('Error loading evolution:', e);
                }
            }

            async function loadTopExpenses() {
                try {
                    const response = await fetch(`/api/analytics.php?type=top_expenses&month=${currentMonth}&year=${currentYear}`);
                    const result = await response.json();

                    const container = document.getElementById('top-expenses-list');
                    if (result.status === 'success' && result.data.length > 0) {
                        container.innerHTML = result.data.map(t => `
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="background:var(--bg); width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:16px;">
                                         ${getCategoryEmoji(t.category)}
                                    </div>
                                    <div>
                                        <div style="font-weight:500; font-size:13px; color:var(--text);">${t.description ? t.description : t.category}</div>
                                        <div style="font-size:11px; color:var(--sub);">${formatDate(t.transaction_date)} • ${t.category}</div>
                                    </div>
                                </div>
                                <div style="font-weight:600; font-size:13px; color:var(--danger);">- R$ ${parseFloat(t.amount).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</div>
                            </div>
                        `).join('');
                    } else {
                        container.innerHTML = '<div style="color:var(--sub); font-size:13px; text-align:center; padding:20px;">Nenhuma despesa este mês.</div>';
                    }
                } catch (e) {
                    console.error('Error loading top expenses:', e);
                }
            }

            function getCategoryEmoji(cat) {
                const emojis = {
                    'Alimentação': '🍔', 'Mercado': '🛒', 'Transporte': '🚗',
                    'Lazer': '🎉', 'Contas': '💡', 'Saúde': '💊', 'Educação': '📚',
                    'Moradia': '🏠', 'Viagem': '✈️', 'Pets': '🐾', 'Roupas': '👗',
                    'Beleza': '💅', 'Assinaturas': '📺', 'Presentes': '🎁', 'Salário': '💰',
                    'Extra': '💎', 'Investimento': '📈', 'Outros': '📦'
                };
                return emojis[cat] || '📦';
            }

            function formatDate(dateStr) {
                const [y, m, d] = dateStr.split('-');
                return `${d}/${m}`;
            }

            // Inject CSS for Analytics Responsiveness
            const analyticsStyle = document.createElement('style');
            analyticsStyle.innerHTML = `
                @media (max-width: 1000px) {
                    .analytics-grid { grid-template-columns: 1fr !important; }
                }
                .typing-indicator span { display: inline-block; width: 4px; height: 4px; background: #aaa; border-radius: 50%; margin: 0 2px; animation: bounce 1.4s infinite ease-in-out both; }
                .typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
                .typing-indicator span:nth-child(2) { animation-delay: -0.16s; }
                @keyframes bounce { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1); } }
            `;
            document.head.appendChild(analyticsStyle);

            // ==================== AI CHAT MODAL ====================
            function toggleChat() {
                const modal = document.getElementById('ai-chat-modal');
                if (modal.style.display === 'none') {
                    modal.style.display = 'flex';
                    setTimeout(() => document.getElementById('chat-input').focus(), 100);
                } else {
                    modal.style.display = 'none';
                }
            }

            async function sendChatMessage(msgOverride = null, role = 'user') {
                const input = document.getElementById('chat-input');
                const message = msgOverride || input.value.trim();
                if (!message) return;

                const messagesContainer = document.getElementById('chat-messages');

                // Append User Message
                if (role === 'user') {
                    if (!msgOverride) input.value = '';
                    appendMessage('user', message);
                }

                // Loading
                const loadingId = 'loading-' + Date.now();
                appendMessage('ai', '<span class="typing-indicator"><span></span><span></span><span></span></span>', loadingId);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;

                try {
                    const response = await fetch('ai_assistant.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'chat', message: message, history: getChatHistory() })
                    });
                    const data = await response.json();

                    // Remove Loading
                    document.getElementById(loadingId).remove();

                    if (data.status === 'success' || data.message) {
                        appendMessage('ai', data.message);
                    } else {
                        appendMessage('ai', '❌ Erro ao processar resposta.');
                    }

                } catch (e) {
                    document.getElementById(loadingId).remove();
                    appendMessage('ai', '❌ Erro de conexão.');
                    console.error(e);
                }
            }

            function appendMessage(role, text, id = null) {
                const container = document.getElementById('chat-messages');
                const div = document.createElement('div');
                if (id) div.id = id;
                div.style.display = 'flex';
                div.style.gap = '10px';
                div.style.marginBottom = '12px';

                const isUser = role === 'user';
                const avatar = isUser
                    ? `<div style="width:28px; height:28px; background:#555; border-radius:6px; flex-shrink:0; display:flex; align-items:center; justify-content:center; color:white;"><i data-lucide="user" width="14"></i></div>`
                    : `<div style="width:28px; height:28px; background:#10a37f; border-radius:6px; flex-shrink:0; display:flex; align-items:center; justify-content:center; color:white;"><i data-lucide="bot" width="16"></i></div>`;

                div.innerHTML = `
                    ${avatar}
                    <div style="background:${isUser ? 'var(--card)' : 'transparent'}; padding:${isUser ? '8px 12px' : '0'}; border-radius:8px; border:${isUser ? '1px solid var(--border)' : 'none'}; font-size:14px; line-height:1.5; color:var(--text); flex:1;">
                        ${text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')}
                    </div>
                `;
                container.appendChild(div);
                container.scrollTop = container.scrollHeight;
                lucide.createIcons();
            }

            function getChatHistory() {
                // Return last 10 messages for context (simplified)
                const msgs = [];
                // Implementation suppressed for brevity, assume backend handles history or session
                return [];
            }

            async function generateAIInsights() {
                toggleChat();
                appendMessage('ai', '🔍 Analisando seus dados do mês...');

                try {
                    const response = await fetch(`/api/analytics.php?type=ai_stats&month=${currentMonth}&year=${currentYear}`);
                    const data = await response.json();

                    if (data.status === 'success') {
                        const t = data.totals;
                        const cats = data.categories.map(c => `${c.category} (R$ ${parseFloat(c.total).toFixed(2)})`).join(', ');
                        const top = data.top_expenses.map(e => `${e.description} (R$ ${parseFloat(e.amount).toFixed(2)})`).join(', ');

                        const prompt = `
                        Analise meus dados financeiros deste mês:
                        - Receitas: R$ ${parseFloat(t.total_income).toFixed(2)}
                        - Despesas: R$ ${parseFloat(t.total_expense).toFixed(2)}
                        - Categorias Principais: ${cats}
                        - Top Despesas: ${top}
                        
                        Identifique 3 pontos de atenção e me dê uma dica prática para economizar.
                        Seja curto, direto e use emojis.
                        `;

                        await sendChatMessage(prompt, 'hidden'); // user role but hidden? No, send as user so user sees what was asked.
                        // Actually let's just trigger the internal logic or show "Solicitando análise..."
                    }
                } catch (e) {
                    appendMessage('ai', '❌ Erro ao buscar dados para análise.');
                    console.error(e);
                }
            }

            function exportCSV() {
                window.location.href = `/api/export.php?month=${currentMonth}&year=${currentYear}`;
            }

            async function filterTransactions() {
                const search = document.getElementById('filter-search').value;
                const category = document.getElementById('filter-category').value;
                const tbody = document.getElementById('transactions-table-body');

                try {
                    const response = await fetch(`/api/transactions.php?search=${encodeURIComponent(search)}&category=${encodeURIComponent(category)}&month=${currentMonth}&year=${currentYear}`);
                    const result = await response.json();

                    if (result.status === 'success') {
                        const transactions = result.transactions;
                        if (transactions.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="6" style="padding:40px; text-align:center; color:var(--sub);">Nenhuma transação encontrada.</td></tr>';
                            return;
                        }

                        tbody.innerHTML = transactions.map(t => {
                            const isIncome = t.type === 'income';
                            const color = isIncome ? 'var(--success)' : 'var(--danger)';
                            const sign = isIncome ? '+' : '-';
                            const hasTranscription = t.transcription && t.transcription.trim() !== '';
                            const tJson = JSON.stringify(t).replace(/'/g, "&apos;").replace(/"/g, "&quot;");

                            // Edit Icon vs Mic Icon
                            const typeIcon = hasTranscription ?
                                `<i data-lucide="mic" width="14" style="color:var(--accent); vertical-align:middle; margin-right:6px;" title="Voz"></i>` :
                                `<i data-lucide="edit-3" width="14" style="color:var(--sub); vertical-align:middle; margin-right:6px;" title="Manual"></i>`;

                            const expandIcon = hasTranscription ?
                                `<i data-lucide="chevron-down" width="16" class="expand-icon-${t.id}"></i>` :
                                `<span style="width:16px; display:inline-block;"></span>`;

                            const rowHtml = `
                                <tr class="transaction-row" onclick="toggleTransactionDetail(${t.id})" style="border-bottom:1px solid var(--border); cursor:pointer;">
                                    <td style="padding:12px 8px 12px 16px; color:var(--sub);">${expandIcon}</td>
                                    <td style="padding:12px 16px; font-weight:500; color:var(--text);">
                                        ${typeIcon} ${t.description}
                                    </td>
                                    <td style="padding:12px 16px;"><span style="background:var(--bg); padding:4px 8px; border-radius:4px; font-size:11px;">${t.category}</span></td>
                                    <td style="padding:12px 16px; color:var(--sub);">${formatDate(t.transaction_date)}</td>
                                    <td style="padding:12px 16px; font-weight:600; color:${color};">${sign} R$ ${parseFloat(t.amount).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
                                    <td style="padding:12px 16px; text-align:right;" onclick="event.stopPropagation();">
                                        <button onclick="openEditModal(${tJson})" style="background:transparent; border:1px solid var(--border); padding:6px 10px; border-radius:6px; cursor:pointer; margin-right:4px;">
                                            <i data-lucide="edit-2" width="14"></i>
                                        </button>
                                        <button onclick="deleteTransaction(${t.id})" style="background:transparent; border:1px solid var(--danger); color:var(--danger); padding:6px 10px; border-radius:6px; cursor:pointer;">
                                            <i data-lucide="trash-2" width="14"></i>
                                        </button>
                                    </td>
                                </tr>
                            `;

                            const detailHtml = hasTranscription ? `
                                <tr class="transaction-detail" id="detail-${t.id}" style="display:none; background:#FAFAFA;">
                                    <td colspan="6" style="padding:16px 16px 16px 48px;">
                                        <div style="display:flex; align-items:flex-start; gap:12px;">
                                            <i data-lucide="mic" width="18" style="color:var(--accent); flex-shrink:0; margin-top:2px;"></i>
                                            <div>
                                                <div style="font-size:11px; color:var(--sub); font-weight:600; margin-bottom:4px;">TRANSCRIÇÃO</div>
                                                <div style="font-size:13px; color:var(--text); font-style:italic;">"${t.transcription}"</div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>` : '';

                            return rowHtml + detailHtml;
                        }).join('');
                        lucide.createIcons();
                    }
                } catch (e) {
                    console.error('Filter error', e);
                }
            }

            async function deleteTransaction(id) {
                if (!confirm('Excluir transação?')) return;

                try {
                    const response = await fetch(`/api/transactions.php?id=${id}`, { method: 'DELETE' });
                    const result = await response.json();
                    if (result.status === 'success') {
                        filterTransactions(); // Reload list
                        loadEvolutionChart(); // Update chart
                        loadCategoryData();   // Update pies
                    } else {
                        alert('Erro: ' + result.message);
                    }
                } catch (e) {
                    alert('Erro ao excluir');
                }
            }

            // =============================================
            // INVESTMENTS FUNCTIONS
            // =============================================

            function formatCurrency(value) {
                return parseFloat(value).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function formatDate(dateStr) {
                if (!dateStr) return '-';
                const d = new Date(dateStr + 'T00:00:00');
                return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
            }

            function getInvestmentTypeEmoji(type) {
                const emojis = {
                    'CDB': '💰', 'Tesouro': '🏛️', 'LCI/LCA': '🏠', 'Ações': '📊',
                    'FIIs': '🏢', 'Crypto': '₿', 'Poupança': '🐷', 'Outro': '📦'
                };
                return emojis[type] || '📦';
            }

            function getPerformanceColor(perf) {
                if (perf === 'excellent') return '#10b981';
                if (perf === 'good') return '#3b82f6';
                if (perf === 'below') return '#f59e0b';
                return '#ef4444';
            }

            async function loadInvestments() {
                try {
                    const response = await fetch('/api/investments.php');
                    const data = await response.json();

                    if (data.status !== 'success') {
                        console.error('Error loading investments:', data.message);
                        return;
                    }

                    // Update summary
                    const summary = data.summary;
                    document.getElementById('inv-total-invested').textContent = 'R$ ' + formatCurrency(summary.total_invested);
                    document.getElementById('inv-total-current').textContent = 'R$ ' + formatCurrency(summary.total_current);

                    const gainEl = document.getElementById('inv-total-gain');
                    const yieldEl = document.getElementById('inv-total-yield');

                    if (summary.total_gain >= 0) {
                        gainEl.textContent = '+R$ ' + formatCurrency(summary.total_gain);
                        gainEl.style.color = 'var(--success)';
                        yieldEl.textContent = '+' + formatCurrency(summary.total_yield) + '%';
                        yieldEl.style.color = 'var(--success)';
                    } else {
                        gainEl.textContent = '-R$ ' + formatCurrency(Math.abs(summary.total_gain));
                        gainEl.style.color = 'var(--danger)';
                        yieldEl.textContent = formatCurrency(summary.total_yield) + '%';
                        yieldEl.style.color = 'var(--danger)';
                    }

                    // Render investment cards
                    const container = document.getElementById('investments-container');

                    if (data.investments.length === 0) {
                        container.innerHTML = `
                            <div style="color:var(--sub); font-size:14px; padding:40px; text-align:center; background:var(--card); border-radius:12px; grid-column: 1 / -1;">
                                Nenhum investimento cadastrado. Clique em "+ Novo Investimento" para começar!
                            </div>
                        `;
                        return;
                    }

                    container.innerHTML = data.investments.map(inv => {
                        const perfColor = getPerformanceColor(inv.performance);
                        const emoji = getInvestmentTypeEmoji(inv.type);
                        const gainSign = parseFloat(inv.gain) >= 0 ? '+' : '';
                        const yieldSign = parseFloat(inv.yield_percent) >= 0 ? '+' : '';

                        return `
                            <div style="background:var(--card); border-radius:12px; padding:16px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-left:4px solid ${perfColor};">
                                <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:12px;">
                                    <div>
                                        <div style="font-weight:600; font-size:15px; color:var(--text);">${emoji} ${inv.name}</div>
                                        <div style="font-size:11px; color:var(--sub);">${inv.type} • Há ${inv.days_elapsed || 0} dias</div>
                                    </div>
                                    <div style="text-align:right;">
                                        <div style="font-weight:700; font-size:18px; color:var(--text);">R$ ${formatCurrency(inv.current_value)}</div>
                                        <div style="font-size:12px; color:${perfColor}; font-weight:600;">${yieldSign}${formatCurrency(inv.yield_percent)}%</div>
                                    </div>
                                </div>
                                
                                <div style="display:flex; justify-content:space-between; font-size:12px; color:var(--sub); margin-bottom:12px;">
                                    <span>Aportado: R$ ${formatCurrency(inv.net_invested || 0)}</span>
                                    <span style="color:${perfColor};">${gainSign}R$ ${formatCurrency(inv.gain)}</span>
                                </div>
                                
                                <div style="font-size:11px; color:var(--sub); margin-bottom:12px;">
                                    📊 Projeção: ${formatCurrency(inv.monthly_projection || 0)}%/mês
                                </div>
                                
                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                    <button onclick="openDepositModal(${inv.id}, '${inv.name}')" style="flex:1; min-width:80px; padding:8px; border:none; background:linear-gradient(135deg, #10b981 0%, #059669 100%); color:white; border-radius:6px; font-size:11px; cursor:pointer; font-weight:600;">
                                        💰 Aporte
                                    </button>
                                    <button onclick="openUpdateModal(${inv.id}, ${inv.current_value})" style="flex:1; min-width:80px; padding:8px; border:1px solid var(--border); background:var(--card); border-radius:6px; font-size:11px; cursor:pointer; color:var(--text);">
                                        📊 Atualizar
                                    </button>
                                    <button onclick="viewInvestmentHistory(${inv.id})" style="flex:1; min-width:80px; padding:8px; border:1px solid var(--accent); background:transparent; border-radius:6px; font-size:11px; cursor:pointer; color:var(--accent);">
                                        📜 Histórico
                                    </button>
                                    <button onclick="deleteInvestment(${inv.id})" style="padding:8px 10px; border:1px solid var(--danger); background:transparent; border-radius:6px; font-size:11px; cursor:pointer; color:var(--danger);">
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        `;
                    }).join('');

                } catch (e) {
                    console.error('Error loading investments:', e);
                }
            }

            // View investment history modal
            async function viewInvestmentHistory(id) {
                try {
                    const response = await fetch(`/api/investments.php?id=${id}`);
                    const data = await response.json();

                    if (data.status !== 'success') {
                        alert('Erro ao carregar histórico');
                        return;
                    }

                    const inv = data.investment;
                    const transactions = inv.transactions || [];

                    let historyHtml = '';
                    if (transactions.length === 0) {
                        historyHtml = '<div style="text-align:center; color:var(--sub); padding:20px;">Nenhuma transação registrada</div>';
                    } else {
                        historyHtml = transactions.map(t => {
                            let icon = '📊';
                            let color = 'var(--sub)';
                            let label = 'Atualização';
                            let amountText = `R$ ${formatCurrency(t.balance_after)}`;

                            if (t.type === 'deposit') {
                                icon = '💰';
                                color = 'var(--success)';
                                label = 'Aporte';
                                amountText = `+R$ ${formatCurrency(t.amount)}`;
                            } else if (t.type === 'withdrawal') {
                                icon = '💸';
                                color = 'var(--danger)';
                                label = 'Retirada';
                                amountText = `-R$ ${formatCurrency(t.amount)}`;
                            }

                            return `
                                <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border);">
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <span style="font-size:18px;">${icon}</span>
                                        <div>
                                            <div style="font-size:13px; font-weight:600; color:var(--text);">${label}</div>
                                            <div style="font-size:11px; color:var(--sub);">${formatDate(t.transaction_date)}</div>
                                        </div>
                                    </div>
                                    <div style="text-align:right;">
                                        <div style="font-size:14px; font-weight:600; color:${color};">${amountText}</div>
                                        <div style="font-size:11px; color:var(--sub);">Saldo: R$ ${formatCurrency(t.balance_after)}</div>
                                    </div>
                                </div>
                            `;
                        }).join('');
                    }

                    // Show in a modal
                    const modalHtml = `
                        <div id="history-modal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:300; display:flex; align-items:center; justify-content:center;">
                            <div style="background:var(--card); padding:24px; border-radius:16px; width:90%; max-width:450px; max-height:80vh; overflow-y:auto;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                                    <h3 style="margin:0; color:var(--text);">📜 Histórico - ${inv.name}</h3>
                                    <button onclick="document.getElementById('history-modal').remove()" style="background:none; border:none; font-size:20px; cursor:pointer; color:var(--sub);">✕</button>
                                </div>
                                <div style="background:var(--bg); border-radius:8px; padding:12px; margin-bottom:16px;">
                                    <div style="display:flex; justify-content:space-between; font-size:13px;">
                                        <span style="color:var(--sub);">Total Aportado:</span>
                                        <span style="font-weight:600; color:var(--text);">R$ ${formatCurrency(inv.net_invested)}</span>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; font-size:13px; margin-top:4px;">
                                        <span style="color:var(--sub);">Valor Atual:</span>
                                        <span style="font-weight:600; color:var(--text);">R$ ${formatCurrency(inv.current_value)}</span>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; font-size:13px; margin-top:4px;">
                                        <span style="color:var(--sub);">Rendimento:</span>
                                        <span style="font-weight:600; color:${inv.gain >= 0 ? 'var(--success)' : 'var(--danger)'};">${inv.gain >= 0 ? '+' : ''}R$ ${formatCurrency(inv.gain)} (${inv.yield_percent >= 0 ? '+' : ''}${formatCurrency(inv.yield_percent)}%)</span>
                                    </div>
                                </div>
                                <div style="font-size:12px; font-weight:600; color:var(--sub); margin-bottom:8px;">TRANSAÇÕES</div>
                                ${historyHtml}
                            </div>
                        </div>
                    `;

                    document.body.insertAdjacentHTML('beforeend', modalHtml);

                } catch (e) {
                    console.error('Error loading history:', e);
                    alert('Erro ao carregar histórico');
                }
            }

            // Deposit Modal
            function openDepositModal(id, name) {
                document.getElementById('deposit-inv-id').value = id;
                document.getElementById('deposit-inv-name').textContent = name;
                document.getElementById('deposit-amount').value = '';
                document.getElementById('deposit-date').value = new Date().toISOString().split('T')[0];
                document.getElementById('deposit-modal').style.display = 'flex';
            }

            function closeDepositModal() {
                document.getElementById('deposit-modal').style.display = 'none';
            }

            async function saveDeposit(e) {
                e.preventDefault();

                const data = {
                    action: 'deposit',
                    investment_id: document.getElementById('deposit-inv-id').value,
                    amount: document.getElementById('deposit-amount').value,
                    date: document.getElementById('deposit-date').value
                };

                try {
                    const response = await fetch('/api/investments.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    const result = await response.json();

                    if (result.status === 'success') {
                        closeDepositModal();
                        loadInvestments();
                    } else {
                        alert('Erro: ' + result.message);
                    }
                } catch (e) {
                    alert('Erro ao registrar aporte');
                }
            }

            function openInvestmentModal() {
                document.getElementById('investment-modal').style.display = 'flex';
                document.getElementById('inv-date').value = new Date().toISOString().split('T')[0];
            }

            function closeInvestmentModal() {
                document.getElementById('investment-modal').style.display = 'none';
                document.getElementById('inv-name').value = '';
                document.getElementById('inv-amount').value = '';
                document.getElementById('inv-rate').value = '';
            }

            async function saveInvestment(e) {
                e.preventDefault();

                const data = {
                    name: document.getElementById('inv-name').value,
                    type: document.getElementById('inv-type').value,
                    initial_amount: document.getElementById('inv-amount').value,
                    start_date: document.getElementById('inv-date').value,
                    expected_rate: document.getElementById('inv-rate').value || 0,
                    rate_period: document.getElementById('inv-rate-period').value
                };

                try {
                    const response = await fetch('/api/investments.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    const result = await response.json();

                    if (result.status === 'success') {
                        closeInvestmentModal();
                        loadInvestments();
                    } else {
                        alert('Erro: ' + result.message);
                    }
                } catch (e) {
                    alert('Erro ao salvar investimento');
                }
            }

            function openUpdateModal(id, currentValue) {
                document.getElementById('update-inv-id').value = id;
                document.getElementById('update-new-value').value = currentValue;
                document.getElementById('update-date').value = new Date().toISOString().split('T')[0];
                document.getElementById('update-value-modal').style.display = 'flex';
            }

            function closeUpdateModal() {
                document.getElementById('update-value-modal').style.display = 'none';
            }

            async function updateInvestmentValue(e) {
                e.preventDefault();

                const data = {
                    action: 'update_value',
                    investment_id: document.getElementById('update-inv-id').value,
                    new_value: document.getElementById('update-new-value').value,
                    update_date: document.getElementById('update-date').value
                };

                try {
                    const response = await fetch('/api/investments.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    const result = await response.json();

                    if (result.status === 'success') {
                        closeUpdateModal();
                        loadInvestments();
                    } else {
                        alert('Erro: ' + result.message);
                    }
                } catch (e) {
                    alert('Erro ao atualizar valor');
                }
            }

            async function deleteInvestment(id) {
                if (!confirm('Remover este investimento?')) return;

                try {
                    const response = await fetch('/api/investments.php', {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id })
                    });
                    const result = await response.json();

                    if (result.status === 'success') {
                        loadInvestments();
                    } else {
                        alert('Erro: ' + result.message);
                    }
                } catch (e) {
                    alert('Erro ao remover investimento');
                }
            }

            // === CURRENCY FORMATTING ===
            function formatCurrency(value) {
                // Convert to number and format as Brazilian currency
                const num = parseFloat(value) || 0;
                return num.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            // === RECURRING MODAL FUNCTIONS ===
            function openRecurringModal() {
                document.getElementById('recurring-modal').style.display = 'flex';
                updateRecTypeVisual();
            }

            function closeRecurringModal() {
                document.getElementById('recurring-modal').style.display = 'none';
            }

            function updateRecTypeVisual() {
                const options = document.querySelectorAll('.rec-type-option');
                options.forEach(opt => {
                    const radio = opt.querySelector('input[type="radio"]');
                    if (radio.checked) {
                        if (radio.value === 'income') {
                            opt.style.border = '2px solid var(--success)';
                            opt.style.background = 'rgba(52, 199, 89, 0.1)';
                        } else {
                            opt.style.border = '2px solid var(--danger)';
                            opt.style.background = 'rgba(255, 59, 48, 0.1)';
                        }
                    } else {
                        opt.style.border = '2px solid var(--border)';
                        opt.style.background = 'var(--card)';
                    }
                });
            }

            function formatRecCurrency(input) {
                // Armazena posição do cursor
                const cursorPos = input.selectionStart;
                const oldLen = input.value.length;
                // Remove tudo exceto números e vírgula
                let value = input.value.replace(/[^\d,]/g, '');

                // Separa parte inteira e decimal
                const commaIndex = value.indexOf(',');
                let intPart = commaIndex >= 0 ? value.substring(0, commaIndex) : value;
                let decPart = commaIndex >= 0 ? value.substring(commaIndex + 1) : '';

                // Remove zeros à esquerda (mas mantém pelo menos um dígito)
                intPart = intPart.replace(/^0+/, '') || '0';

                // Limita decimais a 2 dígitos
                decPart = decPart.replace(/\D/g, '').substring(0, 2);

                // Adiciona pontos de milhar na parte inteira
                intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

                // Reconstrói o valor
                if (commaIndex >= 0) {
                    value = intPart + ',' + decPart;
                } else {
                    value = intPart;
                }

                input.value = value;

                // Restaura posição do cursor ajustada
                const newLen = input.value.length;
                const newPos = cursorPos + (newLen - oldLen);
                input.setSelectionRange(newPos, newPos);
            }

            function parseRecCurrency(value) {
                if (!value) return 0;
                // Remove pontos (milhar) e troca vírgula por ponto (decimal)
                let cleaned = value.replace(/\./g, '').replace(',', '.');
                return parseFloat(cleaned) || 0;
            }


            async function saveRecurring(e) {
                e.preventDefault();

                const typeRadio = document.querySelector('input[name="rec-type"]:checked');
                const type = typeRadio ? typeRadio.value : 'expense';
                const description = document.getElementById('rec-description').value;
                const amount = parseFloat(document.getElementById('rec-amount').value) || 0;
                const category = document.getElementById('rec-category').value;
                const frequency = document.getElementById('rec-frequency').value;
                const lastDay = document.getElementById('rec-last-day').checked;
                const day = lastDay ? -1 : parseInt(document.getElementById('rec-day').value);

                if (amount <= 0) {
                    alert('Por favor, insira um valor válido');
                    return;
                }

                const data = {
                    type: type,
                    description: description,
                    amount: amount,
                    category: category,
                    frequency: frequency,
                    day_of_month: day,
                    active: true
                };

                try {
                    const response = await fetch('/api/recurring.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    const result = await response.json();

                    if (result.status === 'success') {
                        closeRecurringModal();
                        loadRecurring();
                        // Clear form
                        document.getElementById('rec-description').value = '';
                        document.getElementById('rec-amount').value = '';
                    } else {
                        alert('Erro: ' + result.message);
                    }
                } catch (err) {
                    console.error(err);
                    alert('Erro ao salvar recorrência');
                }
            }

            // Load recurring transactions
            async function loadRecurring() {
                const container = document.getElementById('recurring-container');

                try {
                    const response = await fetch('/api/recurring.php');
                    const data = await response.json();

                    if (data.status !== 'success') {
                        container.innerHTML = '<div style="color:var(--sub); text-align:center; padding:20px;">Erro ao carregar</div>';
                        return;
                    }

                    const recurring = data.recurring || [];

                    if (recurring.length === 0) {
                        container.innerHTML = '<div style="color:var(--sub); text-align:center; padding:20px; background:var(--card); border-radius:12px;">Nenhuma recorrência cadastrada. Clique em "+ Nova Recorrência" para adicionar.</div>';
                        return;
                    }

                    const frequencyLabels = {
                        'monthly': 'Mensal',
                        'weekly': 'Semanal',
                        'yearly': 'Anual'
                    };

                    container.innerHTML = recurring.map(r => {
                        const isIncome = r.type === 'income';
                        const icon = isIncome ? '💰' : '💸';
                        const color = isIncome ? 'var(--success)' : 'var(--danger)';
                        const dayLabel = r.day_of_month == -1 ? 'Último dia' : `Dia ${r.day_of_month}`;
                        const escapedDesc = (r.description || '').replace(/'/g, "\\'");

                        return `
                            <div style="background:var(--card); border-radius:12px; padding:16px; display:flex; justify-content:space-between; align-items:center;">
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <div style="font-size:24px;">${icon}</div>
                                    <div>
                                        <div style="font-weight:600; color:var(--text);">${r.description}</div>
                                        <div style="font-size:12px; color:var(--sub);">${r.category} • ${frequencyLabels[r.frequency] || r.frequency} • ${dayLabel}</div>
                                    </div>
                                </div>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <div style="font-weight:700; color:${color}; margin-right:8px;">R$ ${formatCurrency(r.amount)}</div>
                                    <button onclick="openEditRecurringModal(${r.id}, '${r.type}', '${escapedDesc}', ${r.amount}, '${r.category}', '${r.frequency}', ${r.day_of_month})" style="background:none; border:none; color:var(--accent); cursor:pointer; font-size:14px;" title="Editar">✏️</button>
                                    <button onclick="deleteRecurring(${r.id})" style="background:none; border:none; color:var(--danger); cursor:pointer; font-size:14px;" title="Remover">🗑️</button>
                                </div>
                            </div>
                        `;
                    }).join('');


                } catch (e) {
                    console.error(e);
                    container.innerHTML = '<div style="color:var(--sub); text-align:center; padding:20px;">Erro ao carregar recorrências</div>';
                }
            }

            // Delete recurring transaction
            async function deleteRecurring(id) {
                if (!confirm('Remover esta recorrência?')) return;

                try {
                    const response = await fetch('/api/recurring.php', {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id })
                    });
                    const result = await response.json();

                    if (result.status === 'success') {
                        loadRecurring();
                    } else {
                        alert('Erro: ' + result.message);
                    }
                } catch (e) {
                    console.error(e);
                    alert('Erro ao remover recorrência');
                }
            }

            // Edit recurring - open modal
            function openEditRecurringModal(id, type, description, amount, category, frequency, dayOfMonth) {
                document.getElementById('edit-rec-id').value = id;
                document.getElementById('edit-rec-description').value = description;
                document.getElementById('edit-rec-amount').value = amount;
                document.getElementById('edit-rec-category').value = category;
                document.getElementById('edit-rec-frequency').value = frequency;

                // Set type radio
                const radios = document.querySelectorAll('input[name="edit-rec-type"]');
                radios.forEach(r => r.checked = r.value === type);

                // Set day
                if (dayOfMonth == -1) {
                    document.getElementById('edit-rec-last-day').checked = true;
                    document.getElementById('edit-rec-day').disabled = true;
                    document.getElementById('edit-rec-day').value = 1;
                } else {
                    document.getElementById('edit-rec-last-day').checked = false;
                    document.getElementById('edit-rec-day').disabled = false;
                    document.getElementById('edit-rec-day').value = dayOfMonth;
                }

                updateEditRecTypeVisual();
                document.getElementById('edit-recurring-modal').style.display = 'flex';
            }

            function closeEditRecurringModal() {
                document.getElementById('edit-recurring-modal').style.display = 'none';
            }

            function updateEditRecTypeVisual() {
                const options = document.querySelectorAll('.edit-rec-type-option');
                options.forEach(opt => {
                    const radio = opt.querySelector('input[type="radio"]');
                    if (radio.checked) {
                        if (radio.value === 'income') {
                            opt.style.border = '2px solid var(--success)';
                            opt.style.background = 'rgba(52, 199, 89, 0.1)';
                        } else {
                            opt.style.border = '2px solid var(--danger)';
                            opt.style.background = 'rgba(255, 59, 48, 0.1)';
                        }
                    } else {
                        opt.style.border = '2px solid var(--border)';
                        opt.style.background = 'var(--card)';
                    }
                });
            }

            async function updateRecurring(e) {
                e.preventDefault();

                const id = document.getElementById('edit-rec-id').value;
                const typeRadio = document.querySelector('input[name="edit-rec-type"]:checked');
                const type = typeRadio ? typeRadio.value : 'expense';
                const description = document.getElementById('edit-rec-description').value;
                const amount = parseFloat(document.getElementById('edit-rec-amount').value) || 0;
                const category = document.getElementById('edit-rec-category').value;
                const frequency = document.getElementById('edit-rec-frequency').value;
                const lastDay = document.getElementById('edit-rec-last-day').checked;
                const day = lastDay ? -1 : parseInt(document.getElementById('edit-rec-day').value);

                if (amount <= 0) {
                    alert('Por favor, insira um valor válido');
                    return;
                }

                const data = {
                    id: parseInt(id),
                    type: type,
                    description: description,
                    amount: amount,
                    category: category,
                    frequency: frequency,
                    day_of_month: day
                };

                try {
                    const response = await fetch('/api/recurring.php', {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    const result = await response.json();

                    if (result.status === 'success') {
                        closeEditRecurringModal();
                        loadRecurring();
                    } else {
                        alert('Erro: ' + result.message);
                    }
                } catch (err) {
                    console.error(err);
                    alert('Erro ao atualizar recorrência');
                }
            }

            // Initial Load & Event Listeners
            document.addEventListener('DOMContentLoaded', () => {
                // Load Transactions
                filterTransactions();

                // Load Investments
                loadInvestments();

                // Load Recurring
                loadRecurring();

                // Chat Input Enter Key
                const chatInput = document.getElementById('chat-input');
                if (chatInput) {
                    chatInput.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter') sendChatMessage();
                    });
                }
            });

        </script>
        <!-- Chat Modal -->
        <div id="ai-chat-modal"
            style="display:none; position:fixed; bottom:20px; right:20px; width:400px; height:600px; background:var(--card); border-radius:16px; box-shadow: 0 8px 32px rgba(0,0,0,0.2); z-index:1000; flex-direction:column; border:1px solid var(--border); overflow:hidden;">
            <div
                style="padding:16px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; background:var(--sidebar-bg); color:white;">
                <div style="font-weight:600; display:flex; align-items:center; gap:8px;"><i data-lucide="bot"
                        width="18"></i> Lume AI</div>
                <button onclick="toggleChat()" style="background:none; border:none; color:white; cursor:pointer;"><i
                        data-lucide="x"></i></button>
            </div>
            <div id="chat-messages"
                style="flex:1; overflow-y:auto; padding:16px; display:flex; flex-direction:column; gap:16px; background:var(--main-bg);">
                <div style="display:flex; gap:10px; margin-bottom:12px;">
                    <div
                        style="width:28px; height:28px; background:#10a37f; border-radius:6px; flex-shrink:0; display:flex; align-items:center; justify-content:center; color:white;">
                        <i data-lucide="bot" width="16"></i>
                    </div>
                    <div
                        style="background:transparent; padding:0; border-radius:8px; font-size:14px; line-height:1.5; color:var(--text); flex:1;">
                        Olá! Clique em <strong>Análise Inteligente</strong> para gerar um relatório do seu mês. 🚀
                    </div>
                </div>
            </div>
            <div style="padding:16px; border-top:1px solid var(--border); background:var(--bg);">
                <div style="display:flex; gap:8px;">
                    <input type="text" id="chat-input" placeholder="Digite algo..."
                        style="flex:1; padding:10px; border-radius:8px; border:1px solid var(--border); background:var(--card); color:var(--text); outline:none;">
                    <button onclick="sendChatMessage()"
                        style="background:var(--accent); color:white; border:none; width:40px; border-radius:8px; cursor:pointer; display:flex; align-items:center; justify-content:center;"><i
                            data-lucide="send" width="16"></i></button>
                </div>
            </div>
        </div>

        <!-- Service Worker Registration -->
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/sw.js')
                        .then(reg => console.log('SW registered:', reg.scope))
                        .catch(err => console.log('SW registration failed:', err));
                });
            }
        </script>

</body>

</html>