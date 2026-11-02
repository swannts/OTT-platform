'use client';

import React, { useState, useEffect, useRef } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useAuth, useLanguage } from '@/providers';
import {
  User as UserIcon,
  LogOut,
  Crown,
  Heart,
  Tv,
  Settings,
  ShieldCheck,
  CreditCard,
  LogIn,
  UserPlus,
  Sparkles,
} from 'lucide-react';

export default function UserProfileDropdown() {
  const router = useRouter();
  const { user, subscription, login, logout, isLoading } = useAuth();
  const { t } = useLanguage();
  const [isOpen, setIsOpen] = useState(false);
  const [imgError, setImgError] = useState(false);
  const [isLoggingInDemo, setIsLoggingInDemo] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  // Close on click outside and escape key
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    }

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        setIsOpen(false);
      }
    }

    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
      document.addEventListener('keydown', handleKeyDown);
    }
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [isOpen]);

  const handleSignOut = async () => {
    setIsOpen(false);
    await logout();
    window.location.href = '/';
  };

  const handleDemoLogin = async () => {
    setIsLoggingInDemo(true);
    try {
      await login({ email: 'admin@swann.com', password: '12345678' });
      setIsOpen(false);
    } catch (err) {
      console.error('Demo login failed:', err);
    } finally {
      setIsLoggingInDemo(false);
    }
  };

  // Derive user initials (e.g. "SA" or "U")
  const getInitials = (name?: string) => {
    if (!name) return 'U';
    const parts = name.trim().split(' ');
    if (parts.length >= 2) {
      return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
  };

  const userAvatar = user?.avatar || user?.profile_image;

  return (
    <div className="relative flex-shrink-0" ref={dropdownRef}>
      {/* Profile Avatar Trigger Button */}
      <button
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        className={`relative w-9 h-9 rounded-full overflow-hidden transition-all flex items-center justify-center cursor-pointer active:scale-95 flex-shrink-0 ${
          isOpen
            ? 'ring-2 ring-[#E5B869] shadow-[0_0_12px_rgba(229,184,105,0.4)]'
            : 'ring-1 ring-white/20 hover:ring-[#E5B869]/70'
        } bg-white/[0.08]`}
        aria-label="User Profile"
        aria-expanded={isOpen}
      >
        {userAvatar && !imgError ? (
          <img
            src={userAvatar}
            alt={user?.name || 'User Profile'}
            onError={() => setImgError(true)}
            className="w-full h-full object-cover"
          />
        ) : (
          <div className="w-full h-full bg-gradient-to-tr from-[#99732e] to-[#E5B869] text-black font-bold text-xs flex items-center justify-center">
            {getInitials(user?.name)}
          </div>
        )}

        {/* Genuine Online Green Indicator Dot - Only when authenticated */}
        {user && (
          <span className="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-emerald-400 ring-2 ring-[#070a0e] pointer-events-none" />
        )}
      </button>

      {/* Dropdown Menu */}
      {isOpen && (
        <div className="absolute right-0 mt-2.5 w-64 bg-[#090e15]/98 backdrop-blur-2xl rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.9)] py-2 z-50 text-sm border border-white/10 animate-hero-title">
          {user ? (
            <>
              {/* Logged in User Card */}
              <div className="px-4 py-3 border-b border-white/10 bg-white/[0.02]">
                <div className="flex items-center space-x-3 mb-2">
                  <div className="w-10 h-10 rounded-full overflow-hidden flex items-center justify-center ring-2 ring-[#E5B869]/50 flex-shrink-0 bg-[#E5B869]/20">
                    {userAvatar && !imgError ? (
                      <img
                        src={userAvatar}
                        alt={user.name}
                        onError={() => setImgError(true)}
                        className="w-full h-full object-cover"
                      />
                    ) : (
                      <div className="w-full h-full bg-gradient-to-tr from-[#99732e] to-[#E5B869] text-black font-bold text-xs flex items-center justify-center">
                        {getInitials(user.name)}
                      </div>
                    )}
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="font-semibold text-white truncate text-sm">{user.name}</p>
                    <p className="text-xs text-gray-400 truncate">{user.email}</p>
                  </div>
                </div>

                <div className="flex items-center justify-between pt-1">
                  <div className="inline-flex items-center space-x-1.5 px-2 py-0.5 rounded-md bg-[#E5B869]/15 border border-[#E5B869]/30 text-[10px] font-bold text-[#E5B869]">
                    <Crown className="w-3 h-3" />
                    <span>
                      {subscription?.has_active_subscription ? t('vip_member', 'VIP MEMBER') : t('free_viewer', 'FREE VIEWER')}
                    </span>
                  </div>

                  {!subscription?.has_active_subscription && (
                    <Link
                      href="/pricing"
                      onClick={() => setIsOpen(false)}
                      className="text-[10px] font-semibold text-[#E5B869] hover:underline"
                    >
                      {t('upgrade', 'Upgrade')} &rarr;
                    </Link>
                  )}
                </div>
              </div>

              {/* Navigation Items */}
              <div className="py-1">
                <Link
                  href="/account"
                  onClick={() => setIsOpen(false)}
                  className="flex items-center space-x-2.5 px-4 py-2 text-xs text-gray-300 hover:text-[#E5B869] hover:bg-white/5 transition-colors cursor-pointer"
                >
                  <Settings className="w-4 h-4 text-gray-400" />
                  <span>{t('account_settings', 'Account Settings')}</span>
                </Link>

                <Link
                  href="/watchlist"
                  onClick={() => setIsOpen(false)}
                  className="flex items-center space-x-2.5 px-4 py-2 text-xs text-gray-300 hover:text-[#E5B869] hover:bg-white/5 transition-colors cursor-pointer"
                >
                  <Heart className="w-4 h-4 text-rose-400" />
                  <span>{t('nav_watchlist', 'My Watchlist')}</span>
                </Link>

                <Link
                  href="/profiles"
                  onClick={() => setIsOpen(false)}
                  className="flex items-center space-x-2.5 px-4 py-2 text-xs text-gray-300 hover:text-[#E5B869] hover:bg-white/5 transition-colors cursor-pointer"
                >
                  <Tv className="w-4 h-4 text-gray-400" />
                  <span>{t('who_watching', "Who's Watching")}</span>
                </Link>

                <Link
                  href="/account/billing"
                  onClick={() => setIsOpen(false)}
                  className="flex items-center space-x-2.5 px-4 py-2 text-xs text-gray-300 hover:text-[#E5B869] hover:bg-white/5 transition-colors cursor-pointer"
                >
                  <CreditCard className="w-4 h-4 text-[#E5B869]" />
                  <span>{t('billing_invoices', 'Billing & Invoices')}</span>
                </Link>

                <Link
                  href="/account/security"
                  onClick={() => setIsOpen(false)}
                  className="flex items-center space-x-2.5 px-4 py-2 text-xs text-gray-300 hover:text-[#E5B869] hover:bg-white/5 transition-colors cursor-pointer"
                >
                  <ShieldCheck className="w-4 h-4 text-emerald-400" />
                  <span>{t('security_parental', 'Security & Parental Lock')}</span>
                </Link>
              </div>

              {/* Sign Out */}
              <div className="border-t border-white/10 pt-1">
                <button
                  type="button"
                  onClick={handleSignOut}
                  className="w-full flex items-center space-x-2.5 px-4 py-2 text-xs text-red-400 hover:text-red-300 hover:bg-red-500/10 transition-colors cursor-pointer"
                >
                  <LogOut className="w-4 h-4" />
                  <span>{t('sign_out', 'Sign Out')}</span>
                </button>
              </div>
            </>
          ) : (
            /* Guest / Unauthenticated Dropdown */
            <div className="p-4 space-y-3">
              <div className="flex items-center space-x-2">
                <span className="w-2 h-2 rounded-full bg-[#E5B869]" />
                <p className="text-xs font-semibold text-white">{t('welcome_title', 'Welcome to NOOR')}</p>
              </div>
              <p className="text-[11px] text-gray-400 leading-relaxed">
                {t('welcome_desc', 'Sign in to access your sacred watchlist, continue watching across devices, and unlock 4K Ultra HD.')}
              </p>

              <div className="space-y-2 pt-1">
                <Link
                  href="/login"
                  onClick={() => setIsOpen(false)}
                  className="flex items-center justify-center space-x-2 w-full bg-[#E5B869] hover:bg-[#f3cf8a] text-black font-bold text-xs py-2.5 rounded-xl shadow-md transition-all cursor-pointer"
                >
                  <LogIn className="w-3.5 h-3.5" />
                  <span>{t('sign_in', 'Sign In')}</span>
                </Link>

                <Link
                  href="/register"
                  onClick={() => setIsOpen(false)}
                  className="flex items-center justify-center space-x-2 w-full bg-white/5 hover:bg-white/10 border border-white/10 text-white font-semibold text-xs py-2 rounded-xl transition-all cursor-pointer"
                >
                  <UserPlus className="w-3.5 h-3.5" />
                  <span>{t('create_account', 'Create Account')}</span>
                </Link>

                <div className="pt-2 border-t border-white/10">
                  <button
                    type="button"
                    onClick={handleDemoLogin}
                    disabled={isLoggingInDemo}
                    className="flex items-center justify-center space-x-1.5 w-full text-[11px] text-[#E5B869] hover:text-[#f3cf8a] py-1.5 rounded-lg hover:bg-white/5 transition-colors cursor-pointer"
                  >
                    <Sparkles className="w-3 h-3 text-[#E5B869]" />
                    <span>{isLoggingInDemo ? t('loading', 'Signing in...') : t('demo_login', '1-Click Demo Login (Admin)')}</span>
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

