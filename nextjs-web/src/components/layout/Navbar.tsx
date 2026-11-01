'use client';

import React, { useState, useEffect, useRef } from 'react';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useAuth } from '@/providers';
import Logo from './Logo';
import NotificationDropdown from './NotificationDropdown';
import UserProfileDropdown from './UserProfileDropdown';
import {
  Search,
  ChevronDown,
  Crown,
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
  const { subscription } = useAuth();

  const [isScrolled, setIsScrolled] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [isExploreOpen, setIsExploreOpen] = useState(false);
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

  // Primary curated desktop navigation links
  const primaryDesktopLinks = [
    { label: 'Home', href: '/' },
    { label: 'Live TV', href: '/live-tv', isLive: true },
    { label: 'Series', href: '/tv-shows' },
    { label: 'Documentaries', href: '/movies' },
    { label: 'Journal', href: '/blog' },
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
        { label: 'Journal & Stories', href: '/blog', icon: BookOpen, desc: 'History & cinema insights' },
        { label: 'Waz & Bayan', href: '/videos', icon: Flame, desc: 'Lectures by renowned scholars' },
        { label: 'Podcasts & Audio', href: '/videos', icon: Radio, desc: 'Spiritual talks & discussions' },
        { label: 'Kids & Family', href: '/profiles', icon: Smile, desc: 'Safe educational cartoons' },
      ],
    },
  ];

  // Full flat list for mobile navigation
  const mobileNavLinks = [
    { label: 'Home', href: '/' },
    { label: 'Live TV', href: '/live-tv' },
    { label: 'TV Series', href: '/tv-shows' },
    { label: 'Movies & Docs', href: '/movies' },
    { label: 'Journal & Stories', href: '/blog' },
    { label: 'Lectures & Waz', href: '/videos' },
    { label: 'Subscription Plans', href: '/pricing' },
    { label: 'My Watchlist', href: '/watchlist' },
    { label: 'Notifications', href: '/notifications' },
  ];

  return (
    <nav
      className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${
        isScrolled
          ? 'bg-[#05080c]/95 backdrop-blur-xl border-b border-white/[0.08] shadow-[0_10px_30px_rgba(0,0,0,0.8)] py-2.5'
          : 'bg-gradient-to-b from-[#05080c]/95 via-[#05080c]/60 to-transparent py-4'
      }`}
    >
      <div className="w-full px-4 sm:px-6 lg:px-8 xl:px-10">
        <div className="flex items-center justify-between">
          {/* Left: Brand Identity + Main Navigation */}
          <div className="flex items-center space-x-5 lg:space-x-7 flex-shrink-0">
            <Link href="/" className="flex-shrink-0 flex items-center group cursor-pointer">
              <Logo />
            </Link>

            {/* Desktop Navigation Links with Active Indicator Pill */}
            <div className="hidden lg:flex items-center space-x-1 xl:space-x-1.5 text-xs font-medium flex-shrink-0">
              {primaryDesktopLinks.map((link) => {
                const isActive = pathname === link.href;
                return (
                  <Link
                    key={link.label}
                    href={link.href}
                    className={`relative px-3.5 xl:px-4 py-2 rounded-full transition-all duration-200 cursor-pointer flex items-center space-x-2 whitespace-nowrap flex-shrink-0 ${
                      isActive
                        ? 'text-[#E5B869] font-bold bg-white/[0.05] shadow-inner ring-1 ring-white/10'
                        : 'text-gray-300 hover:text-white hover:bg-white/[0.04]'
                    }`}
                  >
                    {link.isLive && (
                      <span className="relative flex h-2 w-2 flex-shrink-0">
                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75" />
                        <span className="relative inline-flex rounded-full h-2 w-2 bg-red-500" />
                      </span>
                    )}
                    <span className="whitespace-nowrap">{link.label}</span>
                    {isActive && (
                      <span className="absolute -bottom-1.5 left-1/2 -translate-x-1/2 w-4 h-0.5 bg-[#E5B869] rounded-full shadow-[0_0_8px_rgba(229,184,105,0.9)]" />
                    )}
                  </Link>
                );
              })}

              {/* Explore Mega-Menu Dropdown */}
              <div
                className="relative flex-shrink-0"
                onMouseEnter={handleExploreEnter}
                onMouseLeave={handleExploreLeave}
              >
                <button
                  type="button"
                  onClick={() => setIsExploreOpen(!isExploreOpen)}
                  className={`flex items-center space-x-1.5 px-3.5 py-1.5 font-medium rounded-full transition-all duration-200 whitespace-nowrap cursor-pointer flex-shrink-0 ${
                    isExploreOpen
                      ? 'text-[#E5B869] bg-white/[0.06]'
                      : 'text-gray-300 hover:text-white hover:bg-white/[0.04]'
                  }`}
                >
                  <span className="whitespace-nowrap">Explore</span>
                  <ChevronDown
                    className={`w-3.5 h-3.5 flex-shrink-0 transition-transform duration-200 ${
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

          {/* Right: Search Pill, VIP Upgrade, Functional Notifications & Functional User Profile */}
          <div className="flex items-center space-x-2.5 sm:space-x-3.5 flex-shrink-0">
            {/* Sleek Expandable Search Input Pill */}
            <form onSubmit={handleSearchSubmit} className="relative hidden md:block group flex-shrink-0">
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search movies, series..."
                className="w-44 lg:w-52 focus:w-64 bg-white/[0.06] hover:bg-white/[0.09] focus:bg-[#080d14] border border-white/10 focus:border-[#E5B869]/60 text-white placeholder-gray-400 text-xs rounded-full pl-9 pr-7 py-2 focus:outline-none transition-all duration-300 backdrop-blur-md shadow-inner whitespace-nowrap"
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
                className="hidden sm:inline-flex items-center space-x-1.5 px-3.5 py-1.5 rounded-full bg-gradient-to-r from-[#e5b869]/15 to-[#f3cf8a]/10 hover:from-[#e5b869]/25 hover:to-[#f3cf8a]/20 border border-[#e5b869]/30 hover:border-[#e5b869]/60 text-[#e5b869] text-xs font-semibold shadow-sm transition-all cursor-pointer hover:scale-[1.02] active:scale-95 whitespace-nowrap flex-shrink-0"
              >
                <Crown className="w-3.5 h-3.5 text-[#e5b869] flex-shrink-0" />
                <span className="tracking-wide whitespace-nowrap">Go VIP</span>
              </Link>
            )}

            {/* Functional Notification Dropdown with Live Unread Tracking */}
            <div className="flex-shrink-0">
              <NotificationDropdown />
            </div>

            {/* Functional User Profile Dropdown with Auth Integration */}
            <div className="flex-shrink-0">
              <UserProfileDropdown />
            </div>

            {/* Mobile Hamburger Button */}
            <button
              onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
              className="lg:hidden w-9 h-9 rounded-full flex items-center justify-center bg-white/[0.04] border border-white/10 text-gray-300 hover:text-white cursor-pointer flex-shrink-0"
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
