import { NextRequest, NextResponse } from "next/server";
import { createClient } from "@/lib/supabase/server";
import OpenAI from "openai";

const GROQ_KEY = "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";
const OPENROUTER_KEY = "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";

const groq = new OpenAI({
    apiKey: GROQ_KEY,
    baseURL: "https://api.groq.com/openai/v1"
});

const openrouter = new OpenAI({
    apiKey: OPENROUTER_KEY,
    baseURL: "https://openrouter.ai/api/v1"
});

export async function POST(req: NextRequest) {
    try {
        const supabase = await createClient();
        const { data: { user } } = await supabase.auth.getUser();
        if (!user) {
            return NextResponse.json({ error: "Não autorizado" }, { status: 401 });
        }

        let userInstruction = "";
        let transactions: any[] = [];

        const contentType = req.headers.get("content-type") || "";

        if (contentType.includes("multipart/form-data")) {
            const formData = await req.formData();
            const audioFile = formData.get("audio") as File | null;
            const textInstruction = formData.get("instruction") as string | null;
            const transactionsJson = formData.get("transactions") as string;

            transactions = JSON.parse(transactionsJson || "[]");

            if (audioFile) {
                // Transcribe audio instruction
                const transcription = await groq.audio.transcriptions.create({
                    file: audioFile,
                    model: "whisper-large-v3",
                    language: "pt",
                    prompt: "Transcreva a instrução de categorização financeira."
                });
                userInstruction = transcription.text;
            } else if (textInstruction) {
                userInstruction = textInstruction;
            }
        } else {
            const body = await req.json();
            transactions = body.transactions || [];
            userInstruction = body.instruction || "";
        }

        if (transactions.length === 0) {
            return NextResponse.json({ error: "Nenhuma transação para categorizar." }, { status: 400 });
        }

        // Build the transaction list for the prompt
        const txList = transactions.map((t: any, i: number) =>
            `${i}. [${t.type}] ${t.date} | ${t.description} | R$ ${Number(t.amount).toFixed(2)}`
        ).join("\n");

        const completion = await openrouter.chat.completions.create({
            model: "openai/gpt-4o-mini",
            messages: [
                {
                    role: "system",
                    content: `Você é um categorizador de transações financeiras. Sua tarefa é atribuir a categoria correta para cada transação.

CATEGORIAS DISPONÍVEIS (use EXATAMENTE uma dessas):
"Alimentação", "Mercado", "Transporte", "Lazer", "Roupas", "Jogos", "Saúde", "Esportes", "Investimento", "Educação", "Moradia", "Contas", "Assinaturas", "Beleza", "Pets", "Viagem", "Presentes", "Salário", "Extra", "Outros"

REGRAS:
1. Retorne um JSON puro: { "categories": ["Cat1", "Cat2", ...] }
2. O array deve ter EXATAMENTE o mesmo número de itens que transações recebidas.
3. Cada item do array é a categoria para a transação correspondente.
4. Use o contexto da descrição para inferir a melhor categoria:
   - Uber, 99, Rodoviária → "Transporte"
   - iFood, McDonalds, Restaurante → "Alimentação"  
   - Farmácia, Drogaria, Hospital → "Saúde"
   - Netflix, Spotify, Disney+ → "Assinaturas"
   - Luz, Água, Internet, Aluguel → "Contas" ou "Moradia"
   - PIX Recebido, Salário, Pagamento → "Salário" ou "Extra"
   - Mercado, Supermercado → "Mercado"
5. Se o usuário deu instruções específicas, siga-as com PRIORIDADE MÁXIMA.
6. Evite "Outros" sempre que possível.`
                },
                {
                    role: "user",
                    content: `TRANSAÇÕES:
${txList}

${userInstruction ? `INSTRUÇÕES DO USUÁRIO: "${userInstruction}"` : "Categorize automaticamente baseado nas descrições."}`
                }
            ],
            max_tokens: 2048,
            temperature: 0.1
        });

        const aiResponse = completion.choices[0].message.content || "{}";

        let parsed;
        try {
            parsed = JSON.parse(aiResponse);
        } catch {
            const jsonMatch = aiResponse.match(/\{[\s\S]*\}/);
            if (jsonMatch) {
                parsed = JSON.parse(jsonMatch[0]);
            } else {
                return NextResponse.json({ error: "IA não conseguiu categorizar." }, { status: 422 });
            }
        }

        const categories: string[] = parsed.categories || [];

        // Apply categories to transactions
        const categorized = transactions.map((t: any, i: number) => ({
            ...t,
            category: categories[i] || t.category || "Outros"
        }));

        return NextResponse.json({
            success: true,
            transcription: userInstruction || null,
            transactions: categorized
        });

    } catch (error: any) {
        console.error("[Statement Categorize] Error:", error);
        return NextResponse.json({ error: "Erro ao categorizar: " + error.message }, { status: 500 });
    }
}
