"use client";

import { useState } from "react";
import { createClient } from "@/lib/supabase/client";
import { X, Loader2, Calendar, Tag, DollarSign, AlignLeft, RefreshCw, Repeat } from "lucide-react";

interface AddRecurringModalProps {
    userId: string;
    isOpen: boolean;
    onClose: () => void;
    onSuccess: () => void;
}

export function AddRecurringModal({ userId, isOpen, onClose, onSuccess }: AddRecurringModalProps) {
    const supabase = createClient();
    const [loading, setLoading] = useState(false);
    const [type, setType] = useState<"income" | "expense">("expense");
    const [formData, setFormData] = useState({
        description: "",
        amount: "",
        category: "Outros",
        dayOfMonth: "1",
        frequency: "monthly"
    });

    if (!isOpen) return null;

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);

        const { error } = await supabase.from("recurring_transactions").insert({
            user_id: userId,
            type: type,
            description: formData.description,
            amount: parseFloat(formData.amount.replace(",", ".")) || 0,
            category: formData.category,
            day_of_month: parseInt(formData.dayOfMonth),
            frequency: formData.frequency,
            active: true
        });

        setLoading(false);

        if (error) {
            alert("Erro ao salvar recorrência: " + error.message);
        } else {
            onSuccess();
            onClose();
            setFormData({
                description: "",
                amount: "",
                category: "Outros",
                dayOfMonth: "1",
                frequency: "monthly"
            });
        }
    };

    return (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div className="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div className="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                    <h3 className="text-xl font-[var(--font-playfair)] font-medium text-[#0b3680]">
                        Nova Recorrência
                    </h3>
                    <button onClick={onClose} className="p-2 hover:bg-gray-100 rounded-full transition-colors text-gray-500">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-6">
                    {/* Type Toggle */}
                    <div className="grid grid-cols-2 gap-2 bg-gray-100 p-1 rounded-xl">
                        <button
                            type="button"
                            onClick={() => setType("expense")}
                            className={`py-2 rounded-lg text-sm font-medium transition-all ${type === "expense"
                                ? "bg-white text-red-600 shadow-sm"
                                : "text-gray-500 hover:text-gray-700"
                                }`}
                        >
                            Despesa Recorrente
                        </button>
                        <button
                            type="button"
                            onClick={() => setType("income")}
                            className={`py-2 rounded-lg text-sm font-medium transition-all ${type === 'income'
                                ? "bg-white text-emerald-600 shadow-sm"
                                : "text-gray-500 hover:text-gray-700"
                                }`}
                        >
                            Receita Recorrente
                        </button>
                    </div>

                    <div className="space-y-4">
                        <div className="space-y-2">
                            <label className="text-xs font-medium text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                <DollarSign className="w-3 h-3" /> Valor Mensal
                            </label>
                            <div className="relative">
                                <span className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-medium">R$</span>
                                <input
                                    type="number"
                                    step="0.01"
                                    required
                                    value={formData.amount}
                                    onChange={e => setFormData({ ...formData, amount: e.target.value })}
                                    className="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-200 focus:border-[#0b3680] outline-none text-xl font-medium"
                                    placeholder="0,00"
                                    autoFocus
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <label className="text-xs font-medium text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                <AlignLeft className="w-3 h-3" /> Descrição
                            </label>
                            <input
                                type="text"
                                required
                                value={formData.description}
                                onChange={e => setFormData({ ...formData, description: e.target.value })}
                                className="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-[#0b3680] outline-none"
                                placeholder="Ex: Netflix, Aluguel, Salário..."
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <label className="text-xs font-medium text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                    <Tag className="w-3 h-3" /> Categoria
                                </label>
                                <select
                                    className="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-[#0b3680] outline-none bg-white"
                                    value={formData.category}
                                    onChange={e => setFormData({ ...formData, category: e.target.value })}
                                >
                                    <option>Alimentação</option>
                                    <option>Mercado</option>
                                    <option>Transporte</option>
                                    <option>Lazer</option>
                                    <option>Roupas</option>
                                    <option>Jogos</option>
                                    <option>Saúde</option>
                                    <option>Esportes</option>
                                    <option>Investimento</option>
                                    <option>Educação</option>
                                    <option>Moradia</option>
                                    <option>Contas</option>
                                    <option>Assinaturas</option>
                                    <option>Beleza</option>
                                    <option>Pets</option>
                                    <option>Viagem</option>
                                    <option>Presentes</option>
                                    <option>Salário</option>
                                    <option>Extra</option>
                                    <option>Outros</option>
                                </select>
                            </div>

                            <div className="space-y-2">
                                <label className="text-xs font-medium text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                    <Repeat className="w-3 h-3" /> Dia do Cobrança
                                </label>
                                <select
                                    required
                                    value={formData.dayOfMonth}
                                    onChange={e => setFormData({ ...formData, dayOfMonth: e.target.value })}
                                    className="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-[#0b3680] outline-none bg-white"
                                >
                                    {Array.from({ length: 31 }, (_, i) => i + 1).map(day => (
                                        <option key={day} value={day}>Dia {day}</option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </div>

                    <button
                        type="submit"
                        disabled={loading}
                        className={`w-full py-4 rounded-xl font-medium text-white transition-all flex items-center justify-center gap-2 ${type === 'expense' ? 'bg-red-600 hover:bg-red-700' : 'bg-emerald-600 hover:bg-emerald-700'
                            } disabled:opacity-70`}
                    >
                        {loading ? <Loader2 className="w-5 h-5 animate-spin" /> : "Salvar Recorrência"}
                    </button>
                </form>
            </div>
        </div>
    );
}
