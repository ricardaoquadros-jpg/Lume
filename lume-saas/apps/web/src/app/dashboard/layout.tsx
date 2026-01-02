"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { LayoutDashboard, Wallet, CreditCard, PieChart, Bell, Settings, LogOut, Menu } from "lucide-react";
import Image from "next/image";
import { useState } from "react";
import { createClient } from "@/lib/supabase/client";
import { cn } from "@/lib/utils";
import { motion } from "framer-motion";
import { VoiceFAB } from "@/components/dashboard/VoiceFAB";
import { AiAssistantFAB } from "@/components/dashboard/AiAssistantFAB";

const sidebarItems = [
    { icon: LayoutDashboard, label: "Visão Geral", href: "/dashboard" },
    { icon: Wallet, label: "Transações", href: "/dashboard/transacoes" },
    { icon: CreditCard, label: "Recorrências", href: "/dashboard/recorrencias" },
    { icon: PieChart, label: "Investimentos", href: "/dashboard/investimentos" },
];

export default function DashboardLayout({ children }: { children: React.ReactNode }) {
    const pathname = usePathname();
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
    const router = useRouter();
    const supabase = createClient();

    const handleLogout = async () => {
        await supabase.auth.signOut();
        router.push("/login"); // or /
    };

    return (
        <div className="min-h-screen bg-[#F5F7FA] flex text-[#0b3680] font-[var(--font-inter)]">
            {/* Sidebar - Desktop */}
            <aside className="hidden md:flex flex-col w-64 bg-white border-r border-[#E6EAF0] fixed h-full z-10 transition-all duration-300">
                <div className="p-8 flex items-center gap-3">
                    <div className="relative w-12 h-12 rounded-xl overflow-hidden shrink-0 shadow-sm">
                        <Image
                            src="/logo.jpg"
                            alt="Lume"
                            fill
                            className="object-cover"
                        />
                    </div>
                    <span className="text-2xl font-medium font-[var(--font-playfair)] tracking-tight">Lume</span>
                </div>

                <nav className="flex-1 px-4 py-6 space-y-1">
                    {sidebarItems.map((item) => {
                        const isActive = pathname === item.href;
                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={cn(
                                    "flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 group relative overflow-hidden",
                                    isActive
                                        ? "bg-[#0b3680] text-white shadow-lg shadow-[#0b3680]/15"
                                        : "text-gray-500 hover:bg-gray-50 hover:text-[#0b3680]"
                                )}
                            >
                                <item.icon className={cn("w-5 h-5", isActive ? "text-white" : "text-gray-400 group-hover:text-[#0b3680]")} />
                                <span className="relative z-10">{item.label}</span>
                            </Link>
                        );
                    })}
                </nav>

                <div className="p-4 border-t border-[#E6EAF0]">
                    <button
                        onClick={handleLogout}
                        className="flex items-center gap-3 px-4 py-3 w-full text-sm font-medium text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-xl transition-colors"
                    >
                        <LogOut className="w-5 h-5" />
                        <span>Sair</span>
                    </button>
                </div>
            </aside>

            {/* Mobile Header */}
            <div className="md:hidden fixed top-0 w-full bg-white border-b border-[#E6EAF0] z-20 px-6 py-4 flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <div className="relative w-10 h-10 rounded-lg overflow-hidden shrink-0 shadow-sm">
                        <Image
                            src="/logo.jpg"
                            alt="Lume"
                            fill
                            className="object-cover"
                        />
                    </div>
                    <span className="text-xl font-medium font-[var(--font-playfair)]">Lume</span>
                </div>
                <button onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}>
                    <Menu className="w-6 h-6 text-[#0b3680]" />
                </button>
            </div>

            {/* Main Content */}
            <main className="flex-1 md:ml-64 p-6 md:p-12 pt-24 md:pt-12 overflow-y-auto">
                <div className="max-w-7xl mx-auto">
                    {children}
                </div>
            </main>

            {/* Mobile Menu Overlay */}
            {isMobileMenuOpen && (
                <motion.div
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    exit={{ opacity: 0 }}
                    className="fixed inset-0 bg-black/50 z-30 md:hidden"
                    onClick={() => setIsMobileMenuOpen(false)}
                >
                    <motion.div
                        initial={{ x: "-100%" }}
                        animate={{ x: 0 }}
                        className="bg-white w-3/4 h-full p-6"
                        onClick={e => e.stopPropagation()}
                    >
                        <div className="flex items-center gap-3 mb-8">
                            <div className="relative w-12 h-12 rounded-xl overflow-hidden shrink-0 shadow-sm">
                                <Image
                                    src="/logo.jpg"
                                    alt="Lume"
                                    fill
                                    className="object-cover"
                                />
                            </div>
                            <span className="text-2xl font-medium font-[var(--font-playfair)]">Lume</span>
                        </div>
                        <nav className="space-y-2">
                            {sidebarItems.map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    onClick={() => setIsMobileMenuOpen(false)}
                                    className={cn(
                                        "flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium",
                                        pathname === item.href
                                            ? "bg-[#0b3680] text-white"
                                            : "text-gray-500 hover:text-[#0b3680]"
                                    )}
                                >
                                    <item.icon className="w-5 h-5" />
                                    <span>{item.label}</span>
                                </Link>
                            ))}
                        </nav>
                    </motion.div>
                </motion.div>
            )}

            <VoiceFAB />
            <AiAssistantFAB />
        </div>
    );
}
