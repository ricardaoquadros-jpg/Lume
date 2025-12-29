<?php
session_start();
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once 'conexao.php';

    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error = "As senhas não coincidem";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "Usuário já existe";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
            if ($stmt->execute([$username, $hash])) {
                $_SESSION['user_id'] = $pdo->lastInsertId(); // Auto login
                header("Location: setup.php"); // Go to setup
                exit;
            } else {
                $error = "Falha no cadastro";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Criar Conta - Lume</title>
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
            color: var(--text-main);
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            animation: slideIn 0.5s ease-out;
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
            transition: transform 0.1s;
        }

        button:active {
            transform: scale(0.98);
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
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>

    <h1>Criar Conta</h1>
    <p class="subtitle">Comece a usar o Lume</p>

    <?php if ($error): ?>
        <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="username" placeholder="Nome de usuário" required autocomplete="username">
        <input type="password" name="password" placeholder="Senha" required autocomplete="new-password">
        <input type="password" name="confirm_password" placeholder="Confirmar senha" required>
        <button type="submit">Continuar</button>
    </form>

    <div class="links">
        Já tem uma conta? <a href="login.php">Entrar</a>
    </div>

</body>

</html>