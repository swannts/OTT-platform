'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';
import { api } from '@/lib/api';
import {
  Shield,
  CreditCard,
  User as UserIcon,
  Users,
  Lock,
  Smartphone,
  CheckCircle2,
  AlertCircle,
  KeyRound,
} from 'lucide-react';

export default function SecurityPage() {
  const { user, refreshUser } = useAuth();
  const router = useRouter();

  React.useEffect(() => {
    if (!user) {
      router.push('/login');
    }
  }, [user, router]);

  const [parentalLock, setParentalLock] = useState<boolean>(
    Boolean(user?.is_parental_lock_enable)
  );
  const [pin, setPin] = useState('');
  const [pinLoading, setPinLoading] = useState(false);
  const [pinMessage, setPinMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  const [logoutLoading, setLogoutLoading] = useState(false);
  const [logoutMessage, setLogoutMessage] = useState<string | null>(null);

  if (!user) return null;

  const handleToggleParentalLock = async (e: React.FormEvent) => {
    e.preventDefault();
    if (parentalLock && pin.length !== 4) {
      setPinMessage({ type: 'error', text: 'Parental PIN must be exactly 4 digits.' });
      return;
    }

    setPinLoading(true);
    setPinMessage(null);
    try {
      await api.updateParentalLock(parentalLock, pin);
      setPinMessage({
        type: 'success',
        text: parentalLock
          ? 'Parental Lock enabled with 4-digit PIN.'
          : 'Parental Lock has been disabled.',
      });
      await refreshUser();
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to update parental lock.';
      setPinMessage({ type: 'error', text: message });
    } finally {
      setPinLoading(false);
    }
  };

  const handleLogoutAllDevices = async () => {
    if (!confirm('This will end your sessions on all other TVs, mobile devices, and browsers. Continue?')) {
      return;
    }
    setLogoutLoading(true);
    setLogoutMessage(null);
    try {
      await api.logout();
      router.push('/login');
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to logout devices.';
      setLogoutMessage(message);
    } finally {
      setLogoutLoading(false);
    }
  };

  return (
    <div className="min-h-screen pt-24 pb-16 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto">
      {/* Header */}
      <div className="border-b border-white/10 pb-6 mb-8">
        <h1 className="text-3xl font-extrabold text-white tracking-tight">Security & PIN Controls</h1>
        <p className="text-gray-400 text-sm mt-1">Configure parental access restrictions and manage trusted sessions</p>
      </div>

      {/* Navigation Sub-Tabs */}
      <div className="flex flex-wrap gap-2 mb-8 border-b border-white/10 pb-4">
        <Link
          href="/account"
          className="flex items-center space-x-2 px-4 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/5 text-sm font-medium transition-colors"
        >
          <UserIcon className="w-4 h-4" />
          <span>General Info</span>
        </Link>
        <Link
          href="/account/billing"
          className="flex items-center space-x-2 px-4 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/5 text-sm font-medium transition-colors"
        >
          <CreditCard className="w-4 h-4" />
          <span>Billing & History</span>
        </Link>
        <Link
          href="/account/security"
          className="flex items-center space-x-2 px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold shadow-md"
        >
          <Shield className="w-4 h-4" />
          <span>Security & PIN</span>
        </Link>
        <Link
          href="/profiles"
          className="flex items-center space-x-2 px-4 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/5 text-sm font-medium transition-colors"
        >
          <Users className="w-4 h-4" />
          <span>Profiles</span>
        </Link>
      </div>

      <div className="space-y-8">
        {/* Parental Lock & PIN Protection */}
        <div className="glass-card rounded-2xl p-6 sm:p-8 border border-white/10">
          <div className="flex items-center space-x-3 mb-2">
            <Shield className="w-6 h-6 text-red-500" />
            <h2 className="text-xl font-bold text-white">Parental Lock & PIN</h2>
          </div>
          <p className="text-sm text-gray-400 mb-6">
            Restrict age-rated content, adult TV channels, and pay-per-view purchases behind a 4-digit PIN.
          </p>

          {pinMessage && (
            <div
              className={`flex items-center space-x-2 p-4 rounded-xl text-sm mb-6 ${
                pinMessage.type === 'success'
                  ? 'bg-green-500/10 text-green-400 border border-green-500/20'
                  : 'bg-red-500/10 text-red-400 border border-red-500/20'
              }`}
            >
              {pinMessage.type === 'success' ? (
                <CheckCircle2 className="w-5 h-5" />
              ) : (
                <AlertCircle className="w-5 h-5" />
              )}
              <span>{pinMessage.text}</span>
            </div>
          )}

          <form onSubmit={handleToggleParentalLock} className="space-y-6 max-w-lg">
            <div className="flex items-center justify-between p-4 rounded-xl bg-white/5 border border-white/10">
              <div>
                <p className="font-semibold text-white text-sm">Enable Parental Restriction</p>
                <p className="text-xs text-gray-400 mt-0.5">Prompt for PIN when opening 18+ content</p>
              </div>
              <label className="relative inline-flex items-center cursor-pointer">
                <input
                  type="checkbox"
                  checked={parentalLock}
                  onChange={(e) => setParentalLock(e.target.checked)}
                  className="sr-only peer"
                />
                <div className="w-11 h-6 bg-white/20 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
              </label>
            </div>

            {parentalLock && (
              <div>
                <label className="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1.5">
                  Set 4-Digit Security PIN
                </label>
                <div className="relative">
                  <input
                    type="password"
                    maxLength={4}
                    value={pin}
                    onChange={(e) => setPin(e.target.value.replace(/\D/g, ''))}
                    placeholder="••••"
                    required
                    className="w-48 tracking-widest text-center text-2xl font-bold bg-black/40 border border-white/10 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-red-500"
                  />
                  <KeyRound className="w-4 h-4 text-gray-500 absolute left-3 top-3.5" />
                </div>
              </div>
            )}

            <button
              type="submit"
              disabled={pinLoading}
              className="bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white font-semibold text-sm px-6 py-2.5 rounded-xl transition-colors shadow-lg shadow-red-600/30"
            >
              {pinLoading ? 'Saving...' : 'Save Security Settings'}
            </button>
          </form>
        </div>

        {/* Active Devices & Session Management */}
        <div className="glass-card rounded-2xl p-6 sm:p-8 border border-white/10">
          <div className="flex items-center space-x-3 mb-2">
            <Smartphone className="w-6 h-6 text-blue-400" />
            <h2 className="text-xl font-bold text-white">Device Sessions</h2>
          </div>
          <p className="text-sm text-gray-400 mb-6">
            If you signed in on a public TV or friend's device, you can instantly terminate all active sessions.
          </p>

          {logoutMessage && (
            <div className="p-4 rounded-xl bg-white/10 text-sm text-white mb-6 border border-white/10">
              {logoutMessage}
            </div>
          )}

          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-4 rounded-xl bg-white/5 border border-white/10">
            <div>
              <p className="text-sm font-semibold text-white">Current Web Browser</p>
              <p className="text-xs text-gray-400">Active session • This device</p>
            </div>
            <button
              onClick={handleLogoutAllDevices}
              disabled={logoutLoading}
              className="self-start sm:self-auto text-xs text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/20 px-4 py-2 rounded-xl transition-colors border border-red-500/20"
            >
              {logoutLoading ? 'Logging out...' : 'Sign Out of All Devices'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

