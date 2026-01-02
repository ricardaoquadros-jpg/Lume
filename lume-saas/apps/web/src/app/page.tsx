"use client";

import Link from "next/link";
import Image from "next/image";
import { motion, useScroll, useTransform } from "framer-motion";
import { ArrowRight, Check } from "lucide-react";
import { useRef } from "react";

export default function Home() {
  const { scrollY } = useScroll();
  const opacity = useTransform(scrollY, [0, 100], [1, 0.8]);

  return (
    <div className="min-h-screen bg-white font-[var(--font-inter)] selection:bg-[#0b3680] selection:text-white">
      {/* Header */}
      <motion.header
        initial={{ y: -100 }}
        animate={{ y: 0 }}
        transition={{ duration: 0.6, ease: [0.16, 1, 0.3, 1] }}
        className="fixed top-0 w-full bg-white/80 backdrop-blur-md border-b border-gray-100 z-50"
      >
        <nav className="container mx-auto px-6 py-4 flex items-center justify-between">
          <Link href="/" className="flex items-center gap-3 group">
            <div className="relative overflow-hidden rounded-lg">
              <Image
                src="/logo.jpg"
                alt="Lume"
                width={36}
                height={36}
                className="transition-transform duration-500 group-hover:scale-110"
              />
            </div>
            <span className="text-xl font-medium text-[#0b3680] font-[var(--font-playfair)] tracking-tight">
              Lume
            </span>
          </Link>
          <div className="hidden md:flex items-center gap-8">
            <NavLink href="#features">Recursos</NavLink>
            <NavLink href="#pricing">Planos</NavLink>
            <NavLink href="/login">Entrar</NavLink>
            <Link
              href="/register"
              className="text-sm bg-[#0b3680] text-white px-5 py-2.5 rounded-full hover:bg-[#092960] transition-all hover:shadow-lg hover:shadow-[#0b3680]/20 active:scale-95"
            >
              Criar conta
            </Link>
          </div>
        </nav>
      </motion.header>

      {/* Hero */}
      <section className="pt-48 pb-32 overflow-hidden">
        <div className="container mx-auto px-6 text-center">
          <motion.div
            initial={{ opacity: 0, y: 40 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, ease: [0.16, 1, 0.3, 1], delay: 0.2 }}
          >
            <h1 className="text-6xl md:text-7xl font-semibold text-slate-900 tracking-tight max-w-4xl mx-auto leading-[1.1] font-[var(--font-playfair)]">
              Simplifique suas <br />
              <span className="text-[#0b3680]">finanças pessoais.</span>
            </h1>
          </motion.div>

          <motion.p
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, ease: [0.16, 1, 0.3, 1], delay: 0.4 }}
            className="mt-8 text-xl text-slate-500 max-w-xl mx-auto leading-relaxed"
          >
            Acompanhe gastos, investimentos e recorrências com elegância.
            Sem ruídos, apenas o essencial.
          </motion.p>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, ease: [0.16, 1, 0.3, 1], delay: 0.6 }}
            className="mt-12 flex items-center justify-center gap-6"
          >
            <Link
              href="/register"
              className="group inline-flex items-center gap-2 bg-[#0b3680] text-white px-8 py-4 rounded-full text-base font-medium transition-all hover:bg-[#092960] hover:shadow-xl hover:shadow-[#0b3680]/20 active:scale-95"
            >
              Começar agora
              <ArrowRight className="w-4 h-4 transition-transform group-hover:translate-x-1" />
            </Link>
            <Link
              href="#features"
              className="text-slate-600 hover:text-[#0b3680] transition-colors font-medium"
            >
              Saber mais
            </Link>
          </motion.div>
        </div>
      </section>

      {/* Features */}
      <section id="features" className="py-32 bg-slate-50 relative">
        <div className="container mx-auto px-6">
          <SectionHeader
            title="Recursos"
            subtitle="Tudo que você precisa para controlar seu patrimônio."
          />

          <div className="grid md:grid-cols-3 gap-8 max-w-6xl mx-auto">
            <Feature
              title="Transações Inteligentes"
              description="Registre receitas e despesas com categorização automática e feedbacks visuais instantâneos."
              delay={0.2}
            />
            <Feature
              title="Recorrências"
              description="Gestão automática de contas fixas e assinaturas. Nunca mais esqueça um pagamento."
              delay={0.3}
            />
            <Feature
              title="Investimentos"
              description="Monitore a evolução do seu patrimônio em tempo real com gráficos sofisticados."
              delay={0.4}
            />
          </div>
        </div>
      </section>

      {/* Pricing */}
      <section id="pricing" className="py-32">
        <div className="container mx-auto px-6">
          <SectionHeader
            title="Planos"
            subtitle="Escolha a melhor opção para sua jornada."
          />

          <div className="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
            <PricingCard
              name="Gratuito"
              price="0"
              features={[
                "50 transações por mês",
                "1 investimento",
                "Dashboard básico",
              ]}
              delay={0.2}
            />
            <PricingCard
              name="Pro"
              price="4,99"
              featured
              features={[
                "Transações ilimitadas",
                "5 investimentos",
                "Assistente IA",
                "Relatórios mensais",
              ]}
              delay={0.3}
            />
            <PricingCard
              name="Premium"
              price="12,99"
              features={[
                "Tudo do Pro",
                "Investimentos ilimitados",
                "IA avançada",
                "Exportar dados",
              ]}
              delay={0.4}
            />
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="border-t border-gray-100 py-16 bg-white">
        <div className="container mx-auto px-6 flex flex-col md:flex-row items-center justify-between gap-8">
          <div className="flex items-center gap-3 opacity-80 hover:opacity-100 transition-opacity">
            <div className="relative w-8 h-8 rounded-lg overflow-hidden grayscale hover:grayscale-0 transition-all">
              <Image src="/logo.jpg" alt="Lume" fill className="object-cover" />
            </div>
            <span className="text-sm font-medium text-slate-900">© 2026 Lume</span>
          </div>

          <div className="flex items-center gap-8">
            <FooterLink href="/privacy">Privacidade</FooterLink>
            <FooterLink href="/terms">Termos</FooterLink>
            <FooterLink href="mailto:contato@lume.app">Contato</FooterLink>
          </div>
        </div>
      </footer>
    </div>
  );
}

