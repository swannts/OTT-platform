'use client';

import React, { useState, useEffect } from 'react';
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
  Shield,
  Film,
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

  const navLinks = [
    { label: 'Home', href: '/' },
    {
      label: 'Explore',
      isDropdown: true,
      items: [
        { label: 'All Movies', href: '/movies' },
        { label: 'All TV Series', href: '/tv-shows' },
        { label: 'Documentaries', href: '/movies' },
        { label: 'Islamic History', href: '/movies' },
        { label: 'Prophetic Biographies', href: '/tv-shows' },
      ],
    },
    { label: 'Live TV', href: '/live-tv' },
    { label: 'Podcasts', href: '/videos' },
    { label: 'Series', href: '/tv-shows' },
    { label: 'Documentaries', href: '/movies' },
    { label: 'Waz & Bayan', href: '/videos' },
    { label: 'Quran', href: '/movies' },
    { label: 'Kids', href: '/profiles' },
    { label: 'Shorts', href: '/videos' },
  ];

  return (
    <nav
      className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${
        isScrolled
          ? 'glass-nav border-b border-white/5 py-3'
          : 'bg-gradient-to-b from-[#070a0e]/95 via-[#070a0e]/60 to-transparent py-4'
      }`}
    >
      <div className="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between gap-4">
          {/* Left: Brand Logo & Desktop Nav Links */}
          <div className="flex items-center space-x-7 lg:space-x-8">
            <Link href="/" className="flex-shrink-0">
              <Logo />
            </Link>

            {/* Desktop Navigation Links */}
            <div className="hidden xl:flex items-center space-x-5 text-[13.5px]">
              {navLinks.map((link) => {
                if (link.isDropdown) {
                  return (
                    <div
                      key={link.label}
                      className="relative"
                      onMouseEnter={() => setIsExploreOpen(true)}
                      onMouseLeave={() => setIsExploreOpen(false)}
                    >
                      <button className="flex items-center space-x-1 text-gray-300 hover:text-white transition-colors py-1 font-medium">
                        <span>{link.label}</span>
                        <ChevronDown className="w-3.5 h-3.5 text-gray-400 mt-0.5" />
                      </button>

                      {isExploreOpen && (
                        <div className="absolute top-full left-0 w-52 glass-card rounded-2xl py-2 shadow-2xl border border-white/10 z-50 mt-1">
                          {link.items?.map((sub) => (
                            <Link
                              key={sub.label}
                              href={sub.href}
                              onClick={() => setIsExploreOpen(false)}
                              className="block px-4 py-2 text-xs text-gray-300 hover:text-[#E5B869] hover:bg-white/5 transition-colors"
                            >
                              {sub.label}
                            </Link>
                          ))}
                        </div>
                      )}
                    </div>
                  );
                }

                const isActive = pathname === link.href;
                return (
                  <Link
                    key={link.label}
                    href={link.href}
                    className={`transition-all py-1 font-medium ${
                      isActive
                        ? 'text-[#E5B869] font-semibold border-b-2 border-[#E5B869]'
                        : 'text-gray-300 hover:text-white'
                    }`}
                  >
                    {link.label}
                  </Link>
                );
              })}
            </div>
          </div>

          {/* Right: Search Pill, Notifications, User Profile */}
          <div className="flex items-center space-x-3 sm:space-x-4">
            {/* Search Input Pill */}
            <form onSubmit={handleSearchSubmit} className="relative hidden md:block">
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search for content..."
                className="w-52 lg:w-64 bg-white/10 hover:bg-white/15 focus:bg-black/50 border border-white/10 text-white placeholder-gray-400 text-xs rounded-full pl-9 pr-4 py-2 focus:outline-none focus:border-[#E5B869]/60 transition-all backdrop-blur-md"
              />
              <Search className="w-4 h-4 text-gray-400 absolute left-3 top-2.5 pointer-events-none" />
            </form>

            {/* Notification Bell with Red/Gold Alert Dot */}
            <Link
              href="/notifications"
              className="p-1.5 text-gray-300 hover:text-[#E5B869] transition-colors relative"
              aria-label="Notifications"
            >
              <Bell className="w-5 h-5" />
              <span className="absolute top-1 right-1 w-2 h-2 rounded-full bg-[#E5B869]" />
            </Link>

            {/* User Profile Avatar with Online Status Indicator */}
            <div className="relative">
              <button
                onClick={() => setIsUserMenuOpen(!isUserMenuOpen)}
                className="relative w-8 h-8 rounded-full overflow-hidden border border-white/20 hover:border-[#E5B869]/60 transition-all flex items-center justify-center bg-[#E5B869]/20"
                aria-label="User Profile"
              >
                <img
                  src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&auto=format&fit=crop&q=80"
                  alt={user?.name || 'User Profile'}
                  className="w-full h-full object-cover"
                />
              </button>
              {/* Green Active Dot */}
              <span className="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-[#070a0e] pointer-events-none" />

              {isUserMenuOpen && (
                <div
                  className="absolute right-0 mt-2 w-56 glass-card rounded-2xl shadow-2xl py-2 z-50 text-sm border border-white/10"
                  onMouseLeave={() => setIsUserMenuOpen(false)}
                >
                  {user ? (
                    <>
                      <div className="px-4 py-2 border-b border-white/10">
                        <p className="font-semibold text-white truncate">{user.name}</p>
                        <p className="text-xs text-gray-400 truncate">{user.email}</p>
                      </div>

                      <Link
                        href="/account"
                        onClick={() => setIsUserMenuOpen(false)}
                        className="flex items-center space-x-2.5 px-4 py-2.5 text-gray-300 hover:text-[#E5B869] hover:bg-white/5 transition-colors"
                      >
                        <UserIcon className="w-4 h-4" />
                        <span>Account Settings</span>
                      </Link>

                      <Link
                        href="/profiles"
                        onClick={() => setIsUserMenuOpen(false)}
                        className="flex items-center space-x-2.5 px-4 py-2.5 text-gray-300 hover:text-[#E5B869] hover:bg-white/5 transition-colors"
                      >
                        <Tv className="w-4 h-4" />
                        <span>Who's Watching</span>
                      </Link>

                      <Link
                        href="/watchlist"
                        onClick={() => setIsUserMenuOpen(false)}
                        className="flex items-center space-x-2.5 px-4 py-2.5 text-gray-300 hover:text-[#E5B869] hover:bg-white/5 transition-colors"
                      >
                        <Heart className="w-4 h-4 text-red-400" />
                        <span>My Watchlist</span>
                      </Link>

                      <Link
                        href="/account/billing"
                        onClick={() => setIsUserMenuOpen(false)}
                        className="flex items-center space-x-2.5 px-4 py-2.5 text-gray-300 hover:text-[#E5B869] hover:bg-white/5 transition-colors"
                      >
                        <Crown className="w-4 h-4 text-[#E5B869]" />
                        <span>Billing & Invoices</span>
                      </Link>

                      <button
                        onClick={() => {
                          setIsUserMenuOpen(false);
                          logout();
                        }}
                        className="w-full flex items-center space-x-2.5 px-4 py-2.5 text-red-400 hover:text-red-300 hover:bg-white/5 transition-colors border-t border-white/10 mt-1"
                      >
                        <LogOut className="w-4 h-4" />
                        <span>Sign Out</span>
                      </button>
                    </>
                  ) : (
                    <div className="p-3 space-y-2">
                      <p className="text-xs text-gray-400">Welcome to NOOR</p>
                      <Link
                        href="/login"
                        onClick={() => setIsUserMenuOpen(false)}
                        className="block w-full text-center bg-[#E5B869] hover:bg-[#d8a855] text-black font-bold text-xs py-2 rounded-xl transition-colors"
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
              className="xl:hidden p-2 text-gray-300 hover:text-white"
            >
              {isMobileMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
            </button>
          </div>
        </div>

        {/* Mobile menu dropdown */}
        {isMobileMenuOpen && (
          <div className="xl:hidden mt-3 pt-3 border-t border-white/10 glass-card rounded-2xl p-4 space-y-2">
            <form onSubmit={handleSearchSubmit} className="mb-3">
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search movies, series, live TV..."
                className="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-2 text-xs text-white"
              />
            </form>
            {navLinks.map((link) => (
              <Link
                key={link.label}
                href={link.href || '/'}
                onClick={() => setIsMobileMenuOpen(false)}
                className="block px-3 py-2 text-sm font-medium text-gray-300 hover:text-[#E5B869] hover:bg-white/5 rounded-lg"
              >
                {link.label}
              </Link>
            ))}
          </div>
        )}
      </div>
    </nav>
  );
}
