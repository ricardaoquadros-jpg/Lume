# 🌙 Lume - Rastreador Financeiro Inteligente

Sistema completo de gestão financeira com rastreamento em tempo real, integração voice-to-expense e assistente IA.

---

## ✨ Features

### 💰 Dashboard Financeiro
- **Rastreamento em tempo real** dos ganhos diários e mensais
- **Cálculo automático** de salários (mensal/por hora)
- **Progresso visual** com barras dinâmicas
- **Resumo completo** (patrimônio líquido, ganhos semanais, saldo atual)

### 📊 Gestão de Transações
- **Histórico completo** com saldo anterior de cada transação
- **Categorias personalizadas** (20+ categorias com ícones)
- **Gráficos por categoria** (mensal e total)
- **Filtro** por mês
- **Edição e exclusão** inline

### 🎙️ Voice-to-Expense (n8n + OpenAI)
- **Grave e transcreva** despesas por comando de voz
- **IA extrai automaticamente:** descrição, valor, categoria e tipo
- **Suporta múltiplas transações** em um único áudio
- **Integração n8n** com OpenAI Whisper + GPT

### 🤖 AI Assistant (Lume AI)
- **Assistente conversacional** com OpenRouter (LLaMA 3.3 70B)
- **Comandos naturais:** "Gastei 50 reais no almoço"
- **Gerenciamento:** Adicionar, remover, editar transações por chat
- **Análise financeira** e respostas contextuais

### 📅 Configuração de Trabalho
- **Calendário visual** para seleção de dias de trabalho
- **Horários flexíveis** com suporte a turnos noturnos
- **Intervalos opcionais**
- **Saldo inicial** personalizável
- **Edição sem reset** de dados

---

## 🛠️ Tecnologias

- **Backend:** PHP 8+ (PDO)
- **Database:** MySQL 5.7+
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
- **Charts:** Chart.js
- **Icons:** Lucide Icons
- **Automation:** n8n (self-hosted ou cloud)
- **AI:** OpenRouter (LLaMA 3.3), OpenAI (Whisper/GPT opcional)

---

## 📦 Instalação

### 1. Pré-requisitos

```bash
# Windows (XAMPP)
- PHP 8.0+
- MySQL 5.7+
- Servidor web (Apache/PHP built-in)

# Ou via Laragon/WAMP
```

### 2. Clone o Repositório

```bash
git clone https://github.com/SEU_USUARIO/Lume.git
cd Lume
```

### 3. Configure o Banco de Dados

```bash
# 1. Crie o banco no MySQL
mysql -u root -p
CREATE DATABASE lume_db;
exit

# 2. Execute as migrations
php migrate.php
```

Ou importe manualmente via phpMyAdmin:
```sql
-- Carregue o arquivo database.sql
```

### 4. Configure Variáveis de Ambiente

```bash
# Copie o template
copy .env.example .env

# Edite .env e adicione suas chaves:
```

**`.env`:**
```env
OPENROUTER_API_KEY=sk-or-v1-sua_chave_aqui
DB_HOST=localhost
DB_NAME=lume_db
DB_USER=root
DB_PASS=
```

### 5. Inicie o Servidor

```bash
# Servidor embutido do PHP
php -S localhost:8000

# Ou configure no Apache/XAMPP
```

### 6. Acesse

```
http://localhost:8000
```

---

## ⚙️ Configuração de APIs

### OpenRouter (AI Assistant - Obrigatório)

1. Crie conta: https://openrouter.ai
2. Gere API key: https://openrouter.ai/keys
3. Adicione ao `.env`: `OPENROUTER_API_KEY=sua_chave`
4. **Custo:** $0 (LLaMA 3.3 70B é gratuito)

### n8n + OpenAI (Voice Commands - Opcional)

**Setup n8n:**
1. Self-hosted: `npx n8n` ou Cloud: https://n8n.io
2. Importe: `n8n_workflow_OPENAI_v2.json`
3. Configure credenciais OpenAI no workflow
4. Copie a **Test URL** do webhook
5. Atualize `voice_proxy.php` linha 30 com a URL

