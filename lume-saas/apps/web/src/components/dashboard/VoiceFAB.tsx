"use client";

import { useState, useRef } from "react";
import { Mic, Square, Loader2, Sparkles, Check, X } from "lucide-react";
import { motion, AnimatePresence } from "framer-motion";
import { useRouter } from "next/navigation";

export function VoiceFAB() {
    const router = useRouter();
    const [isRecording, setIsRecording] = useState(false);
    const [isProcessing, setIsProcessing] = useState(false);
    const [result, setResult] = useState<{ type: 'success' | 'error', message: string } | null>(null);
    const mediaRecorderRef = useRef<MediaRecorder | null>(null);
    const chunksRef = useRef<Blob[]>([]);

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
                await processAudio(blob);
                stream.getTracks().forEach(track => track.stop());
            };

            mediaRecorderRef.current.start();
            setIsRecording(true);
            setResult(null);
        } catch (err) {
            console.error("Access denied", err);
            alert("Permissão de microfone negada. Verifique suas configurações.");
        }
    };

    const stopRecording = () => {
        if (mediaRecorderRef.current && isRecording) {
            mediaRecorderRef.current.stop();
            setIsRecording(false);
            setIsProcessing(true);
        }
    };

    const processAudio = async (audioBlob: Blob) => {
        const formData = new FormData();
        formData.append("audio", audioBlob, "recording.webm");

        try {
            const response = await fetch("/api/ai/process", {
                method: "POST",
                body: formData,
            });

            // Check if response is JSON
            const contentType = response.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                const text = await response.text();
                console.error("Non-JSON response:", text);
                throw new Error("Erro no servidor (500). Verifique a chave de API.");
            }

            const data = await response.json();

            if (!response.ok) throw new Error(data.error || "Erro ao processar");

            if (data.actionsExecuted) {
                setResult({
                    type: 'success',
                    message: data.message || "Comando processado!"
                });

                // Refresh to show changes
                router.refresh();
                window.dispatchEvent(new Event("transaction-updated"));

                // Clear success message after 3s
                setTimeout(() => setResult(null), 3000);
            } else {
                // Handover to Chatbot (Clarification/Reply)
                window.dispatchEvent(new CustomEvent("open-ai-chat", {
                    detail: {
                        userMessage: data.transcript,
                        aiMessage: data.message
                    }
                }));
                // No toast needed, chat opens
            }

        } catch (error: any) {
            setResult({
                type: 'error',
                message: "Erro: " + error.message
            });
            setTimeout(() => setResult(null), 4000);
        } finally {
            setIsProcessing(false);
        }
    };

    return (
        <div className="fixed bottom-8 right-8 z-[100]">
            <AnimatePresence>
                {result && (
                    <motion.div
                        initial={{ opacity: 0, y: 10, scale: 0.9 }}
                        animate={{ opacity: 1, y: 0, scale: 1 }}
                        exit={{ opacity: 0, y: 10, scale: 0.9 }}
                        className={`absolute bottom-20 right-0 mb-4 px-6 py-4 rounded-2xl shadow-xl flex items-center gap-3 w-max max-w-sm border ${result.type === 'success'
                            ? 'bg-white border-emerald-100 text-emerald-700'
                            : 'bg-white border-red-100 text-red-600'
                            }`}
                    >
                        <div className={`p-2 rounded-full ${result.type === 'success' ? 'bg-emerald-100' : 'bg-red-100'}`}>
                            {result.type === 'success' ? <Check className="w-4 h-4" /> : <X className="w-4 h-4" />}
                        </div>
                        <p className="font-medium text-sm">{result.message}</p>
                    </motion.div>
                )}
            </AnimatePresence>

            <motion.button
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
                onClick={isRecording ? stopRecording : startRecording}
                disabled={isProcessing}
                className={`relative w-16 h-16 rounded-full shadow-[0_8px_30px_rgba(0,0,0,0.12)] flex items-center justify-center transition-all bg-[#0b3680] text-white hover:bg-[#092a66]`}
            >
                {/* Ping Animation while recording */}
                {isRecording && (
                    <span className="absolute inset-0 rounded-full bg-red-500 opacity-20 animate-ping" />
                )}

                {isProcessing ? (
                    <Loader2 className="w-6 h-6 animate-spin" />
                ) : isRecording ? (
                    <Square className="w-6 h-6 fill-current" />
                ) : (
                    <div className="relative">
                        <Mic className="w-7 h-7" />
                        <div className="absolute -top-1 -right-1">
                            <Sparkles className="w-3 h-3 text-yellow-300 animate-pulse" />
                        </div>
                    </div>
                )}
            </motion.button>

            {/* Tooltip hint when idle */}
            {!isRecording && !isProcessing && !result && (
                <div className="absolute right-20 top-1/2 -translate-y-1/2 bg-black/80 text-white text-xs px-3 py-1.5 rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                    Fale com a IA
                </div>
            )}
        </div>
    );
}
