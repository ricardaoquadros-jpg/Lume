import { NextRequest, NextResponse } from "next/server";
import OpenAI from "openai";
import { tools } from "@/lib/ai/tools";
import { performAIAction } from "@/lib/ai/actions";
import { createClient } from "@/lib/supabase/server";

// KEYS (Hardcoded for immediate fix)
const GROQ_KEY = "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";

// Initialize Groq Client
const groq = new OpenAI({
    apiKey: GROQ_KEY,
    baseURL: "https://api.groq.com/openai/v1"
});

const SENSITIVE_ACTIONS = ["remove_last_transaction", "remove_transaction"];

export async function POST(req: NextRequest) {
    console.log("[AI API] Processing request...");

    try {
        const supabase = await createClient();
        const { data: { user } } = await supabase.auth.getUser();

        let userText = "";
        let isConfirmation = false;

        // 1. Determine Input Type
        const contentType = req.headers.get("content-type") || "";

        if (contentType.includes("application/json")) {
            const body = await req.json();
            if (body.confirmAction) {
                console.log("[AI API] Executing CONFIRMED action:", body.actionPayload);
                await performAIAction(
                    body.actionPayload.functionName,
                    body.actionPayload.args,
                    body.actionPayload.transcription // Pass transcription from original request
                );
                return NextResponse.json({
                    message: "Ação confirmada e executada com sucesso!",
                    actionsExecuted: true
                });
            }
            userText = body.text;
        } else if (contentType.includes("multipart/form-data")) {
            const formData = await req.formData();
            const file = formData.get("audio") as File;
            if (!file) return NextResponse.json({ error: "No audio file" }, { status: 400 });

            console.log("[AI API] Transcribing...");
            const transcription = await groq.audio.transcriptions.create({
                file: file,
                model: "whisper-large-v3",
                language: "pt",
                prompt: "Transcreva o comando financeiro."
            });
            userText = transcription.text;
        }

        console.log("[AI API] User Intent:", userText);

        // 2. Fetch User Context & Data
        let systemPrompt = `Você é o LUME AI, um assistente financeiro.`;

        if (user) {
            // Fetch Data in Parallel for Speed
            const [profileResult, transactionsResult] = await Promise.all([
                supabase
                    .from("work_profiles")
                    .select("*")
                    .eq("user_id", user.id)
                    .single(),
                supabase
                    .from("transactions")
                    .select("amount, type, transaction_date, description")
                    .eq("user_id", user.id)
                    .order("transaction_date", { ascending: false })
            ]);

            const profile = profileResult.data;
            const transactions = transactionsResult.data;

            // Calculate Metrics
            let balance = profile?.initial_balance ? Number(profile.initial_balance) : 0;
            let monthExp = 0;
            let dayRev = 0;
            const now = new Date();
            const currentMonth = now.getMonth();
            const currentYear = now.getFullYear();
            const todayStr = now.toISOString().split('T')[0];

            if (transactions) {
                transactions.forEach(t => {
                    const val = Number(t.amount);
                    if (t.type === 'income') balance += val;
                    else balance -= val;

                    const tDate = new Date(t.transaction_date + "T00:00:00"); // Fix timezone date parsing offset
                    if (t.type === 'expense' && tDate.getMonth() === currentMonth && tDate.getFullYear() === currentYear) {
                        monthExp += val;
                    }
                    if (t.type === 'income' && t.transaction_date === todayStr) {
                        dayRev += val;
                    }
                });
            }

            // Calc Hourly Rate (Approx)
            const salary = profile?.salary ? Number(profile.salary) : 0;
            const hoursPerDay = 8; // Default
            const daysPerMonth = 22; // Default
            const hourlyRate = salary > 0 ? (salary / (daysPerMonth * hoursPerDay)).toFixed(2) : "N/A";

            // Recent Transactions List (Use Slice)
            const recentList = transactions?.slice(0, 5).map(t =>
                `- ${t.transaction_date}: ${t.description} (R$ ${Number(t.amount).toFixed(2)}) [${t.type}]`
            ).join("\n") || "Nenhuma transação recente.";

            // User Profile Text
            const userProfileText = profile?.ai_profile_data
                ? JSON.stringify(profile.ai_profile_data)
                : "Perfil Padrão (Sem dados específicos de personalidade).";

            // Construct Enhanced Prompt
            systemPrompt = `
Você é o LUME AI, um Consultor Financeiro de Alta Performance.
Você tem acesso total aos dados financeiros e ao perfil do usuário.

=== PERFIL DO USUÁRIO (ESTUDADO) ===
${userProfileText}

=== DADOS EM TEMPO REAL ===
💰 Saldo: R$ ${balance.toFixed(2)}
💸 Despesas Mês: R$ ${monthExp.toFixed(2)}
⏱️ Ganho Hoje: R$ ${dayRev.toFixed(2)}
⏳ Valor Hora: R$ ${hourlyRate}

=== HISTÓRICO RECENTE ===
${recentList}

=== SUA MISSÃO ===
1. **Seja Funcional e Inteligente:** Não dê respostas genéricas. Se o usuário perguntar "Como estou?", analise os dados cruzando com o perfil dele.
2. **Personalização Extrema:** Se o perfil é "Arrojado", não sugira Poupança. Se é "Endividado", foque em corte de gastos.
3. **Gerencie o Sistema:** Se o usuário mandar fazer algo (add/remove), faça.

=== TOM DE VOZ ===
- Profissional, mas acessível.
- **CONCISÃO EXTREMA (Máx 10 linhas):** Suas respostas devem ser curtas e diretas.
- SÓ ultrapasse 10 linhas se o usuário pedir explicitamente por "detalhes", "relatório completo" ou "análise profunda".
- Dê insights rápidos. O usuário está no celular e quer ler rápido.
- Vá direto ao ponto.
- Hoje é ${new Date().toLocaleString("pt-BR")}.

=== CATEGORIZAÇÃO (CRÍTICO) ===
Classifique as transações EXATAMENTE em uma das seguintes categorias:
"Alimentação", "Mercado", "Transporte", "Lazer", "Roupas", "Jogos", "Saúde", "Esportes", "Investimento", "Educação", "Moradia", "Contas", "Assinaturas", "Beleza", "Pets", "Viagem", "Presentes", "Salário", "Extra", "Outros".

REGRAS DE OURO:
1. **Contexto Implícito:** Se o usuário disser "Uber", é "Transporte". Se disser "McDonalds", é "Alimentação". Se disser "Vodka/Cerveja" em contexto de festa, é "Lazer". 
2. **Evite 'Outros':** Tente encaixar em uma categoria específica sempre que possível.
3. **Bebidas:** "Vodka", "Cerveja", "Vinho" -> Geralmente "Lazer" ou "Mercado".
4. **Farmácia:** "Remédio", "Dipirona" -> "Saúde".
5. **Livros/Cursos:** "Educação".

=== INVESTIMENTOS vs GASTOS ===
CRÍTICO: Quando o usuário disser "INVESTI" ou "coloquei em [ativo]", use add_investment, NÃO add_transaction.
- "Investi R$639 no Tesouro" -> add_investment (name: "Tesouro", type: "Tesouro Direto")
- "Coloquei R$1000 no Bitcoin" -> add_investment (name: "Bitcoin", type: "Criptomoeda")
- "Invisto 100% do CDI no CDB Neon" -> add_investment (benchmark: "CDI", yield_rate: 100). TENTE CALCULAR o valor atual se o investimento for antigo. Considere CDI ~0.9% a.m.

Se o investimento JÁ EXISTE e o usuário está APORTANDO MAIS, use add_contribution.
Se o usuário disser "Meu Bitcoin valorizou para R$2000" ou "Saldo atual do Tesouro é X", use update_investment_value.

=== CÁLCULO DE INDEXADORES ===
Se o usuário informar "100% do CDI desde Janeiro", e hoje for Julho:
1. Calcule quantos meses se passaram.
2. Aplique uma taxa média (ex: 0.9% a.m) para estimar o valor atual de mercado.
3. **IMPORTANTE:** Se a data for MUITO RECENTE (menos de 1 mês) ou você não tiver certeza, NÃO INVENTE VALORES. Mantenha o valor atual IGUAL ao valor investido. O usuário atualizará depois.

=== AÇÕES vs PERGUNTAS (CRÍTICO) ===
Você DEVE distinguir entre:
1. **ORDENS/COMANDOS:** "Adiciona R$50 no Uber", "Cadastra gasto de mercado" -> USE tool_calls.
2. **PERGUNTAS/ANÁLISES:** "Como estou financeiramente?", "Vou conseguir comprar um carro?", "O que posso melhorar?" -> NÃO use tools. Responda com texto analítico.

Se o usuário fizer uma PERGUNTA, você deve ANALISAR os dados e RESPONDER com insights. NÃO chame nenhuma ferramenta.
Se o usuário der uma ORDEM, aí sim use add_transaction, etc.

=== AÇÕES ===
Se o usuário pedir para realizar uma ação (ex: "Adicionar R$ 50 em Pizza"), USO OBRIGATÓRIO das ferramentas (tool_calls).
NÃO responda apenas com texto se houver uma ação a ser feita.

=== MÚLTIPLOS ITENS (CRÍTICO - LEIA COM ATENÇÃO) ===
Se o usuário listar vários itens, você DEVE gerar uma chamada "add_transaction" SEPARADA para CADA item.
Exemplo: "Gastei 10 no Uber, 50 no Mercado e 20 na Farmácia" = 3 tool_calls.
VOCÊ É PROIBIDO DE IGNORAR ITENS. Se ele disse 6 gastos, gere 6 tool_calls. Se disse 10, gere 10.
NÃO PARE NO MEIO. PROCESSE TODOS OS ITENS ATÉ O FIM.

=== DATAS (OBRIGATÓRIO) ===
SEMPRE extraia a data do que o usuário disse. Se ele disse "dia 30 de dezembro", a data é "2025-12-30".
Se ele disse "ontem", calcule baseado na data atual: ${new Date().toLocaleDateString("pt-BR")}.
Se NÃO houver data mencionada, use a data de HOJE no formato YYYY-MM-DD.
O campo "date" é OBRIGATÓRIO em toda chamada de add_transaction.
`;
        }

        // 3. AI Processing
        const completion = await groq.chat.completions.create({
            model: "llama-3.3-70b-versatile", // RESTORED SMART MODEL
            messages: [
                { role: "system", content: systemPrompt },
                { role: "user", content: userText }
            ],
            tools: tools as any,
            tool_choice: "auto",
            max_tokens: 4096, // Ensure enough space for bulk tool calls
        });

        const message = completion.choices[0].message;
        const toolCalls = message.tool_calls;
        const content = message.content;

        // PARSE JSON REPLY IF EXISTS (The prompt asks for JSON, but Llama might return tool_calls directly OR JSON in content)
        // With 'tool_choice: auto', if it wants to call a tool, it will conform to tool_calls structure.
        // If it wants to reply, it might put JSON in content or just text if it fails to follow strict JSON.
        // For robustness, we check tool_calls first.

        const results = [];
        let confirmationRequired = false;
        let pendingAction = null;
        let finalMessage = "";

        if (toolCalls) {
            for (const toolCall of toolCalls) {
                const functionName = (toolCall as any).function.name;
                const functionArgs = JSON.parse((toolCall as any).function.arguments);

                // CHECK FOR SENSITIVE ACTIONS
                if (SENSITIVE_ACTIONS.includes(functionName)) {
                    console.log(`[AI API] Sensitive action detected: ${functionName}`);
                    confirmationRequired = true;
                    pendingAction = { functionName, args: functionArgs, transcription: userText }; // Store transcription
                    break;
                }

                console.log(`[AI API] Executing safe action: ${functionName}`);
                await performAIAction(functionName, functionArgs, userText);
                results.push({ action: functionName, status: "success" });
            }
            finalMessage = results.length > 0
                ? `✅ ${results.length} ação(ões) executada(s) com sucesso.`
                : (message.content || "Comando processado.");
        } else {
            // Try to parse content as JSON if model followed instructions, else use as raw text
            try {
                const jsonRes = JSON.parse(content || "{}");
                if (jsonRes.action === 'reply') {
                    finalMessage = jsonRes.message;
                } else {
                    finalMessage = content || "Entendi.";
                }
            } catch (e) {
                // Fallback if not valid JSON
                finalMessage = content || "Entendi.";
            }
        }

        if (confirmationRequired && pendingAction) {
            return NextResponse.json({
                message: "Ação requer confirmação.",
                confirmationRequired: true,
                actionDescription: `Executar ${pendingAction.functionName}?`,
                actionPayload: pendingAction
            });
        }

        return NextResponse.json({
            transcript: userText,
            actions: results,
            actionsExecuted: results.length > 0,
            message: finalMessage
        });

    } catch (error: any) {
        console.error("[AI API] Error:", error);
        // Log Groq-specific error details
        if (error.error) {
            console.error("[AI API] Groq Error Details:", JSON.stringify(error.error, null, 2));
        }
        return NextResponse.json({ error: error.message }, { status: 500 });
    }
}
