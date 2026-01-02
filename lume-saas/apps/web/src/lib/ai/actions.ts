import { createClient } from "@/lib/supabase/server";

export async function performAIAction(functionName: string, args: any, transcription?: string) {
    const supabase = await createClient(); // Server client with cookies
    const { data: { user } } = await supabase.auth.getUser();

    if (!user) throw new Error("Usuário não autenticado");

    console.log(`[AI Action] ${functionName}`, args);

    switch (functionName) {
        case "add_transaction":
            const txResult = await supabase.from("transactions").insert({
                user_id: user.id,
                type: args.type,
                amount: args.amount,
                description: args.description,
                category: args.category || "Outros",
                transaction_date: args.date || new Date().toISOString().split("T")[0],
                // transcription: transcription || null // Disabled until migration is applied
            });
            if (txResult.error) {
                console.error("[AI Action] DB Error:", txResult.error);
                throw new Error(`Erro ao inserir transação: ${txResult.error.message}`);
            }
            console.log("[AI Action] Transaction inserted successfully");
            return txResult;

        case "add_recurring_transaction":
            return await supabase.from("recurring_transactions").insert({
                user_id: user.id,
                type: args.type,
                amount: args.amount,
                description: args.description,
                category: args.category || "Outros",
                day_of_month: args.day_of_month || 1,
                active: true,
                frequency: "monthly",
                transcription: transcription || null
            });

        case "delete_transaction_by_description":
            // Find the most recent transaction matching description
            // This is a naive implementation, good enough for "undo last lunch"
            const { data: txs } = await supabase
                .from("transactions")
                .select("id")
                .eq("user_id", user.id)
                .ilike("description", `%${args.description}%`)
                .order("created_at", { ascending: false })
                .limit(1);

            if (txs && txs.length > 0) {
                return await supabase.from("transactions").delete().eq("id", txs[0].id);
            }
            return { error: "Transação não encontrada" };

        case "get_financial_summary":
            // Not implementing return data for now, just action
            return { message: "Resumo consultado" }; // The dashboard shows it anyway

        case "add_investment":
            // Create new investment
            const invResult = await supabase.from("investments").insert({
                user_id: user.id,
                name: args.name,
                type: args.type || "Outro",
                invested_value: args.invested_value,
                current_value: args.current_value || args.invested_value,
                start_date: args.start_date || new Date().toISOString().split("T")[0],
                benchmark: args.benchmark || null,
                yield_rate: args.yield_rate || null,
                active: true
            }).select().single();

            if (invResult.error) {
                console.error("[AI Action] Investment Error:", invResult.error);
                throw new Error(`Erro ao criar investimento: ${invResult.error.message}`);
            }

            // AUTOMATICALLY CREATE FIRST CONTRIBUTION
            await supabase.from("investment_contributions").insert({
                user_id: user.id,
                investment_id: invResult.data.id,
                amount: args.invested_value,
                contribution_date: args.start_date || new Date().toISOString().split("T")[0],
                notes: "Aporte Inicial"
            });

            console.log("[AI Action] Investment created with initial contribution:", args.name);
            return invResult;

        case "add_contribution":
            // Find investment by name
            const { data: matchingInv } = await supabase
                .from("investments")
                .select("id, invested_value, current_value")
                .eq("user_id", user.id)
                .ilike("name", `%${args.investment_name}%`)
                .limit(1);

            if (!matchingInv || matchingInv.length === 0) {
                throw new Error(`Investimento "${args.investment_name}" não encontrado`);
            }

            const targetInv = matchingInv[0];
            const previousInvested = Number(targetInv.invested_value) || 0;
            const previousCurrent = Number(targetInv.current_value) || 0;

            // Add contribution
            const contribResult = await supabase.from("investment_contributions").insert({
                user_id: user.id,
                investment_id: targetInv.id,
                amount: args.amount,
                contribution_date: args.date || new Date().toISOString().split("T")[0],
                notes: args.notes || null
            });

            if (contribResult.error) {
                console.error("[AI Action] Contribution Error:", contribResult.error);
                throw new Error(`Erro ao adicionar aporte: ${contribResult.error.message}`);
            }

            // Update total invested AND current value
            // We explicitly convert to Number to ensure SUM happens, not replacement or string concat
            await supabase
                .from("investments")
                .update({
                    invested_value: previousInvested + Number(args.amount),
                    current_value: previousCurrent + Number(args.amount)
                })
                .eq("id", targetInv.id);

            console.log("[AI Action] Contribution added to:", args.investment_name);
            return contribResult;

        case "update_investment_value":
            // Find investment
            const { data: invToUpdate } = await supabase
                .from("investments")
                .select("id")
                .eq("user_id", user.id)
                .ilike("name", `%${args.investment_name}%`)
                .limit(1);

            if (!invToUpdate || invToUpdate.length === 0) {
                throw new Error(`Investimento "${args.investment_name}" não encontrado`);
            }

            return await supabase.from("investments")
                .update({
                    current_value: args.current_value,
                    last_update: args.date || new Date().toISOString().split("T")[0]
                })
                .eq("id", invToUpdate[0].id);

        default:
            throw new Error("Função desconhecida");
    }
}