**OpenAI API:**
- Crie conta: https://platform.openai.com
- Adicione $5 (mínimo)
- Custos: ~$0.006/min (Whisper) + ~$0.002/1K tokens (GPT)

---

## 📱 Uso

### Registro/Login
1. Acesse `/register.php` para criar conta
2. Login em `/login.php`

### Configuração Inicial
1. Após login, preencha o **Work Profile**:
   - Salário mensal
   - Dias de trabalho (clique no calendário)
   - Horário de início/fim
   - Intervalo (opcional)
   - Saldo bancário inicial
2. Salve e seja redirecionado ao dashboard

### Adicionar Transação Manual
1. Clique em **+ Nova** no histórico
2. Selecione tipo (Entrada/Saída)
3. Preencha descrição, valor, categoria e data
4. Salve

### Adicionar por Voz
1. Clique no **ícone do microfone** (canto inferior direito)
2. Permita acesso ao microfone
3. Fale: *"Gastei vinte reais no almoço"*
4. Clique novamente para parar
5. Aguarde processamento (~3-5s)

### Usar AI Assistant
1. Clique no **ícone do robô** (canto inferior esquerdo)
2. Digite comandos naturais:
   - *"Adiciona um lanche de 15 reais"*
   - *"Remove a transação ID 10"*
   - *"Quanto gastei este mês?"*
3. Confirme ações quando solicitado

---

## 📁 Estrutura do Projeto

```
Lume/
├── .env.example          # Template de variáveis de ambiente
├── .gitignore
├── README.md
├── ai_assistant.php      # Backend do chatbot IA
├── api/
│   ├── categories.php    # API de categorias
│   └── transactions.php  # API CRUD de transações
├── conexao.php           # Conexão MySQL (PDO)
├── dashboard.php         # Dashboard principal
├── database.sql          # Schema do banco
├── env.php               # Loader de .env
├── index.php             # Entry point
├── login.php             # Login
├── logout.php            # Logout
├── migrate.php           # Script de migração
├── n8n_workflow_OPENAI_v2.json  # Workflow n8n
├── register.php          # Registro
├── reset_profile.php     # Reset de configurações
├── setup.php             # Configuração de perfil
└── voice_proxy.php       # Proxy para n8n (CORS bypass)
```

---

## 🔐 Segurança

- ✅ Senhas com `password_hash()` (bcrypt)
- ✅ Sessões PHP para autenticação
- ✅ Prepared statements (PDO) contra SQL injection
- ✅ API keys em `.env` (não commitadas)
- ✅ `.env` no `.gitignore`
- ⚠️ **IMPORTANTE:** Revogue API keys expostas antes de commitar!

---

## 🐛 Troubleshooting

**"Banco de dados não conecta"**
- Verifique se MySQL está rodando
- Confirme credenciais em `.env`
- Execute `php migrate.php`

**"AI Assistant não responde"**
- Verifique se `.env` tem `OPENROUTER_API_KEY`
- Teste a key em https://openrouter.ai

**"Voice command dá erro 404"**
- Verifique se n8n workflow está **ativo** (toggle verde)
- Confirme URL do webhook em `voice_proxy.php`
- Teste manualmente a URL do webhook

**"Real-time earnings não atualiza"**
- Verifique se horários de trabalho estão configurados
- Confirme que hoje é um dia de trabalho (calendário)

---

## 📄 Licença

MIT License - Sinta-se livre para usar e modificar!

---

## 👤 Autor

**Ricardo Quadros**
- GitHub: [@ricardaoquadros-jpg](https://github.com/ricardaoquadros-jpg)

---

## 🤝 Contribuindo

Pull requests são bem-vindos! Para mudanças grandes, abra uma issue primeiro.

---

## 📸 Screenshots

*Em breve...*

---

**Desenvolvido com ❤️ usando PHP puro e IA open-source**