function SectionHeader({ title, subtitle }: { title: string; subtitle: string }) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true }}
      transition={{ duration: 0.8 }}
      className="text-center mb-24"
    >
      <h2 className="text-4xl font-semibold text-slate-900 mb-4 font-[var(--font-playfair)] tracking-tight">
        {title}
      </h2>
      <p className="text-xl text-slate-500 max-w-2xl mx-auto font-light">
        {subtitle}
      </p>
    </motion.div>
  );
}

function NavLink({ href, children }: { href: string; children: React.ReactNode }) {
  return (
    <Link
      href={href}
      className="text-sm text-slate-600 hover:text-[#0b3680] transition-colors font-medium"
    >
      {children}
    </Link>
  );
}

function FooterLink({ href, children }: { href: string; children: React.ReactNode }) {
  return (
    <Link
      href={href}
      className="text-sm text-slate-500 hover:text-[#0b3680] transition-colors"
    >
      {children}
    </Link>
  );
}

function Feature({ title, description, delay }: { title: string; description: string; delay: number }) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true }}
      transition={{ duration: 0.6, delay }}
      className="p-8 rounded-3xl bg-white border border-slate-100 shadow-sm hover:shadow-xl hover:shadow-[#0b3680]/5 hover:-translate-y-1 transition-all duration-300"
    >
      <h3 className="text-xl font-medium text-slate-900 mb-3 font-[var(--font-playfair)]">{title}</h3>
      <p className="text-slate-500 leading-relaxed font-light">{description}</p>
    </motion.div>
  );
}

function PricingCard({ name, price, features, featured, delay }: { name: string; price: string; features: string[]; featured?: boolean; delay: number }) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 30 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true }}
      transition={{ duration: 0.6, delay }}
      className={`p-10 rounded-3xl flex flex-col relative ${featured
        ? 'bg-[#0b3680] text-white shadow-2xl shadow-[#0b3680]/20 scale-105 z-10'
        : 'bg-white text-slate-900 border border-slate-100 hover:border-[#0b3680]/20 transition-colors'
        }`}
    >
      <h3 className={`text-lg font-medium mb-2 ${featured ? 'text-white/90' : 'text-slate-500'}`}>
        {name}
      </h3>
      <div className="mb-8 flex items-baseline gap-1">
        <span className="text-4xl font-semibold tracking-tight">R$ {price}</span>
        <span className={`text-sm ${featured ? 'text-white/60' : 'text-slate-400'}`}>/mês</span>
      </div>

      <ul className="space-y-4 flex-1 mb-10">
        {features.map((feature, i) => (
          <li key={i} className={`text-sm flex items-center gap-3 ${featured ? 'text-white/80' : 'text-slate-600'}`}>
            <Check className={`w-4 h-4 shrink-0 ${featured ? 'text-white' : 'text-[#0b3680]'}`} />
            {feature}
          </li>
        ))}
      </ul>

      <Link
        href="/register"
        className={`w-full text-center py-4 rounded-2xl text-sm font-semibold transition-all active:scale-95 ${featured
          ? 'bg-white text-[#0b3680] hover:bg-slate-50'
          : 'bg-slate-50 text-slate-900 hover:bg-slate-100'
          }`}
      >
        Assinar
      </Link>
    </motion.div>
  );
}
