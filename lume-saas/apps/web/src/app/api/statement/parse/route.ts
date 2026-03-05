import { NextRequest, NextResponse } from "next/server";
import { createClient } from "@/lib/supabase/server";
import OpenAI from "openai";

const OPENROUTER_KEY = "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";

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

        const formData = await req.formData();
        const file = formData.get("file") as File;

        if (!file) {
            return NextResponse.json({ error: "Nenhum arquivo enviado" }, { status: 400 });
        }

        const fileName = file.name.toLowerCase();
        let rawText = "";

        if (fileName.endsWith(".csv")) {
            rawText = await file.text();
        } else if (fileName.endsWith(".pdf")) {
            const buffer = Buffer.from(await file.arrayBuffer());
            // Dynamic import to avoid issues with pdf-parse in edge runtime
            const pdfParse = (await import("pdf-parse")).default;
            const pdfData = await pdfParse(buffer);
            rawText = pdfData.text;
        } else {
            return NextResponse.json({ error: "Formato não suportado. Use PDF ou CSV." }, { status: 400 });
        }

        if (!rawText || rawText.trim().length < 10) {
            return NextResponse.json({ error: "Não foi possível extrair texto do arquivo." }, { status: 400 });
        }

        // Truncate to avoid token limits (keep first ~8000 chars)
        const truncatedText = rawText.substring(0, 8000);

        // Use AI to extract structured transactions from raw text
        const completion = await openrouter.chat.completions.create({
            model: "openai/gpt-4o-mini",
            messages: [
                {
                    role: "system",
                    content: `Você é um parser de extratos bancários brasileiros. Extraia TODAS as transações do texto de extrato abaixo.

REGRAS:
1. Retorne um JSON puro (sem markdown, sem \`\`\`).
2. O formato deve ser: { "transactions": [...] }
3. Cada transação deve ter:
   - "date": data no formato "YYYY-MM-DD"
   - "description": descrição original da transação
   - "amount": valor numérico positivo (sem sinal)
   - "type": "income" para entradas/créditos ou "expense" para saídas/débitos
4. Analise o contexto para determinar se é entrada ou saída:
   - PIX Recebido, Transferência Recebida, Depósito, TED Recebida = "income"
   - PIX Enviado, Compra, Débito, Pagamento, Saque, TED Enviada = "expense"
5. Se o valor tiver sinal negativo ou indicação de débito = "expense"
6. Se o valor tiver sinal positivo ou indicação de crédito = "income"
7. Ignore linhas de saldo, cabeçalhos e metadados
8. Para datas com apenas dia/mês, assuma o ano atual (${new Date().getFullYear()})
9. Valores em formato brasileiro: "1.234,56" = 1234.56

EXEMPLO de saída:
{"transactions":[{"date":"2026-01-15","description":"PIX Recebido - JOAO SILVA","amount":500.00,"type":"income"},{"date":"2026-01-16","description":"Compra no débito - MERCADO EXTRA","amount":89.50,"type":"expense"}]}`
                },
                {
                    role: "user",
                    content: truncatedText
                }
            ],
            max_tokens: 4096,
            temperature: 0.1
        });

        const aiResponse = completion.choices[0].message.content || "{}";

        // Parse the AI response — try to extract JSON
        let parsed;
        try {
            // Try direct parse
            parsed = JSON.parse(aiResponse);
        } catch {
            // Try to extract JSON from possible markdown wrapping
            const jsonMatch = aiResponse.match(/\{[\s\S]*\}/);
            if (jsonMatch) {
                parsed = JSON.parse(jsonMatch[0]);
            } else {
                return NextResponse.json({ error: "IA não conseguiu extrair transações do arquivo." }, { status: 422 });
            }
        }

        const transactions = parsed.transactions || [];

        if (transactions.length === 0) {
            return NextResponse.json({ error: "Nenhuma transação encontrada no arquivo." }, { status: 422 });
        }

        // Normalize and validate
        const normalized = transactions.map((t: any, index: number) => ({
            id: `import-${index}`,
            date: t.date || new Date().toISOString().split("T")[0],
            description: t.description || "Sem descrição",
            amount: Math.abs(Number(t.amount) || 0),
            type: t.type === "income" ? "income" : "expense",
            category: "Outros",
            selected: true
        }));

        return NextResponse.json({
            success: true,
            fileName: file.name,
            totalFound: normalized.length,
            transactions: normalized
        });

    } catch (error: any) {
        console.error("[Statement Parse] Error:", error);
        return NextResponse.json({ error: "Erro ao processar arquivo: " + error.message }, { status: 500 });
    }
}
