"use client";

import { useEffect, useState } from "react";
import { createClient } from "@/lib/supabase/client";
import { ArrowDownLeft, ArrowUpRight, Trash2, Plus, CreditCard, Calendar } from "lucide-react";

interface RecurringTransactionsProps {
    userId: string;
    refreshTrigger: number;
    onAddClick: () => void;
}

export function RecurringTransactions({ userId, refreshTrigger, onAddClick }: RecurringTransactionsProps) {
    const supabase = createClient();
    const [recurrings, setRecurrings] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchRecurrings();
    }, [userId, refreshTrigger]);

    const fetchRecurrings = async () => {
        const { data } = await supabase
            .from("recurring_transactions")
            .select("*")
            .eq("user_id", userId)
            .order("day_of_month", { ascending: true });

        setRecurrings(data || []);
        setLoading(false);
    };

    const handleDelete = async (id: string) => {
        if (!confirm("Tem certeza que deseja remover essa recorrência?")) return;

        await supabase.from("recurring_transactions").delete().eq("id", id);
        fetchRecurrings();
    };

    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(value);
    };

    return (
        <div className="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm h-full flex flex-col">
            <div className="flex items-center justify-between mb-8">
                <div className="flex items-center gap-2">
                    <CreditCard className="w-5 h-5 text-gray-400" />
                    <h3 className="text-lg font-medium text-gray-900 font-[var(--font-playfair)]">
                        Contas Fixas
                    </h3>
                </div>
                <button
                    onClick={onAddClick}
                    className="text-sm font-medium text-[#0b3680] hover:bg-blue-50 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1"
                >
                    <Plus className="w-4 h-4" /> Nova
                </button>
            </div>

            {loading ? (
                <div className="space-y-4">
                    {[1, 2, 3].map(i => (
                        <div key={i} className="h-14 bg-gray-50 rounded-xl animate-pulse" />
                    ))}
                </div>
            ) : recurrings.length === 0 ? (
                <div className="text-center py-12 text-gray-400 flex-1 flex flex-col items-center justify-center">
                    <Calendar className="w-10 h-10 mb-3 opacity-20" />
                    <p>Nenhuma conta fixa.</p>
                    <button onClick={onAddClick} className="text-[#0b3680] font-medium mt-2 text-sm">Adicionar Assinaturas</button>
                </div>
            ) : (
                <div className="space-y-3 overflow-y-auto max-h-[300px] pr-2 custom-scrollbar">
                    {recurrings.map((t) => (
                        <div key={t.id} className="group flex items-center justify-between p-3 rounded-xl hover:bg-gray-50 transition-colors border border-transparent hover:border-gray-100">
                            <div className="flex items-center gap-3">
                                <div className={`w-8 h-8 rounded-full flex items-center justify-center shrink-0 text-xs font-bold ${t.type === 'income' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'
                                    }`}>
                                    {t.day_of_month}
                                </div>
                                <div>
                                    <p className="font-medium text-gray-900 text-sm leading-tight">{t.description}</p>
                                    <p className="text-[10px] text-gray-400 uppercase tracking-wide">
                                        {t.category}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <span className={`font-medium text-sm whitespace-nowrap ${t.type === 'income' ? 'text-emerald-600' : 'text-gray-900'
                                    }`}>
                                    {formatCurrency(t.amount)}
                                </span>
                                <button
                                    onClick={() => handleDelete(t.id)}
                                    className="opacity-0 group-hover:opacity-100 p-1.5 text-gray-400 hover:text-red-600 transition-all rounded-lg hover:bg-red-50"
                                    title="Remover Recorrência"
                                >
                                    <Trash2 className="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
