"use client";

import { useEffect, useState } from "react";
import { createClient } from "@/lib/supabase/client";
import { AddRecurringModal } from "@/components/dashboard/AddRecurringModal";
import { motion, AnimatePresence } from "framer-motion";
import {
    Plus, Search, Filter, ArrowUpRight, ArrowDownRight,
    Loader2, Trash2, Calendar, Tag, Repeat, ToggleLeft, ToggleRight
} from "lucide-react";

type RecurringTransaction = {
    id: string;
    description: string;
    amount: number;
    type: "income" | "expense";
    category: string;
    day_of_month: number;
    frequency: string;
    active: boolean;
};

export default function RecorrenciasPage() {
    const supabase = createClient();
    const [loading, setLoading] = useState(true);
    const [recurrings, setRecurrings] = useState<RecurringTransaction[]>([]);
    const [filteredRecurrings, setFilteredRecurrings] = useState<RecurringTransaction[]>([]);
    const [userId, setUserId] = useState<string | null>(null);
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);
    const [refreshTrigger, setRefreshTrigger] = useState(0);

    // Filters
    const [searchTerm, setSearchTerm] = useState("");
    const [filterType, setFilterType] = useState<"all" | "income" | "expense">("all");
    const [filterActive, setFilterActive] = useState<"all" | "active" | "inactive">("all");

    // Stats
    const [totalMonthlyIncome, setTotalMonthlyIncome] = useState(0);
    const [totalMonthlyExpense, setTotalMonthlyExpense] = useState(0);

    useEffect(() => {
        fetchRecurrings();
    }, [refreshTrigger]);

    useEffect(() => {
        applyFilters();
    }, [recurrings, searchTerm, filterType, filterActive]);

    const fetchRecurrings = async () => {
        const { data: { user } } = await supabase.auth.getUser();
        if (!user) return;
        setUserId(user.id);

        const { data, error } = await supabase
            .from("recurring_transactions")
            .select("*")
            .eq("user_id", user.id)
            .order("day_of_month", { ascending: true });

        if (data) {
            setRecurrings(data);

            // Calculate monthly totals (only active)
            let inc = 0, exp = 0;
            data.forEach(t => {
                if (t.active) {
                    if (t.type === "income") inc += Number(t.amount);
                    else exp += Number(t.amount);
                }
            });
            setTotalMonthlyIncome(inc);
            setTotalMonthlyExpense(exp);
        }

        setLoading(false);
    };

    const applyFilters = () => {
        let result = [...recurrings];

        // Search filter
        if (searchTerm) {
            result = result.filter(t =>
                t.description.toLowerCase().includes(searchTerm.toLowerCase()) ||
                t.category?.toLowerCase().includes(searchTerm.toLowerCase())
            );
        }

        // Type filter
        if (filterType !== "all") {
            result = result.filter(t => t.type === filterType);
        }

        // Active filter
        if (filterActive === "active") {
            result = result.filter(t => t.active);
        } else if (filterActive === "inactive") {
            result = result.filter(t => !t.active);
        }

        setFilteredRecurrings(result);
    };

    const handleDelete = async (id: string) => {
        if (!confirm("Tem certeza que deseja remover essa recorrência?")) return;

        const { error } = await supabase.from("recurring_transactions").delete().eq("id", id);
        if (!error) {
            setRefreshTrigger(prev => prev + 1);
        }
    };

    const handleToggleActive = async (id: string, currentActive: boolean) => {
        const { error } = await supabase
            .from("recurring_transactions")
            .update({ active: !currentActive })
            .eq("id", id);

        if (!error) {
            setRefreshTrigger(prev => prev + 1);
        }
    };

    const formatCurrency = (value: number) => {
        return value.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const getFrequencyLabel = (freq: string) => {
        switch (freq) {
            case "weekly": return "Semanal";
            case "monthly": return "Mensal";
            case "yearly": return "Anual";
            default: return "Mensal";
        }
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
                        Recorrências
                    </h1>
                    <p className="text-[12px] font-medium text-gray-400 mt-2 tracking-wide">
                        Gerencie suas contas fixas e assinaturas
                    </p>
                </div>
                <button
                    onClick={() => setIsAddModalOpen(true)}
                    className="flex items-center gap-2 bg-[#0b3680] text-white px-6 py-3 rounded-xl font-semibold text-[14px] hover:bg-[#092a66] transition-all shadow-lg shadow-[#0b3680]/20"
                >
                    <Plus className="w-5 h-5" />
                    Nova Recorrência
                </button>
            </div>

            {/* Stats Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <p className="text-[12px] font-semibold text-gray-400 uppercase tracking-[0.5px] mb-2">Receitas Mensais Fixas</p>
                    <p className="text-[28px] font-extrabold text-emerald-600 tracking-[-1px]">
                        R$ {formatCurrency(totalMonthlyIncome)}
                    </p>
                </div>
                <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <p className="text-[12px] font-semibold text-gray-400 uppercase tracking-[0.5px] mb-2">Despesas Mensais Fixas</p>
                    <p className="text-[28px] font-extrabold text-red-500 tracking-[-1px]">
                        R$ {formatCurrency(totalMonthlyExpense)}
                    </p>
                </div>
                <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <p className="text-[12px] font-semibold text-gray-400 uppercase tracking-[0.5px] mb-2">Impacto Mensal</p>
                    <p className={`text-[28px] font-extrabold tracking-[-1px] ${totalMonthlyIncome - totalMonthlyExpense >= 0 ? "text-emerald-600" : "text-red-500"}`}>
                        R$ {formatCurrency(totalMonthlyIncome - totalMonthlyExpense)}
                    </p>
                </div>
            </div>

            {/* Filters */}
            <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <div className="flex flex-col md:flex-row gap-4">
                    {/* Search */}
                    <div className="flex-1 relative">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Buscar por descrição ou categoria..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="w-full pl-12 pr-4 py-3 rounded-xl border border-gray-200 focus:border-[#0b3680] focus:ring-2 focus:ring-[#0b3680]/10 outline-none text-[14px] font-medium"
                        />
                    </div>

                    {/* Type Filter */}
                    <div className="flex items-center gap-2">
                        <Filter className="w-5 h-5 text-gray-400" />
                        <select
                            value={filterType}
                            onChange={(e) => setFilterType(e.target.value as "all" | "income" | "expense")}
                            className="px-4 py-3 rounded-xl border border-gray-200 focus:border-[#0b3680] outline-none text-[14px] font-medium bg-white"
                        >
                            <option value="all">Todos os Tipos</option>
                            <option value="income">Receitas</option>
                            <option value="expense">Despesas</option>
                        </select>
                    </div>

                    {/* Active Filter */}
                    <div className="flex items-center gap-2">
                        <Repeat className="w-5 h-5 text-gray-400" />
                        <select
                            value={filterActive}
                            onChange={(e) => setFilterActive(e.target.value as "all" | "active" | "inactive")}
                            className="px-4 py-3 rounded-xl border border-gray-200 focus:border-[#0b3680] outline-none text-[14px] font-medium bg-white"
                        >
                            <option value="all">Todas</option>
                            <option value="active">Ativas</option>
                            <option value="inactive">Pausadas</option>
                        </select>
                    </div>
                </div>
            </div>

            {/* Recurrings List */}
            <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                {/* Table Header */}
                <div className="grid grid-cols-12 gap-4 px-6 py-4 bg-gray-50 border-b border-gray-100 text-[12px] font-semibold text-gray-500 uppercase tracking-[0.5px]">
                    <div className="col-span-1">Status</div>
                    <div className="col-span-1">Dia</div>
                    <div className="col-span-3">Descrição</div>
                    <div className="col-span-2">Categoria</div>
                    <div className="col-span-2">Frequência</div>
                    <div className="col-span-2 text-right">Valor</div>
                    <div className="col-span-1 text-right">Ações</div>
                </div>

                {/* Table Body */}
                <AnimatePresence>
                    {filteredRecurrings.length === 0 ? (
                        <div className="px-6 py-12 text-center text-gray-400">
                            <Repeat className="w-12 h-12 mx-auto mb-3 opacity-20" />
                            <p className="text-[14px] font-medium">Nenhuma recorrência encontrada</p>
                            <button
                                onClick={() => setIsAddModalOpen(true)}
                                className="text-[#0b3680] font-semibold mt-2 text-sm"
                            >
                                Adicionar primeira recorrência
                            </button>
                        </div>
                    ) : (
                        filteredRecurrings.map((recurring, index) => (
                            <motion.div
                                key={recurring.id}
                                initial={{ opacity: 0, y: 10 }}
                                animate={{ opacity: 1, y: 0 }}
                                exit={{ opacity: 0, y: -10 }}
                                transition={{ delay: index * 0.03 }}
                                className={`grid grid-cols-12 gap-4 px-6 py-4 border-b border-gray-50 hover:bg-gray-50/50 transition-colors items-center ${!recurring.active ? "opacity-50" : ""}`}
                            >
                                {/* Toggle Active */}
                                <div className="col-span-1">
                                    <button
                                        onClick={() => handleToggleActive(recurring.id, recurring.active)}
                                        className={`p-1 rounded-lg transition-colors ${recurring.active ? "text-emerald-500 hover:bg-emerald-50" : "text-gray-400 hover:bg-gray-100"}`}
                                        title={recurring.active ? "Ativo - Clique para pausar" : "Pausado - Clique para ativar"}
                                    >
                                        {recurring.active ? (
                                            <ToggleRight className="w-6 h-6" />
                                        ) : (
                                            <ToggleLeft className="w-6 h-6" />
                                        )}
                                    </button>
                                </div>

                                {/* Day */}
                                <div className="col-span-1">
                                    <div className={`w-10 h-10 rounded-xl flex items-center justify-center font-bold text-sm ${recurring.type === "income"
                                            ? "bg-emerald-100 text-emerald-600"
                                            : "bg-red-100 text-red-500"
                                        }`}>
                                        {recurring.day_of_month}
                                    </div>
                                </div>

                                {/* Description */}
                                <div className="col-span-3">
                                    <div className="flex items-center gap-2">
                                        {recurring.type === "income" ? (
                                            <ArrowUpRight className="w-4 h-4 text-emerald-500" />
                                        ) : (
                                            <ArrowDownRight className="w-4 h-4 text-red-500" />
                                        )}
                                        <p className="text-[14px] font-semibold text-gray-900 truncate">
                                            {recurring.description}
                                        </p>
                                    </div>
                                </div>

                                {/* Category */}
                                <div className="col-span-2">
                                    <span className="inline-block px-3 py-1 rounded-full bg-gray-100 text-gray-600 text-[12px] font-medium">
                                        {recurring.category || "Sem categoria"}
                                    </span>
                                </div>

                                {/* Frequency */}
                                <div className="col-span-2 flex items-center gap-2 text-gray-500">
                                    <Calendar className="w-4 h-4" />
                                    <span className="text-[13px] font-medium">
                                        {getFrequencyLabel(recurring.frequency)}
                                    </span>
                                </div>

                                {/* Amount */}
                                <div className="col-span-2 text-right">
                                    <span className={`text-[16px] font-bold ${recurring.type === "income" ? "text-emerald-600" : "text-red-500"
                                        }`}>
                                        {recurring.type === "income" ? "+" : "-"} R$ {formatCurrency(Number(recurring.amount))}
                                    </span>
                                </div>

                                {/* Actions */}
                                <div className="col-span-1 text-right">
                                    <button
                                        onClick={() => handleDelete(recurring.id)}
                                        className="p-2 rounded-lg hover:bg-red-100 text-gray-400 hover:text-red-500 transition-colors"
                                    >
                                        <Trash2 className="w-4 h-4" />
                                    </button>
                                </div>
                            </motion.div>
                        ))
                    )}
                </AnimatePresence>
            </div>

            {/* Info Card */}
            <div className="bg-blue-50 border border-blue-100 rounded-2xl p-6">
                <div className="flex items-start gap-4">
                    <div className="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center shrink-0">
                        <Repeat className="w-5 h-5 text-[#0b3680]" />
                    </div>
                    <div>
                        <h4 className="text-[14px] font-semibold text-[#0b3680] mb-1">Como funcionam as recorrências?</h4>
                        <p className="text-[13px] text-gray-600 leading-relaxed">
                            As recorrências são cobranças ou receitas que se repetem automaticamente.
                            Configure o dia do mês em que elas ocorrem e o sistema irá considerar esses valores
                            em seus cálculos mensais. Você pode pausar temporariamente uma recorrência sem excluí-la.
                        </p>
                    </div>
                </div>
            </div>

            {/* Add Modal */}
            {userId && (
                <AddRecurringModal
                    userId={userId}
                    isOpen={isAddModalOpen}
                    onClose={() => setIsAddModalOpen(false)}
                    onSuccess={() => setRefreshTrigger(prev => prev + 1)}
                />
            )}
        </div>
    );
}
