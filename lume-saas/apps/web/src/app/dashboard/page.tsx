"use client";

import { useEffect, useState, useRef } from "react";
import { createClient } from "@/lib/supabase/client";
import { SetupModal } from "@/components/dashboard/SetupModal";
import { RecentTransactions } from "@/components/dashboard/RecentTransactions";
import { AddTransactionModal } from "@/components/dashboard/AddTransactionModal";
import { RecurringTransactions } from "@/components/dashboard/RecurringTransactions";
import { AddRecurringModal } from "@/components/dashboard/AddRecurringModal";
import { ExpensesCharts } from "@/components/dashboard/ExpensesCharts";
import { motion } from "framer-motion";
import {
    Calendar, Wallet, Clock, TrendingUp, DollarSign,
    Loader2, Landmark, Hourglass, CreditCard, Sun, CalendarCheck, RefreshCw, Sparkles
} from "lucide-react";
import { TopExpenses } from "@/components/dashboard/TopExpenses";
import { BalanceChart } from "@/components/dashboard/BalanceChart";

type WorkProfile = {
    salary: number;
    work_start: string;
    work_end: string;
    work_days: number[];
    initial_balance: number;
    has_interval: boolean;
    interval_start: string;
    interval_end: string;
};

export default function DashboardPage() {
    const supabase = createClient();
    const [loading, setLoading] = useState(true);
    const [profile, setProfile] = useState<WorkProfile | null>(null);
    const [showSetup, setShowSetup] = useState(false);
    const [userId, setUserId] = useState<string | null>(null);
    const [userName, setUserName] = useState<string>("Ricardo Quadros");
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);
    const [isRecurringModalOpen, setIsRecurringModalOpen] = useState(false);
    const [refreshTrigger, setRefreshTrigger] = useState(0);

    const [liveEarnings, setLiveEarnings] = useState(0);
    const [monthEarnings, setMonthEarnings] = useState(0);
    const [monthProgressPct, setMonthProgressPct] = useState(0);
    const [monthTimePct, setMonthTimePct] = useState(0);
    const [dayProgress, setDayProgress] = useState(0);

    // Real-time clock state
    const [currentTime, setCurrentTime] = useState(new Date());

    const [dailySalary, setDailySalary] = useState(0);
    const [weeklySalary, setWeeklySalary] = useState(0);
    const [hourlyRate, setHourlyRate] = useState(0);
    const [totalExpenses, setTotalExpenses] = useState(0);
    const [currentBalance, setCurrentBalance] = useState(0);
    const [totalInvested, setTotalInvested] = useState(0);

    const timerRef = useRef<NodeJS.Timeout | null>(null);

    useEffect(() => {
        fetchData();

        const handleUpdate = () => {
            console.log("Refreshing dashboard data...");
            fetchData();
            setRefreshTrigger(prev => prev + 1); // Trigger sub-components
        };

        window.addEventListener("transaction-updated", handleUpdate);

        const handleKeyDown = (e: KeyboardEvent) => {
            // Ignore if typing in an input
            if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) return;

            if (e.key.toLowerCase() === 't') {
                e.preventDefault();
                setIsAddModalOpen(true);
            }
            if (e.key.toLowerCase() === 'r') {
                e.preventDefault();
                setIsRecurringModalOpen(true);
            }
        };

        window.addEventListener('keydown', handleKeyDown);

        return () => {
            if (timerRef.current) clearInterval(timerRef.current);
            window.removeEventListener('keydown', handleKeyDown);
            window.removeEventListener("transaction-updated", handleUpdate);
        };
    }, [refreshTrigger]);

    const fetchData = async () => {
        const { data: { user } } = await supabase.auth.getUser();
        if (!user) return;
        setUserId(user.id);

        if (user.user_metadata?.full_name) {
            setUserName(user.user_metadata.full_name);
        } else if (user.user_metadata?.name) {
            setUserName(user.user_metadata.name);
        }

        const { data: profileData } = await supabase
            .from("work_profiles")
            .select("*")
            .eq("user_id", user.id)
            .single();

        if (!profileData) {
            setShowSetup(true);
            setLoading(false);
            return;
        }

        let days = profileData.work_days;
        if (typeof days === 'string') {
            try { days = JSON.parse(days); } catch (e) { days = [1, 2, 3, 4, 5]; }
        }
        profileData.work_days = days;

        setProfile(profileData);

        const { data: transactions } = await supabase
            .from("transactions")
            .select("amount, type, transaction_date")
            .eq("user_id", user.id);

        let income = 0;
        let expense = 0;
        const now = new Date();
        const currentMonth = now.getMonth();
        const currentYear = now.getFullYear();
        let currentMonthExpenses = 0;

        transactions?.forEach(t => {
            const val = Number(t.amount);
            if (t.type === 'income') income += val;
            else {
                expense += val;
                const parts = t.transaction_date.split('-');
                if (Number(parts[1]) - 1 === currentMonth && Number(parts[0]) === currentYear) {
                    currentMonthExpenses += val;
                }
            }
        });

        const daysWorkedPerWeek = profileData.work_days.length || 5;
        const avgWeeksPerMonth = 4.33;
        const daysWorkedPerMonth = daysWorkedPerWeek * avgWeeksPerMonth;

        const effectiveMonthlyDays = daysWorkedPerMonth || 21.65;
        const calcDailySalary = profileData.salary / effectiveMonthlyDays;

        const [sH, sM] = profileData.work_start.split(":").map(Number);
        const [eH, eM] = profileData.work_end.split(":").map(Number);
        const startMins = sH * 60 + sM;
        const endMins = eH * 60 + eM;
        let totalMins = endMins - startMins;
        if (totalMins < 0) totalMins += 1440;

        if (profileData.has_interval) {
            const [isH, isM] = profileData.interval_start.split(":").map(Number);
            const [ieH, ieM] = profileData.interval_end.split(":").map(Number);
            let intMins = (ieH * 60 + ieM) - (isH * 60 + isM);
            if (intMins < 0) intMins += 1440;
            totalMins -= intMins;
        }

        const totalHours = totalMins / 60;
        const calcHourlyRate = calcDailySalary / totalHours;
        const calcWeeklySalary = calcDailySalary * daysWorkedPerWeek;

        setDailySalary(calcDailySalary);
        setWeeklySalary(calcWeeklySalary);
        setHourlyRate(calcHourlyRate);
        setTotalExpenses(currentMonthExpenses);

        const { data: investments } = await supabase
            .from("investments")
            .select("current_value")
            .eq("user_id", user.id)
            .eq("active", true);

        const totalInvestments = investments?.reduce((sum, inv) => sum + Number(inv.current_value), 0) || 0;

        const calcBalance = (profileData.initial_balance || 0) + income - expense;
        setCurrentBalance(calcBalance);
        setTotalInvested(totalInvestments); // New state needed

        setLoading(false);

        startLiveTimer(profileData, calcDailySalary, totalHours * 3600);
    };

    const startLiveTimer = (profile: WorkProfile, dailySalary: number, totalWorkSeconds: number) => {
        if (timerRef.current) clearInterval(timerRef.current);

        const calculate = () => {
            const now = new Date();
            setCurrentTime(now); // Update time state for real-time display

            const currentDay = now.getDay();

            const [sH, sM] = profile.work_start.split(":").map(Number);
            const [eH, eM] = profile.work_end.split(":").map(Number);

            const start = new Date(now);
            start.setHours(sH, sM, 0, 0);

            const end = new Date(now);
            end.setHours(eH, eM, 0, 0);

            let inInterval = false;

            if (profile.has_interval) {
                const [isH, isM] = profile.interval_start.split(":").map(Number);
                const [ieH, ieM] = profile.interval_end.split(":").map(Number);
                const intStart = new Date(now); intStart.setHours(isH, isM, 0, 0);
                const intEnd = new Date(now); intEnd.setHours(ieH, ieM, 0, 0);

                if (now >= intStart && now < intEnd) {
                    inInterval = true;
                }
            }

            const salaryPerSecond = dailySalary / totalWorkSeconds;
            const isWorkDay = profile.work_days.includes(currentDay);

            if (!isWorkDay) {
                setLiveEarnings(0);
                setDayProgress(0);
            } else if (now < start) {
                setLiveEarnings(0);
                setDayProgress(0);
            } else if (now > end) {
                setLiveEarnings(dailySalary);
                setDayProgress(100);
            } else if (inInterval) {
                setLiveEarnings(prev => prev);
            } else {
                let secondsWorked = (now.getTime() - start.getTime()) / 1000;

                if (profile.has_interval) {
                    const [isH, isM] = profile.interval_start.split(":").map(Number);
                    const intStart = new Date(now); intStart.setHours(isH, isM, 0, 0);
                    if (now > intStart) {
                        const [ieH, ieM] = profile.interval_end.split(":").map(Number);
                        const intEnd = new Date(now); intEnd.setHours(ieH, ieM, 0, 0);

                        if (now > intEnd) {
                            const intDuration = (intEnd.getTime() - intStart.getTime()) / 1000;
                            secondsWorked -= intDuration;
                        }
                    }
                }

                const earnedToday = secondsWorked * salaryPerSecond;
                const progress = (secondsWorked / totalWorkSeconds) * 100;

                setLiveEarnings(Math.min(earnedToday, dailySalary));
                setDayProgress(Math.min(progress, 100));
            }

            const currentMonth = now.getMonth();
            const dayOfMonth = now.getDate();
            const currentYear = now.getFullYear();
            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();

            let pastWorkDays = 0;
            for (let d = 1; d < dayOfMonth; d++) {
                const tempDate = new Date(currentYear, currentMonth, d);
                if (profile.work_days.includes(tempDate.getDay())) {
                    pastWorkDays++;
                }
            }

            let monthTotal = (pastWorkDays * dailySalary);

            if (isWorkDay) {
                if (now > end) monthTotal += dailySalary;
                else if (now >= start) {
                    let seconds = (now.getTime() - start.getTime()) / 1000;
                    if (profile.has_interval) {
                        const [isH, isM] = profile.interval_start.split(":").map(Number);
                        const [ieH, ieM] = profile.interval_end.split(":").map(Number);
                        const iS = new Date(now); iS.setHours(isH, isM, 0, 0);
                        const iE = new Date(now); iE.setHours(ieH, ieM, 0, 0);
                        if (now > iE) seconds -= (iE.getTime() - iS.getTime()) / 1000;
                    }
                    monthTotal += Math.min(seconds * salaryPerSecond, dailySalary);
                }
            }

            setMonthEarnings(monthTotal);
            setMonthProgressPct((monthTotal / profile.salary) * 100);
            setMonthTimePct((dayOfMonth / daysInMonth) * 100);
        };

        calculate();
        timerRef.current = setInterval(calculate, 1000);
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center h-[50vh]">
                <Loader2 className="w-8 h-8 animate-spin text-[#0b3680]" />
            </div>
        );
    }

    return (
        <div className="space-y-12 font-[Inter]">
            {showSetup && userId && (
                <SetupModal userId={userId} onComplete={() => { setShowSetup(false); fetchData(); }} />
            )}

            {/* Header Section */}
            <div className="flex items-end justify-between">
                <div>
                    <h1 className="text-[32px] font-bold text-[#0b3680] tracking-tight">
                        Olá, {userName}
                    </h1>
                    <p className="text-[12px] font-medium text-gray-400 mt-2 tracking-wide">
                        Seu painel visão geral e simplificada
                    </p>
                </div>

                <div className="flex items-center gap-2">
                    <button
                        onClick={() => {
                            window.dispatchEvent(new CustomEvent("trigger-ai-analysis", {
                                detail: "Analise meus gastos deste mês e me dê 3 insights breves sobre onde posso economizar."
                            }));
                        }}
                        className="flex items-center gap-2 bg-[#0b3680] text-white px-4 py-2 rounded-full text-sm font-medium hover:bg-[#092a66] transition-all shadow-md hover:shadow-lg"
                    >
                        <Sparkles className="w-4 h-4" />
                        Análise Inteligente
                    </button>

                    <button
                        onClick={() => { fetchData(); setRefreshTrigger(prev => prev + 1); }}
                        className="p-2 text-gray-400 hover:text-[#0b3680] hover:bg-white rounded-full transition-all"
                        title="Atualizar dados"
                    >
                        <RefreshCw className="w-5 h-5" />
                    </button>
                    <div className="flex items-center gap-2 text-[12px] font-medium text-gray-400 bg-white px-4 py-2 rounded-full border border-gray-100 shadow-sm">
                        <Calendar className="w-4 h-4" />
                        <span>{currentTime.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })}</span>
                    </div>
                </div>
            </div>

            {/* Live Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                {/* Live Earnings */}
                <div className="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm relative overflow-hidden group hover:shadow-md transition-all">
                    <div className="absolute top-0 right-0 p-6 opacity-5 group-hover:opacity-10 transition-opacity">
                        <Wallet className="w-32 h-32 text-[#0b3680]" />
                    </div>
                    <div className="relative z-10">
                        <h3 className="text-[14px] font-semibold text-gray-500 uppercase tracking-[0.5px] flex items-center gap-2 mb-4">
                            <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
                            Ganhos do Dia
                        </h3>
                        <div className="text-[42px] font-extrabold text-gray-900 tracking-[-1px] mb-1">
                            R$ {liveEarnings.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </div>
                        <p className="text-[12px] font-medium text-gray-400">
                            Tempo Real
                        </p>
                    </div>
                </div>

                {/* Month Earnings Progress */}
                <div className="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm hover:shadow-md transition-all">
                    <div className="flex justify-between items-center mb-6">
                        <h3 className="text-[14px] font-semibold text-gray-500 uppercase tracking-[0.5px]">
                            Ganhos do Mês
                        </h3>
                        <span className="text-[12px] font-bold text-[#0b3680] bg-blue-50 px-2 py-1 rounded-md">
                            {Math.min(monthProgressPct, 100).toFixed(1)}%
                        </span>
                    </div>

                    <div className="text-[42px] font-extrabold text-gray-900 tracking-[-1px] mb-2">
                        R$ {monthEarnings.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                    </div>

                    <div className="w-full bg-gray-100 h-2 rounded-full overflow-hidden mb-2">
                        <motion.div
                            initial={{ width: 0 }}
                            animate={{ width: `${Math.min(monthProgressPct, 100)}%` }}
                            transition={{ duration: 1 }}
                            className="h-full bg-[#0b3680]"
                        />
                    </div>
                    <p className="text-[12px] font-medium text-gray-400 text-right">
                        Meta: R$ {profile?.salary?.toLocaleString('pt-BR')}
                    </p>
                </div>

                {/* Journey & Month Time */}
                <div className="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm flex flex-col justify-between hover:shadow-md transition-all">
                    <div className="flex justify-between items-start">
                        <h3 className="text-[14px] font-semibold text-gray-500 uppercase tracking-[0.5px]">
                            Jornada & Progresso
                        </h3>
                        <Clock className="w-5 h-5 text-gray-300" />
                    </div>

                    <div className="text-center py-4">
                        <div className="text-[32px] font-bold text-gray-900 font-mono tracking-wider">
                            {currentTime.toLocaleTimeString('pt-BR')}
                        </div>
                        {/* Fixed: Now includes weekday, day number AND month */}
                        <p className="text-[12px] font-medium text-gray-400 mt-1 capitalize">
                            {currentTime.toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' })}
                        </p>
                    </div>

                    <div className="space-y-4">
                        <div>
                            <div className="flex justify-between text-[12px] font-semibold text-gray-400 mb-1 uppercase tracking-[0.5px]">
                                <span>Dia</span>
                                <span className="text-[#0b3680]">{Math.min(dayProgress, 100).toFixed(1)}%</span>
                            </div>
                            <div className="w-full bg-gray-100 h-1.5 rounded-full overflow-hidden">
                                <motion.div
                                    animate={{ width: `${Math.min(dayProgress, 100)}%` }}
                                    className="h-full bg-[#0b3680]"
                                />
                            </div>
                        </div>

                        <div>
                            <div className="flex justify-between text-[12px] font-semibold text-gray-400 mb-1 uppercase tracking-[0.5px]">
                                <span>Mês</span>
                                <span className="text-[#0b3680]">{monthTimePct.toFixed(1)}%</span>
                            </div>
                            <div className="w-full bg-gray-100 h-1.5 rounded-full overflow-hidden">
                                <motion.div
                                    animate={{ width: `${monthTimePct}%` }}
                                    className="h-full bg-[#0b3680]"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Financial Summary - NOW ABOVE TRANSACTIONS */}
            <div className="bg-[#0b3680] text-white p-10 rounded-3xl relative overflow-hidden shadow-xl">
                <div className="absolute inset-0 bg-[url('/grid.svg')] opacity-10" />

                <h3 className="relative z-10 text-[14px] font-semibold text-white/70 uppercase tracking-[0.5px] mb-8 border-b border-white/10 pb-4 flex items-center gap-2">
                    <TrendingUp className="w-5 h-5" /> Resumo Financeiro
                </h3>

                <div className="relative z-10 grid md:grid-cols-2 lg:grid-cols-3 gap-y-10 gap-x-12">

                    <div className="lg:col-span-1">
                        <div className="text-[12px] font-semibold text-white/50 uppercase tracking-[0.5px] mb-1">
                            Patrimônio Líquido
                        </div>
                        <div className="text-[32px] font-bold tracking-tight">
                            R$ {(currentBalance + totalInvested).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                        </div>
                        <div className="text-[12px] font-medium text-white/40 mt-2">Saldo + Investimentos</div>
                    </div>

                    <div className="lg:col-span-2 grid grid-cols-2 md:grid-cols-3 gap-6">

                        <div className="space-y-1">
                            <div className="flex items-center gap-2 text-[12px] font-semibold text-white/50 uppercase tracking-[0.5px]">
                                <DollarSign className="w-3 h-3" /> Salário Base
                            </div>
                            <div className="text-[20px] font-bold">R$ {profile?.salary.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</div>
                        </div>

                        <div className="space-y-1">
                            <div className="flex items-center gap-2 text-[12px] font-semibold text-white/50 uppercase tracking-[0.5px]">
                                <CalendarCheck className="w-3 h-3" /> Ganho Semanal
                            </div>
                            <div className="text-[20px] font-bold">R$ {weeklySalary.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</div>
                        </div>

                        <div className="space-y-1">
                            <div className="flex items-center gap-2 text-[12px] font-semibold text-white/50 uppercase tracking-[0.5px]">
                                <Sun className="w-3 h-3" /> Ganho Diário
                            </div>
                            <div className="text-[20px] font-bold">R$ {dailySalary.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</div>
                        </div>

                        <div className="space-y-1">
                            <div className="flex items-center gap-2 text-[12px] font-semibold text-white/50 uppercase tracking-[0.5px]">
                                <Hourglass className="w-3 h-3" /> Ganho / Hora
                            </div>
                            <div className="text-[20px] font-bold">R$ {hourlyRate.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</div>
                        </div>

                        <div className="space-y-1">
                            <div className="flex items-center gap-2 text-[12px] font-semibold text-white/50 uppercase tracking-[0.5px]">
                                <Landmark className="w-3 h-3" /> Saldo em Conta
                            </div>
                            <div className="text-[20px] font-bold">R$ {currentBalance.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</div>
                        </div>

                        <div className="space-y-1">
                            <div className="flex items-center gap-2 text-[12px] font-semibold text-emerald-300 uppercase tracking-[0.5px]">
                                <TrendingUp className="w-3 h-3" /> Investimentos
                            </div>
                            <div className="text-[20px] font-bold text-emerald-100">R$ {totalInvested.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</div>
                        </div>

                        <div className="space-y-1">
                            <div className="flex items-center gap-2 text-[12px] font-semibold text-red-300 uppercase tracking-[0.5px]">
                                <CreditCard className="w-3 h-3" /> Despesas (Mês)
                            </div>
                            <div className="text-[20px] font-bold text-red-200">R$ {totalExpenses.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</div>
                        </div>

                    </div>
                </div>
            </div>

            {/* Expenses Charts Section - Layer 2 */}
            <div className="bg-white rounded-3xl border border-gray-100 shadow-sm p-4">
                {userId && <ExpensesCharts userId={userId} />}
            </div>

            {/* Analysis Row - Layer 3 */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div className="h-[400px]">
                    {userId && <BalanceChart userId={userId} refreshTrigger={refreshTrigger} initialBalance={profile?.initial_balance || 0} />}
                </div>
                <div className="h-full">
                    {userId && <TopExpenses userId={userId} refreshTrigger={refreshTrigger} />}
                </div>
            </div>

            {/* Transactions Side-by-Side */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                {userId && (
                    <RecentTransactions
                        userId={userId}
                        refreshTrigger={refreshTrigger}
                        onAddClick={() => setIsAddModalOpen(true)}
                    />
                )}
                {userId && (
                    <RecurringTransactions
                        userId={userId}
                        refreshTrigger={refreshTrigger}
                        onAddClick={() => setIsRecurringModalOpen(true)}
                    />
                )}
            </div>

            {/* Modals */}
            {
                userId && (
                    <AddTransactionModal
                        userId={userId}
                        isOpen={isAddModalOpen}
                        onClose={() => setIsAddModalOpen(false)}
                        onSuccess={() => setRefreshTrigger(prev => prev + 1)}
                    />
                )
            }
            {
                userId && (
                    <AddRecurringModal
                        userId={userId}
                        isOpen={isRecurringModalOpen}
                        onClose={() => setIsRecurringModalOpen(false)}
                        onSuccess={() => setRefreshTrigger(prev => prev + 1)}
                    />
                )
            }
        </div >
    );
}
