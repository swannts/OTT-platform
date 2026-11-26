'use client';

import React, { useEffect, useState, Suspense } from 'react';
import { useSearchParams } from 'next/navigation';
import Link from 'next/link';
import { api } from '@/lib/api';
import { useAuth } from '@/context/AuthContext';
import { Spinner } from '@/components/ui';
import { CheckCircle2, Crown, Sparkles, ArrowRight } from 'lucide-react';
import { useLocaleText } from '@/providers';

function PaymentSuccessContent() {
  const tr = useLocaleText();
  const searchParams = useSearchParams();
  const { refreshSubscription } = useAuth();

  const gateway = searchParams?.get('gateway') || 'stripe';
  const sessionId = searchParams?.get('session_id') || '';
  const isFree = searchParams?.get('free') === '1';

  const [isVerifying, setIsVerifying] = useState(!isFree && !!sessionId);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  useEffect(() => {
    async function verify() {
      if (isFree) {
        await refreshSubscription();
        return;
      }

      if (!sessionId) {
        setErrorMessage('Payment verification details are missing. No subscription changes were confirmed.');
        setIsVerifying(false);
        return;
      }

      try {
        await api.verifySubscriptionPayment({
          gateway,
          session_id: sessionId,
        });
        await refreshSubscription();
      } catch (err: unknown) {
        setErrorMessage(err instanceof Error ? err.message : 'Payment verification failed.');
      } finally {
        setIsVerifying(false);
      }
    }

    verify();
  }, [sessionId, gateway, isFree, refreshSubscription]);

  if (isVerifying) {
    return (
      <div className="min-h-screen pt-32 text-center text-white space-y-4">
        <Spinner size="lg" className="mx-auto" />
        <h2 className="text-xl font-bold">{tr("Verifying your payment...")}</h2>
        <p className="text-sm text-gray-400">{tr("Please do not close or refresh this window.")}</p>
      </div>
    );
  }

  if (errorMessage) {
    return (
      <div className="min-h-screen pt-32 text-center text-white max-w-md mx-auto px-4 space-y-4">
        <div className="w-16 h-16 rounded-full bg-red-600/20 text-red-500 flex items-center justify-center mx-auto">
          ✕
        </div>
        <h2 className="text-2xl font-bold">{tr("Payment Issue")}</h2>
        <p className="text-sm text-gray-400">{errorMessage}</p>
        <Link
          href="/pricing"
          className="inline-block bg-red-600 text-white font-bold py-3 px-6 rounded-xl mt-4"
        >
          {tr("Return to Pricing")}</Link>
      </div>
    );
  }

  return (
    <div className="min-h-screen pt-28 px-4 max-w-xl mx-auto text-center space-y-6 pb-20">
      <div className="w-20 h-20 rounded-full bg-green-500/20 text-green-400 border border-green-500/30 flex items-center justify-center mx-auto shadow-2xl">
        <CheckCircle2 className="w-10 h-10" />
      </div>

      <div className="space-y-2">
        <div className="inline-flex items-center space-x-1.5 bg-amber-500/20 text-amber-300 border border-amber-500/30 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider">
          <Crown className="w-3.5 h-3.5" />
          <span>{tr("VIP Member Activated")}</span>
        </div>
        <h1 className="text-3xl font-black text-white">{tr("Payment Successful!")}</h1>
        <p className="text-sm text-gray-300">
          {tr("Your subscription is now active. You have unlimited access to movies, series, and live television.")}</p>
      </div>

      <div className="glass-card p-6 rounded-2xl text-left space-y-3 text-xs text-gray-300">
        <div className="flex justify-between pb-2 border-b border-white/10">
          <span className="text-gray-400">{tr("Status")}</span>
          <span className="text-green-400 font-bold">{tr("Active")}</span>
        </div>
        <div className="flex justify-between">
          <span className="text-gray-400">{tr("Stream Quality")}</span>
          <span className="text-white font-semibold">{tr("4K Ultra HD + HDR")}</span>
        </div>
        <div className="flex justify-between">
          <span className="text-gray-400">{tr("Simultaneous Devices")}</span>
          <span className="text-white font-semibold">{tr("Unlimited Access")}</span>
        </div>
      </div>

      <Link
        href="/"
        className="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3.5 px-6 rounded-xl shadow-lg shadow-red-600/40 flex items-center justify-center space-x-2 transition-transform active:scale-95"
      >
        <Sparkles className="w-4 h-4" />
        <span>{tr("Start Streaming Now")}</span>
        <ArrowRight className="w-4 h-4" />
      </Link>
    </div>
  );
}

export default function PaymentSuccessPage() {
  const tr = useLocaleText();
  return (
    <Suspense fallback={<div className="min-h-screen pt-32 text-center text-white">{tr("Loading...")}</div>}>
      <PaymentSuccessContent />
    </Suspense>
  );
}
