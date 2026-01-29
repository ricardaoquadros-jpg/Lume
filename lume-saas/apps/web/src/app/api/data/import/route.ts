import { createClient } from '@/lib/supabase/server';
import { NextRequest, NextResponse } from 'next/server';

export async function POST(request: NextRequest) {
    try {
        const supabase = await createClient();

        const { data: { user } } = await supabase.auth.getUser();
        if (!user) {
            return NextResponse.json({ error: 'Não autorizado' }, { status: 401 });
        }

        const body = await request.json();

        // Validate format
        if (!body.version || !body.data) {
            return NextResponse.json({ error: 'Formato de arquivo inválido' }, { status: 400 });
        }

        const { data } = body;

        // Start import - Delete existing data first
        await Promise.all([
            supabase.from('investment_contributions').delete().eq('user_id', user.id),
            supabase.from('investments').delete().eq('user_id', user.id),
            supabase.from('recurring_transactions').delete().eq('user_id', user.id),
            supabase.from('transactions').delete().eq('user_id', user.id),
        ]);

        // Import work_profile (upsert)
        if (data.work_profile) {
            const profileData = { ...data.work_profile };
            delete profileData.id;
            delete profileData.created_at;
            delete profileData.updated_at;
            profileData.user_id = user.id;

            // If ai_profile_data is an object (from our clean export), stringify it for storage if DB expects string/jsonb
            if (typeof profileData.ai_profile_data === 'object' && profileData.ai_profile_data !== null) {
                profileData.ai_profile_data = JSON.stringify(profileData.ai_profile_data);
            }

            await supabase.from('work_profiles').upsert(profileData, {
                onConflict: 'user_id'
            });
        }

        // Import transactions
        if (data.transactions?.length > 0) {
            const transactions = data.transactions.map((t: any) => {
                const { id, created_at, ...rest } = t;
                return { ...rest, user_id: user.id };
            });
            await supabase.from('transactions').insert(transactions);
        }

        // Import recurring transactions
        if (data.recurring_transactions?.length > 0) {
            const recurring = data.recurring_transactions.map((r: any) => {
                const { id, created_at, ...rest } = r;
                return { ...rest, user_id: user.id };
            });
            await supabase.from('recurring_transactions').insert(recurring);
        }

        // Import investments with ID mapping for contributions
        const investmentIdMap: Record<string, string> = {};
        if (data.investments?.length > 0) {
            for (const inv of data.investments) {
                const oldId = inv.id;
                const { id, created_at, ...rest } = inv;
                const { data: newInv } = await supabase
                    .from('investments')
                    .insert({ ...rest, user_id: user.id })
                    .select('id')
                    .single();

                if (newInv) {
                    investmentIdMap[oldId] = newInv.id;
                }
            }
        }

        // Import investment contributions with mapped IDs
        if (data.investment_contributions?.length > 0) {
            const contributions = data.investment_contributions
                .filter((c: any) => investmentIdMap[c.investment_id])
                .map((c: any) => {
                    const { id, created_at, ...rest } = c;
                    return {
                        ...rest,
                        user_id: user.id,
                        investment_id: investmentIdMap[c.investment_id]
                    };
                });

            if (contributions.length > 0) {
                await supabase.from('investment_contributions').insert(contributions);
            }
        }

        return NextResponse.json({
            success: true,
            message: 'Dados importados com sucesso!',
            imported: {
                transactions: data.transactions?.length || 0,
                recurring: data.recurring_transactions?.length || 0,
                investments: data.investments?.length || 0,
                contributions: data.investment_contributions?.length || 0,
                profile_restored: !!data.work_profile
            }
        });

    } catch (error: any) {
        console.error('Import error:', error);
        return NextResponse.json({ error: 'Erro ao importar dados: ' + error.message }, { status: 500 });
    }
}
