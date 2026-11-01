'use client';

import React, { useState, useEffect, useRef } from 'react';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';
import Logo from './Logo';
import {
  Search,
  Bell,
  ChevronDown,
  User as UserIcon,
  LogOut,
  Crown,
  Heart,
  Tv,
  Menu,
  X,
  Film,
  Radio,
  BookOpen,
  Sparkles,
  Smile,
  Flame,
  Compass,
} from 'lucide-react';

export default function Navbar() {
  const pathname = usePathname();
  const router = useRouter();
  const { user, subscription, logout } = useAuth();

  const [isScrolled, setIsScrolled] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [isExploreOpen, setIsExploreOpen] = useState(false);
  const [isUserMenuOpen, setIsUserMenuOpen] = useState(false);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const exploreTimeoutRef = useRef<NodeJS.Timeout | null>(null);

  useEffect(() => {
    const handleScroll = () => {
      setIsScrolled(window.scrollY > 15);
    };
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchQuery.trim()) {
      router.push(`/search?q=${encodeURIComponent(searchQuery.trim())}`);
    }
  };

  const handleExploreEnter = () => {
    if (exploreTimeoutRef.current) clearTimeout(exploreTimeoutRef.current);
    setIsExploreOpen(true);
  };

  const handleExploreLeave = () => {
    exploreTimeoutRef.current = setTimeout(() => {
      setIsExploreOpen(false);
    }, 150);
  };

  // Primary curated desktop navigation links (no awkward wrapping!)
  const primaryDesktopLinks = [
    { label: 'Home', href: '/' },
    { label: 'Live TV', href: '/live-tv', isLive: true },
    { label: 'Series', href: '/tv-shows' },
    { label: 'Documentaries', href: '/movies' },
  ];

  // Comprehensive categorized explore catalog
  const exploreCategories = [
    {
      title: 'Cinema & Series',
      items: [
        { label: 'All Movies', href: '/movies', icon: Film, desc: 'Sacred features & cinema' },
        { label: 'TV Series', href: '/tv-shows', icon: Tv, desc: 'Multi-episode drama & series' },
        { label: 'Documentaries', href: '/movies', icon: Compass, desc: 'Historic & scientific films' },
        { label: 'Prophetic Biographies', href: '/tv-shows', icon: Sparkles, desc: 'Seerah & companions' },
      ],
    },
    {
      title: 'Knowledge & Spiritual',
      items: [
        { label: 'Waz & Bayan', href: '/videos', icon: Flame, desc: 'Lectures by renowned scholars' },
        { label: 'Holy Quran Recitation', href: '/movies', icon: BookOpen, desc: 'Tajweed & translations' },
        { label: 'Podcasts & Audio', href: '/videos', icon: Radio, desc: 'Spiritual talks & discussions' },
        { label: 'Kids & Family', href: '/profiles', icon: Smile, desc: 'Safe educational cartoons' },
      ],
    },
  ];

  // Full flat list for mobile navigation
  const mobileNavLinks = [
    { label: 'Home', href: '/' },
    { label: 'Live TV', href: '/live-tv' },
    { label: 'Movies & Cinema', href: '/movies' },
    { label: 'Series & Shows', href: '/tv-shows' },
    { label: 'Documentaries', href: '/movies' },
    { label: 'Waz & Bayan', href: '/videos' },
    { label: 'Quran Studies', href: '/movies' },
    { label: 'Podcasts', href: '/videos' },
    { label: 'Kids Corner', href: '/profiles' },
    { label: 'Shorts', href: '/videos' },
  ];

  return (
    <nav
      className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${
        isScrolled
          ? 'bg-[#070a0e]/92 backdrop-blur-2xl border-b border-white/[0.08] shadow-[0_4px_30px_rgba(0,0,0,0.8)] py-3'
          : 'bg-gradient-to-b from-[#070a0e]/95 via-[#070a0e]/60 to-transparent py-4 sm:py-4.5'
      }`}
    >
      <div className="max-w-[1440px] mx-auto px-6 sm:px-10 md:px-14 lg:px-16 xl:px-24">
        <div className="flex items-center justify-between gap-3 sm:gap-6">
          {/* Left: Brand Logo & Desktop Nav Links */}
          <div className="flex items-center space-x-6 lg:space-x-8">
            <Link
              href="/"
              className="flex-shrink-0 outline-none focus:outline-none focus-visible:ring-2 focus-visible:ring-[#E5B869]/50 rounded-xl transition-transform hover:scale-[1.02] cursor-pointer"
              aria-label="NOOR Home"
            >
              <Logo />
            </Link>

            {/* Desktop Navigation Links (Pill Style, Zero Text-Wrap) */}
            <div className="hidden lg:flex items-center space-x-1 xl:space-x-1.5 text-[13.5px]">
              {primaryDesktopLinks.map((link) => {
                const isActive = pathname === link.href;
                return (
                  <Link
                    key={link.label}
                    href={link.href}
                    className={`relative px-3.5 py-1.5 font-medium rounded-full transition-all duration-200 whitespace-nowrap cursor-pointer flex items-center space-x-1.5 ${
                      isActive
                        ? 'text-[#E5B869] font-semibold bg-white/[0.06]'
                        : 'text-gray-300 hover:text-white hover:bg-white/[0.04]'
                    }`}
                  >
                    {link.isLive && (
                      <span className="relative flex h-2 w-2">
                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75" />
                        <span className="relative inline-flex rounded-full h-2 w-2 bg-red-500" />
                      </span>
                    )}
                    <span>{link.label}</span>
                    {isActive && (
                      <span className="absolute -bottom-1.5 left-1/2 -translate-x-1/2 w-4 h-0.5 bg-[#E5B869] rounded-full shadow-[0_0_8px_rgba(229,184,105,0.9)]" />
                    )}
                  </Link>
                );
              })}

              {/* Explore Mega-Menu Dropdown */}
              <div
                className="relative"
                onMouseEnter={handleExploreEnter}
                onMouseLeave={handleExploreLeave}
              >
                <button
                  type="button"
                  onClick={() => setIsExploreOpen(!isExploreOpen)}
                  className={`flex items-center space-x-1.5 px-3.5 py-1.5 font-medium rounded-full transition-all duration-200 whitespace-nowrap cursor-pointer ${
                    isExploreOpen
                      ? 'text-[#E5B869] bg-white/[0.06]'
                      : 'text-gray-300 hover:text-white hover:bg-white/[0.04]'
                  }`}
                >
                  <span>Explore</span>
                  <ChevronDown
                    className={`w-3.5 h-3.5 transition-transform duration-200 ${
                      isExploreOpen ? 'rotate-180 text-[#E5B869]' : 'text-gray-400'
                    }`}
                  />
                </button>

                {isExploreOpen && (
                  <div className="absolute top-full -left-12 w-[480px] bg-[#090e15]/98 backdrop-blur-2xl rounded-2xl p-5 shadow-[0_20px_50px_rgba(0,0,0,0.85)] border border-white/10 z-50 mt-2 animate-hero-title">
                    <div className="flex items-center justify-between pb-3 mb-3 border-b border-white/10">
                      <div className="flex items-center space-x-2">
                        <span className="w-1.5 h-1.5 rounded-full bg-[#E5B869]" />
                        <span className="text-[11px] font-bold uppercase tracking-wider text-[#E5B869]">
                          Curated Islamic Library
                        </span>
                      </div>
                      <Link
                        href="/movies"
                        onClick={() => setIsExploreOpen(false)}
                        className="text-[11px] text-gray-400 hover:text-white transition-colors"
                      >
                        Browse All &rarr;
                      </Link>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                      {exploreCategories.map((cat) => (
                        <div key={cat.title} className="space-y-1">
                          <p className="text-[11px] font-semibold text-gray-400 uppercase tracking-wider px-2.5 mb-1.5">
                            {cat.title}
                          </p>
                          {cat.items.map((sub) => {
                            const IconComponent = sub.icon;
                            return (
                              <Link
                                key={sub.label}
                                href={sub.href}
                                onClick={() => setIsExploreOpen(false)}
                                className="group flex items-start space-x-2.5 p-2 rounded-xl hover:bg-white/[0.06] transition-colors cursor-pointer"
                              >
                                <div className="w-7 h-7 rounded-lg bg-white/5 group-hover:bg-[#E5B869]/15 flex items-center justify-center flex-shrink-0 text-gray-400 group-hover:text-[#E5B869] transition-colors mt-0.5">
                                  <IconComponent className="w-3.5 h-3.5" />
                                </div>
                                <div className="min-w-0">
                                  <p className="text-xs font-semibold text-gray-200 group-hover:text-[#E5B869] transition-colors leading-tight truncate">
                                    {sub.label}
                                  </p>
                                  <p className="text-[10px] text-gray-500 truncate mt-0.5">
                                    {sub.desc}
                                  </p>
                                </div>
                              </Link>
                            );
                          })}
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Right: Search Pill, VIP Upgrade, Notifications, User Profile */}
          <div className="flex items-center space-x-2.5 sm:space-x-3.5">
            {/* Sleek Expandable Search Input Pill */}
            <form onSubmit={handleSearchSubmit} className="relative hidden md:block group">
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search movies, series..."
                className="w-44 lg:w-52 focus:w-64 bg-white/[0.06] hover:bg-white/[0.09] focus:bg-[#080d14] border border-white/10 focus:border-[#E5B869]/60 text-white placeholder-gray-400 text-xs rounded-full pl-9 pr-7 py-2 focus:outline-none transition-all duration-300 backdrop-blur-md shadow-inner"
              />
              <Search className="w-3.5 h-3.5 text-gray-400 group-focus-within:text-[#E5B869] absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none transition-colors" />
              {searchQuery && (
                <button
                  type="button"
                  onClick={() => setSearchQuery('')}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white cursor-pointer"
                  aria-label="Clear search"
                >
                  <X className="w-3 h-3" />
                </button>
              )}
            </form>

            {/* VIP Membership Pill Button */}
            {!subscription?.has_active_subscription && (
              <Link
                href="/pricing"
                className="hidden sm:inline-flex items-center space-x-1.5 px-3.5 py-1.5 rounded-full bg-gradient-to-r from-[#e5b869]/15 to-[#f3cf8a]/10 hover:from-[#e5b869]/25 hover:to-[#f3cf8a]/20 border border-[#e5b869]/30 hover:border-[#e5b869]/60 text-[#e5b869] text-xs font-semibold shadow-sm transition-all cursor-pointer hover:scale-[1.02] active:scale-95"
              >
                <Crown className="w-3.5 h-3.5 text-[#e5b869]" />
                <span className="tracking-wide">Go VIP</span>
              </Link>
            )}

            {/* Notification Bell with Glowing Badge */}
            <Link
              href="/notifications"
              className="w-9 h-9 rounded-full flex items-center justify-center bg-white/[0.04] hover:bg-white/10 border border-white/10 hover:border-[#E5B869]/40 text-gray-300 hover:text-[#E5B869] transition-all relative cursor-pointer active:scale-95"
              aria-label="Notifications"
            >
              <Bell className="w-4 h-4" />
              <span className="absolute top-2 right-2 w-2 h-2 rounded-full bg-[#E5B869] ring-2 ring-[#070a0e] shadow-[0_0_6px_#E5B869]" />
            </Link>

            {/* User Profile Avatar with Online Ring */}
            <div className="relative">
              <button
                onClick={() => setIsUserMenuOpen(!isUserMenuOpen)}
                className="relative w-9 h-9 rounded-full overflow-hidden ring-2 ring-white/15 hover:ring-[#E5B869]/70 transition-all flex items-center justify-center bg-[#E5B869]/20 shadow-md cursor-pointer active:scale-95"
                aria-label="User Profile"
              >
                <img
                  src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&auto=format&fit=crop&q=80"
                  alt={user?.name || 'User Profile'}
                  className="w-full h-full object-cover"
                />
              </button>
              {/* Online Green Indicator Dot */}
              <span className="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-emerald-400 ring-2 ring-[#070a0e] pointer-events-none" />

              {isUserMenuOpen && (
                <div
                  className="absolute right-0 mt-2.5 w-60 bg-[#090e15]/98 backdrop-blur-2xl rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.9)] py-2 z-50 text-sm border border-white/10 animate-hero-title"
                  onMouseLeave={() => setIsUserMenuOpen(false)}
                >
                  {user ? (
                    <>
                      <div className="px-4 py-3 border-b border-white/10 bg-white/[0.02]">
                        <p className="font-semibold text-white truncate">{user.name}</p>
                        <p className="text-xs text-gray-400 truncate mt-0.5">{user.email}</p>
                        <div className="mt-2 inline-flex items-center space-x-1.5 px-2 py-0.5 rounded-md bg-[#E5B869]/15 border border-[#E5B869]/30 text-[10px] font-bold text-[#E5B869]">
                          <Crown className="w-3 h-3" />
                          <span>{subscription?.has_active_subscription ? 'VIP MEMBER' : 'FREE VIEWER'}</span>
                        </div>
                      </div>

                      <div className="py-1">
                        <Link
                          href="/account"
                          onClick={() => setIsUserMenuOpen(false)}
                          className="flex items-center space-x-2.5 px-4 py-2 text-xs text-gray-300 hover:text-[#E5B869] hover:bg-white/5 transition-colors cursor-pointer"
                        >
                          <UserIcon className="w-4 h-4 text-gray-400" />
                          <span>Account Settings</span>
                        </Link>

                        <Link
                          href="/profiles"
                          onClick={() => setIsUserMenuOpen(false)}
                          className="flex items-center space-x-2.5 px-4 py-2 text-xs text-gray-300 hover:text-[#E5B869] hover:bg-white/5 transition-colors cursor-pointer"
                        >
                          <Tv className="w-4 h-4 text-gray-400" />
                          <span>Who's Watching</span>
                        </Link>

                        <Link
                          href="/watchlist"
                          onClick={() => setIsUserMenuOpen(false)}
                          className="flex items-center space-x-2.5 px-4 py-2 text-xs text-gray-300 hover:text-[#E5B869] hover:bg-white/5 transition-colors cursor-pointer"
                        >
                          <Heart className="w-4 h-4 text-rose-400" />
                          <span>My Watchlist</span>
                        </Link>

                        <Link
                          href="/account/billing"
                          onClick={() => setIsUserMenuOpen(false)}
                          className="flex items-center space-x-2.5 px-4 py-2 text-xs text-gray-300 hover:text-[#E5B869] hover:bg-white/5 transition-colors cursor-pointer"
                        >
                          <Crown className="w-4 h-4 text-[#E5B869]" />
                          <span>Billing & Invoices</span>
                        </Link>
                      </div>

                      <div className="border-t border-white/10 pt-1">
                        <button
                          onClick={() => {
                            setIsUserMenuOpen(false);
                            logout();
                          }}
                          className="w-full flex items-center space-x-2.5 px-4 py-2 text-xs text-red-400 hover:text-red-300 hover:bg-red-500/10 transition-colors cursor-pointer"
                        >
                          <LogOut className="w-4 h-4" />
                          <span>Sign Out</span>
                        </button>
                      </div>
                    </>
                  ) : (
                    <div className="p-4 space-y-3">
                      <div className="flex items-center space-x-2">
                        <span className="w-2 h-2 rounded-full bg-[#E5B869]" />
                        <p className="text-xs font-semibold text-white">Welcome to NOOR</p>
                      </div>
                      <p className="text-[11px] text-gray-400 leading-relaxed">
                        Sign in to access your sacred watchlist, personalized recommendations, and continue watching.
                      </p>
                      <Link
                        href="/login"
                        onClick={() => setIsUserMenuOpen(false)}
                        className="block w-full text-center bg-gradient-to-r from-[#d8a855] to-[#E5B869] hover:from-[#f3cf8a] hover:to-[#E5B869] text-black font-bold text-xs py-2.5 rounded-xl shadow-md transition-all cursor-pointer"
                      >
                        Sign In / Register
                      </Link>
                    </div>
                  )}
                </div>
              )}
            </div>

            {/* Mobile Hamburger Button */}
            <button
              onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
              className="lg:hidden w-9 h-9 rounded-full flex items-center justify-center bg-white/[0.04] border border-white/10 text-gray-300 hover:text-white cursor-pointer"
              aria-label="Toggle Navigation Menu"
            >
              {isMobileMenuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
            </button>
          </div>
        </div>

        {/* Mobile menu dropdown */}
        {isMobileMenuOpen && (
          <div className="lg:hidden mt-3 pt-4 border-t border-white/10 bg-[#090e15]/98 backdrop-blur-2xl rounded-2xl p-4 space-y-2 shadow-2xl animate-hero-title">
            <form onSubmit={handleSearchSubmit} className="mb-3">
              <div className="relative">
                <input
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Search movies, series, channels..."
                  className="w-full bg-white/[0.06] border border-white/10 rounded-xl pl-9 pr-4 py-2.5 text-xs text-white placeholder-gray-400 focus:outline-none focus:border-[#E5B869]"
                />
                <Search className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
              </div>
            </form>

            <div className="grid grid-cols-2 gap-1.5 pt-1">
              {mobileNavLinks.map((link) => (
                <Link
                  key={link.label}
                  href={link.href}
                  onClick={() => setIsMobileMenuOpen(false)}
                  className="block px-3 py-2 text-xs font-medium text-gray-300 hover:text-[#E5B869] hover:bg-white/5 rounded-xl transition-colors"
                >
                  {link.label}
                </Link>
              ))}
            </div>

            {!subscription?.has_active_subscription && (
              <div className="pt-2 border-t border-white/10 mt-2">
                <Link
                  href="/pricing"
                  onClick={() => setIsMobileMenuOpen(false)}
                  className="flex items-center justify-center space-x-2 w-full bg-gradient-to-r from-[#d8a855] to-[#E5B869] text-black text-xs font-bold py-2.5 rounded-xl shadow-lg"
                >
                  <Crown className="w-4 h-4" />
                  <span>Upgrade to VIP</span>
                </Link>
              </div>
            )}
          </div>
        )}
      </div>
    </nav>
  );
}
