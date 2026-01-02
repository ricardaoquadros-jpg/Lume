"use client";

import { useEffect, useState } from "react";
import { createClient } from "@/lib/supabase/client";
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Area, AreaChart } from "recharts";
import { TrendingUp, Loader2 } from "lucide-react";

interface BalanceChartProps {
    userId: string;
    refreshTrigger: number;
    initialBalance: number;
}

export function BalanceChart({ userId, refreshTrigger, initialBalance }: BalanceChartProps) {
    const [data, setData] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);
    const supabase = createClient();

    useEffect(() => {
        const fetchData = async () => {
            setLoading(true);
            const { data: transactions } = await supabase
                .from("transactions")
                .select("amount, type, transaction_date")
                .eq("user_id", userId)
                .order("transaction_date", { ascending: true });

            if (!transactions) {
                setLoading(false);
                return;
            }

            // Calculate running balance
            let currentBal = initialBalance;
            const balanceHistory: { date: string, balance: number }[] = [];

            // Group by date to avoid multiple points per day? 
            // Better to process chronological and take end-of-day balance
            // Map: Date -> Balance
            const balanceMap = new Map<string, number>();

            // Create range of dates for the current month? 
            // Or just show history? Let's show LAST 30 DAYS for relevance.

            // 1. Process ALL transactions to get current state
            transactions.forEach(t => {
                const amount = Number(t.amount);
                if (t.type === 'income') currentBal += amount;
                else currentBal -= amount;

                balanceMap.set(t.transaction_date, currentBal);
            });

            // 2. Filter for display (Last 30 days)
            const today = new Date();
            const last30 = new Array(30).fill(0).map((_, i) => {
                const d = new Date();
                d.setDate(today.getDate() - (29 - i));
                return d.toISOString().split('T')[0];
            });

            // Build chart data
            // We need the balance at START of the 30 day window.
            // Re-calculate... efficiently.

            // Simpler approach: Just chart the days we HAVE data for, plus fill gaps?
            // Let's stick to: "Show balance evolution for available transaction history in current month"

            // Refined: Show Current Month Only
            const now = new Date();
            const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);

            // Calculate starting balance of the month
            let monthStartBalance = initialBalance;
            transactions.forEach(t => {
                if (new Date(t.transaction_date) < startOfMonth) {
                    if (t.type === 'income') monthStartBalance += Number(t.amount);
                    else monthStartBalance -= Number(t.amount);
                }
            });

            const daysInMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
            const chartData = [];
            let dailyBal = monthStartBalance;

            for (let d = 1; d <= daysInMonth; d++) {
                const dateStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;

                // Find transactions for this day
                const dayTxs = transactions.filter(t => t.transaction_date === dateStr);
                dayTxs.forEach(t => {
                    if (t.type === 'income') dailyBal += Number(t.amount);
                    else dailyBal -= Number(t.amount);
                });

                // Only add if day <= today
                if (d <= now.getDate()) {
                    chartData.push({
                        day: String(d),
                        balance: dailyBal,
                        date: dateStr
                    });
                }
            }

            setData(chartData);
            setLoading(false);
        };

        fetchData();
    }, [userId, refreshTrigger, initialBalance]);

    if (loading) return <div className="h-[200px] flex items-center justify-center"><Loader2 className="animate-spin text-gray-300" /></div>;

    return (
        <div className="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm h-full flex flex-col">
            <h3 className="text-[14px] font-semibold text-gray-500 uppercase tracking-[0.5px] mb-4 flex items-center gap-2">
                <TrendingUp className="w-4 h-4 text-emerald-500" /> Evolução do Saldo (Mês)
            </h3>

            <div className="flex-1 min-h-[200px]">
                <ResponsiveContainer width="100%" height="100%">
                    <AreaChart data={data}>
                        <defs>
                            <linearGradient id="colorBal" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="5%" stopColor="#0b3680" stopOpacity={0.2} />
                                <stop offset="95%" stopColor="#0b3680" stopOpacity={0} />
                            </linearGradient>
                        </defs>
                        <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f0f0f0" />
                        <XAxis
                            dataKey="day"
                            axisLine={false}
                            tickLine={false}
                            tick={{ fontSize: 12, fill: '#9ca3af' }}
                            interval={4}
                        />
                        <Tooltip
                            formatter={(value: number) => [`R$ ${value.toLocaleString('pt-BR')}`, 'Saldo']}
                            contentStyle={{ borderRadius: '12px', border: 'none', boxShadow: '0 4px 12px rgba(0,0,0,0.1)' }}
                        />
                        <Area
                            type="monotone"
                            dataKey="balance"
                            stroke="#0b3680"
                            strokeWidth={3}
                            fillOpacity={1}
                            fill="url(#colorBal)"
                        />
                    </AreaChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}
