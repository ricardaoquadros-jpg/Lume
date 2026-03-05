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

        // Calculate summaries for AI context
        const totalIncome = transactions?.filter(t => t.type === 'income').reduce((acc, t) => acc + (Number(t.amount) || 0), 0) || 0;
        const totalExpenses = transactions?.filter(t => t.type === 'expense').reduce((acc, t) => acc + (Number(t.amount) || 0), 0) || 0;
        const currentBalance = totalIncome - totalExpenses;

        const totalInvested = investments?.reduce((acc, i) => acc + (Number(i.invested_value) || 0), 0) || 0;
        const currentInvestmentValue = investments?.reduce((acc, i) => acc + (Number(i.current_value) || 0), 0) || 0;

        const exportData = {
            metadata: {
                version: '2.0',
                exported_at: new Date().toISOString(),
                user_id_hash: user.id.split('-')[0] + '...', // Privacy friendly ID hint
                app_name: 'Lume SaaS',
                description: 'Full financial data export optimized for AI analysis. Contains transactions, recurring items, and investment portfolio.'
            },
            summary: {
                financial_overview: {
                    total_income: totalIncome,
                    total_expenses: totalExpenses,
                    net_balance: currentBalance,
                    savings_rate: totalIncome > 0 ? ((totalIncome - totalExpenses) / totalIncome * 100).toFixed(2) + '%' : '0%'
                },
                investment_overview: {
                    total_invested: totalInvested,
                    current_portfolio_value: currentInvestmentValue,
                    portfolio_growth: totalInvested > 0 ? ((currentInvestmentValue - totalInvested) / totalInvested * 100).toFixed(2) + '%' : '0%',
                    total_assets: investments?.length || 0
                }
            },
            schema: {
                transactions: {
                    description: "List of all financial transactions (income and expenses).",
                    fields: {
                        type: "'income' or 'expense'",
                        amount: "Numeric value of the transaction",
                        category: "Category of the transaction (e.g., 'Alimentação', 'Salário')",
                        transaction_date: "Date when the transaction occurred (YYYY-MM-DD)",
                        description: "User provided description"
                    }
                },
                investments: {
                    description: "Current investment portfolio holdings.",
                    fields: {
                        type: "Type of investment (e.g., 'Ações', 'Fundos Imobiliários')",
                        invested_value: "Total amount originally invested",
                        current_value: "Current market value of the investment",
                        yield_rate: "Annual yield rate (if applicable)"
                    }
                },
                recurring_transactions: {
                    description: "Active recurring monthly transactions.",
                    fields: {
                        amount: "Monthly amount",
                        day_of_month: "Day of the month the transaction occurs",
                        frequency: "Frequency of occurrence (e.g., 'monthly')",
                        active: "Whether the recurrence is currently active"
                    }
                },
                investment_contributions: {
                    description: "History of contributions made to investments.",
                    fields: {
                        amount: "Amount contributed",
                        contribution_date: "Date of the contribution",
                        notes: "Optional notes about the contribution"
                    }
                }
            },
            data: {
                work_profile: work_profile ? {
                    ...work_profile,
                    // Parse AI data if it is a string to insure clean JSON export
                    ai_profile_data: typeof work_profile.ai_profile_data === 'string' 
                        ? JSON.parse(work_profile.ai_profile_data) 
                        : work_profile.ai_profile_data
                } : null,
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
