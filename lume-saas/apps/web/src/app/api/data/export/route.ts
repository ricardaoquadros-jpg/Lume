import { createClient } from '@/lib/supabase/server';
import { NextResponse } from 'next/server';

export async function GET() {
    try {
        const supabase = await createClient();

        const { data: { user } } = await supabase.auth.getUser();
        if (!user) {
            return NextResponse.json({ error: 'Não autorizado' }, { status: 401 });
        }

        // Fetch all user data
        const [
            { data: work_profile },
            { data: transactions },
            { data: recurring_transactions },
            { data: investments },
            { data: investment_contributions }
        ] = await Promise.all([
            supabase.from('work_profiles').select('*').eq('user_id', user.id).single(),
            supabase.from('transactions').select('*').eq('user_id', user.id).order('transaction_date', { ascending: false }),
            supabase.from('recurring_transactions').select('*').eq('user_id', user.id),
            supabase.from('investments').select('*').eq('user_id', user.id),
            supabase.from('investment_contributions').select('*').eq('user_id', user.id)
        ]);

        const exportData = {
            version: '1.0',
            exported_at: new Date().toISOString(),
            data: {
                work_profile: work_profile || null,
                transactions: transactions || [],
                recurring_transactions: recurring_transactions || [],
                investments: investments || [],
                investment_contributions: investment_contributions || []
            }
        };

        return new NextResponse(JSON.stringify(exportData, null, 2), {
            status: 200,
            headers: {
                'Content-Type': 'application/json',
                'Content-Disposition': `attachment; filename="lume-backup-${new Date().toISOString().split('T')[0]}.json"`
            }
        });

    } catch (error: any) {
        console.error('Export error:', error);
        return NextResponse.json({ error: 'Erro ao exportar dados' }, { status: 500 });
    }
}
