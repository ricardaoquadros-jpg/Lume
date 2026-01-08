<?php
session_start();
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once 'conexao.php';

    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];

        // Check if user has a profile
        $stmt_profile = $pdo->prepare("SELECT id FROM work_profiles WHERE user_id = ?");
        $stmt_profile->execute([$user['id']]);
        if ($stmt_profile->fetch()) {
            header("Location: /");
        } else {
            header("Location: setup.php");
        }
        exit;
    } else {
        $error = "Usuário ou senha incorretos";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Entrar - Lume</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #F5F7FA;
            --card-bg: #FFFFFF;
            --text-main: #1D1D1F;
            --text-secondary: #86868B;
            --accent: #0b3680;
            --error: #FF3B30;
            --input-bg: #F5F5F7;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-font-smoothing: antialiased;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Inter", sans-serif;
            background-color: var(--card-bg);
            /* Pure white for clean look */
            color: var(--text-main);
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            animation: fadeIn 0.8s ease-out;
        }

        h1 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        p.subtitle {
            font-size: 17px;
            color: var(--text-secondary);
            margin-bottom: 40px;
        }

        form {
            width: 100%;
            max-width: 320px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .input-group {
            position: relative;
        }

        input {
            width: 100%;
            padding: 18px 16px;
            background: var(--card-bg);
            border: 1px solid #D2D2D7;
            border-radius: 12px;
            font-size: 17px;
            color: var(--text-main);
            outline: none;
            transition: all 0.2s ease;
        }

        input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(0, 113, 227, 0.1);
        }

        input::placeholder {
            color: #86868B;
        }

        button {
            margin-top: 10px;
            padding: 16px;
            background-color: var(--accent);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 17px;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.1s, opacity 0.2s;
        }

        button:active {
            transform: scale(0.98);
        }

        button:hover {
            opacity: 0.95;
        }

        .links {
            margin-top: 32px;
            font-size: 14px;
        }

        a {
            color: var(--accent);
            text-decoration: none;
        }

        .error-message {
            color: var(--error);
            font-size: 14px;
            text-align: center;
            margin-bottom: 16px;
            animation: shake 0.4s ease-in-out;
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.98);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-4px);
            }

            75% {
                transform: translateX(4px);
            }
        }
    </style>
</head>

<body>

    <h1>Lume</h1>
    <p class="subtitle">Faça login para continuar</p>

    <?php if ($error): ?>
        <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="POST">
        <div class="input-group">
            <input type="text" name="username" placeholder="Usuário" required autofocus autocomplete="username">
        </div>

        <div class="input-group">
            <input type="password" name="password" placeholder="Senha" required autocomplete="current-password">
        </div>

        <button type="submit">Entrar</button>
    </form>

    <div class="links">
        Não tem uma conta? <a href="register.php">Criar agora</a>
    </div>

</body>

</html>