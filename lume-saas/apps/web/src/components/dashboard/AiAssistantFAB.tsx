"use client";

import { useState, useRef, useEffect } from "react";
import { Bot, Send, X, Loader2, Sparkles, AlertTriangle } from "lucide-react";
import { motion, AnimatePresence } from "framer-motion";
import { useRouter } from "next/navigation";

type Message = {
    role: "user" | "assistant";
    content: string;
    type?: "text" | "confirmation";
    actionData?: any; // To store data for confirmation
};

export function AiAssistantFAB() {
    const router = useRouter();
    const [isOpen, setIsOpen] = useState(false);
    const [messages, setMessages] = useState<Message[]>([
        { role: "assistant", content: "Olá! Sou seu assistente financeiro. Posso ajudar a adicionar, remover ou analisar suas transações." }
    ]);
    const [input, setInput] = useState("");
    const [isTyping, setIsTyping] = useState(false);
    const messagesEndRef = useRef<HTMLDivElement>(null);

    const scrollToBottom = () => {
        messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
    };

    useEffect(() => {
        scrollToBottom();
    }, [messages, isOpen]);

    // Listen for VoiceFAB handover
    useEffect(() => {
        const handleOpenChat = (event: any) => {
            const { userMessage, aiMessage } = event.detail;
            setIsOpen(true);
            setMessages(prev => [
                ...prev,
                { role: "user", content: userMessage || "Áudio transcrito..." },
                { role: "assistant", content: aiMessage }
            ]);
        };

        window.addEventListener("open-ai-chat", handleOpenChat);
        return () => window.removeEventListener("open-ai-chat", handleOpenChat);
    }, []);

    // Listen for Auto-Trigger (e.g. from Dashboard "Smart Analysis" button)
    useEffect(() => {
        const handleTrigger = (event: any) => {
            const prompt = event.detail;
            setIsOpen(true);
            // Simulate typing and sending
            setInput(prompt);
            setTimeout(() => {
                // accessing handleSend directly matches the closure if defined here, 
                // but handleSend depends on 'input' state which might not be updated inside closure immediately 
                // unless we use a ref or pass arg. simpler to just call API directly here?
                // Actually, let's just update messages and call callAPI.
                setMessages(prev => [...prev, { role: "user", content: prompt }]);
                setIsTyping(true);
                fetch("/api/ai/process", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ text: prompt }),
                })
                    .then(res => res.json())
                    .then(data => {
                        setMessages(prev => [...prev, { role: "assistant", content: data.message }]);
                    })
                    .catch(() => {
                        setMessages(prev => [...prev, { role: "assistant", content: "Erro na análise." }]);
                    })
                    .finally(() => setIsTyping(false));
                setInput("");
            }, 500);
        };

        window.addEventListener("trigger-ai-analysis", handleTrigger);
        return () => window.removeEventListener("trigger-ai-analysis", handleTrigger);
    }, []);

    const handleSend = async () => {
        if (!input.trim()) return;

        const userMsg = input;
        setInput("");
        setMessages(prev => [...prev, { role: "user", content: userMsg }]);
        setIsTyping(true);

        try {
            const response = await fetch("/api/ai/process", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ text: userMsg }),
            });

            const data = await response.json();

            if (data.confirmationRequired) {
                // Handle Confirmation Flow
                setMessages(prev => [...prev, {
                    role: "assistant",
                    content: `Você deseja mesmo executar esta ação: ${data.actionDescription}?`,
                    type: "confirmation",
                    actionData: data.actionPayload
                }]);
            } else {
                // Global Success/Response
                setMessages(prev => [...prev, { role: "assistant", content: data.message }]);
                if (data.actionsExecuted) {
                    window.dispatchEvent(new Event("transaction-updated"));
                    router.refresh();
                }
            }
        } catch (error) {
            setMessages(prev => [...prev, { role: "assistant", content: "Desculpe, ocorreu um erro ao processar seu pedido." }]);
        } finally {
            setIsTyping(false);
        }
    };

    const handleConfirmAction = async (actionData: any) => {
        setIsTyping(true);
        // Remove confirmation buttons from UI visually (optional, or just append new message)

        try {
            const response = await fetch("/api/ai/process", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    confirmAction: true,
                    actionPayload: actionData
                }),
            });
            const data = await response.json();

            setMessages(prev => [...prev, { role: "assistant", content: data.message }]);
            if (data.actionsExecuted) {
                window.dispatchEvent(new Event("transaction-updated"));
                router.refresh();
            }

        } catch (error) {
            setMessages(prev => [...prev, { role: "assistant", content: "Erro ao executar ação confirmada." }]);
        } finally {
            setIsTyping(false);
        }
    };

    return (
        <div className="fixed bottom-8 left-8 z-[100]">
            <AnimatePresence>
                {isOpen && (
                    <motion.div
                        initial={{ opacity: 0, scale: 0.9, y: 20, originX: 0, originY: 1 }}
                        animate={{ opacity: 1, scale: 1, y: 0 }}
                        exit={{ opacity: 0, scale: 0.9, y: 20 }}
                        className="absolute bottom-16 left-0 w-[350px] md:w-[400px] bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden flex flex-col h-[500px]"
                    >
                        {/* Header */}
                        <div className="bg-[#0b3680] p-4 flex items-center justify-between text-white">
                            <div className="flex items-center gap-2">
                                <Bot className="w-5 h-5" />
                                <span className="font-semibold text-sm">Assistente Lume</span>
                            </div>
                            <button onClick={() => setIsOpen(false)} className="hover:bg-white/10 p-1 rounded-lg transition-colors">
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        {/* Messages */}
                        <div className="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50">
                            {messages.map((msg, idx) => (
                                <div key={idx} className={`flex ${msg.role === "user" ? "justify-end" : "justify-start"}`}>
                                    <div className={`
                                        max-w-[80%] rounded-2xl p-3 text-[14px] leading-relaxed
                                        ${msg.role === "user"
                                            ? "bg-[#0b3680] text-white rounded-tr-none"
                                            : "bg-white border border-gray-200 text-gray-700 rounded-tl-none shadow-sm"}
                                    `}>
                                        {msg.content}

                                        {/* Confirmation UI */}
                                        {msg.type === "confirmation" && (
                                            <div className="mt-3 flex gap-2">
                                                <button
                                                    onClick={() => handleConfirmAction(msg.actionData)}
                                                    className="flex-1 bg-emerald-100 text-emerald-700 py-2 rounded-lg text-xs font-bold hover:bg-emerald-200 transition-colors"
                                                >
                                                    Confirmar
                                                </button>
                                                <button
                                                    onClick={() => setMessages(prev => [...prev, { role: "assistant", content: "Ação cancelada." }])}
                                                    className="flex-1 bg-red-100 text-red-700 py-2 rounded-lg text-xs font-bold hover:bg-red-200 transition-colors"
                                                >
                                                    Cancelar
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            ))}
                            {isTyping && (
                                <div className="flex justify-start">
                                    <div className="bg-white border border-gray-200 rounded-2xl rounded-tl-none p-3 shadow-sm">
                                        <Loader2 className="w-4 h-4 animate-spin text-gray-400" />
                                    </div>
                                </div>
                            )}
                            <div ref={messagesEndRef} />
                        </div>

                        {/* Input */}
                        <div className="p-3 bg-white border-t border-gray-100 flex gap-2">
                            <input
                                type="text"
                                placeholder="Digite seu comando..."
                                value={input}
                                onChange={(e) => setInput(e.target.value)}
                                onKeyDown={(e) => e.key === "Enter" && handleSend()}
                                className="flex-1 bg-gray-50 border border-gray-200 rounded-xl px-4 py-2 text-sm outline-none focus:border-[#0b3680] transition-colors"
                            />
                            <button
                                onClick={handleSend}
                                disabled={!input.trim() || isTyping}
                                className="bg-[#0b3680] text-white p-2 rounded-xl hover:bg-[#092a66] disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                <Send className="w-5 h-5" />
                            </button>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>

            <motion.button
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
                onClick={() => setIsOpen(!isOpen)}
                className="w-14 h-14 bg-[#0b3680] text-white rounded-full flex items-center justify-center shadow-lg hover:bg-[#092a66] transition-colors"
            >
                {isOpen ? <X className="w-6 h-6" /> : <Bot className="w-7 h-7" />}
            </motion.button>
        </div>
    );
}
