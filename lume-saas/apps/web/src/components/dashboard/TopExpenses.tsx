"use client";

import { useEffect, useState } from "react";
import { createClient } from "@/lib/supabase/client";
import { TrendingDown, ArrowRight } from "lucide-react";

interface TopExpensesProps {
    userId: string;
    refreshTrigger: number;
}

export function TopExpenses({ userId, refreshTrigger }: TopExpensesProps) {
    const [expenses, setExpenses] = useState<any[]>([]);
    const supabase = createClient();

    useEffect(() => {
        const fetchTop = async () => {
            const now = new Date();
            const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1).toISOString();
            const endOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString();

            const { data } = await supabase
                .from("transactions")
                .select("description, amount, category, transaction_date")
                .eq("user_id", userId)
                .eq("type", "expense")
                .gte("transaction_date", startOfMonth)
                .lte("transaction_date", endOfMonth)
                .order("amount", { ascending: false })
                .limit(5);

            if (data) setExpenses(data);
        };

        fetchTop();
    }, [userId, refreshTrigger]);

    if (expenses.length === 0) return null;

    return (
        <div className="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm h-full">
            <h3 className="text-[14px] font-semibold text-gray-500 uppercase tracking-[0.5px] mb-6 flex items-center gap-2">
                <TrendingDown className="w-4 h-4 text-red-400" /> Top 5 Despesas (Mês)
            </h3>
            <div className="space-y-4">
                {expenses.map((ex, i) => (
                    <div key={i} className="flex items-center justify-between group">
                        <div className="flex items-center gap-3">
                            <span className="text-xl font-bold text-gray-200 group-hover:text-[#0b3680] transition-colors">
                                #{i + 1}
                            </span>
                            <div>
                                <div className="font-medium text-gray-800">{ex.description}</div>
                                <div className="text-xs text-gray-400">{ex.category}</div>
                            </div>
                        </div>
                        <div className="text-right">
                            <div className="font-bold text-gray-900">
                                R$ {ex.amount.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                            </div>
                            <div className="text-xs text-gray-400">
                                {new Date(ex.transaction_date + 'T12:00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })}
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
