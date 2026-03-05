"use client";

import { useState, useRef, useCallback } from "react";
import { createClient } from "@/lib/supabase/client";
import { motion, AnimatePresence } from "framer-motion";
import {
    X, Upload, FileText, Loader2, Check, CheckSquare, Square,
    Mic, MicOff, Sparkles, ArrowRight, ArrowLeft, ArrowUpRight,
    ArrowDownRight, AlertCircle, FileSpreadsheet, ChevronDown
} from "lucide-react";

interface ImportedTransaction {
    id: string;
    date: string;
    description: string;
    amount: number;
    type: "income" | "expense";
    category: string;
    selected: boolean;
}

interface ImportStatementModalProps {
    userId: string;
    isOpen: boolean;
    onClose: () => void;
    onSuccess: () => void;
}

const CATEGORIES = [
    "Alimentação", "Mercado", "Transporte", "Lazer", "Roupas", "Jogos",
    "Saúde", "Esportes", "Investimento", "Educação", "Moradia", "Contas",
    "Assinaturas", "Beleza", "Pets", "Viagem", "Presentes", "Salário",
    "Extra", "Outros"
];

export function ImportStatementModal({ userId, isOpen, onClose, onSuccess }: ImportStatementModalProps) {
    const supabase = createClient();

    // Step management
    const [step, setStep] = useState<1 | 2 | 3>(1);

    // Step 1 - Upload
    const [file, setFile] = useState<File | null>(null);
    const [parsing, setParsing] = useState(false);
    const [parseError, setParseError] = useState<string | null>(null);
    const [isDragging, setIsDragging] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    // Step 2 - Review & Categorize
    const [transactions, setTransactions] = useState<ImportedTransaction[]>([]);
    const [instruction, setInstruction] = useState("");
    const [categorizing, setCategorizing] = useState(false);
    const [isRecording, setIsRecording] = useState(false);
    const mediaRecorderRef = useRef<MediaRecorder | null>(null);
    const chunksRef = useRef<Blob[]>([]);

    // Step 3 - Confirm
    const [importing, setImporting] = useState(false);
    const [importError, setImportError] = useState<string | null>(null);

    if (!isOpen) return null;

    const resetAll = () => {
        setStep(1);
        setFile(null);
        setParsing(false);
        setParseError(null);
        setTransactions([]);
        setInstruction("");
        setCategorizing(false);
        setImporting(false);
        setImportError(null);
        setIsDragging(false);
    };

    const handleClose = () => {
        resetAll();
        onClose();
    };

    // === STEP 1: Upload ===

    const handleFileSelect = (selectedFile: File) => {
        const name = selectedFile.name.toLowerCase();
        if (!name.endsWith(".pdf") && !name.endsWith(".csv")) {
            setParseError("Formato não suportado. Use PDF ou CSV.");
            return;
        }
        setFile(selectedFile);
        setParseError(null);
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(false);
        const droppedFile = e.dataTransfer.files[0];
        if (droppedFile) handleFileSelect(droppedFile);
    };

    const handleParse = async () => {
        if (!file) return;
        setParsing(true);
        setParseError(null);

        try {
            const formData = new FormData();
            formData.append("file", file);

            const res = await fetch("/api/statement/parse", {
                method: "POST",
                body: formData
            });

            const data = await res.json();

            if (!res.ok) {
                setParseError(data.error || "Erro ao processar arquivo.");
                return;
            }

            setTransactions(data.transactions);
            setStep(2);
        } catch (err: any) {
            setParseError("Erro de conexão: " + err.message);
        } finally {
            setParsing(false);
        }
    };

    // === STEP 2: Review & Categorize ===

    const toggleSelect = (id: string) => {
        setTransactions(prev =>
            prev.map(t => t.id === id ? { ...t, selected: !t.selected } : t)
        );
    };

    const toggleSelectAll = () => {
        const allSelected = transactions.every(t => t.selected);
        setTransactions(prev =>
            prev.map(t => ({ ...t, selected: !allSelected }))
        );
    };

    const updateCategory = (id: string, category: string) => {
        setTransactions(prev =>
            prev.map(t => t.id === id ? { ...t, category } : t)
        );
    };

    const updateType = (id: string, type: "income" | "expense") => {
        setTransactions(prev =>
            prev.map(t => t.id === id ? { ...t, type } : t)
        );
    };

    const handleCategorize = async (audioBlob?: Blob) => {
        setCategorizing(true);

        try {
            const formData = new FormData();
            formData.append("transactions", JSON.stringify(transactions));

            if (audioBlob) {
                formData.append("audio", audioBlob, "instruction.webm");
            } else if (instruction.trim()) {
                formData.append("instruction", instruction);
            }

            const res = await fetch("/api/statement/categorize", {
                method: "POST",
                body: formData
            });

            const data = await res.json();
            if (res.ok && data.transactions) {
                setTransactions(data.transactions.map((t: any) => ({
                    ...t,
                    selected: transactions.find(ot => ot.id === t.id)?.selected ?? true
                })));
                if (data.transcription && !instruction) {
                    setInstruction(data.transcription);
                }
            }
        } catch {
            // silently fail, user can still manually categorize
        } finally {
            setCategorizing(false);
        }
    };

    // Audio recording
    const startRecording = async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorderRef.current = new MediaRecorder(stream);
            chunksRef.current = [];

            mediaRecorderRef.current.ondataavailable = (e) => {
                if (e.data.size > 0) chunksRef.current.push(e.data);
            };

            mediaRecorderRef.current.onstop = async () => {
                const blob = new Blob(chunksRef.current, { type: "audio/webm" });
                stream.getTracks().forEach(track => track.stop());
                await handleCategorize(blob);
            };

            mediaRecorderRef.current.start();
            setIsRecording(true);
        } catch {
            alert("Permissão de microfone negada.");
        }
    };

    const stopRecording = () => {
        if (mediaRecorderRef.current && isRecording) {
            mediaRecorderRef.current.stop();
            setIsRecording(false);
        }
    };

    // === STEP 3: Confirm & Import ===

    const selectedTransactions = transactions.filter(t => t.selected);

    const handleImport = async () => {
        setImporting(true);
        setImportError(null);

        try {
            const payload = selectedTransactions.map(t => ({
                user_id: userId,
                type: t.type,
                description: t.description,
                amount: t.amount,
                category: t.category,
                transaction_date: t.date
            }));

            // Batch insert (Supabase supports array insert)
            const { error } = await supabase
                .from("transactions")
                .insert(payload);

            if (error) {
                setImportError("Erro ao salvar: " + error.message);
                return;
            }

            // Success!
            onSuccess();
            window.dispatchEvent(new Event("transaction-updated"));
            handleClose();
        } catch (err: any) {
            setImportError("Erro: " + err.message);
        } finally {
            setImporting(false);
        }
    };

    const totalIncome = selectedTransactions.filter(t => t.type === "income").reduce((acc, t) => acc + t.amount, 0);
    const totalExpense = selectedTransactions.filter(t => t.type === "expense").reduce((acc, t) => acc + t.amount, 0);

    const formatCurrency = (val: number) => val.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    return (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <motion.div
                initial={{ opacity: 0, scale: 0.95 }}
                animate={{ opacity: 1, scale: 1 }}
                exit={{ opacity: 0, scale: 0.95 }}
                className="bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col"
            >
                {/* Header */}
                <div className="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50 shrink-0">
                    <div className="flex items-center gap-3">
                        <div className="p-2 rounded-xl bg-[#0b3680]/10">
                            <Upload className="w-5 h-5 text-[#0b3680]" />
                        </div>
                        <div>
                            <h3 className="text-xl font-[var(--font-playfair)] font-medium text-[#0b3680]">
                                Importar Extrato
                            </h3>
                            <p className="text-xs text-gray-400 mt-0.5">
                                {step === 1 && "Selecione o arquivo do extrato"}
                                {step === 2 && "Revise e categorize as transações"}
                                {step === 3 && "Confirme a importação"}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-4">
                        {/* Step indicator */}
                        <div className="flex items-center gap-2">
                            {[1, 2, 3].map(s => (
                                <div
                                    key={s}
                                    className={`w-2 h-2 rounded-full transition-all ${s === step ? "bg-[#0b3680] w-6" : s < step ? "bg-[#0b3680]/40" : "bg-gray-200"}`}
                                />
                            ))}
                        </div>
                        <button onClick={handleClose} className="p-2 hover:bg-gray-100 rounded-full transition-colors text-gray-500">
                            <X className="w-5 h-5" />
                        </button>
                    </div>
                </div>

                {/* Content */}
                <div className="flex-1 overflow-y-auto p-6">
                    <AnimatePresence mode="wait">
                        {/* STEP 1: Upload */}
                        {step === 1 && (
                            <motion.div
                                key="step1"
                                initial={{ opacity: 0, x: -20 }}
                                animate={{ opacity: 1, x: 0 }}
                                exit={{ opacity: 0, x: 20 }}
                            >
                                <div
                                    onDragOver={(e) => { e.preventDefault(); setIsDragging(true); }}
                                    onDragLeave={() => setIsDragging(false)}
                                    onDrop={handleDrop}
                                    onClick={() => fileInputRef.current?.click()}
                                    className={`border-2 border-dashed rounded-2xl p-12 text-center cursor-pointer transition-all ${isDragging
                                        ? "border-[#0b3680] bg-[#0b3680]/5"
                                        : file
                                            ? "border-emerald-300 bg-emerald-50/50"
                                            : "border-gray-200 hover:border-[#0b3680]/40 hover:bg-gray-50"
                                        }`}
                                >
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        accept=".pdf,.csv"
                                        className="hidden"
                                        onChange={(e) => e.target.files?.[0] && handleFileSelect(e.target.files[0])}
                                    />

                                    {file ? (
                                        <div className="flex flex-col items-center gap-3">
                                            <div className="w-16 h-16 rounded-2xl bg-emerald-100 flex items-center justify-center">
                                                {file.name.endsWith(".csv")
                                                    ? <FileSpreadsheet className="w-8 h-8 text-emerald-600" />
                                                    : <FileText className="w-8 h-8 text-emerald-600" />
                                                }
                                            </div>
                                            <div>
                                                <p className="font-semibold text-gray-900">{file.name}</p>
                                                <p className="text-sm text-gray-400">{(file.size / 1024).toFixed(1)} KB</p>
                                            </div>
                                            <p className="text-xs text-emerald-600 font-medium">Arquivo selecionado ✓</p>
                                        </div>
                                    ) : (
                                        <div className="flex flex-col items-center gap-3">
                                            <div className="w-16 h-16 rounded-2xl bg-[#0b3680]/5 flex items-center justify-center">
                                                <Upload className="w-8 h-8 text-[#0b3680]/40" />
                                            </div>
                                            <div>
                                                <p className="font-semibold text-gray-700">Arraste o extrato aqui</p>
                                                <p className="text-sm text-gray-400 mt-1">ou clique para selecionar</p>
                                            </div>
                                            <div className="flex items-center gap-2 mt-2">
                                                <span className="px-3 py-1 rounded-full bg-gray-100 text-xs font-medium text-gray-500">PDF</span>
                                                <span className="px-3 py-1 rounded-full bg-gray-100 text-xs font-medium text-gray-500">CSV</span>
                                            </div>
                                        </div>
                                    )}
                                </div>

                                {parseError && (
                                    <div className="mt-4 p-4 bg-red-50 border border-red-100 rounded-xl flex items-start gap-3 text-red-600">
                                        <AlertCircle className="w-5 h-5 shrink-0 mt-0.5" />
                                        <span className="text-sm">{parseError}</span>
                                    </div>
                                )}

                                <button
                                    onClick={handleParse}
                                    disabled={!file || parsing}
                                    className="mt-6 w-full bg-[#0b3680] text-white py-4 rounded-xl font-medium hover:bg-[#092960] transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                                >
                                    {parsing ? (
                                        <>
                                            <Loader2 className="w-5 h-5 animate-spin" />
                                            Processando extrato...
                                        </>
                                    ) : (
                                        <>
                                            Processar Extrato
                                            <ArrowRight className="w-4 h-4" />
                                        </>
                                    )}
                                </button>
                            </motion.div>
                        )}

                        {/* STEP 2: Review & Categorize */}
                        {step === 2 && (
                            <motion.div
                                key="step2"
                                initial={{ opacity: 0, x: -20 }}
                                animate={{ opacity: 1, x: 0 }}
                                exit={{ opacity: 0, x: 20 }}
                                className="space-y-4"
                            >
                                {/* AI Categorization Bar */}
                                <div className="flex items-center gap-2 p-3 bg-gradient-to-r from-[#0b3680]/5 to-purple-50 rounded-xl border border-[#0b3680]/10">
                                    <Sparkles className="w-4 h-4 text-[#0b3680] shrink-0" />
                                    <input
                                        type="text"
                                        placeholder='Instrua a IA: "Restaurante é Alimentação, Uber é Transporte..."'
                                        value={instruction}
                                        onChange={(e) => setInstruction(e.target.value)}
                                        onKeyDown={(e) => e.key === "Enter" && handleCategorize()}
                                        className="flex-1 bg-transparent outline-none text-sm text-gray-700 placeholder:text-gray-400"
                                    />

                                    {/* Mic button */}
                                    <button
                                        onClick={isRecording ? stopRecording : startRecording}
                                        disabled={categorizing}
                                        className={`p-2 rounded-lg transition-all ${isRecording
                                            ? "bg-red-100 text-red-600 animate-pulse"
                                            : "hover:bg-[#0b3680]/10 text-gray-400 hover:text-[#0b3680]"
                                            }`}
                                        title={isRecording ? "Parar gravação" : "Gravar instrução por áudio"}
                                    >
                                        {isRecording ? <MicOff className="w-4 h-4" /> : <Mic className="w-4 h-4" />}
                                    </button>

                                    <button
                                        onClick={() => handleCategorize()}
                                        disabled={categorizing || (!instruction.trim() && !isRecording)}
                                        className="px-4 py-2 bg-[#0b3680] text-white text-xs font-semibold rounded-lg hover:bg-[#092960] disabled:opacity-50 disabled:cursor-not-allowed transition-all flex items-center gap-1.5"
                                    >
                                        {categorizing ? (
                                            <Loader2 className="w-3.5 h-3.5 animate-spin" />
                                        ) : (
                                            <Sparkles className="w-3.5 h-3.5" />
                                        )}
                                        Categorizar
                                    </button>
                                </div>

                                {/* Select All / Info Bar */}
                                <div className="flex items-center justify-between px-2">
                                    <button
                                        onClick={toggleSelectAll}
                                        className="flex items-center gap-2 text-sm font-medium text-gray-500 hover:text-[#0b3680] transition-colors"
                                    >
                                        {transactions.every(t => t.selected)
                                            ? <CheckSquare className="w-4 h-4 text-[#0b3680]" />
                                            : <Square className="w-4 h-4" />
                                        }
                                        {transactions.every(t => t.selected) ? "Desmarcar Todos" : "Selecionar Todos"}
                                    </button>
                                    <span className="text-xs text-gray-400">
                                        {selectedTransactions.length} de {transactions.length} selecionadas
                                    </span>
                                </div>

                                {/* Transaction List */}
                                <div className="border border-gray-100 rounded-2xl overflow-hidden">
                                    {/* Table header */}
                                    <div className="grid grid-cols-12 gap-2 px-4 py-3 bg-gray-50 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">
                                        <div className="col-span-1"></div>
                                        <div className="col-span-1">Tipo</div>
                                        <div className="col-span-2">Data</div>
                                        <div className="col-span-3">Descrição</div>
                                        <div className="col-span-2 text-right">Valor</div>
                                        <div className="col-span-3">Categoria</div>
                                    </div>

                                    <div className="max-h-[360px] overflow-y-auto divide-y divide-gray-50">
                                        {transactions.map((t) => (
                                            <div
                                                key={t.id}
                                                className={`grid grid-cols-12 gap-2 px-4 py-3 items-center text-sm transition-all ${!t.selected ? "opacity-40 bg-gray-50/50" : "hover:bg-gray-50/50"
                                                    }`}
                                            >
                                                {/* Checkbox */}
                                                <div className="col-span-1">
                                                    <button onClick={() => toggleSelect(t.id)}>
                                                        {t.selected
                                                            ? <CheckSquare className="w-4 h-4 text-[#0b3680]" />
                                                            : <Square className="w-4 h-4 text-gray-300" />
                                                        }
                                                    </button>
                                                </div>

                                                {/* Type toggle */}
                                                <div className="col-span-1">
                                                    <button
                                                        onClick={() => updateType(t.id, t.type === "income" ? "expense" : "income")}
                                                        className={`w-8 h-8 rounded-lg flex items-center justify-center transition-colors ${t.type === "income"
                                                            ? "bg-emerald-100 text-emerald-600"
                                                            : "bg-red-100 text-red-500"
                                                            }`}
                                                        title="Clique para alternar tipo"
                                                    >
                                                        {t.type === "income"
                                                            ? <ArrowUpRight className="w-4 h-4" />
                                                            : <ArrowDownRight className="w-4 h-4" />
                                                        }
                                                    </button>
                                                </div>

                                                {/* Date */}
                                                <div className="col-span-2 text-xs text-gray-500 font-medium">
                                                    {new Date(t.date + "T00:00:00").toLocaleDateString("pt-BR", { day: "2-digit", month: "short" })}
                                                </div>

                                                {/* Description */}
                                                <div className="col-span-3 text-gray-800 font-medium truncate text-xs" title={t.description}>
                                                    {t.description}
                                                </div>

                                                {/* Amount */}
                                                <div className={`col-span-2 text-right font-bold text-sm ${t.type === "income" ? "text-emerald-600" : "text-red-500"}`}>
                                                    {t.type === "income" ? "+" : "-"} R$ {formatCurrency(t.amount)}
                                                </div>

                                                {/* Category */}
                                                <div className="col-span-3">
                                                    <select
                                                        value={t.category}
                                                        onChange={(e) => updateCategory(t.id, e.target.value)}
                                                        className="w-full text-xs px-2 py-1.5 rounded-lg border border-gray-200 focus:border-[#0b3680] outline-none bg-white"
                                                    >
                                                        {CATEGORIES.map(c => (
                                                            <option key={c} value={c}>{c}</option>
                                                        ))}
                                                    </select>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                {/* Navigation */}
                                <div className="flex items-center justify-between pt-2">
                                    <button
                                        onClick={() => setStep(1)}
                                        className="flex items-center gap-2 text-sm font-medium text-gray-500 hover:text-[#0b3680] transition-colors"
                                    >
                                        <ArrowLeft className="w-4 h-4" /> Voltar
                                    </button>
                                    <button
                                        onClick={() => setStep(3)}
                                        disabled={selectedTransactions.length === 0}
                                        className="flex items-center gap-2 bg-[#0b3680] text-white px-6 py-3 rounded-xl font-medium text-sm hover:bg-[#092960] transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        Revisar & Importar
                                        <ArrowRight className="w-4 h-4" />
                                    </button>
                                </div>
                            </motion.div>
                        )}

                        {/* STEP 3: Confirmation */}
                        {step === 3 && (
                            <motion.div
                                key="step3"
                                initial={{ opacity: 0, x: -20 }}
                                animate={{ opacity: 1, x: 0 }}
                                exit={{ opacity: 0, x: 20 }}
                                className="space-y-6"
                            >
                                {/* Summary Cards */}
                                <div className="grid grid-cols-3 gap-4">
                                    <div className="bg-gray-50 p-5 rounded-2xl text-center">
                                        <p className="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Transações</p>
                                        <p className="text-2xl font-bold text-[#0b3680]">{selectedTransactions.length}</p>
                                    </div>
                                    <div className="bg-emerald-50 p-5 rounded-2xl text-center">
                                        <p className="text-[11px] font-semibold text-emerald-400 uppercase tracking-wider mb-1">Receitas</p>
                                        <p className="text-2xl font-bold text-emerald-600">R$ {formatCurrency(totalIncome)}</p>
                                    </div>
                                    <div className="bg-red-50 p-5 rounded-2xl text-center">
                                        <p className="text-[11px] font-semibold text-red-400 uppercase tracking-wider mb-1">Despesas</p>
                                        <p className="text-2xl font-bold text-red-500">R$ {formatCurrency(totalExpense)}</p>
                                    </div>
                                </div>

                                {/* Mini preview */}
                                <div className="border border-gray-100 rounded-2xl overflow-hidden">
                                    <div className="px-4 py-3 bg-gray-50 text-[11px] font-semibold text-gray-400 uppercase tracking-wider">
                                        Prévia das Transações
                                    </div>
                                    <div className="max-h-[240px] overflow-y-auto divide-y divide-gray-50">
                                        {selectedTransactions.map(t => (
                                            <div key={t.id} className="flex items-center justify-between px-4 py-3">
                                                <div className="flex items-center gap-3">
                                                    <div className={`w-8 h-8 rounded-lg flex items-center justify-center ${t.type === "income" ? "bg-emerald-100 text-emerald-600" : "bg-red-100 text-red-500"}`}>
                                                        {t.type === "income" ? <ArrowUpRight className="w-4 h-4" /> : <ArrowDownRight className="w-4 h-4" />}
                                                    </div>
                                                    <div>
                                                        <p className="text-sm font-medium text-gray-800 truncate max-w-[200px]">{t.description}</p>
                                                        <p className="text-xs text-gray-400">{t.category} · {new Date(t.date + "T00:00:00").toLocaleDateString("pt-BR")}</p>
                                                    </div>
                                                </div>
                                                <span className={`font-bold text-sm ${t.type === "income" ? "text-emerald-600" : "text-red-500"}`}>
                                                    {t.type === "income" ? "+" : "-"} R$ {formatCurrency(t.amount)}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                {importError && (
                                    <div className="p-4 bg-red-50 border border-red-100 rounded-xl flex items-start gap-3 text-red-600">
                                        <AlertCircle className="w-5 h-5 shrink-0 mt-0.5" />
                                        <span className="text-sm">{importError}</span>
                                    </div>
                                )}

                                {/* Actions */}
                                <div className="flex items-center justify-between">
                                    <button
                                        onClick={() => setStep(2)}
                                        className="flex items-center gap-2 text-sm font-medium text-gray-500 hover:text-[#0b3680] transition-colors"
                                    >
                                        <ArrowLeft className="w-4 h-4" /> Voltar
                                    </button>
                                    <button
                                        onClick={handleImport}
                                        disabled={importing}
                                        className="flex items-center gap-2 bg-emerald-600 text-white px-8 py-3.5 rounded-xl font-semibold text-sm hover:bg-emerald-700 transition-all disabled:opacity-70 disabled:cursor-not-allowed shadow-lg shadow-emerald-600/20"
                                    >
                                        {importing ? (
                                            <>
                                                <Loader2 className="w-5 h-5 animate-spin" />
                                                Importando...
                                            </>
                                        ) : (
                                            <>
                                                <Check className="w-5 h-5" />
                                                Importar {selectedTransactions.length} Transações
                                            </>
                                        )}
                                    </button>
                                </div>
                            </motion.div>
                        )}
                    </AnimatePresence>
                </div>
            </motion.div>
        </div>
    );
}
