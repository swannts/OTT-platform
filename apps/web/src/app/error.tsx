'use client';

import React, { useEffect } from 'react';
import Link from 'next/link';
import { AlertTriangle, RotateCcw, Home } from 'lucide-react';

interface ErrorProps {
  error: Error & { digest?: string };
  reset: () => void;
}

export default function ErrorBoundary({ error, reset }: ErrorProps) {
  useEffect(() => {
    // Log error to monitoring service (e.g. Sentry) in production
    console.error('App Route Error caught by boundary:', error);
  }, [error]);

  return (
    <div className="min-h-[80vh] flex items-center justify-center px-4 sm:px-6 lg:px-8 py-20 relative overflow-hidden">
      {/* Background glow */}
      <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-red-500/10 rounded-full blur-[120px] pointer-events-none" />

      <div className="max-w-md w-full text-center relative z-10 space-y-6">
        <div className="w-16 h-16 rounded-2xl bg-red-500/15 border border-red-500/30 text-red-400 flex items-center justify-center mx-auto shadow-xl">
          <AlertTriangle className="w-8 h-8" />
        </div>

        <div className="space-y-2">
          <h1 className="text-2xl font-extrabold text-white">Something went wrong</h1>
          <p className="text-sm text-gray-400 leading-relaxed">
            We encountered an unexpected streaming issue. Please try refreshing or return to the main catalog.
          </p>
          {error.digest && (
            <p className="text-[11px] font-mono text-gray-500 pt-1">
              Error Digest: {error.digest}
            </p>
          )}
        </div>

        <div className="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
          <button
            type="button"
            onClick={() => reset()}
            className="w-full sm:w-auto inline-flex items-center justify-center space-x-2 px-6 py-2.5 rounded-full bg-[#E5B869] hover:bg-[#f3cf8a] text-black text-xs sm:text-sm font-bold shadow-md transition-all cursor-pointer hover:scale-[1.02] active:scale-95"
          >
            <RotateCcw className="w-4 h-4" />
            <span>Try Again</span>
          </button>
          <Link
            href="/"
            className="w-full sm:w-auto inline-flex items-center justify-center space-x-2 px-6 py-2.5 rounded-full bg-white/[0.06] hover:bg-white/[0.12] border border-white/10 text-white text-xs sm:text-sm font-semibold transition-all cursor-pointer hover:border-white/20 active:scale-95"
          >
            <Home className="w-4 h-4 text-[#E5B869]" />
            <span>Go to Home</span>
          </Link>
        </div>
      </div>
    </div>
  );
}

