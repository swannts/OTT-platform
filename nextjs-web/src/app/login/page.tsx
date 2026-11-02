'use client';

import React, { useState, Suspense } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';
import { Lock, Mail, ArrowRight, Eye, EyeOff } from 'lucide-react';
import Logo from '@/components/layout/Logo';

function LoginFormContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const redirect = searchParams?.get('redirect') || '/';
  const { login } = useAuth();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setIsSubmitting(true);
    try {
      await login({ email, password });
      router.push(redirect);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Invalid email or password.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const fillQuickAccount = (testEmail: string) => {
    setEmail(testEmail);
    setPassword('12345678');
    setError(null);
  };

  return (
    <div className="min-h-screen pt-28 pb-20 px-4 flex items-center justify-center relative overflow-hidden bg-[#070a0e]">
      {/* Cinematic Ambient Glow */}
      <div 
        className="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[550px] h-[400px] bg-[#E5B869]/[0.06] rounded-full blur-[130px] pointer-events-none" 
        aria-hidden="true"
      />
      <div 
        className="absolute bottom-10 left-1/3 w-[400px] h-[300px] bg-[#0c181f]/40 rounded-full blur-[100px] pointer-events-none" 
        aria-hidden="true"
      />

      <div className="w-full max-w-md relative z-10 glass-card bg-[#0e141a]/90 backdrop-blur-2xl p-7 sm:p-9 rounded-3xl space-y-6 border border-white/10 shadow-[0_25px_60px_rgba(0,0,0,0.85)]">
        {/* Subtle Gold Ambient Line */}
        <div className="absolute top-0 left-8 right-8 h-[2px] bg-gradient-to-r from-transparent via-[#E5B869]/80 to-transparent" />

        <div className="text-center space-y-2 pt-1">
          <Link href="/" className="inline-block transform hover:scale-105 transition-transform duration-200">
            <Logo />
          </Link>
          <h2 className="text-2xl font-bold font-serif-title text-white tracking-tight mt-2">
            Welcome Back
          </h2>
          <p className="text-xs text-gray-400">
            Sign in to continue watching your sacred collections
          </p>
        </div>

        {error && (
          <div className="bg-red-500/10 border border-red-500/30 text-red-400 text-xs p-3.5 rounded-xl flex items-center space-x-2 animate-shake">
            <span className="w-1.5 h-1.5 rounded-full bg-red-400 flex-shrink-0" />
            <p className="flex-1">{error}</p>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="text-xs font-semibold text-gray-300 block mb-1.5">
              Email Address
            </label>
            <div className="relative group">
              <input
                type="email"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="name@example.com"
                className="w-full bg-white/[0.04] hover:bg-white/[0.06] focus:bg-[#080d14] border border-white/10 focus:border-[#E5B869]/70 focus:ring-1 focus:ring-[#E5B869]/40 rounded-xl px-4 py-3 pl-10 text-white text-sm placeholder-gray-500 transition-all duration-200 outline-none"
              />
              <Mail className="w-4 h-4 text-gray-400 group-focus-within:text-[#E5B869] absolute left-3.5 top-3.5 pointer-events-none transition-colors" />
            </div>
          </div>

          <div>
            <div className="flex items-center justify-between mb-1.5">
              <label className="text-xs font-semibold text-gray-300 block">Password</label>
            </div>
            <div className="relative group">
              <input
                type={showPassword ? 'text' : 'password'}
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
                className="w-full bg-white/[0.04] hover:bg-white/[0.06] focus:bg-[#080d14] border border-white/10 focus:border-[#E5B869]/70 focus:ring-1 focus:ring-[#E5B869]/40 rounded-xl px-4 py-3 pl-10 pr-10 text-white text-sm placeholder-gray-500 transition-all duration-200 outline-none"
              />
              <Lock className="w-4 h-4 text-gray-400 group-focus-within:text-[#E5B869] absolute left-3.5 top-3.5 pointer-events-none transition-colors" />
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className="absolute right-3.5 top-3.5 text-gray-400 hover:text-white transition-colors cursor-pointer"
                aria-label={showPassword ? 'Hide password' : 'Show password'}
              >
                {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
              </button>
            </div>
          </div>

          <button
            type="submit"
            disabled={isSubmitting}
            className="w-full bg-[#E5B869] hover:bg-[#f3cf8a] disabled:opacity-50 text-black font-bold py-3.5 rounded-xl transition-all duration-200 shadow-lg shadow-[#E5B869]/20 hover:shadow-[#E5B869]/35 flex items-center justify-center space-x-2 text-sm tracking-wide cursor-pointer hover:scale-[1.01] active:scale-[0.98] mt-2"
          >
            <span>{isSubmitting ? 'Signing in...' : 'Sign In'}</span>
            <ArrowRight className="w-4 h-4 text-black" />
          </button>
        </form>

        {/* Quick Testing Account Pills */}
        <div className="pt-2 border-t border-white/[0.08] space-y-2">
          <div className="flex items-center justify-between text-[11px] text-gray-400">
            <span>Quick Test Credentials:</span>
            <span className="text-[10px] text-gray-500">pw: 12345678</span>
          </div>
          <div className="flex flex-wrap gap-1.5">
            <button
              type="button"
              onClick={() => fillQuickAccount('admin@swann.com')}
              className="text-[11px] px-2.5 py-1 rounded-lg bg-white/[0.04] hover:bg-white/[0.08] hover:text-[#E5B869] text-gray-300 border border-white/10 transition-colors cursor-pointer"
            >
              👑 Admin
            </button>
            <button
              type="button"
              onClick={() => fillQuickAccount('john@gmail.com')}
              className="text-[11px] px-2.5 py-1 rounded-lg bg-white/[0.04] hover:bg-white/[0.08] hover:text-[#E5B869] text-gray-300 border border-white/10 transition-colors cursor-pointer"
            >
              ⭐ Subscriber (VIP)
            </button>
            <button
              type="button"
              onClick={() => fillQuickAccount('jorge@gmail.com')}
              className="text-[11px] px-2.5 py-1 rounded-lg bg-white/[0.04] hover:bg-white/[0.08] hover:text-[#E5B869] text-gray-300 border border-white/10 transition-colors cursor-pointer"
            >
              👤 Free User
            </button>
          </div>
        </div>

        <div className="text-center text-xs text-gray-400 pt-1">
          Don&apos;t have an account?{' '}
          <Link href="/register" className="text-[#E5B869] hover:text-[#f3cf8a] font-semibold transition-colors hover:underline">
            Sign up now
          </Link>
        </div>
      </div>
    </div>
  );
}

export default function LoginPage() {
  return (
    <Suspense fallback={<div className="min-h-screen pt-32 text-center text-white">Loading...</div>}>
      <LoginFormContent />
    </Suspense>
  );
}

