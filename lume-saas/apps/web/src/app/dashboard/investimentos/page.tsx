"use client";

import { useEffect, useState } from "react";
import { createClient } from "@/lib/supabase/client";
import { AddInvestmentModal } from "@/components/dashboard/AddInvestmentModal";
import { AddContributionModal } from "@/components/dashboard/AddContributionModal";
import { motion, AnimatePresence } from "framer-motion";
import {
    Plus, Search, TrendingUp, TrendingDown,
    Loader2, Trash2, Edit3, PiggyBank, BarChart3,
    ChevronDown, ChevronUp, Calendar, DollarSign
} from "lucide-react";

type Contribution = {
    id: string;
    amount: number;
    contribution_date: string;
    notes: string | null;
};

type Investment = {
    id: string;
    name: string;
    type: string;
    invested_value: number;
    current_value: number;
    active: boolean;
    start_date: string;
    last_update: string;
    contributions?: Contribution[];
};

export default function InvestimentosPage() {
    const supabase = createClient();
    const [loading, setLoading] = useState(true);
    const [investments, setInvestments] = useState<Investment[]>([]);
    const [filteredInvestments, setFilteredInvestments] = useState<Investment[]>([]);
    const [userId, setUserId] = useState<string | null>(null);
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);
    const [refreshTrigger, setRefreshTrigger] = useState(0);
    const [editingId, setEditingId] = useState<string | null>(null);
    const [editValue, setEditValue] = useState("");
    const [editDate, setEditDate] = useState("");
    const [expandedId, setExpandedId] = useState<string | null>(null);

    // Contribution modal
    const [contributionModal, setContributionModal] = useState<{
        isOpen: boolean;
        investmentId: string;
        investmentName: string;
    }>({ isOpen: false, investmentId: "", investmentName: "" });

    // Filters
    const [searchTerm, setSearchTerm] = useState("");
    const [filterType, setFilterType] = useState<string>("all");

    // Stats
    const [totalInvested, setTotalInvested] = useState(0);
    const [totalCurrentValue, setTotalCurrentValue] = useState(0);
    const [totalReturn, setTotalReturn] = useState(0);
    const [returnPercentage, setReturnPercentage] = useState(0);

    const [investmentTypes, setInvestmentTypes] = useState<string[]>([]);

    useEffect(() => {
        fetchInvestments();
    }, [refreshTrigger]);

    useEffect(() => {
        applyFilters();
    }, [investments, searchTerm, filterType]);

    const fetchInvestments = async () => {
        const { data: { user } } = await supabase.auth.getUser();
        if (!user) return;
        setUserId(user.id);

        // Fetch investments
        const { data: invData } = await supabase
            .from("investments")
            .select("*")
            .eq("user_id", user.id)
            .order("current_value", { ascending: false });

        if (!invData) {
            setLoading(false);
            return;
        }

        // Fetch contributions for each investment
        const { data: contribData } = await supabase
            .from("investment_contributions")
            .select("*")
            .eq("user_id", user.id)
            .order("contribution_date", { ascending: false });

        // Map contributions to investments
        const investmentsWithContribs = invData.map(inv => ({
            ...inv,
            contributions: contribData?.filter(c => c.investment_id === inv.id) || []
        }));

        setInvestments(investmentsWithContribs);

        // Extract unique types
        const types = [...new Set(invData.map(i => i.type).filter(Boolean))];
        setInvestmentTypes(types);

        // Calculate stats (only active)
        let invested = 0, current = 0;
        investmentsWithContribs.forEach(inv => {
            if (inv.active) {
                invested += Number(inv.invested_value) || 0;
                current += Number(inv.current_value) || 0;
            }
        });

        const returnVal = current - invested;
        const returnPct = invested > 0 ? (returnVal / invested) * 100 : 0;

        setTotalInvested(invested);
        setTotalCurrentValue(current);
        setTotalReturn(returnVal);
        setReturnPercentage(returnPct);

        setLoading(false);
    };

    const applyFilters = () => {
        let result = [...investments];

        if (searchTerm) {
            result = result.filter(inv =>
                inv.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                inv.type?.toLowerCase().includes(searchTerm.toLowerCase())
            );
        }

        if (filterType !== "all") {
            result = result.filter(inv => inv.type === filterType);
        }

        setFilteredInvestments(result);
    };

    const handleDelete = async (id: string) => {
        if (!confirm("Tem certeza que deseja remover esse investimento e todos os seus aportes?")) return;

        await supabase.from("investments").delete().eq("id", id);
        setRefreshTrigger(prev => prev + 1);
    };

    const handleDeleteContribution = async (id: string, investmentId: string) => {
        if (!confirm("Remover este aporte?")) return;

        await supabase.from("investment_contributions").delete().eq("id", id);

        // Recalculate total invested
        const { data: contributions } = await supabase
            .from("investment_contributions")
            .select("amount")
            .eq("investment_id", investmentId);

        const totalInvested = contributions?.reduce((sum, c) => sum + Number(c.amount), 0) || 0;

        await supabase
            .from("investments")
            .update({ invested_value: totalInvested })
            .eq("id", investmentId);

        setRefreshTrigger(prev => prev + 1);
    };

    const handleUpdateValue = async (id: string) => {
        const newValue = parseFloat(editValue.replace(",", "."));
        if (isNaN(newValue)) return;

        // Update Investment
        await supabase
            .from("investments")
            .update({
                current_value: newValue,
                last_update: new Date().toISOString().split("T")[0],
                start_date: editDate // Update start date
            })
            .eq("id", id);

        // Update First Contribution Date (to align with start date change)
        // We find the earliest contribution and update it.
        const { data: contribs } = await supabase
            .from("investment_contributions")
            .select("id")
            .eq("investment_id", id)
            .order("contribution_date", { ascending: true })
            .limit(1);

        if (contribs && contribs.length > 0) {
            await supabase
                .from("investment_contributions")
                .update({ contribution_date: editDate })
                .eq("id", contribs[0].id);
        }

        setEditingId(null);
        setEditValue("");
        setEditDate("");
        setRefreshTrigger(prev => prev + 1);
    };

    const formatCurrency = (value: number) => {
        return value.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const formatDate = (dateStr: string) => {
        if (!dateStr) return "-";
        return new Date(dateStr + "T00:00:00").toLocaleDateString("pt-BR", {
            day: "2-digit",
            month: "short",
            year: "numeric"
        });
    };

    const getInvestmentReturn = (inv: Investment) => {
        const invested = Number(inv.invested_value) || 0;
        const current = Number(inv.current_value) || 0;
        const returnVal = current - invested;

        // Modified Dietz Method (Returns % Weighted by Time)
        // Helps prevent "new money" from heavily diluting the percentage
        if (inv.contributions && inv.contributions.length > 0) {
            const today = new Date().getTime();

            // Find Start Date (Earliest Contribution)
            const dates = inv.contributions.map(c => new Date(c.contribution_date + "T00:00:00").getTime());
            const startDate = Math.min(...dates);
            const totalDuration = today - startDate;

            // Only apply if duration is meaningful (> 1 day)
            if (totalDuration > 86400000) {
                let weightedInvested = 0;
                inv.contributions.forEach(c => {
                    const cDate = new Date(c.contribution_date + "T00:00:00").getTime();
                    // Duration this money has been in the investment
                    const cDuration = Math.max(0, today - cDate);
                    const weight = cDuration / totalDuration;
                    weightedInvested += Number(c.amount) * weight;
                });

                if (weightedInvested > 0) {
                    const dietzReturn = (returnVal / weightedInvested) * 100;
                    return { returnVal, returnPct: dietzReturn };
                }
            }
        }

        const returnPct = invested > 0 ? (returnVal / invested) * 100 : 0;
        return { returnVal, returnPct };
    };

    // Calculate days since first contribution
    const getDaysSinceStart = (inv: Investment) => {
        if (!inv.contributions || inv.contributions.length === 0) return 0;
        const dates = inv.contributions.map(c => new Date(c.contribution_date + "T00:00:00"));
        const firstDate = new Date(Math.min(...dates.map(d => d.getTime())));
        const today = new Date();
        return Math.floor((today.getTime() - firstDate.getTime()) / (1000 * 60 * 60 * 24));
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center h-[50vh]">
                <Loader2 className="w-8 h-8 animate-spin text-[#0b3680]" />
            </div>
        );
    }

    return (
        <div className="space-y-8 font-[Inter]">
            {/* Header */}
            <div className="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                <div>
                    <h1 className="text-[32px] font-bold text-[#0b3680] tracking-tight">
                        Investimentos
                    </h1>
                    <p className="text-[12px] font-medium text-gray-400 mt-2 tracking-wide">
                        Acompanhe sua carteira e aportes
                    </p>
                </div>
                <button
                    onClick={() => setIsAddModalOpen(true)}
                    className="flex items-center gap-2 bg-[#0b3680] text-white px-6 py-3 rounded-xl font-semibold text-[14px] hover:bg-[#092a66] transition-all shadow-lg shadow-[#0b3680]/20"
                >
                    <Plus className="w-5 h-5" />
                    Novo Investimento
                </button>
            </div>

            {/* Stats Cards */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <p className="text-[12px] font-semibold text-gray-400 uppercase tracking-[0.5px] mb-2">Total Investido</p>
                    <p className="text-[28px] font-extrabold text-gray-900 tracking-[-1px]">
                        R$ {formatCurrency(totalInvested)}
                    </p>
                </div>
                <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <p className="text-[12px] font-semibold text-gray-400 uppercase tracking-[0.5px] mb-2">Valor Atual</p>
                    <p className="text-[28px] font-extrabold text-gray-900 tracking-[-1px]">
                        R$ {formatCurrency(totalCurrentValue)}
                    </p>
                </div>
                <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <p className="text-[12px] font-semibold text-gray-400 uppercase tracking-[0.5px] mb-2">Rendimento</p>
                    <p className={`text-[28px] font-extrabold tracking-[-1px] ${totalReturn >= 0 ? "text-emerald-600" : "text-red-500"}`}>
                        {totalReturn >= 0 ? "+" : ""}R$ {formatCurrency(totalReturn)}
                    </p>
                </div>
                <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <p className="text-[12px] font-semibold text-gray-400 uppercase tracking-[0.5px] mb-2">Rentabilidade</p>
                    <p className={`text-[28px] font-extrabold tracking-[-1px] ${returnPercentage >= 0 ? "text-emerald-600" : "text-red-500"}`}>
                        {returnPercentage >= 0 ? "+" : ""}{returnPercentage.toFixed(2)}%
                    </p>
                </div>
            </div>

            {/* Filters */}
            <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <div className="flex flex-col md:flex-row gap-4">
                    <div className="flex-1 relative">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Buscar por nome ou tipo..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="w-full pl-12 pr-4 py-3 rounded-xl border border-gray-200 focus:border-[#0b3680] focus:ring-2 focus:ring-[#0b3680]/10 outline-none text-[14px] font-medium"
                        />
                    </div>

                    <div className="flex items-center gap-2">
                        <BarChart3 className="w-5 h-5 text-gray-400" />
                        <select
                            value={filterType}
                            onChange={(e) => setFilterType(e.target.value)}
                            className="px-4 py-3 rounded-xl border border-gray-200 focus:border-[#0b3680] outline-none text-[14px] font-medium bg-white"
                        >
                            <option value="all">Todos os Tipos</option>
                            {investmentTypes.map((type) => (
                                <option key={type} value={type}>{type}</option>
                            ))}
                        </select>
                    </div>
                </div>
            </div>

            {/* Investments List */}
            <div className="space-y-4">
                <AnimatePresence>
                    {filteredInvestments.length === 0 ? (
                        <div className="bg-white rounded-2xl border border-gray-100 shadow-sm px-6 py-12 text-center text-gray-400">
                            <PiggyBank className="w-12 h-12 mx-auto mb-3 opacity-20" />
                            <p className="text-[14px] font-medium">Nenhum investimento encontrado</p>
                            <button
                                onClick={() => setIsAddModalOpen(true)}
                                className="text-[#0b3680] font-semibold mt-2 text-sm"
                            >
                                Adicionar primeiro investimento
                            </button>
                        </div>
                    ) : (
                        filteredInvestments.map((investment, index) => {
                            const { returnVal, returnPct } = getInvestmentReturn(investment);
                            const isPositive = returnVal >= 0;
                            const isExpanded = expandedId === investment.id;
                            const daysSinceStart = getDaysSinceStart(investment);

                            return (
                                <motion.div
                                    key={investment.id}
                                    initial={{ opacity: 0, y: 20 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    exit={{ opacity: 0, scale: 0.95 }}
                                    transition={{ delay: index * 0.05 }}
                                    className={`bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden ${!investment.active ? "opacity-60" : ""}`}
                                >
                                    {/* Main Row */}
                                    <div className="p-6">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-4">
                                                <div className={`w-12 h-12 rounded-xl flex items-center justify-center ${isPositive ? "bg-emerald-100" : "bg-red-100"
                                                    }`}>
                                                    {isPositive ? (
                                                        <TrendingUp className="w-6 h-6 text-emerald-600" />
                                                    ) : (
                                                        <TrendingDown className="w-6 h-6 text-red-500" />
                                                    )}
                                                </div>
                                                <div>
                                                    <p className="text-[16px] font-bold text-gray-900">{investment.name}</p>
                                                    <div className="flex items-center gap-2 mt-1">
                                                        <span className="px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-[11px] font-medium">
                                                            {investment.type}
                                                        </span>
                                                        {daysSinceStart > 0 && (
                                                            <span className="text-[11px] text-gray-400">
                                                                {daysSinceStart} dias
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-6">
                                                <div className="text-right">
                                                    <p className="text-[10px] font-semibold text-gray-400 uppercase">Investido</p>
                                                    <p className="text-[16px] font-bold text-gray-700">
                                                        R$ {formatCurrency(Number(investment.invested_value) || 0)}
                                                    </p>

                                                    {editingId === investment.id ? (
                                                        <input
                                                            type="date"
                                                            value={editDate}
                                                            onChange={(e) => setEditDate(e.target.value)}
                                                            className="text-[10px] text-gray-500 mt-1 border border-gray-200 rounded px-1"
                                                        />
                                                    ) : (
                                                        <p className="text-[10px] text-gray-400 mt-1">
                                                            Em {formatDate(investment.start_date)}
                                                        </p>
                                                    )}
                                                </div>

                                                <div className="text-right">
                                                    <p className="text-[10px] font-semibold text-gray-400 uppercase">Valor Atual</p>
                                                    {editingId === investment.id ? (
                                                        <input
                                                            type="number"
                                                            step="0.01"
                                                            value={editValue}
                                                            onChange={(e) => setEditValue(e.target.value)}
                                                            className="w-32 px-2 py-1 text-[14px] font-bold border border-gray-200 rounded-lg focus:border-[#0b3680] outline-none text-right"
                                                            autoFocus
                                                            onKeyDown={(e) => {
                                                                if (e.key === "Enter") handleUpdateValue(investment.id);
                                                                if (e.key === "Escape") { setEditingId(null); setEditValue(""); setEditDate(""); }
                                                            }}
                                                        // onBlur commented out to avoid conflict with date input
                                                        />
                                                    ) : (
                                                        <>
                                                            <p className="text-[16px] font-bold text-gray-900">
                                                                R$ {formatCurrency(Number(investment.current_value) || 0)}
                                                            </p>
                                                            <p className="text-[10px] text-gray-400 mt-1">
                                                                Atualizado em {formatDate(investment.last_update)}
                                                            </p>
                                                        </>
                                                    )}
                                                </div>

                                                <div className={`text-right px-3 py-2 rounded-xl ${isPositive ? "bg-emerald-50" : "bg-red-50"
                                                    }`}>
                                                    <p className={`text-[14px] font-bold ${isPositive ? "text-emerald-600" : "text-red-500"}`}>
                                                        {isPositive ? "+" : ""}{returnPct.toFixed(2)}%
                                                    </p>
                                                    <p className={`text-[12px] font-medium ${isPositive ? "text-emerald-600" : "text-red-500"}`}>
                                                        {isPositive ? "+" : ""}R$ {formatCurrency(returnVal)}
                                                    </p>
                                                </div>

                                                <div className="flex items-center gap-1">
                                                    <button
                                                        onClick={() => setContributionModal({
                                                            isOpen: true,
                                                            investmentId: investment.id,
                                                            investmentName: investment.name
                                                        })}
                                                        className="p-2 rounded-lg hover:bg-emerald-50 text-gray-400 hover:text-emerald-600 transition-colors"
                                                        title="Adicionar aporte"
                                                    >
                                                        <Plus className="w-5 h-5" />
                                                    </button>
                                                    <button
                                                        onClick={() => {
                                                            setEditingId(investment.id);
                                                            setEditValue(String(investment.current_value));
                                                            setEditDate(investment.start_date || new Date().toISOString().split("T")[0]);
                                                        }}
                                                        className="p-2 rounded-lg hover:bg-blue-50 text-gray-400 hover:text-[#0b3680] transition-colors"
                                                        title="Editar"
                                                    >
                                                        <Edit3 className="w-4 h-4" />
                                                    </button>
                                                    <button
                                                        onClick={() => setExpandedId(isExpanded ? null : investment.id)}
                                                        className="p-2 rounded-lg hover:bg-gray-100 text-gray-400 transition-colors"
                                                        title="Ver aportes"
                                                    >
                                                        {isExpanded ? <ChevronUp className="w-5 h-5" /> : <ChevronDown className="w-5 h-5" />}
                                                    </button>
                                                    <button
                                                        onClick={() => handleDelete(investment.id)}
                                                        className="p-2 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-500 transition-colors"
                                                        title="Remover"
                                                    >
                                                        <Trash2 className="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Contributions Panel (Expanded) */}
                                    <AnimatePresence>
                                        {isExpanded && (
                                            <motion.div
                                                initial={{ height: 0, opacity: 0 }}
                                                animate={{ height: "auto", opacity: 1 }}
                                                exit={{ height: 0, opacity: 0 }}
                                                className="border-t border-gray-100 bg-gray-50"
                                            >
                                                <div className="p-6">
                                                    <div className="flex items-center justify-between mb-4">
                                                        <h4 className="text-[12px] font-semibold text-gray-500 uppercase tracking-wider">
                                                            Histórico de Aportes
                                                        </h4>
                                                        <button
                                                            onClick={() => setContributionModal({
                                                                isOpen: true,
                                                                investmentId: investment.id,
                                                                investmentName: investment.name
                                                            })}
                                                            className="text-[12px] font-semibold text-[#0b3680] hover:underline"
                                                        >
                                                            + Novo Aporte
                                                        </button>
                                                    </div>

                                                    {investment.contributions && investment.contributions.length > 0 ? (
                                                        <div className="space-y-2">
                                                            {investment.contributions.map((contrib) => (
                                                                <div
                                                                    key={contrib.id}
                                                                    className="flex items-center justify-between p-3 bg-white rounded-xl border border-gray-100"
                                                                >
                                                                    <div className="flex items-center gap-3">
                                                                        <div className="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center">
                                                                            <DollarSign className="w-4 h-4 text-emerald-600" />
                                                                        </div>
                                                                        <div>
                                                                            <p className="text-[14px] font-semibold text-gray-900">
                                                                                R$ {formatCurrency(Number(contrib.amount))}
                                                                            </p>
                                                                            {contrib.notes && (
                                                                                <p className="text-[11px] text-gray-400">{contrib.notes}</p>
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                    <div className="flex items-center gap-3">
                                                                        <div className="flex items-center gap-1 text-gray-400">
                                                                            <Calendar className="w-3 h-3" />
                                                                            <span className="text-[12px]">{formatDate(contrib.contribution_date)}</span>
                                                                        </div>
                                                                        <button
                                                                            onClick={() => handleDeleteContribution(contrib.id, investment.id)}
                                                                            className="p-1.5 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-500 transition-colors"
                                                                        >
                                                                            <Trash2 className="w-3 h-3" />
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    ) : (
                                                        <p className="text-[13px] text-gray-400 text-center py-4">
                                                            Nenhum aporte registrado ainda.
                                                        </p>
                                                    )}
                                                </div>
                                            </motion.div>
                                        )}
                                    </AnimatePresence>
                                </motion.div>
                            );
                        })
                    )}
                </AnimatePresence>
            </div>

            {/* Modals */}
            {userId && (
                <AddInvestmentModal
                    userId={userId}
                    isOpen={isAddModalOpen}
                    onClose={() => setIsAddModalOpen(false)}
                    onSuccess={() => setRefreshTrigger(prev => prev + 1)}
                />
            )}
            {userId && (
                <AddContributionModal
                    userId={userId}
                    investmentId={contributionModal.investmentId}
                    investmentName={contributionModal.investmentName}
                    isOpen={contributionModal.isOpen}
                    onClose={() => setContributionModal({ isOpen: false, investmentId: "", investmentName: "" })}
                    onSuccess={() => setRefreshTrigger(prev => prev + 1)}
                />
            )}
        </div>
    );
}
