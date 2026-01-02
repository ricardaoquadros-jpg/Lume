"use client";

import { useState } from "react";
import { createClient } from "@/lib/supabase/client";
import { X, Loader2, DollarSign, Calendar, AlignLeft } from "lucide-react";

interface AddContributionModalProps {
    userId: string;
    investmentId: string;
    investmentName: string;
    isOpen: boolean;
    onClose: () => void;
    onSuccess: () => void;
}

export function AddContributionModal({
    userId,
    investmentId,
    investmentName,
    isOpen,
    onClose,
    onSuccess
}: AddContributionModalProps) {
    const supabase = createClient();
    const [loading, setLoading] = useState(false);
    const [formData, setFormData] = useState({
        amount: "",
        contribution_date: new Date().toISOString().split("T")[0],
        notes: ""
    });

    if (!isOpen) return null;

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);

        const amount = parseFloat(formData.amount.replace(",", ".")) || 0;

        // Insert contribution
        const { error: contribError } = await supabase.from("investment_contributions").insert({
            investment_id: investmentId,
            user_id: userId,
            amount: amount,
            contribution_date: formData.contribution_date,
            notes: formData.notes || null
        });

        if (contribError) {
            alert("Erro ao salvar aporte: " + contribError.message);
            setLoading(false);
            return;
        }

        // Update total invested_value on the investment
        const { data: contributions } = await supabase
            .from("investment_contributions")
            .select("amount")
            .eq("investment_id", investmentId);

        const totalInvested = contributions?.reduce((sum, c) => sum + Number(c.amount), 0) || 0;

        // Also fetch current_value to update it
        const { data: invData } = await supabase
            .from("investments")
            .select("current_value")
            .eq("id", investmentId)
            .single();

        const currentVal = Number(invData?.current_value) || 0;

        await supabase
            .from("investments")
            .update({
                invested_value: totalInvested,
                current_value: currentVal + amount
            })
            .eq("id", investmentId);

        setLoading(false);
        onSuccess();
        onClose();
        setFormData({
            amount: "",
            contribution_date: new Date().toISOString().split("T")[0],
            notes: ""
        });
    };

    return (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div className="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div className="p-6 border-b border-gray-100 flex justify-between items-center bg-gradient-to-r from-blue-50 to-emerald-50">
                    <div>
                        <h3 className="text-xl font-bold text-[#0b3680]">
                            Novo Aporte
                        </h3>
                        <p className="text-[12px] text-gray-500 mt-1">{investmentName}</p>
                    </div>
                    <button onClick={onClose} className="p-2 hover:bg-white/50 rounded-full transition-colors text-gray-500">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-6">
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <label className="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                <DollarSign className="w-3 h-3" /> Valor do Aporte
                            </label>
                            <div className="relative">
                                <span className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-medium">R$</span>
                                <input
                                    type="number"
                                    step="0.01"
                                    required
                                    value={formData.amount}
                                    onChange={e => setFormData({ ...formData, amount: e.target.value })}
                                    className="w-full pl-12 pr-4 py-4 rounded-xl border border-gray-200 focus:border-[#0b3680] outline-none text-2xl font-bold"
                                    placeholder="0,00"
                                    autoFocus
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <label className="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                <Calendar className="w-3 h-3" /> Data do Aporte
                            </label>
                            <input
                                type="date"
                                required
                                value={formData.contribution_date}
                                onChange={e => setFormData({ ...formData, contribution_date: e.target.value })}
                                className="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-[#0b3680] outline-none text-[14px] font-medium"
                            />
                        </div>

                        <div className="space-y-2">
                            <label className="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-2">
                                <AlignLeft className="w-3 h-3" /> Observação (opcional)
                            </label>
                            <input
                                type="text"
                                value={formData.notes}
                                onChange={e => setFormData({ ...formData, notes: e.target.value })}
                                className="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-[#0b3680] outline-none text-[14px] font-medium"
                                placeholder="Ex: Reinvestimento de dividendos"
                            />
                        </div>
                    </div>

                    <button
                        type="submit"
                        disabled={loading}
                        className="w-full py-4 rounded-xl font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition-all flex items-center justify-center gap-2 disabled:opacity-70 shadow-lg shadow-emerald-600/20"
                    >
                        {loading ? <Loader2 className="w-5 h-5 animate-spin" /> : "Registrar Aporte"}
                    </button>
                </form>
            </div>
        </div>
    );
}
