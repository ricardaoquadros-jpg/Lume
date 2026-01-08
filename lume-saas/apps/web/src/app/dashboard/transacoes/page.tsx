"use client";

import { useEffect, useState } from "react";
import { createClient } from "@/lib/supabase/client";
import { AddTransactionModal } from "@/components/dashboard/AddTransactionModal";
import { motion, AnimatePresence } from "framer-motion";
import {
    Plus, Search, Filter, ArrowUpRight, ArrowDownRight,
    Loader2, Trash2, Pencil, Calendar, Tag, ChevronLeft, ChevronRight, RefreshCw, Mic, ChevronUp, ChevronDown
} from "lucide-react";

type Transaction = {
    id: string;
    description: string;
    amount: number;
    type: "income" | "expense";
    category: string;
    transaction_date: string;
    transcription?: string;
    display_order?: number;
    running_balance?: number; // Calculated field for display
};

export default function TransacoesPage() {
    const supabase = createClient();
    const [loading, setLoading] = useState(true);
    const [transactions, setTransactions] = useState<Transaction[]>([]);
    const [filteredTransactions, setFilteredTransactions] = useState<Transaction[]>([]);
    const [userId, setUserId] = useState<string | null>(null);
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);
    const [refreshTrigger, setRefreshTrigger] = useState(0);
    const [editingTransaction, setEditingTransaction] = useState<Transaction | null>(null);
    const [expandedTranscriptionId, setExpandedTranscriptionId] = useState<string | null>(null);

    // Filters
    const [searchTerm, setSearchTerm] = useState("");
    const [filterType, setFilterType] = useState<"all" | "income" | "expense">("all");
    const [filterCategory, setFilterCategory] = useState<string>("all");
    const [categories, setCategories] = useState<string[]>([]);

    // Pagination
    const [currentPage, setCurrentPage] = useState(1);
    const itemsPerPage = 15;

    // Stats
    const [totalIncome, setTotalIncome] = useState(0);
    const [totalExpense, setTotalExpense] = useState(0);

    // Old useEffect replaced by the new one above
    useEffect(() => {
        fetchTransactions();

        const handleUpdate = () => {
            console.log("Refreshing transactions data...");
            fetchTransactions();
            setRefreshTrigger(prev => prev + 1);
        };

        window.addEventListener("transaction-updated", handleUpdate);

        return () => {
            window.removeEventListener("transaction-updated", handleUpdate);
        };
    }, [refreshTrigger]);

    useEffect(() => {
        applyFilters();
    }, [transactions, searchTerm, filterType, filterCategory]);

    const fetchTransactions = async () => {
        const { data: { user } } = await supabase.auth.getUser();
        if (!user) return;
        setUserId(user.id);

        const { data: profile } = await supabase
            .from("work_profiles")
            .select("initial_balance, created_at")
            .eq("user_id", user.id)
            .single();

        const { data, error } = await supabase
            .from("transactions")
            .select("*")
            .eq("user_id", user.id)
            .order("transaction_date", { ascending: false });

        if (data) {
            let allTransactions = [...data];

            if (profile && Number(profile.initial_balance) !== 0) {
                const initialTx: Transaction = {
                    id: "initial_balance",
                    description: "Saldo Inicial (Conta)",
                    amount: Number(profile.initial_balance),
                    type: "income",
                    category: "Saldo",
                    transaction_date: (() => {
                        const d = new Date(profile.created_at);
                        const localDate = new Date(d.getTime() - d.getTimezoneOffset() * 60000);
                        return localDate.toISOString().split("T")[0];
                    })(),
                };
                allTransactions.push(initialTx);
            }

            // Sort by date descending, then by display_order ascending within the same date
            allTransactions.sort((a, b) => {
                if (a.id === "initial_balance") return 1;
                if (b.id === "initial_balance") return -1;
                const dateA = new Date(a.transaction_date).getTime();
                const dateB = new Date(b.transaction_date).getTime();
                if (dateB !== dateA) return dateB - dateA;
                // Same date: sort by display_order ascending
                return (a.display_order || 0) - (b.display_order || 0);
            });

            // Calculate running balance (from oldest to newest)
            const sortedForBalance = [...allTransactions].reverse();
            let cumulativeBalance = 0;
            const balanceMap = new Map<string, number>();
            sortedForBalance.forEach(t => {
                if (t.type === "income") cumulativeBalance += Number(t.amount);
                else cumulativeBalance -= Number(t.amount);
                balanceMap.set(t.id, cumulativeBalance);
            });

            // Assign running_balance to each transaction
            allTransactions = allTransactions.map(t => ({
                ...t,
                running_balance: balanceMap.get(t.id) || 0
            }));

            setTransactions(allTransactions);

            // Extract unique categories
            const uniqueCategories = [...new Set(allTransactions.map(t => t.category).filter(Boolean))];
            setCategories(uniqueCategories);

            // Calculate totals
            let inc = 0, exp = 0;
            allTransactions.forEach(t => {
                if (t.type === "income") inc += Number(t.amount);
                else exp += Number(t.amount);
            });
            setTotalIncome(inc);
            setTotalExpense(exp);
        }

        setLoading(false);
    };

    const applyFilters = () => {
        let result = [...transactions];

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

        // Category filter
        if (filterCategory !== "all") {
            result = result.filter(t => t.category === filterCategory);
        }

        setFilteredTransactions(result);
        setCurrentPage(1);
    };

    const handleDelete = async (id: string) => {
        if (id === "initial_balance") {
            alert("O saldo inicial deve ser editado nas configurações.");
            return;
        }

        const { error } = await supabase.from("transactions").delete().eq("id", id);
        if (!error) {
            setRefreshTrigger(prev => prev + 1);
        }
    };

    // Helper to get transactions on the same date
    const getSameDayTransactions = (txDate: string) => {
        return transactions.filter(t => t.transaction_date === txDate && t.id !== "initial_balance");
    };

    // Handle reordering transactions within the same day
    const handleReorder = async (transaction: Transaction, direction: "up" | "down") => {
        const sameDayTxs = getSameDayTransactions(transaction.transaction_date);
        if (sameDayTxs.length < 2) return;

        // Sort by display_order, then by id as tiebreaker for consistent ordering
        sameDayTxs.sort((a, b) => {
            const orderDiff = (a.display_order || 0) - (b.display_order || 0);
            if (orderDiff !== 0) return orderDiff;
            return a.id.localeCompare(b.id); // Stable tiebreaker
        });

        const currentIndex = sameDayTxs.findIndex(t => t.id === transaction.id);
        const swapIndex = direction === "up" ? currentIndex - 1 : currentIndex + 1;
        if (swapIndex < 0 || swapIndex >= sameDayTxs.length) return;

        // Normalize: assign sequential display_order to all same-day transactions
        const updates: Promise<any>[] = [];
        sameDayTxs.forEach((tx, idx) => {
            // Swap positions for current and swap transactions
            let newOrder = idx;
            if (idx === currentIndex) newOrder = swapIndex;
            else if (idx === swapIndex) newOrder = currentIndex;

            updates.push(
                supabase.from("transactions").update({ display_order: newOrder }).eq("id", tx.id)
            );
        });

        await Promise.all(updates);
        setRefreshTrigger(prev => prev + 1);
    };


    const formatCurrency = (value: number) => {
        return value.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const formatDate = (dateString: string) => {
        const date = new Date(dateString + "T00:00:00");
        return date.toLocaleDateString("pt-BR", { day: "2-digit", month: "short", year: "numeric" });
    };

    const groupTransactionsByMonth = (txs: Transaction[]) => {
        const groups: { [key: string]: Transaction[] } = {};

        txs.forEach(t => {
            const date = new Date(t.transaction_date + "T00:00:00");
            const monthYear = date.toLocaleDateString("pt-BR", { month: "long", year: "numeric" });
            const key = monthYear.charAt(0).toUpperCase() + monthYear.slice(1);

            if (!groups[key]) groups[key] = [];
            groups[key].push(t);
        });

        return groups;
    };

    // Pagination logic
    const totalPages = Math.ceil(filteredTransactions.length / itemsPerPage);
    const startIndex = (currentPage - 1) * itemsPerPage;
    const paginatedTransactions = filteredTransactions.slice(startIndex, startIndex + itemsPerPage);
    const groupedPaginated = groupTransactionsByMonth(paginatedTransactions);

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
                        Transações
                    </h1>
                    <p className="text-[12px] font-medium text-gray-400 mt-2 tracking-wide">
                        Gerencie todas as suas movimentações financeiras
                    </p>
                </div>
                <div className="flex items-center gap-3">
                    <button
                        onClick={() => {
                            setLoading(true);
                            fetchTransactions();
                        }}
                        className="p-3 rounded-xl bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-[#0b3680] transition-colors shadow-sm"
                        title="Atualizar"
                    >
                        <RefreshCw className="w-5 h-5" />
                    </button>
                    <button
                        onClick={() => setIsAddModalOpen(true)}
                        className="flex items-center gap-2 bg-[#0b3680] text-white px-6 py-3 rounded-xl font-semibold text-[14px] hover:bg-[#092a66] transition-all shadow-lg shadow-[#0b3680]/20"
                    >
                        <Plus className="w-5 h-5" />
                        Nova Transação
                    </button>
                </div>
            </div>

            {/* Stats Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <p className="text-[12px] font-semibold text-gray-400 uppercase tracking-[0.5px] mb-2">Total de Entradas</p>
                    <p className="text-[28px] font-extrabold text-emerald-600 tracking-[-1px]">
                        R$ {formatCurrency(totalIncome)}
                    </p>
                </div>
                <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <p className="text-[12px] font-semibold text-gray-400 uppercase tracking-[0.5px] mb-2">Total de Saídas</p>
                    <p className="text-[28px] font-extrabold text-red-500 tracking-[-1px]">
                        R$ {formatCurrency(totalExpense)}
                    </p>
                </div>
                <div className="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <p className="text-[12px] font-semibold text-gray-400 uppercase tracking-[0.5px] mb-2">Balanço</p>
                    <p className={`text-[28px] font-extrabold tracking-[-1px] ${totalIncome - totalExpense >= 0 ? "text-emerald-600" : "text-red-500"}`}>
                        R$ {formatCurrency(totalIncome - totalExpense)}
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
                            <option value="income">Entradas</option>
                            <option value="expense">Saídas</option>
                        </select>
                    </div>

                    {/* Category Filter */}
                    <div className="flex items-center gap-2">
                        <Tag className="w-5 h-5 text-gray-400" />
                        <select
                            value={filterCategory}
                            onChange={(e) => setFilterCategory(e.target.value)}
                            className="px-4 py-3 rounded-xl border border-gray-200 focus:border-[#0b3680] outline-none text-[14px] font-medium bg-white"
                        >
                            <option value="all">Todas as Categorias</option>
                            {categories.map((cat) => (
                                <option key={cat} value={cat}>{cat}</option>
                            ))}
                        </select>
                    </div>
                </div>
            </div>

            {/* Transactions Table */}
            <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                {/* Table Header */}
                <div className="grid grid-cols-12 gap-4 px-6 py-4 bg-gray-50 border-b border-gray-100 text-[12px] font-semibold text-gray-500 uppercase tracking-[0.5px]">
                    <div className="col-span-1">Tipo</div>
                    <div className="col-span-3">Descrição</div>
                    <div className="col-span-2">Categoria</div>
                    <div className="col-span-2">Data</div>
                    <div className="col-span-2 text-right">Valor</div>
                    <div className="col-span-1 text-right">Saldo</div>
                    <div className="col-span-1 text-right">Ação</div>
                </div>

                {/* Table Body */}
                <div className="max-h-[600px] overflow-y-auto">
                    <AnimatePresence mode="wait">
                        {paginatedTransactions.length === 0 ? (
                            <div className="px-6 py-12 text-center text-gray-400">
                                <p className="text-[14px] font-medium">Nenhuma transação encontrada</p>
                            </div>
                        ) : (
                            Object.entries(groupedPaginated).map(([month, txs]) => (
                                <div key={month} className="border-b border-gray-100 last:border-0">
                                    <div className="px-6 py-3 bg-gray-50/50 border-y border-gray-100 text-[#0b3680] font-bold text-sm sticky top-0 backdrop-blur-sm z-10">
                                        {month}
                                    </div>
                                    {txs.map((transaction) => (
                                        <motion.div
                                            key={transaction.id}
                                            initial={{ opacity: 0, y: 10 }}
                                            animate={{ opacity: 1, y: 0 }}
                                            exit={{ opacity: 0, y: -10 }}
                                            className="flex flex-col bg-white border-b border-gray-50 last:border-0 hover:bg-gray-50/50 transition-colors"
                                        >
                                            <div className="grid grid-cols-12 gap-4 px-6 py-4 items-center">
                                                <div className="col-span-1">
                                                    <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${transaction.type === "income" ? "bg-emerald-100 text-emerald-600" : "bg-red-100 text-red-500"}`}>
                                                        {transaction.type === "income" ? <ArrowUpRight className="w-5 h-5" /> : <ArrowDownRight className="w-5 h-5" />}
                                                    </div>
                                                </div>
                                                <div className="col-span-3">
                                                    <p className="text-[14px] font-semibold text-gray-900 truncate">{transaction.description}</p>
                                                </div>
                                                <div className="col-span-2">
                                                    <span className="inline-block px-3 py-1 rounded-full bg-gray-100 text-gray-600 text-[12px] font-medium">
                                                        {transaction.category || "Sem categoria"}
                                                    </span>
                                                </div>
                                                <div className="col-span-2 flex items-center gap-2 text-gray-500">
                                                    <Calendar className="w-4 h-4" />
                                                    <span className="text-[13px] font-medium">{formatDate(transaction.transaction_date)}</span>
                                                </div>
                                                <div className="col-span-2 text-right">
                                                    <span className={`text-[16px] font-bold ${transaction.type === "income" ? "text-emerald-600" : "text-red-500"}`}>
                                                        {transaction.type === "income" ? "+" : "-"} R$ {formatCurrency(Number(transaction.amount))}
                                                    </span>
                                                </div>
                                                <div className="col-span-1 text-right">
                                                    <span className={`text-[13px] font-semibold ${(transaction.running_balance || 0) >= 0 ? "text-gray-700" : "text-red-500"}`}>
                                                        R$ {formatCurrency(transaction.running_balance || 0)}
                                                    </span>
                                                </div>
                                                <div className="col-span-1 text-right flex items-center justify-end gap-1">
                                                    {transaction.id !== "initial_balance" && getSameDayTransactions(transaction.transaction_date).length > 1 && (
                                                        <div className="flex flex-col gap-0.5 mr-2">
                                                            <button
                                                                onClick={() => handleReorder(transaction, "up")}
                                                                className="p-0.5 rounded hover:bg-gray-100 text-gray-400 hover:text-[#0b3680] transition-colors"
                                                                title="Mover para cima"
                                                            >
                                                                <ChevronUp className="w-3.5 h-3.5" />
                                                            </button>
                                                            <button
                                                                onClick={() => handleReorder(transaction, "down")}
                                                                className="p-0.5 rounded hover:bg-gray-100 text-gray-400 hover:text-[#0b3680] transition-colors"
                                                                title="Mover para baixo"
                                                            >
                                                                <ChevronDown className="w-3.5 h-3.5" />
                                                            </button>
                                                        </div>
                                                    )}
                                                    {transaction.transcription && (
                                                        <button
                                                            onClick={() => setExpandedTranscriptionId(expandedTranscriptionId === transaction.id ? null : transaction.id)}
                                                            className={`p-2 rounded-lg transition-colors ${expandedTranscriptionId === transaction.id ? "bg-indigo-100 text-indigo-600" : "hover:bg-indigo-50 text-gray-400 hover:text-indigo-500"}`}
                                                            title="Ver transcrição"
                                                        >
                                                            <Mic className="w-4 h-4" />
                                                        </button>
                                                    )}
                                                    {transaction.id !== "initial_balance" && (
                                                        <>
                                                            <button
                                                                onClick={() => {
                                                                    setEditingTransaction(transaction);
                                                                    setIsAddModalOpen(true);
                                                                }}
                                                                className="p-2 rounded-lg hover:bg-blue-100 text-gray-400 hover:text-blue-500 transition-colors"
                                                                title="Editar"
                                                            >
                                                                <Pencil className="w-4 h-4" />
                                                            </button>
                                                            <button onClick={() => handleDelete(transaction.id)} className="p-2 rounded-lg hover:bg-red-100 text-gray-400 hover:text-red-500 transition-colors" title="Excluir">
                                                                <Trash2 className="w-4 h-4" />
                                                            </button>
                                                        </>
                                                    )}
                                                </div>
                                            </div>

                                            <AnimatePresence>
                                                {expandedTranscriptionId === transaction.id && (
                                                    <motion.div
                                                        initial={{ height: 0, opacity: 0 }}
                                                        animate={{ height: "auto", opacity: 1 }}
                                                        exit={{ height: 0, opacity: 0 }}
                                                        className="overflow-hidden bg-indigo-50/50 border-t border-indigo-100"
                                                    >
                                                        <div className="px-6 py-3 flex items-start gap-3">
                                                            <Mic className="w-4 h-4 text-indigo-400 mt-0.5 shrink-0" />
                                                            <div>
                                                                <p className="text-[11px] font-semibold text-indigo-400 uppercase tracking-wider mb-0.5">Comando de Voz Original</p>
                                                                <p className="text-sm text-indigo-700 italic">"{transaction.transcription}"</p>
                                                            </div>
                                                        </div>
                                                    </motion.div>
                                                )}
                                            </AnimatePresence>
                                        </motion.div>
                                    ))}
                                </div>
                            ))
                        )}
                    </AnimatePresence>
                </div>

                {/* Pagination */}
                {totalPages > 1 && (
                    <div className="flex items-center justify-between px-6 py-4 bg-gray-50 border-t border-gray-100">
                        <p className="text-[12px] font-medium text-gray-500">
                            Mostrando {startIndex + 1}-{Math.min(startIndex + itemsPerPage, filteredTransactions.length)} de {filteredTransactions.length}
                        </p>
                        <div className="flex items-center gap-2">
                            <button
                                onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
                                disabled={currentPage === 1}
                                className="p-2 rounded-lg border border-gray-200 hover:bg-white disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                <ChevronLeft className="w-5 h-5 text-gray-600" />
                            </button>
                            <span className="text-[14px] font-semibold text-gray-700 px-4">
                                {currentPage} / {totalPages}
                            </span>
                            <button
                                onClick={() => setCurrentPage(prev => Math.min(totalPages, prev + 1))}
                                disabled={currentPage === totalPages}
                                className="p-2 rounded-lg border border-gray-200 hover:bg-white disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                <ChevronRight className="w-5 h-5 text-gray-600" />
                            </button>
                        </div>
                    </div>
                )}
            </div>

            {/* Add/Edit Modal */}
            {userId && (
                <AddTransactionModal
                    userId={userId}
                    isOpen={isAddModalOpen}
                    onClose={() => {
                        setIsAddModalOpen(false);
                        setEditingTransaction(null);
                    }}
                    onSuccess={() => setRefreshTrigger(prev => prev + 1)}
                    initialData={editingTransaction}
                />
            )}
        </div>
    );
}
