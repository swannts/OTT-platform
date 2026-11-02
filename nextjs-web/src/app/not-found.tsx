import React from 'react';
import Link from 'next/link';
import { Film, Home, Search, Tv, Compass, Sparkles } from 'lucide-react';

export default function NotFound() {
  return (
    <div className="min-h-[85vh] flex items-center justify-center px-4 sm:px-6 lg:px-8 py-24 relative overflow-hidden">
      {/* Background Ambient Glow */}
      <div className="absolute top-1/3 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-[#E5B869]/10 rounded-full blur-[140px] pointer-events-none" />

      <div className="max-w-xl w-full text-center relative z-10 space-y-8">
        {/* Large 404 Accent Number */}
        <div className="relative inline-block">
          <span className="text-8xl sm:text-9xl font-black text-transparent bg-clip-text bg-gradient-to-b from-[#E5B869] via-[#d4a853] to-white/10 tracking-widest select-none">
            404
          </span>
          <div className="absolute -top-3 -right-3 w-8 h-8 rounded-full bg-[#E5B869]/20 border border-[#E5B869]/40 flex items-center justify-center text-[#E5B869] animate-pulse">
            <Sparkles className="w-4 h-4" />
          </div>
        </div>

        {/* Title & Description */}
        <div className="space-y-3">
          <h1 className="text-2xl sm:text-3xl font-extrabold text-white">
            Page or Broadcast Not Found
          </h1>
          <p className="text-sm sm:text-base text-gray-400 max-w-md mx-auto leading-relaxed">
            The sacred cinema title, series episode, or resource you are looking for does not exist or may have been relocated.
          </p>
        </div>

        {/* Action Buttons */}
        <div className="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
          <Link
            href="/"
            className="w-full sm:w-auto inline-flex items-center justify-center space-x-2 px-6 py-3 rounded-full bg-[#E5B869] hover:bg-[#f3cf8a] text-black text-sm font-bold shadow-lg shadow-[#E5B869]/25 transition-all cursor-pointer hover:scale-[1.02] active:scale-95"
          >
            <Home className="w-4 h-4" />
            <span>Return to Home</span>
          </Link>
          <Link
            href="/search"
            className="w-full sm:w-auto inline-flex items-center justify-center space-x-2 px-6 py-3 rounded-full bg-white/[0.06] hover:bg-white/[0.12] border border-white/10 text-white text-sm font-semibold transition-all cursor-pointer hover:border-white/20 active:scale-95"
          >
            <Search className="w-4 h-4 text-[#E5B869]" />
            <span>Search Library</span>
          </Link>
        </div>

        {/* Quick Direct Exploration Links */}
        <div className="pt-6 border-t border-white/10">
          <p className="text-xs uppercase tracking-wider text-gray-500 font-semibold mb-3">
            Explore Curated Collections
          </p>
          <div className="flex flex-wrap items-center justify-center gap-2">
            <Link
              href="/movies"
              className="inline-flex items-center space-x-1.5 px-3.5 py-1.5 rounded-full bg-white/[0.04] hover:bg-white/[0.08] border border-white/10 text-xs text-gray-300 hover:text-white transition-all cursor-pointer"
            >
              <Film className="w-3.5 h-3.5 text-[#E5B869]" />
              <span>Sacred Cinema</span>
            </Link>
            <Link
              href="/tv-shows"
              className="inline-flex items-center space-x-1.5 px-3.5 py-1.5 rounded-full bg-white/[0.04] hover:bg-white/[0.08] border border-white/10 text-xs text-gray-300 hover:text-white transition-all cursor-pointer"
            >
              <Tv className="w-3.5 h-3.5 text-[#E5B869]" />
              <span>TV Series</span>
            </Link>
            <Link
              href="/live-tv"
              className="inline-flex items-center space-x-1.5 px-3.5 py-1.5 rounded-full bg-white/[0.04] hover:bg-white/[0.08] border border-white/10 text-xs text-gray-300 hover:text-white transition-all cursor-pointer"
            >
              <Compass className="w-3.5 h-3.5 text-[#E5B869]" />
              <span>Live Broadcasts</span>
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
