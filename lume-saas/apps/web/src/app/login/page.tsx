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

                    {/* Divider */}
                    <div className="my-6 flex items-center gap-4">
                        <div className="flex-1 h-px bg-gray-200"></div>
                        <span className="text-sm text-gray-400">ou</span>
                        <div className="flex-1 h-px bg-gray-200"></div>
                    </div>

                    {/* Google Login */}
                    <button
                        type="button"
                        disabled={loading}
                        onClick={async () => {
                            setLoading(true);
                            const { error } = await supabase.auth.signInWithOAuth({
                                provider: 'google',
                                options: {
                                    redirectTo: `${window.location.origin}/auth/callback`
                                }
                            });
                            if (error) {
                                setError(error.message);
                                setLoading(false);
                            }
                        }}
                        className="w-full bg-white border border-gray-200 text-gray-700 py-3.5 rounded-xl font-medium hover:bg-gray-50 active:scale-[0.98] transition-all disabled:opacity-70 disabled:cursor-not-allowed flex items-center justify-center gap-3"
                    >
                        <svg className="w-5 h-5" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                        </svg>
                        Entrar com Google
                    </button>

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
