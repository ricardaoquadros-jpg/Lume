"use client";

import Link from "next/link";
import Image from "next/image";
import { useState } from "react";
import { ArrowLeft, Loader2, Check, AlertCircle } from "lucide-react";
import { createClient } from "@/lib/supabase/client";
import { useRouter } from "next/navigation";

export default function RegisterPage() {
    const supabase = createClient();
    const router = useRouter();
    const [loading, setLoading] = useState(false);
    const [success, setSuccess] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const [formData, setFormData] = useState({
        firstName: '',
        lastName: '',
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

        const { data, error } = await supabase.auth.signUp({
            email: formData.email,
            password: formData.password,
            options: {
                data: {
                    first_name: formData.firstName,
                    last_name: formData.lastName,
                },
                emailRedirectTo: `${window.location.origin}/auth/callback`,
            },
        });

        if (error) {
            setError(error.message);
            setLoading(false);
            return;
        }

        // Check if user is immediately logged in (email confirmation disabled)
        if (data.session) {
            setSuccess(true);
            setLoading(false);
            setTimeout(() => {
                router.push('/dashboard');
            }, 1000);
        } else {
            // Email confirmation required
            setSuccess(true);
            setLoading(false);
            setError('Verifique seu email para confirmar a conta.');
        }
    };

    return (
        <div className="min-h-screen grid grid-cols-1 md:grid-cols-2 bg-white font-[var(--font-inter)]">
            {/* Left Column - Branding */}
            <div className="hidden md:flex flex-col relative bg-[#0b3680] text-white p-12 overflow-hidden items-center justify-center text-center">
                <div className="relative z-10 max-w-md">
                    <div className="mb-8 flex justify-center">
                        <div className="relative w-20 h-20 rounded-2xl overflow-hidden shadow-2xl">
                            <Image src="/logo.jpg" alt="Lume" fill className="object-cover" />
                        </div>
                    </div>
                    <h2 className="text-4xl font-semibold mb-6 font-[var(--font-playfair)]">
                        Comece sua jornada financeira hoje.
                    </h2>
                    <p className="text-blue-100 text-lg leading-relaxed">
                        Junte-se a milhares de pessoas que já controlam suas finanças com elegância e simplicidade.
                    </p>
                </div>

                {/* Testimonial */}
                <div className="absolute bottom-12 left-12 right-12 text-left bg-white/10 backdrop-blur-sm p-6 rounded-2xl border border-white/10">
                    <div className="flex gap-1 text-yellow-400 mb-3">★★★★★</div>
                    <p className="mb-4 text-blue-50 italic">"O Lume transformou completamente a maneira como vejo meu dinheiro. É simples, bonito e direto ao ponto."</p>
                    <div className="flex items-center gap-3">
                        <div className="w-8 h-8 rounded-full bg-white/20" />
                        <div className="text-sm">
                            <div className="font-medium">Sofia Martins</div>
                            <div className="text-blue-200 text-xs">Designer de Produto</div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Right Column - Form */}
            <div className="flex flex-col justify-center p-8 md:p-24 relative">
                <Link
                    href="/"
                    className="absolute top-8 left-8 text-sm text-gray-500 hover:text-[#0b3680] flex items-center gap-2 transition-colors"
                >
                    <ArrowLeft className="w-4 h-4" />
                    Voltar
                </Link>

                <div className="max-w-[400px] w-full mx-auto">
                    <div className="mb-10">
                        <h1 className="text-3xl font-semibold text-gray-900 mb-2 font-[var(--font-playfair)]">Criar conta</h1>
                        <p className="text-gray-500">Informe seus dados para acessar o Lume.</p>
                    </div>

                    {error && (
                        <div className="mb-6 p-4 bg-red-50 border border-red-100 rounded-xl flex items-start gap-3 text-red-600">
                            <AlertCircle className="w-5 h-5 shrink-0 mt-0.5" />
                            <span className="text-sm">{error}</span>
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <InputGroup label="Nome" name="firstName" type="text" placeholder="Seu nome" value={formData.firstName} onChange={handleChange} />
                                <InputGroup label="Sobrenome" name="lastName" type="text" placeholder="Sobrenome" value={formData.lastName} onChange={handleChange} />
                            </div>
                            <InputGroup label="Email" name="email" type="email" placeholder="voce@exemplo.com" value={formData.email} onChange={handleChange} />
                            <InputGroup label="Senha" name="password" type="password" placeholder="Mínimo 6 caracteres" value={formData.password} onChange={handleChange} />
                        </div>

                        <div className="flex items-center gap-2">
                            <input type="checkbox" id="terms" className="rounded border-gray-300 text-[#0b3680] focus:ring-[#0b3680]" required />
                            <label htmlFor="terms" className="text-sm text-gray-500">
                                Concordo com os <Link href="/terms" className="text-[#0b3680] hover:underline">Termos</Link> e <Link href="/privacy" className="text-[#0b3680] hover:underline">Privacidade</Link>
                            </label>
                        </div>

                        <button
                            type="submit"
                            disabled={loading}
                            className="w-full bg-[#0b3680] text-white py-3.5 rounded-xl font-medium hover:bg-[#092960] active:scale-[0.98] transition-all disabled:opacity-70 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                        >
                            {loading ? <Loader2 className="w-5 h-5 animate-spin" /> : (success ? <><Check className="w-5 h-5" /> Conta criada!</> : "Criar minha conta")}
                        </button>
                    </form>

                    <div className="mt-8 text-center text-sm text-gray-500">
                        Já tem uma conta?{' '}
                        <Link href="/login" className="text-[#0b3680] font-medium hover:underline">
                            Fazer login
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
}

function InputGroup({ label, name, type, placeholder, value, onChange }: { label: string; name: string; type: string; placeholder: string; value: string; onChange: (e: React.ChangeEvent<HTMLInputElement>) => void }) {
    return (
        <div className="space-y-1.5">
            <label className="text-sm font-medium text-gray-700 block">{label}</label>
            <input
                type={type}
                name={name}
                placeholder={placeholder}
                value={value}
                onChange={onChange}
                required
                className="w-full px-4 py-3 rounded-lg border border-gray-200 bg-gray-50 focus:bg-white focus:border-[#0b3680] focus:ring-1 focus:ring-[#0b3680] outline-none transition-all placeholder:text-gray-400 text-gray-900"
            />
        </div>
    );
}
