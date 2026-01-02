"use client";

import { useState } from "react";
import { createClient } from "@/lib/supabase/client";
import { Loader2, ArrowRight, Check } from "lucide-react";

interface SetupModalProps {
    userId: string;
    onComplete: () => void;
}

export function SetupModal({ userId, onComplete }: SetupModalProps) {
    const supabase = createClient();
    const [loading, setLoading] = useState(false);
    const [step, setStep] = useState(0); // 0 = AI Prompt, 1 = Financials, 2 = Hours, 3 = Balance
    const [analyzing, setAnalyzing] = useState(false);
    const [prompt, setPrompt] = useState("");

    const [formData, setFormData] = useState({
        salary: "",
        contractType: "CLT", // CLT, PJ, Autonomo
        paymentType: "monthly",
        workStart: "09:00",
        workEnd: "18:00",
        hasInterval: true,
        intervalStart: "12:00",
        intervalEnd: "13:00",
        initialBalance: "",
        workDays: [1, 2, 3, 4, 5] // Mon-Fri default
    });

    const daysOfWeek = [
        { id: 0, label: "D", full: "Domingo" },
        { id: 1, label: "S", full: "Segunda" },
        { id: 2, label: "T", full: "Terça" },
        { id: 3, label: "Q", full: "Quarta" },
        { id: 4, label: "Q", full: "Quinta" },
        { id: 5, label: "S", full: "Sexta" },
        { id: 6, label: "S", full: "Sábado" },
    ];

    const calculateMonthlyHours = () => {
        const start = parseInt(formData.workStart.split(":")[0]) + parseInt(formData.workStart.split(":")[1]) / 60;
        const end = parseInt(formData.workEnd.split(":")[0]) + parseInt(formData.workEnd.split(":")[1]) / 60;
        let dailyHours = end - start;

        if (formData.hasInterval) {
            const intStart = parseInt(formData.intervalStart.split(":")[0]) + parseInt(formData.intervalStart.split(":")[1]) / 60;
            const intEnd = parseInt(formData.intervalEnd.split(":")[0]) + parseInt(formData.intervalEnd.split(":")[1]) / 60;
            dailyHours -= (intEnd - intStart);
        }

        // Average weeks per month (approx 4.33) * Days worked per week
        const daysPerWeek = formData.workDays.length;
        const monthlyHours = dailyHours * daysPerWeek * 4.33;

        return Math.max(0, Math.round(monthlyHours));
    };

    const handleAnalyze = async () => {
        if (!prompt.trim()) return;
        setAnalyzing(true);

        // Simulate AI Analysis
        await new Promise(resolve => setTimeout(resolve, 1500));

        // Extract Salary
        let extractedSalary = "";
        const text = prompt.toLowerCase().replace(/\./g, '').replace(/,/g, '.');
        // Regex for: "ganho 1000", "R$ 1000", "salario 1000"
        const salaryMatch = text.match(/(?:recebo|ganho|salário|renda|tiro|r\$)\s*.*?(\d+[\d\.]*)/);
        if (salaryMatch && salaryMatch[1]) extractedSalary = salaryMatch[1];

        // Extract Contract Type
        let extractedContract = "CLT";
        if (text.includes("pj") || text.includes("jurídica")) extractedContract = "PJ";
        if (text.includes("autônomo") || text.includes("freela")) extractedContract = "Autônomo";

        setFormData(prev => ({
            ...prev,
            salary: extractedSalary,
            contractType: extractedContract
        }));

        setAnalyzing(false);
        setStep(1); // Move to manual review
    };

    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
        let value = e.target.type === 'checkbox' ? (e.target as HTMLInputElement).checked : e.target.value;
        setFormData({ ...formData, [e.target.name]: value });
    };

    const toggleDay = (dayId: number) => {
        setFormData(prev => {
            const days = prev.workDays.includes(dayId)
                ? prev.workDays.filter(d => d !== dayId)
                : [...prev.workDays, dayId];
            return { ...prev, workDays: days.sort() };
        });
    };

    const parseMoneyValue = (value: string) => {
        if (!value) return 0;
        const clean = value.replace(/[^\d.,]/g, ''); // Remove R$ and spaces
        const lastComma = clean.lastIndexOf(',');
        const lastDot = clean.lastIndexOf('.');

        if (lastComma > lastDot) {
            // Case: 1.000,00 (PT-BR) or 1000,00
            // Remove dots (thousands), replace comma with dot
            return parseFloat(clean.replace(/\./g, '').replace(',', '.')) || 0;
        } else {
            // Case: 1,000.00 (US) or 1000.00 or 1000
            // Remove commas (thousands)
            return parseFloat(clean.replace(/,/g, '')) || 0;
        }
    };

    const handleSubmit = async () => {
        setLoading(true);

        const { error: dbError } = await supabase.from("work_profiles").insert({
            user_id: userId,
            salary: parseMoneyValue(formData.salary),
            payment_type: formData.paymentType,
            work_start: formData.workStart,
            work_end: formData.workEnd,
            has_interval: formData.hasInterval,
            interval_start: formData.intervalStart,
            interval_end: formData.intervalEnd,
            initial_balance: parseMoneyValue(formData.initialBalance),
            work_days: JSON.stringify(formData.workDays),
            ai_profile_data: JSON.stringify({
                raw_prompt: prompt, // Saving the user description as requested
                contract_type: formData.contractType,
                monthly_hours: calculateMonthlyHours()
            })
        });

        if (dbError) {
            alert("Erro ao salvar: " + dbError.message);
            setLoading(false);
            return;
        }

        setLoading(false);
        onComplete();
    };

    // If step 0, render clean, comprehensive AI interface
    if (step === 0) {
        return (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div className="bg-white rounded-3xl shadow-2xl w-full max-w-5xl p-10 relative overflow-hidden animate-in zoom-in-95 duration-300 flex flex-col gap-8">

                    <div className="text-center">
                        <p className="text-gray-500 text-lg">
                            Descreva sua vida financeira e profissional abaixo. O assistente preencherá os dados para você.
                        </p>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="bg-gray-50 p-5 rounded-2xl border border-gray-100 cursor-pointer hover:border-[#0b3680] hover:bg-blue-50/30 transition-all group" onClick={() => setPrompt("Sou estagiário, namoro e não tenho despesas fixas. Moro com meus pais. Ganho R$ 1.500 de bolsa e quero juntar dinheiro para comprar um carro.")}>
                            <p className="text-xs font-bold text-[#0b3680] mb-2 uppercase tracking-wide group-hover:underline">Jovem / Estagiário</p>
                            <p className="text-sm text-gray-600 italic leading-relaxed">"Sou estagiário, namoro e não tenho despesas fixas. Moro com meus pais. Ganho R$ 1.500 de bolsa e quero juntar dinheiro para comprar um carro."</p>
                        </div>
                        <div className="bg-gray-50 p-5 rounded-2xl border border-gray-100 cursor-pointer hover:border-[#0b3680] hover:bg-blue-50/30 transition-all group" onClick={() => setPrompt("Sou pai de família, tenho 2 filhos e pago escola particular. Trabalho CLT, ganho R$ 15.000, mas minhas contas estão apertadas. Preciso organizar meu orçamento doméstico.")}>
                            <p className="text-xs font-bold text-[#0b3680] mb-2 uppercase tracking-wide group-hover:underline">Família / Gestão</p>
                            <p className="text-sm text-gray-600 italic leading-relaxed">"Sou pai de família, tenho 2 filhos e pago escola particular. Trabalho CLT, ganho R$ 15.000, mas minhas contas estão apertadas..."</p>
                        </div>
                    </div>

                    <div className="relative">
                        <textarea
                            value={prompt}
                            onChange={(e) => setPrompt(e.target.value)}
                            placeholder="Escreva livremente aqui sobre sua rotina, renda e objetivos..."
                            className="w-full h-40 p-6 rounded-2xl border border-gray-200 focus:border-[#0b3680] focus:ring-4 focus:ring-[#0b3680]/5 outline-none resize-none text-xl text-gray-700 leading-relaxed shadow-sm transition-all placeholder-gray-300"
                        />
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="bg-gray-50 p-5 rounded-2xl border border-gray-100 cursor-pointer hover:border-[#0b3680] hover:bg-blue-50/30 transition-all group" onClick={() => setPrompt("Sou autônomo, trabalho com vendas e minha renda varia muito. Em média tiro R$ 4.000, mas tem mês que é menos. Quero criar uma reserva de emergência.")}>
                            <p className="text-xs font-bold text-[#0b3680] mb-2 uppercase tracking-wide group-hover:underline">Autônomo / Variável</p>
                            <p className="text-sm text-gray-600 italic leading-relaxed">"Sou autônomo, trabalho com vendas e minha renda varia muito. Em média tiro R$ 4.000, mas tem mês que é menos. Quero criar uma reserva..."</p>
                        </div>
                        <div className="bg-gray-50 p-5 rounded-2xl border border-gray-100 cursor-pointer hover:border-[#0b3680] hover:bg-blue-50/30 transition-all group" onClick={() => setPrompt("Sou solteiro, moro de aluguel e gasto muito com delivery e Uber. Ganho R$ 6.000 como Designer PJ e quero me organizar para viajar no final do ano.")}>
                            <p className="text-xs font-bold text-[#0b3680] mb-2 uppercase tracking-wide group-hover:underline">Solteiro / Lifestyle</p>
                            <p className="text-sm text-gray-600 italic leading-relaxed">"Sou solteiro, moro de aluguel e gasto muito com delivery. Ganho R$ 6.000 como Designer PJ e quero me organizar para viajar..."</p>
                        </div>
                    </div>

                    <div className="flex items-center justify-between pt-6 border-t border-gray-100 mt-2">
                        <button onClick={() => setStep(1)} className="text-gray-400 text-sm hover:text-[#0b3680] transition-colors font-medium px-4 uppercase tracking-wider">
                            Pular
                        </button>
                        <button
                            onClick={handleAnalyze}
                            disabled={analyzing || !prompt.trim()}
                            className="bg-[#0b3680] text-white px-12 py-4 rounded-xl font-bold hover:bg-[#092960] flex items-center gap-3 disabled:opacity-70 transition-all shadow-xl shadow-[#0b3680]/20 text-base transform hover:-translate-y-0.5 active:translate-y-0"
                        >
                            {analyzing ? <Loader2 className="w-5 h-5 animate-spin" /> : <>CONTINUAR <ArrowRight className="w-5 h-5" /></>}
                        </button>
                    </div>

                </div>
            </div>
        );
    }

    return (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div className="bg-white rounded-3xl shadow-2xl w-full max-w-lg p-8 relative overflow-hidden animate-in slide-in-from-bottom-8 duration-300">
                {/* Progress Bar */}
                <div className="absolute top-0 left-0 w-full h-1.5 bg-gray-100">
                    <div
                        className="h-full bg-[#0b3680] transition-all duration-500"
                        style={{ width: `${(step / 3) * 100}%` }}
                    />
                </div>

                {/* Step 1: Financials (Salary + Contract) */}
                {step === 1 && (
                    <div className="animate-in fade-in slide-in-from-right-8 duration-300">
                        <h2 className="text-2xl font-[var(--font-playfair)] font-medium text-[#0b3680] mb-6 mt-4">Dados Profissionais</h2>

                        <div className="space-y-6">
                            <div>
                                <label className="text-sm font-medium text-gray-700 block mb-2">Vínculo</label>
                                <div className="grid grid-cols-3 gap-2">
                                    {['CLT', 'PJ', 'Autônomo'].map((type) => (
                                        <button
                                            key={type}
                                            onClick={() => setFormData({ ...formData, contractType: type })}
                                            className={`py-2 rounded-lg text-sm font-medium border transition-all ${formData.contractType === type
                                                ? 'bg-[#0b3680] text-white border-[#0b3680]'
                                                : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'
                                                }`}
                                        >
                                            {type}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            <div>
                                <label className="text-sm font-medium text-gray-700 block mb-2">Salário Mensal</label>
                                <div className="relative">
                                    <span className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">R$</span>
                                    <input
                                        type="text"
                                        name="salary"
                                        placeholder="0,00"
                                        value={formData.salary}
                                        onChange={handleChange}
                                        className="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-200 focus:border-[#0b3680] focus:ring-1 focus:ring-[#0b3680] outline-none text-lg font-medium"
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="flex gap-3 mt-8 pt-4 border-t border-gray-100">
                            <button onClick={() => setStep(0)} className="px-4 py-2 text-sm text-gray-500 hover:text-gray-900">Voltar</button>
                            <button onClick={() => setStep(2)} className="bg-[#0b3680] text-white px-6 py-2 rounded-xl font-medium hover:bg-[#092960] flex-1">Próximo</button>
                        </div>
                    </div>
                )}

                {/* Step 2: Hours */}
                {step === 2 && (
                    <div className="animate-in fade-in slide-in-from-right-8 duration-300">
                        <h2 className="text-2xl font-[var(--font-playfair)] font-medium text-[#0b3680] mb-2 mt-4">Jornada de Trabalho</h2>
                        <p className="text-sm text-gray-500 mb-6">Estimativa: <span className="font-medium text-[#0b3680]">{calculateMonthlyHours()} horas/mês</span></p>

                        <div className="space-y-6">
                            <div>
                                <label className="text-sm font-medium text-gray-700 block mb-2">Dias de Trabalho</label>
                                <div className="flex justify-between gap-1">
                                    {daysOfWeek.map((day) => (
                                        <button
                                            key={day.id}
                                            onClick={() => toggleDay(day.id)}
                                            className={`w-10 h-10 rounded-full text-xs font-bold flex items-center justify-center transition-all ${formData.workDays.includes(day.id)
                                                ? "bg-[#0b3680] text-white shadow-md shadow-blue-900/10"
                                                : "bg-gray-50 text-gray-400 hover:bg-gray-100 border border-transparent"
                                                }`}
                                            title={day.full}
                                        >
                                            {day.label}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-gray-700">Entrada</label>
                                    <input type="time" name="workStart" value={formData.workStart} onChange={handleChange} className="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-[#0b3680] outline-none bg-white" />
                                </div>
                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-gray-700">Saída</label>
                                    <input type="time" name="workEnd" value={formData.workEnd} onChange={handleChange} className="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-[#0b3680] outline-none bg-white" />
                                </div>
                            </div>

                            <div className="bg-gray-50 p-4 rounded-xl border border-gray-100">
                                <label className="flex items-center gap-3 cursor-pointer mb-3">
                                    <input type="checkbox" name="hasInterval" checked={formData.hasInterval} onChange={(e) => setFormData({ ...formData, hasInterval: e.target.checked })} className="w-5 h-5 text-[#0b3680] rounded border-gray-300 focus:ring-[#0b3680]" />
                                    <span className="text-sm font-medium text-gray-700">Horário de Almoço</span>
                                </label>

                                {formData.hasInterval && (
                                    <div className="grid grid-cols-2 gap-4 pl-8 animate-in slide-in-from-top-2">
                                        <div className="space-y-1">
                                            <label className="text-xs text-gray-500">Início</label>
                                            <input type="time" name="intervalStart" value={formData.intervalStart} onChange={handleChange} className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm outline-none focus:border-[#0b3680]" />
                                        </div>
                                        <div className="space-y-1">
                                            <label className="text-xs text-gray-500">Fim</label>
                                            <input type="time" name="intervalEnd" value={formData.intervalEnd} onChange={handleChange} className="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm outline-none focus:border-[#0b3680]" />
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="flex gap-3 mt-8 pt-4 border-t border-gray-100">
                            <button onClick={() => setStep(1)} className="px-4 py-2 text-sm text-gray-500 hover:text-gray-900">Voltar</button>
                            <button onClick={() => setStep(3)} className="bg-[#0b3680] text-white px-6 py-2 rounded-xl font-medium hover:bg-[#092960] flex-1">Próximo</button>
                        </div>
                    </div>
                )}

                {/* Step 3: Balance */}
                {step === 3 && (
                    <div className="animate-in fade-in slide-in-from-right-8 duration-300">
                        <h2 className="text-2xl font-[var(--font-playfair)] font-medium text-[#0b3680] mb-6 mt-4">Saldo Inicial</h2>
                        <div className="space-y-2">
                            <label className="text-sm font-medium text-gray-700">Quanto você tem hoje?</label>
                            <div className="relative">
                                <span className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">R$</span>
                                <input type="text" name="initialBalance" value={formData.initialBalance} onChange={handleChange} placeholder="0,00" className="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-200 focus:border-[#0b3680] outline-none text-lg font-medium" autoFocus />
                            </div>
                        </div>

                        <div className="flex gap-3 mt-8 pt-4 border-t border-gray-100">
                            <button onClick={() => setStep(2)} className="px-4 py-2 text-sm text-gray-500 hover:text-gray-900">Voltar</button>
                            <button onClick={handleSubmit} disabled={loading} className="bg-[#0b3680] text-white px-6 py-2 rounded-xl font-medium hover:bg-[#092960] flex-1 flex items-center justify-center gap-2">
                                {loading ? <Loader2 className="w-4 h-4 animate-spin" /> : <>Concluir <Check className="w-4 h-4" /></>}
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
