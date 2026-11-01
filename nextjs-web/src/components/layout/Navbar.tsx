'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';
import {
  Search,
  User as UserIcon,
  Bell,
  LogOut,
  Tv,
  Film,
  Crown,
  Heart,
  Menu,
  X,
} from 'lucide-react';

export default function Navbar() {
  const pathname = usePathname();
  const router = useRouter();
  const { user, subscription, logout } = useAuth();
  const [isScrolled, setIsScrolled] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [isSearchOpen, setIsSearchOpen] = useState(false);
  const [isUserMenuOpen, setIsUserMenuOpen] = useState(false);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  useEffect(() => {
    const handleScroll = () => {
      setIsScrolled(window.scrollY > 20);
    };
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchQuery.trim()) {
      router.push(`/search?q=${encodeURIComponent(searchQuery.trim())}`);
      setIsSearchOpen(false);
    }
  };

  const navLinks = [
    { label: 'Home', href: '/' },
    { label: 'Movies', href: '/movies' },
    { label: 'TV Shows', href: '/tv-shows' },
    { label: 'Videos', href: '/videos' },
    { label: 'Live TV', href: '/live-tv' },
    { label: 'Pricing', href: '/pricing' },
    ...(user ? [{ label: 'My List', href: '/watchlist' }] : []),
  ];

  return (
    <nav
      className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${
        isScrolled ? 'glass-nav border-b border-white/5 py-3' : 'bg-gradient-to-b from-black/80 via-black/40 to-transparent py-4'
      }`}
    >
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between">
          {/* Logo & Desktop Nav */}
          <div className="flex items-center space-x-8">
            <Link href="/" className="flex items-center space-x-2">
              <span className="text-2xl font-black tracking-wider text-red-600 uppercase">
                SWANN<span className="text-white">OTT</span>
              </span>
            </Link>

            <div className="hidden md:flex items-center space-x-6">
              {navLinks.map((link) => {
                const isActive = pathname === link.href;
                return (
                  <Link
                    key={link.href}
                    href={link.href}
                    className={`text-sm font-medium transition-colors ${
                      isActive ? 'text-white font-semibold' : 'text-gray-300 hover:text-white'
                    }`}
                  >
                    {link.label}
                  </Link>
                );
              })}
            </div>
          </div>

          {/* Right Actions */}
          <div className="flex items-center space-x-4">
            {/* Search Bar */}
            <div className="relative">
              {isSearchOpen ? (
                <form onSubmit={handleSearchSubmit} className="flex items-center">
                  <input
                    type="text"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    placeholder="Search movies, TV shows..."
                    autoFocus
                    className="w-48 sm:w-64 bg-black/60 border border-white/20 text-white text-xs rounded-full px-4 py-2 pl-9 focus:outline-none focus:border-red-500"
                  />
                  <Search className="w-4 h-4 text-gray-400 absolute left-3 pointer-events-none" />
                  <button
                    type="button"
                    onClick={() => setIsSearchOpen(false)}
                    className="ml-2 text-gray-400 hover:text-white text-xs"
                  >
                    <X className="w-4 h-4" />
                  </button>
                </form>
              ) : (
                <button
                  onClick={() => setIsSearchOpen(true)}
                  className="p-2 text-gray-300 hover:text-white transition-colors"
                  aria-label="Search"
                >
                  <Search className="w-5 h-5" />
                </button>
              )}
            </div>

            {/* Subscribe / VIP Badge */}
            {subscription?.has_active_subscription ? (
              <div className="hidden sm:flex items-center space-x-1 bg-gradient-to-r from-amber-500/20 to-yellow-500/20 border border-amber-500/40 text-amber-300 text-xs px-3 py-1 rounded-full font-semibold">
                <Crown className="w-3.5 h-3.5 text-amber-400" />
                <span>VIP ({subscription.days_left}d)</span>
              </div>
            ) : (
              <Link
                href="/pricing"
                className="hidden sm:flex items-center space-x-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold px-3.5 py-1.5 rounded-full transition-colors shadow-lg shadow-red-600/30"
              >
                <Crown className="w-3.5 h-3.5" />
                <span>Subscribe</span>
              </Link>
            )}

            {/* Notifications Button */}
            {user && (
              <Link
                href="/notifications"
                className="p-2 text-gray-300 hover:text-white transition-colors relative"
                aria-label="Notifications"
              >
                <Bell className="w-5 h-5" />
              </Link>
            )}

            {/* User Profile / Login */}
            {user ? (
              <div className="relative">
                <button
                  onClick={() => setIsUserMenuOpen(!isUserMenuOpen)}
                  className="flex items-center space-x-2 p-1 rounded-full border border-white/10 hover:border-white/30 transition-colors"
                >
                  <div className="w-8 h-8 rounded-full bg-red-600/20 border border-red-500/40 flex items-center justify-center text-red-500 font-bold text-sm">
                    {user.name?.charAt(0).toUpperCase() || 'U'}
                  </div>
                </button>

                {isUserMenuOpen && (
                  <div
                    className="absolute right-0 mt-2 w-56 glass-card rounded-xl shadow-2xl py-2 z-50 text-sm border border-white/10"
                    onMouseLeave={() => setIsUserMenuOpen(false)}
                  >
                    <div className="px-4 py-2 border-b border-white/10">
                      <p className="font-semibold text-white truncate">{user.name}</p>
                      <p className="text-xs text-gray-400 truncate">{user.email}</p>
                    </div>

                    <Link
                      href="/account"
                      onClick={() => setIsUserMenuOpen(false)}
                      className="flex items-center space-x-2.5 px-4 py-2.5 text-gray-300 hover:text-white hover:bg-white/5 transition-colors"
                    >
                      <UserIcon className="w-4 h-4 text-gray-400" />
                      <span>Account & Settings</span>
                    </Link>

                    <Link
                      href="/profiles"
                      onClick={() => setIsUserMenuOpen(false)}
                      className="flex items-center space-x-2.5 px-4 py-2.5 text-gray-300 hover:text-white hover:bg-white/5 transition-colors"
                    >
                      <Tv className="w-4 h-4 text-purple-400" />
                      <span>Who's Watching</span>
                    </Link>

                    <Link
                      href="/watchlist"
                      onClick={() => setIsUserMenuOpen(false)}
                      className="flex items-center space-x-2.5 px-4 py-2.5 text-gray-300 hover:text-white hover:bg-white/5 transition-colors"
                    >
                      <Heart className="w-4 h-4 text-red-400" />
                      <span>My Watchlist</span>
                    </Link>

                    <Link
                      href="/account/billing"
                      onClick={() => setIsUserMenuOpen(false)}
                      className="flex items-center space-x-2.5 px-4 py-2.5 text-gray-300 hover:text-white hover:bg-white/5 transition-colors"
                    >
                      <Crown className="w-4 h-4 text-amber-400" />
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
                  </div>
                )}
              </div>
            ) : (
              <Link
                href="/login"
                className="text-xs font-semibold bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded-full transition-colors border border-white/10"
              >
                Sign In
              </Link>
            )}

            {/* Mobile menu button */}
            <button
              onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
              className="md:hidden p-2 text-gray-300 hover:text-white"
            >
              {isMobileMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
            </button>
          </div>
        </div>

        {/* Mobile menu dropdown */}
        {isMobileMenuOpen && (
          <div className="md:hidden mt-3 pt-3 border-t border-white/10 glass-card rounded-2xl p-4 space-y-2">
            {navLinks.map((link) => (
              <Link
                key={link.href}
                href={link.href}
                onClick={() => setIsMobileMenuOpen(false)}
                className="block px-3 py-2 text-sm font-medium text-gray-300 hover:text-white hover:bg-white/5 rounded-lg"
              >
                {link.label}
              </Link>
            ))}
            {!subscription?.has_active_subscription && (
              <Link
                href="/pricing"
                onClick={() => setIsMobileMenuOpen(false)}
                className="block text-center bg-red-600 text-white text-sm font-semibold py-2.5 rounded-lg mt-2"
              >
                Upgrade to VIP
              </Link>
            )}
          </div>
        )}
      </div>
    </nav>
  );
}

