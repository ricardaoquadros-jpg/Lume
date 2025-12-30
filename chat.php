<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Lume AI - Consultor Financeiro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --sidebar-bg: #0b3680;
            /* Navy Blue */
            --sidebar-hover: #0d429a;
            --main-bg: #FFFFFF;
            --text-primary: #1D1D1F;
            --text-secondary: #86868B;
            --user-msg-bg: #F5F5F7;
            --ai-msg-bg: #FFFFFF;
            --border: #E5E5EA;
            --input-bg: #F5F5F7;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            display: flex;
            height: 100vh;
            background: var(--main-bg);
            color: var(--text-primary);
            overflow: hidden;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            color: white;
            display: flex;
            flex-direction: column;
            padding: 20px;
            transition: transform 0.3s ease;
            z-index: 10;
        }

        .brand {
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 40px;
            color: white;
            text-decoration: none;
        }

        .nav-items {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 8px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
        }

        .nav-item:hover,
        .nav-item.active {
            background: var(--sidebar-hover);
            color: white;
        }

        .user-profile {
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: white;
        }

        /* Main Chat Area */
        .main-chat {
            flex: 1;
            display: flex;
            flex-direction: column;
            position: relative;
            background: var(--main-bg);
        }

        .chat-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            display: none;
            /* Only show on mobile */
        }

        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 40px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 24px;
        }

        .message-block {
            width: 100%;
            max-width: 800px;
            padding: 0 24px;
            display: flex;
            gap: 16px;
        }

        .avatar {
            width: 32px;
            height: 32px;
            border-radius: 4px;
            /* ChatGPT style is slightly squrcle */
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .user-avatar {
            background: #555;
            color: white;
        }

        .ai-avatar {
            background: #10a37f;
            /* ChatGPT Greenish */
            background: var(--sidebar-bg);
            /* Use Brand Color */
            color: white;
        }

        .message-content {
            flex: 1;
            font-size: 15px;
            line-height: 1.6;
            margin-top: 4px;
            white-space: pre-wrap;
            /* Preserve formatting */
        }

        .message-content strong {
            font-weight: 600;
        }

        /* Input Area */
        .input-area {
            /* width: 100%; */
            /* max-width: 800px; */
            /* margin: 0 auto; */
            padding: 24px;
            background: transparent;
            /* Sticky bottom behavior handled by flex parent */
            display: flex;
            justify-content: center;
            background-color: var(--main-bg);
        }

        .input-wrapper {
            width: 100%;
            max-width: 800px;
            position: relative;
            background: var(--input-bg);
            border-radius: 16px;
            border: 1px solid var(--border);
            padding: 12px 16px;
            display: flex;
            align-items: flex-end;
            gap: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
        }

        .input-wrapper:focus-within {
            border-color: #aaa;
        }

        textarea {
            flex: 1;
            background: transparent;
            border: none;
            resize: none;
            height: 24px;
            max-height: 200px;
            font-size: 15px;
            outline: none;
            color: var(--text-primary);
            line-height: 1.5;
            padding: 0;
        }

        .send-btn {
            background: var(--sidebar-bg);
            color: white;
            border: none;
            border-radius: 8px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .send-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        /* Pending Action Styling */
        .pending-action-card {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 16px;
            margin-top: 12px;
            font-size: 14px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 12px;
        }

        .btn-confirm {
            background: #10a37f;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-cancel {
            background: #e5e5e5;
            color: #333;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }

        /* Loading Indicator */
        .typing-indicator span {
            display: inline-block;
            width: 6px;
            height: 6px;
            background: #aaa;
            border-radius: 50%;
            margin: 0 2px;
            animation: bounce 1.4s infinite ease-in-out both;
        }

        .typing-indicator span:nth-child(1) {
            animation-delay: -0.32s;
        }

        .typing-indicator span:nth-child(2) {
            animation-delay: -0.16s;
        }

        @keyframes bounce {

            0%,
            80%,
            100% {
                transform: scale(0);
            }

            40% {
                transform: scale(1);
            }
        }

        /* Mobile */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -260px;
                height: 100%;
            }

            .sidebar.mobile-open {
                transform: translateX(260px);
            }

            .chat-header {
                display: flex;
            }
        }
    </style>
</head>

<body>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <a href="dashboard.php" class="brand">
            <i data-lucide="layout-dashboard"></i> Lume
        </a>

        <div class="nav-items">
            <a href="chat.php" class="nav-item active">
                <i data-lucide="message-square"></i> Novo Chat
            </a>
            <a href="dashboard.php" class="nav-item">
                <i data-lucide="arrow-left"></i> Voltar ao Dashboard
            </a>
            <a href="setup.php" class="nav-item">
                <i data-lucide="settings"></i> Configurações
            </a>
        </div>

        <div class="user-profile">
            <div class="avatar user-avatar" style="width:28px; height:28px; font-size:12px;">U</div>
            <span>Usuário</span>
            <a href="logout.php" style="margin-left:auto; color:rgba(255,255,255,0.6);">
                <i data-lucide="log-out" width="16"></i>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-chat">
        <!-- Mobile Header -->
        <div class="chat-header">
            <button onclick="toggleSidebar()" style="background:none; border:none; cursor:pointer;">
                <i data-lucide="menu"></i>
            </button>
            <span style="font-weight:600;">Lume AI</span>
            <div style="width:24px;"></div>
        </div>

        <div class="messages-container" id="messages-container">
            <!-- Welcome Message -->
            <div class="message-block">
                <div class="avatar ai-avatar"><i data-lucide="bot" width="18"></i></div>
                <div class="message-content">
                    Olá! Sou o **Lume**, seu Consultor Financeiro de Elite.

                    Estou analisando seu patrimônio em tempo real. Como posso te ajudar hoje?
                </div>
            </div>
        </div>

        <!-- Input -->
        <div class="input-area">
            <div class="input-wrapper">
                <textarea id="user-input" rows="1"
                    placeholder="Pergunte sobre seus investimentos, gastos ou ganhos..."></textarea>
                <button id="send-btn" class="send-btn" onclick="sendMessage()">
                    <i data-lucide="arrow-up" width="18"></i>
                </button>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // Mobile Sidebar Toggle
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('mobile-open');
        }

        // Auto-resize textarea
        const textarea = document.getElementById('user-input');
        textarea.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
            if (this.value === '') this.style.height = '24px';
        });

        // Enter to submit
        textarea.addEventListener('keypress', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // Chat Logic
        const messagesContainer = document.getElementById('messages-container');
        let pendingAction = null;

        function appendMessage(role, text, isAction = false) {
            const div = document.createElement('div');
            div.className = 'message-block';

            const isUser = role === 'user';
            const avatarHtml = isUser
                ? '<div class="avatar user-avatar"><i data-lucide="user" width="16"></i></div>'
                : '<div class="avatar ai-avatar"><i data-lucide="bot" width="18"></i></div>';

            // Basic Formatting (Bold)
            let formattedText = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

            div.innerHTML = `
                ${avatarHtml}
                <div class="message-content">${formattedText}</div>
            `;

            messagesContainer.appendChild(div);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            lucide.createIcons();
            return div.querySelector('.message-content');
        }

        function appendLoading() {
            const div = document.createElement('div');
            div.className = 'message-block';
            div.id = 'loading-msg';
            div.innerHTML = `
                <div class="avatar ai-avatar"><i data-lucide="bot" width="18"></i></div>
                <div class="message-content">
                    <div class="typing-indicator"><span></span><span></span><span></span></div>
                </div>
            `;
            messagesContainer.appendChild(div);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            lucide.createIcons();
        }

        function removeLoading() {
            const loading = document.getElementById('loading-msg');
            if (loading) loading.remove();
        }

        async function sendMessage() {
            const input = document.getElementById('user-input');
            const navBtn = document.getElementById('send-btn');
            const message = input.value.trim();
            if (!message) return;

            // UI Updates
            input.value = '';
            input.style.height = 'auto';
            appendMessage('user', message);
            appendLoading();
            navBtn.disabled = true;

            try {
                const response = await fetch('ai_assistant.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'chat', message: message })
                });

                const data = await response.json();
                removeLoading();

                if (data.action === 'error') {
                    appendMessage('ai', '❌ Erro: ' + data.message);
                } else if (data.requires_confirmation) {
                    showPendingAction(data);
                } else {
                    appendMessage('ai', data.message || 'Sem resposta.');
                }

            } catch (err) {
                removeLoading();
                appendMessage('ai', '❌ Erro de conexão.');
                console.error(err);
            } finally {
                navBtn.disabled = false;
                input.focus();
            }
        }

        function showPendingAction(data) {
            pendingAction = data;

            let details = '';
            if (data.action === 'add') {
                const t = data.transactions[0];
                details = `Adicionar <strong>${t.description}</strong> (R$ ${t.amount}) em ${t.category}?`;
            } else if (data.action === 'remove') {
                details = `Remover ${data.transaction_ids.length} transações selecionadas?`;
            } else if (data.action === 'edit') {
                details = `Salvar alterações na transação #${data.transaction_id}?`;
            }

            const div = document.createElement('div');
            div.className = 'message-block';
            div.innerHTML = `
                <div class="avatar ai-avatar"><i data-lucide="bot" width="18"></i></div>
                <div class="message-content">
                    ${data.message}
                    
                    <div class="pending-action-card">
                        <div>${details}</div>
                        <div class="action-buttons">
                            <button class="btn-confirm" onclick="confirmAction()">Confirmar</button>
                            <button class="btn-cancel" onclick="cancelAction()">Cancelar</button>
                        </div>
                    </div>
                </div>
            `;
            messagesContainer.appendChild(div);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            lucide.createIcons();
        }

        function cancelAction() {
            // Remove the action card or just append a cancel message
            appendMessage('user', 'Cancelar ação.');
            appendMessage('ai', 'Ação cancelada.');
            pendingAction = null;
        }

        async function confirmAction() {
            if (!pendingAction) return;

            const btn = document.querySelector('.btn-confirm');
            if (btn) {
                btn.textContent = 'Processando...';
                btn.disabled = true;
            }

            try {
                const response = await fetch('ai_assistant.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'confirm', pending_action: pendingAction })
                });

                const result = await response.json();

                if (result.status === 'success') {
                    // Replace the action card with success message
                    appendMessage('ai', result.message + ' ✅');
                } else {
                    appendMessage('ai', 'Erro: ' + result.message);
                }
            } catch (err) {
                appendMessage('ai', 'Erro ao confirmar.');
            }

            pendingAction = null;
        }
    </script>
</body>

</html>