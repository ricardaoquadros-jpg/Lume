"use client";

import { useEffect, useState } from "react";
import { createClient } from "@/lib/supabase/client";
import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip } from "recharts";
import { Loader2, PieChart as PieIcon, ChevronLeft, ChevronRight } from "lucide-react";

interface ExpensesChartsProps {
    userId: string;
}

const COLORS = [
    "#0b3680", "#00C49F", "#FFBB28", "#FF8042", "#8884d8",
    "#82ca9d", "#ffc658", "#8dd1e1", "#a4de6c", "#d0ed57"
];

function formatCurrency(value: number) {
    return new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(value);
}

function getMonthName(date: Date) {
    return date.toLocaleString('pt-BR', { month: 'long', year: 'numeric' });
}

export function ExpensesCharts({ userId }: ExpensesChartsProps) {
    const supabase = createClient();
    const [loading, setLoading] = useState(true);
    const [monthlyData, setMonthlyData] = useState<any[]>([]);
    const [generalData, setGeneralData] = useState<any[]>([]);
    const [currentDate, setCurrentDate] = useState(new Date());

    const fetchMonthlyData = async (date: Date) => {
        const startOfMonth = new Date(date.getFullYear(), date.getMonth(), 1).toISOString();
        const endOfMonth = new Date(date.getFullYear(), date.getMonth() + 1, 0).toISOString();

        const { data } = await supabase
            .from("transactions")
            .select("*")
            .eq("user_id", userId)
            .eq("type", "expense")
            .gte("transaction_date", startOfMonth)
            .lte("transaction_date", endOfMonth);

        const monthMap = new Map();
        data?.forEach(t => {
            const amount = Number(t.amount);
            monthMap.set(t.category, (monthMap.get(t.category) || 0) + amount);
        });

        const total = Array.from(monthMap.values()).reduce((sum: number, val: number) => sum + val, 0);

        const mData = Array.from(monthMap, ([name, value]) => ({
            name,
            value,
            percentage: total > 0 ? (value / total) * 100 : 0
        })).sort((a, b) => b.value - a.value);

        setMonthlyData(mData);
    };

    const fetchGeneralData = async () => {
        const { data } = await supabase
            .from("transactions")
            .select("*")
            .eq("user_id", userId)
            .eq("type", "expense");

        const generalMap = new Map();
        data?.forEach(t => {
            const amount = Number(t.amount);
            generalMap.set(t.category, (generalMap.get(t.category) || 0) + amount);
        });

        const total = Array.from(generalMap.values()).reduce((sum: number, val: number) => sum + val, 0);

        const gData = Array.from(generalMap, ([name, value]) => ({
            name,
            value,
            percentage: total > 0 ? (value / total) * 100 : 0
        })).sort((a, b) => b.value - a.value);

        setGeneralData(gData);
    };

    useEffect(() => {
        if (!userId) return;
        setLoading(true);
        Promise.all([fetchMonthlyData(currentDate), fetchGeneralData()])
            .finally(() => setLoading(false));
    }, [userId]);

    // Refetch only monthly when date changes (after initial load)
    useEffect(() => {
        if (!userId) return;
        fetchMonthlyData(currentDate);
    }, [currentDate]);

    const handlePrevMonth = () => {
        setCurrentDate(prev => new Date(prev.getFullYear(), prev.getMonth() - 1, 1));
    };

    const handleNextMonth = () => {
        setCurrentDate(prev => new Date(prev.getFullYear(), prev.getMonth() + 1, 1));
    };

    if (loading && monthlyData.length === 0) {
        return (
            <div className="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm min-h-[300px] flex items-center justify-center">
                <Loader2 className="w-8 h-8 text-[#0b3680] animate-spin" />
            </div>
        );
    }

    const renderChartSection = (data: any[], title: string, isMonthly: boolean) => (
        <div className="bg-white rounded-3xl p-6 md:p-8 border border-gray-100 shadow-sm flex flex-col h-full min-h-[500px]">
            <div className="flex items-center justify-between mb-8">
                <h3 className="text-xl font-bold text-gray-900 flex items-center gap-2">
                    <PieIcon className="w-6 h-6 text-[#0b3680]" />
                    {title}
                </h3>
                {isMonthly && (
                    <div className="flex items-center gap-2 bg-gray-50 rounded-full p-1 border border-gray-200">
                        <button onClick={handlePrevMonth} className="p-2 hover:bg-white rounded-full transition-all text-gray-600 shadow-sm hover:shadow">
                            <ChevronLeft className="w-4 h-4" />
                        </button>
                        <span className="text-sm font-semibold text-[#0b3680] min-w-[140px] text-center capitalize">
                            {getMonthName(currentDate)}
                        </span>
                        <button onClick={handleNextMonth} className="p-2 hover:bg-white rounded-full transition-all text-gray-600 shadow-sm hover:shadow">
                            <ChevronRight className="w-4 h-4" />
                        </button>
                    </div>
                )}
            </div>

            {data.length > 0 ? (
                <div className="flex flex-col xl:flex-row items-center gap-8 flex-1">
                    {/* Chart Area - BIGGER */}
                    <div className="w-full xl:w-1/2 h-[350px] relative">
                        <ResponsiveContainer width="100%" height="100%">
                            <PieChart>
                                <Pie
                                    data={data}
                                    cx="50%"
                                    cy="50%"
                                    innerRadius={80}
                                    outerRadius={120}
                                    fill="#8884d8"
                                    paddingAngle={4}
                                    dataKey="value"
                                    stroke="none"
                                >
                                    {data.map((entry, index) => (
                                        <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                                    ))}
                                </Pie>
                                <Tooltip
                                    formatter={(value: any) => formatCurrency(Number(value))}
                                    contentStyle={{ borderRadius: '12px', border: 'none', boxShadow: '0 4px 12px rgba(0,0,0,0.1)' }}
                                />
                            </PieChart>
                        </ResponsiveContainer>
                        {/* Center Text (Total?) Optional */}
                        <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                            <div className="text-center">
                                <p className="text-xs text-gray-400 uppercase tracking-wider font-semibold">Total</p>
                                <p className="text-xl font-bold text-[#0b3680]">
                                    {formatCurrency(data.reduce((acc, item) => acc + item.value, 0))}
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Detailed List */}
                    <div className="w-full xl:w-1/2 flex flex-col gap-3 max-h-[350px] overflow-y-auto pr-2 custom-scrollbar">
                        {data.map((item, index) => (
                            <div key={index} className="flex items-center justify-between p-3 rounded-xl hover:bg-gray-50 transition-colors border border-transparent hover:border-gray-100">
                                <div className="flex items-center gap-3">
                                    <div
                                        className="w-4 h-4 rounded-full shrink-0"
                                        style={{ backgroundColor: COLORS[index % COLORS.length] }}
                                    />
                                    <span className="font-medium text-gray-700">{item.name}</span>
                                </div>
                                <div className="text-right">
                                    <p className="font-bold text-[#0b3680]">{formatCurrency(item.value)}</p>
                                    <p className="text-xs font-semibold text-gray-400">{item.percentage.toFixed(1)}%</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            ) : (
                <div className="flex-1 flex flex-col items-center justify-center text-gray-400 gap-4">
                    <div className="w-16 h-16 rounded-full bg-gray-50 flex items-center justify-center">
                        <PieIcon className="w-8 h-8 text-gray-300" />
                    </div>
                    <p>Nenhuma despesa encontrada neste período.</p>
                </div>
            )}
        </div>
    );

    return (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8 mb-8">
            {renderChartSection(monthlyData, "Despesas por Categoria", true)}
            {renderChartSection(generalData, "Visão Geral (Tudo)", false)}
        </div>
    );
}
