# 🚀 Lume SaaS

Um aplicativo de finanças pessoais completo com Web, Android e iOS.

## 📁 Estrutura do Projeto

```
lume-saas/
├── apps/
│   ├── web/          # Next.js (Web App)
│   ├── api/          # Node.js API (opcional, pode usar Next.js API routes)
│   └── mobile/       # React Native (Expo)
├── packages/
│   ├── database/     # Prisma ORM + Schema
│   ├── ui/           # Componentes compartilhados
│   └── config/       # Configurações ESLint, TS, etc.
└── turbo.json        # Configuração Turborepo
```

## 🚀 Como Começar

### 1. Instalar Dependências
```bash
npm install
```

### 2. Configurar Banco de Dados
1. Crie uma conta no [Supabase](https://supabase.com) (gratuito)
2. Crie um novo projeto
3. Copie a Connection String (PostgreSQL)
4. Crie `.env` na raiz:
```env
DATABASE_URL="postgresql://..."
```

### 3. Gerar Prisma Client
```bash
cd packages/database
npx prisma generate
npx prisma db push
```

### 4. Iniciar Web App
```bash
cd apps/web
npm run dev
```

## 📱 Apps

| App | Tecnologia | Status |
|-----|------------|--------|
| Web | Next.js 14 | 🔄 Em desenvolvimento |
| Android | React Native (Expo) | ⏳ Pendente |
| iOS | React Native (Expo) | ⏳ Pendente |

## 🔐 Autenticação

Usando Clerk ou NextAuth para autenticação com:
- Email/Senha
- Google OAuth
- Apple Sign In

## 💳 Monetização

| Plano | Preço | Features |
|-------|-------|----------|
| Free | R$0 | 50 transações/mês |
| Pro | R$9,90/mês | Ilimitado + AI |
| Premium | R$19,90/mês | Tudo + Exportar |

## 📝 Licença

Privado - Todos os direitos reservados.
