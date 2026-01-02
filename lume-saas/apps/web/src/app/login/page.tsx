"use client";

import Link from "next/link";
import Image from "next/image";
import { useState } from "react";
import { ArrowLeft, Loader2, ArrowRight, AlertCircle } from "lucide-react";
import { createClient } from "@/lib/supabase/client";
import { useRouter } from "next/navigation";

export default function LoginPage() {
    const supabase = createClient();
    const router = useRouter();
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const [formData, setFormData] = useState({
        email: '',
        password: '',
    });

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setFormData({ ...formData, [e.target.name]: e.target.value });
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setError(null);

        const { error } = await supabase.auth.signInWithPassword({
            email: formData.email,
            password: formData.password,
        });

        if (error) {
            setError(error.message === 'Invalid login credentials'
                ? 'Email ou senha incorretos'
                : error.message
            );
            setLoading(false);
            return;
        }

        router.push('/dashboard');
    };

    return (
        <div className="min-h-screen grid grid-cols-1 md:grid-cols-2 bg-white font-[var(--font-inter)]">
            {/* Left Column - Form */}
            <div className="flex flex-col justify-center p-8 md:p-24 relative order-2 md:order-1">
                <Link
                    href="/"
                    className="absolute top-8 left-8 text-sm text-gray-500 hover:text-[#0b3680] flex items-center gap-2 transition-colors"
                >
                    <ArrowLeft className="w-4 h-4" />
                    Voltar
                </Link>

                <div className="max-w-[400px] w-full mx-auto">
                    <div className="mb-10">
                        <div className="flex items-center gap-3 mb-6 md:hidden">
                            <div className="relative w-10 h-10 rounded-lg overflow-hidden">
                                <Image src="/logo.jpg" alt="Lume" fill className="object-cover" />
                            </div>
                            <span className="text-xl font-medium text-[#0b3680] font-[var(--font-playfair)]">Lume</span>
                        </div>
                        <h1 className="text-3xl font-semibold text-gray-900 mb-2 font-[var(--font-playfair)]">Bem-vindo de volta</h1>
                        <p className="text-gray-500">Insira suas credenciais para acessar sua conta.</p>
                    </div>

                    {error && (
                        <div className="mb-6 p-4 bg-red-50 border border-red-100 rounded-xl flex items-start gap-3 text-red-600">
                            <AlertCircle className="w-5 h-5 shrink-0 mt-0.5" />
                            <span className="text-sm">{error}</span>
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="space-y-4">
                            <div className="space-y-1.5">
                                <label className="text-sm font-medium text-gray-700 block">Email</label>
                                <input
                                    type="email"
                                    name="email"
                                    placeholder="voce@exemplo.com"
                                    value={formData.email}
                                    onChange={handleChange}
                                    required
                                    className="w-full px-4 py-3 rounded-lg border border-gray-200 bg-gray-50 focus:bg-white focus:border-[#0b3680] focus:ring-1 focus:ring-[#0b3680] outline-none transition-all placeholder:text-gray-400 text-gray-900"
                                />
                            </div>
                            <div className="space-y-1.5">
                                <div className="flex justify-between items-center">
                                    <label className="text-sm font-medium text-gray-700">Senha</label>
                                    <Link href="/forgot-password" className="text-xs text-[#0b3680] hover:underline">
                                        Esqueceu a senha?
                                    </Link>
                                </div>
                                <input
                                    type="password"
                                    name="password"
                                    placeholder="••••••••"
                                    value={formData.password}
                                    onChange={handleChange}
                                    required
                                    className="w-full px-4 py-3 rounded-lg border border-gray-200 bg-gray-50 focus:bg-white focus:border-[#0b3680] focus:ring-1 focus:ring-[#0b3680] outline-none transition-all placeholder:text-gray-400 text-gray-900"
                                />
                            </div>
                        </div>

                        <button
                            type="submit"
                            disabled={loading}
                            className="w-full bg-[#0b3680] text-white py-3.5 rounded-xl font-medium hover:bg-[#092960] active:scale-[0.98] transition-all disabled:opacity-70 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                        >
                            {loading ? (
                                <Loader2 className="w-5 h-5 animate-spin" />
                            ) : (
                                <>
                                    Entrar
                                    <ArrowRight className="w-4 h-4" />
                                </>
                            )}
                        </button>
                    </form>

                    <div className="mt-8 text-center text-sm text-gray-500">
                        Não tem uma conta?{' '}
                        <Link href="/register" className="text-[#0b3680] font-medium hover:underline">
                            Criar conta gratuita
                        </Link>
                    </div>
                </div>
            </div>

            {/* Right Column - Branding */}
            <div className="hidden md:flex flex-col relative bg-[#0b3680] text-white p-12 overflow-hidden items-center justify-center text-center order-1 md:order-2">
                <div className="relative z-10 max-w-md">
                    <div className="mb-8 flex justify-center">
                        <div className="relative w-20 h-20 rounded-2xl overflow-hidden shadow-2xl">
                            <Image src="/logo.jpg" alt="Lume" fill className="object-cover" />
                        </div>
                    </div>
                    <h2 className="text-4xl font-semibold mb-6 font-[var(--font-playfair)]">
                        Seu patrimônio sob controle.
                    </h2>
                    <p className="text-blue-100 text-lg leading-relaxed">
                        Acesse seu dashboard e acompanhe a evolução dos seus investimentos em tempo real.
                    </p>
                </div>
            </div>
        </div>
    );
}
