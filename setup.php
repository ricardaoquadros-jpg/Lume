<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if profile exists to Pre-fill
$stmt = $pdo->prepare("SELECT * FROM work_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

$is_editing = $profile ? true : false;
$existing_days = $profile ? json_decode($profile['work_days'] ?? '[]', true) : [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $salary_raw = $_POST['salary'];
    $salary = str_replace('.', '', $salary_raw);
    $salary = str_replace(',', '.', $salary);

    $payment_type = $_POST['payment_type'];
    $work_days = json_encode($_POST['work_days'] ?? []);
    $work_start = $_POST['work_start'];
    $work_end = $_POST['work_end'];

    // Interval Logic
    $has_interval = isset($_POST['no_interval']) ? 0 : 1;
    $interval_start = $has_interval ? $_POST['interval_start'] : null;
    $interval_end = $has_interval ? $_POST['interval_end'] : null;

    $initial_balance_raw = $_POST['initial_balance'];
    if (empty($initial_balance_raw)) {
        $initial_balance = 0.00;
    } else {
        $initial_balance = str_replace('.', '', $initial_balance_raw);
        $initial_balance = str_replace(',', '.', $initial_balance);
    }

    $current_month = date('Y-m');

    if ($is_editing) {
        $sql = "UPDATE work_profiles SET salary=?, payment_type=?, work_days=?, work_start=?, work_end=?, has_interval=?, interval_start=?, interval_end=?, initial_balance=?, last_configured_month=? WHERE user_id=?";
        $stmt = $pdo->prepare($sql);
        $params = [$salary, $payment_type, $work_days, $work_start, $work_end, $has_interval, $interval_start, $interval_end, $initial_balance, $current_month, $user_id];
    } else {
        $sql = "INSERT INTO work_profiles (user_id, salary, payment_type, work_days, work_start, work_end, has_interval, interval_start, interval_end, initial_balance, last_configured_month) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $params = [$user_id, $salary, $payment_type, $work_days, $work_start, $work_end, $has_interval, $interval_start, $interval_end, $initial_balance, $current_month];
    }

    if ($stmt->execute($params)) {
        header("Location: /");
        exit;
    }
}

// Calendar Logic
$year = date('Y');
$month = date('n');
$num_days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$month_names_pt = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];
$month_label = $month_names_pt[$month];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Configuração Lume</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #FFFFFF;
            --card-bg: #FFFFFF;
            --text-main: #1D1D1F;
            --text-secondary: #86868B;
            --accent: #0b3680;
            --border: #E5E5EA;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            padding: 40px 24px;
            max-width: 480px;
            margin: 0 auto;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .form-section {
            margin-bottom: 40px;
            animation: slideUp 0.6s ease;
            animation-fill-mode: both;
        }

        .form-section:nth-child(1) {
            animation-delay: 0.1s;
        }

        .form-section:nth-child(2) {
            animation-delay: 0.2s;
        }

        .form-section:nth-child(3) {
            animation-delay: 0.3s;
        }

        .form-section:nth-child(4) {
            animation-delay: 0.4s;
        }

        .form-section:nth-child(5) {
            animation-delay: 0.5s;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Salary Input Beautification */
        .currency-input-container {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid var(--border);
            transition: border-color 0.3s;
        }

        .currency-input-container:focus-within {
            border-bottom-color: var(--accent);
        }

        .currency-symbol {
            font-size: 24px;
            color: var(--text-secondary);
            margin-right: 8px;
            font-weight: 500;
        }

        .currency-field {
            width: 220px;
            border: none;
            background: transparent;
            font-size: 40px;
            font-weight: 700;
            color: var(--text-main);
            outline: none;
            text-align: left;
            padding: 0;
            letter-spacing: -1px;
        }

        .currency-field::placeholder {
            color: #E5E5EA;
        }

        /* General Inputs */
        .input-box {
            width: 100%;
            padding: 16px 0;
            border: none;
            border-bottom: 1px solid var(--border);
            font-size: 18px;
            color: var(--text-main);
            background: transparent;
            outline: none;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .input-box:focus {
            border-bottom-color: var(--accent);
        }

        /* Time Inputs (Custom Style) */
        .time-row {
            display: flex;
            gap: 24px;
        }

        .time-col {
            flex: 1;
        }

        .time-input-styled {
            text-align: center;
            font-variant-numeric: tabular-nums;
            letter-spacing: 2px;
        }

        /* Checkbox/Calendar Styling */
        .calendar-wrapper {
            margin-top: 8px;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 6px;
        }

        .day-header {
            text-align: center;
            font-size: 11px;
            color: var(--text-secondary);
            font-weight: 700;
            margin-bottom: 8px;
            opacity: 0.6;
        }

        .day-check {
            display: none;
        }

        .day-box {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.1s;
            color: var(--text-main);
            background: #F9F9F9;
            border: 1px solid transparent;
        }

        .day-box:hover {
            background: #E5E5EA;
        }

        .day-check:checked+.day-box {
            background: var(--accent);
            color: white;
            font-weight: 600;
        }

        /* Checkbox Toggle */
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            margin-top: 16px;
            cursor: pointer;
            user-select: none;
        }

        .custom-checkbox {
            width: 20px;
            height: 20px;
            border-radius: 6px;
            border: 2px solid #C7C7CC;
            margin-right: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }

        input[type="checkbox"]:checked+.custom-checkbox {
            background-color: var(--accent);
            border-color: var(--accent);
        }

        input[type="checkbox"]:checked+.custom-checkbox::after {
            content: '✓';
            color: white;
            font-size: 14px;
            font-weight: bold;
        }

        .checkbox-text {
            font-size: 15px;
            color: var(--text-main);
        }

        /* Interval Area Animation */
        #interval-section {
            overflow: hidden;
            transition: all 0.4s ease;
            max-height: 200px;
            opacity: 1;
            margin-top: 24px;
        }

        #interval-section.hidden {
            max-height: 0;
            opacity: 0;
            margin-top: 0;
        }

        /* Totals Info */
        .totals-row {
            display: flex;
            justify-content: space-between;
            margin-top: 24px;
            padding: 16px;
            background: #F9FAFB;
            border-radius: 12px;
            animation: fadeIn 0.5s ease;
        }

        .total-stat {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .total-stat strong {
            display: block;
            font-size: 20px;
            color: var(--accent);
            margin-top: 4px;
            font-weight: 700;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* Save Button */
        .action-area {
            margin-top: auto;
            padding-top: 40px;
        }

        button.save-btn {
            background: var(--accent);
            color: white;
            width: 100%;
            padding: 20px;
            border: none;
            border-radius: 16px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(11, 54, 128, 0.2);
            transition: transform 0.1s;
        }

        button.save-btn:active {
            transform: scale(0.98);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>

    <form method="POST" style="margin-top: 20px;">

        <!-- Salary -->
        <div class="form-section">
            <label style="text-align:center;">Qual é o seu salário líquido?</label>
            <div class="currency-input-container">
                <span class="currency-symbol">R$</span>
                <input type="text" name="salary" id="salary" class="currency-field" placeholder="0,00" required
                    inputmode="numeric" value="<?php echo $is_editing ? number_format($profile['salary'], 2, ',', '.') : ''; ?>">
            </div>

            <div style="margin-top: 24px;">
                <label>Como você recebe?</label>
                <select name="payment_type" class="input-box" style="padding-left:0;">
                    <option value="monthly">Salário Mensal (Fixo)</option>
                    <option value="hourly">Por Hora Trabalhada</option>
                </select>
            </div>
        </div>

        <!-- Calendar -->
        <div class="form-section">
            <label>Quais dias você trabalha este mês?</label>
            <div style="font-size: 14px; margin-bottom: 12px; color: var(--text-main); font-weight: 500;">
                <?php echo $month_label . ' ' . $year; ?>
            </div>

            <div class="calendar-wrapper">
                <div class="calendar-grid">
                    <?php
                    $days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
                    foreach ($days as $d)
                        echo "<div class='day-header'>$d</div>";

                    $first_day_idx = date('w', strtotime("$year-$month-01"));
                    for ($k = 0; $k < $first_day_idx; $k++)
                        echo "<div></div>";

                    for ($i = 1; $i <= $num_days; $i++): ?>
                        <label>
                            <input type="checkbox" name="work_days[]" value="<?php echo $i; ?>" class="day-check" 
                                   <?php echo (!$is_editing || in_array($i, $existing_days)) ? 'checked' : ''; ?> 
                                   onchange="calculateTotals()">
                            <div class="day-box"><?php echo $i; ?></div>
                        </label>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Hours -->
        <div class="form-section">
            <label>Horário de Expediente</label>
            <div class="time-row">
                <div class="time-col">
                    <label style="font-size:11px; margin-bottom:4px; opacity:0.7;">INÍCIO</label>
                    <input type="text" name="work_start" class="input-box time-input-styled" 
                           placeholder="00:00" maxlength="5" inputmode="numeric" required oninput="maskTime(this)"
                           value="<?php echo $is_editing ? substr($profile['work_start'], 0, 5) : ''; ?>">
                </div>
                <div class="time-col">
                    <label style="font-size:11px; margin-bottom:4px; opacity:0.7;">FIM</label>
                    <input type="text" name="work_end" class="input-box time-input-styled" 
                           placeholder="00:00" maxlength="5" inputmode="numeric" required oninput="maskTime(this)"
                           value="<?php echo $is_editing ? substr($profile['work_end'], 0, 5) : ''; ?>">
                </div>
            </div>

            <!-- Interval Toggle Section -->
            <label class="checkbox-wrapper">
                <input type="checkbox" name="no_interval" id="no_interval_check" style="display:none;" 
                       <?php echo ($is_editing && !$profile['has_interval']) ? 'checked' : ''; ?>>
                <div class="custom-checkbox"></div>
                <span class="checkbox-text">Não possuo horário de almoço/intervalo</span>
            </label>

            <!-- Interval Inputs (Collapsible) -->
            <div id="interval-section">
                <div class="time-row">
                    <div class="time-col">
                        <label style="font-size:11px; margin-bottom:4px; opacity:0.7;">INÍCIO INTERVALO</label>
                        <input type="text" name="interval_start" class="input-box time-input-styled" 
                               placeholder="00:00" maxlength="5" inputmode="numeric" oninput="maskTime(this)"
                               value="<?php echo ($is_editing && $profile['interval_start']) ? substr($profile['interval_start'], 0, 5) : ''; ?>">
                    </div>
                    <div class="time-col">
                        <label style="font-size:11px; margin-bottom:4px; opacity:0.7;">FIM INTERVALO</label>
                        <input type="text" name="interval_end" class="input-box time-input-styled" 
                               placeholder="00:00" maxlength="5" inputmode="numeric" oninput="maskTime(this)"
                               value="<?php echo ($is_editing && $profile['interval_end']) ? substr($profile['interval_end'], 0, 5) : ''; ?>">
                    </div>
                </div>
            </div>

            <!-- Totals Display -->
            <div class="totals-row">
                <div class="total-stat">Dias trabalhados <strong id="total-days">0</strong></div>
                <div class="total-stat">Horas no mês <strong id="total-hours">0h</strong></div>
            </div>
        </div>

        <!-- Balance -->
        <div class="form-section">
            <label>Saldo bancário atual (Opcional)</label>
            <div style="display:flex; align-items:center; border-bottom:1px solid var(--border);">
                <span style="font-size:18px; color:var(--text-secondary); margin-right:8px;">R$</span>
                <input type="text" name="initial_balance" id="initial_balance" class="input-box" placeholder="0,00" 
                       style="border:none;" inputmode="numeric"
                       value="<?php echo $is_editing ? number_format($profile['initial_balance'], 2, ',', '.') : ''; ?>">
            </div>
        </div>

        <div class="action-area">
            <button type="submit" class="save-btn">Finalizar Configuração</button>
        </div>
    </form>

    <script>
        // Currency Formatter
        const formatCurrency = (value) => {
            value = value.replace(/\D/g, "");
            value = (value / 100).toFixed(2) + "";
            value = value.replace(".", ",");
            value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
            return value;
        };

        const inputs = [document.getElementById('salary'), document.getElementById('initial_balance')];

        inputs.forEach(input => {
            input.addEventListener('input', (e) => {
                e.target.value = formatCurrency(e.target.value);
            });
        });

        // Time Mask (HH:MM)
        function maskTime(elem) {
            let value = elem.value.replace(/\D/g, ""); // Only numbers
            if (value.length > 4) value = value.slice(0, 4);

            if (value.length > 2) {
                value = value.slice(0, 2) + ":" + value.slice(2);
            }
            elem.value = value;
            calculateTotals();
        }

        // Interval Toggle Logic
        const noIntervalCheck = document.getElementById('no_interval_check');
        const intervalSection = document.getElementById('interval-section');
        const intervalInputs = intervalSection.querySelectorAll('input');

        noIntervalCheck.addEventListener('change', function () {
            if (this.checked) {
                intervalSection.classList.add('hidden');
                intervalInputs.forEach(input => {
                    input.required = false;
                    input.value = ''; // Clean up values
                });
            } else {
                intervalSection.classList.remove('hidden');
                intervalInputs.forEach(input => input.required = true);
            }
            calculateTotals();
        });

        // Totals Calculation
        function timeToMinutes(timeStr) {
            if (!timeStr || timeStr.length < 5) return 0;
            const [h, m] = timeStr.split(':').map(Number);
            return (h * 60) + m;
        }

        function calculateTotals() {
            // 1. Count Days
            const checkboxes = document.querySelectorAll('.day-check:checked');
            const totalDays = checkboxes.length;
            document.getElementById('total-days').innerText = totalDays;

            // 2. Calculate Daily Duration
            const startStr = document.getElementsByName('work_start')[0].value;
            const endStr = document.getElementsByName('work_end')[0].value;

            let startMin = timeToMinutes(startStr);
            let endMin = timeToMinutes(endStr);

            // Handle overnight shift if needed (end < start)
            if (endMin < startMin) endMin += 1440;

            let workMinutes = endMin - startMin;

            // Subtract Interval
            if (!noIntervalCheck.checked) {
                const intStartStr = document.getElementsByName('interval_start')[0].value;
                const intEndStr = document.getElementsByName('interval_end')[0].value;

                let intStartMin = timeToMinutes(intStartStr);
                let intEndMin = timeToMinutes(intEndStr);

                if (intEndMin < intStartMin) intEndMin += 1440;

                const intervalDuration = intEndMin - intStartMin;
                if (intervalDuration > 0) {
                    workMinutes -= intervalDuration;
                }
            }

            if (workMinutes < 0) workMinutes = 0;

            // 3. Monthly Total
            const totalMinutes = workMinutes * totalDays;

            const totalH = Math.floor(totalMinutes / 60);
            const totalM = totalMinutes % 60;

            let display = totalH + 'h';
            if (totalM > 0) display += ' ' + totalM + 'm';

            document.getElementById('total-hours').innerText = display;
        }

        // Attach listeners to calculation inputs
        const calcInputs = document.querySelectorAll('input[type="checkbox"], input[name$="_start"], input[name$="_end"]');
        calcInputs.forEach(input => {
            input.addEventListener('change', calculateTotals);
            input.addEventListener('input', calculateTotals);
        });

        calculateTotals();
    </script>
</body>

</html>