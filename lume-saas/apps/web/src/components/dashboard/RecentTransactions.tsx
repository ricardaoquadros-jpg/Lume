"use client";

import { useEffect, useState } from "react";
import { createClient } from "@/lib/supabase/client";
import { format } from "date-fns";
import { ptBR } from "date-fns/locale";
import { ArrowDownLeft, ArrowUpRight, Trash2, Plus } from "lucide-react";

interface RecentTransactionsProps {
    userId: string;
    refreshTrigger: number; // Prop to trigger refetch
    onAddClick: () => void;
}

export function RecentTransactions({ userId, refreshTrigger, onAddClick }: RecentTransactionsProps) {
    const supabase = createClient();
    const [transactions, setTransactions] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchTransactions();
    }, [userId, refreshTrigger]);

    const fetchTransactions = async () => {
        const { data } = await supabase
            .from("transactions")
            .select("*")
            .eq("user_id", userId)
            .order("transaction_date", { ascending: false })
            .order("created_at", { ascending: false })
            .limit(5);

        setTransactions(data || []);
        setLoading(false);
    };

    const handleDelete = async (id: string) => {
        if (!confirm("Tem certeza que deseja excluir?")) return;

        await supabase.from("transactions").delete().eq("id", id);
        fetchTransactions();
    };

    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(value);
    };

    return (
        <div className="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm">
            <div className="flex items-center justify-between mb-8">
                <h3 className="text-lg font-medium text-gray-900 font-[var(--font-playfair)]">
                    Últimas Transações
                </h3>
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
                        <div key={i} className="h-16 bg-gray-50 rounded-xl animate-pulse" />
                    ))}
                </div>
            ) : transactions.length === 0 ? (
                <div className="text-center py-12 text-gray-400">
                    <p>Nenhuma transação encontrada.</p>
                    <button onClick={onAddClick} className="text-[#0b3680] font-medium mt-2 text-sm">Adicionar a primeira</button>
                </div>
            ) : (
                <div className="space-y-4">
                    {transactions.map((t) => (
                        <div key={t.id} className="group flex items-center justify-between p-4 rounded-xl hover:bg-gray-50 transition-colors border border-transparent hover:border-gray-100">
                            <div className="flex items-center gap-4">
                                <div className={`w-10 h-10 rounded-full flex items-center justify-center shrink-0 ${t.type === 'income' ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600'
                                    }`}>
                                    {t.type === 'income' ? <ArrowUpRight className="w-5 h-5" /> : <ArrowDownLeft className="w-5 h-5" />}
                                </div>
                                <div>
                                    <p className="font-medium text-gray-900">{t.description}</p>
                                    <p className="text-xs text-gray-500 capitalize">
                                        {format(new Date(t.transaction_date), "d 'de' MMM, yyyy", { locale: ptBR })} • {t.category}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-4">
                                <span className={`font-medium whitespace-nowrap ${t.type === 'income' ? 'text-emerald-600' : 'text-gray-900'
                                    }`}>
                                    {t.type === 'income' ? '+' : '-'} {formatCurrency(t.amount)}
                                </span>
                                <button
                                    onClick={() => handleDelete(t.id)}
                                    className="opacity-0 group-hover:opacity-100 p-2 text-gray-400 hover:text-red-600 transition-all"
                                    title="Excluir"
                                >
                                    <Trash2 className="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
