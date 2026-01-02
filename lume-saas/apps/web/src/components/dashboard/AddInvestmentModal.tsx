"use client";

import { useState } from "react";
import { createClient } from "@/lib/supabase/client";
import { X, Loader2, DollarSign, AlignLeft, Tag, TrendingUp } from "lucide-react";

interface AddInvestmentModalProps {
    userId: string;
    isOpen: boolean;
    onClose: () => void;
    onSuccess: () => void;
}

const investmentTypes = [
    "Ações",
    "Fundos Imobiliários",
    "Renda Fixa",
    "Tesouro Direto",
    "CDB",
    "Criptomoedas",
    "Poupança",
    "Outro"
];

export function AddInvestmentModal({ userId, isOpen, onClose, onSuccess }: AddInvestmentModalProps) {
    const supabase = createClient();
    const [loading, setLoading] = useState(false);
    const [formData, setFormData] = useState({
        name: "",
        type: "Outro",
        invested_value: "",
        current_value: "",
        start_date: new Date().toISOString().split("T")[0],
        benchmark: "",
        yield_rate: ""
    });

    if (!isOpen) return null;

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);

        const investedValue = parseFloat(formData.invested_value.replace(",", ".")) || 0;
        const currentValue = parseFloat(formData.current_value.replace(",", ".")) || investedValue;
        const rate = formData.yield_rate ? parseFloat(formData.yield_rate.replace(",", ".")) : null;

        // Create Investment
        const { data: invData, error } = await supabase.from("investments").insert({
            user_id: userId,
            name: formData.name,
            type: formData.type,
            invested_value: investedValue,
            current_value: currentValue,
            active: true,
            start_date: formData.start_date,
            last_update: new Date().toISOString().split("T")[0],
            benchmark: formData.benchmark || null,
            yield_rate: rate
        }).select().single();

        if (error) {
            setLoading(false);
            alert("Erro ao salvar investimento: " + error.message);
            return;
        }

        // Create Initial Contribution Logic
        if (invData) {
            await supabase.from("investment_contributions").insert({
                user_id: userId,
                investment_id: invData.id,
                amount: investedValue,
                contribution_date: formData.start_date,
                notes: "Investimento Inicial"
            });
        }

        setLoading(false);
        onSuccess();
        onClose();
        setFormData({
            name: "",
            type: "Outro",
            invested_value: "",
            current_value: "",
            start_date: new Date().toISOString().split("T")[0],
            benchmark: "",
            yield_rate: ""
        });
    };

    return (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div className="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div className="p-6 border-b border-gray-100 flex justify-between items-center bg-gradient-to-r from-emerald-50 to-blue-50">
                    <h3 className="text-xl font-bold text-[#0b3680]">
                        Novo Investimento
                    </h3>
                    <button onClick={onClose} className="p-2 hover:bg-white/50 rounded-full transition-colors text-gray-500">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-6">
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <label className="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                <AlignLeft className="w-3 h-3" /> Nome do Investimento
                            </label>
                            <input
                                type="text"
                                required
                                value={formData.name}
                                onChange={e => setFormData({ ...formData, name: e.target.value })}
                                className="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-[#0b3680] outline-none text-[14px] font-medium"
                                placeholder="Ex: PETR4, Tesouro Selic 2029, Bitcoin..."
                                autoFocus
                            />
                        </div>

                        <div className="space-y-2">
                            <label className="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                <Tag className="w-3 h-3" /> Tipo de Investimento
                            </label>
                            <select
                                className="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-[#0b3680] outline-none bg-white text-[14px] font-medium"
                                value={formData.type}
                                onChange={e => setFormData({ ...formData, type: e.target.value })}
                            >
                                {investmentTypes.map(type => (
                                    <option key={type} value={type}>{type}</option>
                                ))}
                            </select>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <label className="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                    <DollarSign className="w-3 h-3" /> Valor Investido
                                </label>
                                <div className="relative">
                                    <span className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-medium text-sm">R$</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        required
                                        value={formData.invested_value}
                                        onChange={e => setFormData({ ...formData, invested_value: e.target.value })}
                                        className="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-200 focus:border-[#0b3680] outline-none text-[14px] font-medium"
                                        placeholder="0,00"
                                    />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <label className="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                    <TrendingUp className="w-3 h-3" /> Valor Atual
                                </label>
                                <div className="relative">
                                    <span className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-medium text-sm">R$</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={formData.current_value}
                                        onChange={e => setFormData({ ...formData, current_value: e.target.value })}
                                        className="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-200 focus:border-[#0b3680] outline-none text-[14px] font-medium"
                                        placeholder="Igual ao investido"
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="space-y-2">
                            <label className="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                <Tag className="w-3 h-3" /> Data do Investimento
                            </label>
                            <input
                                type="date"
                                required
                                value={formData.start_date}
                                onChange={e => setFormData({ ...formData, start_date: e.target.value })}
                                className="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-[#0b3680] outline-none bg-white text-[14px] font-medium"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <label className="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                <TrendingUp className="w-3 h-3" /> Indexador (Opcional)
                            </label>
                            <input
                                type="text"
                                value={formData.benchmark}
                                onChange={e => setFormData({ ...formData, benchmark: e.target.value })}
                                className="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-[#0b3680] outline-none text-[14px] font-medium"
                                placeholder="Ex: CDI, IPCA, Pré..."
                            />
                        </div>

                        <div className="space-y-2">
                            <label className="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                <Tag className="w-3 h-3" /> Taxa % (Opcional)
                            </label>
                            <div className="relative">
                                <span className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 font-medium text-sm">%</span>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={formData.yield_rate}
                                    onChange={e => setFormData({ ...formData, yield_rate: e.target.value })}
                                    className="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-[#0b3680] outline-none text-[14px] font-medium"
                                    placeholder="Ex: 100, 1.0..."
                                />
                            </div>
                        </div>
                    </div>

                    <button
                        type="submit"
                        disabled={loading}
                        className="w-full py-4 rounded-xl font-semibold text-white bg-[#0b3680] hover:bg-[#092a66] transition-all flex items-center justify-center gap-2 disabled:opacity-70 shadow-lg shadow-[#0b3680]/20"
                    >
                        {loading ? <Loader2 className="w-5 h-5 animate-spin" /> : "Salvar Investimento"}
                    </button>
                </form>
            </div>
        </div>
    );
}
