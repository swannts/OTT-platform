'use client';

import React from 'react';
import Link from 'next/link';

interface GlobalErrorProps {
  error: Error & { digest?: string };
  reset: () => void;
}

export default function GlobalError({ error, reset }: GlobalErrorProps) {
  return (
    <html lang="en">
      <body className="bg-[#05080c] text-white min-h-screen flex items-center justify-center p-6 font-sans antialiased">
        <div className="max-w-md w-full text-center space-y-6">
          <div className="w-16 h-16 rounded-2xl bg-[#E5B869]/20 border border-[#E5B869]/40 text-[#E5B869] flex items-center justify-center mx-auto text-2xl font-bold">
            !
          </div>
          <div className="space-y-2">
            <h1 className="text-2xl font-bold">Critical Application Error</h1>
            <p className="text-sm text-gray-400">
              An unexpected system error occurred. We are working to restore service.
            </p>
          </div>
          <div className="flex items-center justify-center gap-3">
            <button
              type="button"
              onClick={() => reset()}
              className="px-6 py-2.5 rounded-full bg-[#E5B869] text-black font-bold text-xs hover:bg-[#f3cf8a] transition-all cursor-pointer"
            >
              Reload Application
            </button>
            <Link
              href="/"
              className="px-6 py-2.5 rounded-full bg-white/10 text-white font-semibold text-xs hover:bg-white/20 transition-all"
            >
              Home
            </Link>
          </div>
        </div>
      </body>
    </html>
  );
}
