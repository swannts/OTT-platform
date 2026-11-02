'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';
import { api } from '@/lib/api';
import {
  User as UserIcon,
  Shield,
  CreditCard,
  Users,
  Lock,
  CheckCircle2,
  AlertCircle,
  LogOut,
  Crown,
  KeyRound,
  Save,
} from 'lucide-react';

export default function AccountPage() {
  const { user, subscription, logout, refreshUser, isLoading } = useAuth();
  const router = useRouter();

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');

  const [profileLoading, setProfileLoading] = useState(false);
  const [profileMessage, setProfileMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  const [passwordLoading, setPasswordLoading] = useState(false);
  const [passwordMessage, setPasswordMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  useEffect(() => {
    if (!isLoading && !user) {
      router.push('/login?redirect=/account');
      return;
    }
    if (user) {
      setName(user.name || '');
      setEmail(user.email || '');
      setPhone(user.phone || '');
    }
  }, [user, isLoading, router]);

  const handleUpdateProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    setProfileLoading(true);
    setProfileMessage(null);
    try {
      await api.updateProfile({ name, email, phone });
      setProfileMessage({ type: 'success', text: 'Profile updated successfully.' });
      await refreshUser();
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to update profile.';
      setProfileMessage({ type: 'error', text: message });
    } finally {
      setProfileLoading(false);
    }
  };

  const handleChangePassword = async (e: React.FormEvent) => {
    e.preventDefault();
    if (newPassword !== confirmPassword) {
      setPasswordMessage({ type: 'error', text: 'New passwords do not match.' });
      return;
    }
    if (newPassword.length < 6) {
      setPasswordMessage({ type: 'error', text: 'Password must be at least 6 characters.' });
      return;
    }

    setPasswordLoading(true);
    setPasswordMessage(null);
    try {
      await api.changePassword({ old_password: currentPassword, new_password: newPassword });
      setPasswordMessage({ type: 'success', text: 'Password changed successfully.' });
      setCurrentPassword('');
      setNewPassword('');
      setConfirmPassword('');
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to change password.';
      setPasswordMessage({ type: 'error', text: message });
    } finally {
      setPasswordLoading(false);
    }
  };

  if (isLoading) {
    return (
      <div className="min-h-screen pt-32 text-center text-white space-y-4">
        <div className="w-10 h-10 border-4 border-[#E5B869] border-t-transparent rounded-full animate-spin mx-auto" />
        <p className="text-sm text-gray-400">Loading your account details...</p>
      </div>
    );
  }

  if (!user) return null;

  return (
    <div className="min-h-screen pt-24 pb-16 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto">
      {/* Header */}
      <div className="border-b border-white/10 pb-6 mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-3xl font-extrabold text-white tracking-tight">Account & Settings</h1>
          <p className="text-gray-400 text-sm mt-1">Manage your personal profile, security, and preferences</p>
        </div>
        <button
          onClick={() => {
            logout();
            router.push('/');
          }}
          className="self-start sm:self-auto flex items-center space-x-2 text-sm text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/20 px-4 py-2 rounded-xl transition-colors border border-red-500/20"
        >
          <LogOut className="w-4 h-4" />
          <span>Sign Out</span>
        </button>
      </div>

      {/* Navigation Sub-Tabs */}
      <div className="flex flex-wrap gap-2 mb-8 border-b border-white/10 pb-4">
        <Link
          href="/account"
          className="flex items-center space-x-2 px-4 py-2 rounded-lg bg-[#E5B869] text-black text-sm font-bold shadow-md shadow-[#E5B869]/20"
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
          className="flex items-center space-x-2 px-4 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/5 text-sm font-medium transition-colors"
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

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Left Column: Subscription & Quick Stats */}
        <div className="space-y-6">
          {/* Subscription Card */}
          <div className="glass-card rounded-2xl p-6 border border-white/10 relative overflow-hidden">
            <div className="flex items-center justify-between mb-4">
              <span className="text-xs uppercase tracking-wider font-bold text-gray-400">Membership</span>
              <Crown className="w-5 h-5 text-amber-400" />
            </div>

            {subscription?.has_active_subscription ? (
              <div>
                <div className="flex items-center space-x-2">
                  <span className="text-2xl font-black text-amber-300">VIP Pass</span>
                  <span className="bg-amber-500/20 text-amber-400 text-xs px-2 py-0.5 rounded-full font-bold">
                    Active
                  </span>
                </div>
                <p className="text-sm text-gray-400 mt-2">
                  You have <span className="text-white font-bold">{subscription.days_left} days</span> remaining.
                </p>
                <Link
                  href="/account/billing"
                  className="block text-center mt-4 w-full bg-white/10 hover:bg-white/20 text-white text-xs font-semibold py-2.5 rounded-xl transition-colors border border-white/10"
                >
                  Manage Membership
                </Link>
              </div>
            ) : (
              <div>
                <p className="text-lg font-bold text-white">Free Account</p>
                <p className="text-xs text-gray-400 mt-1">Unlock 4K streaming, VIP exclusives, and live sports.</p>
                <Link
                  href="/pricing"
                  className="block text-center mt-4 w-full bg-red-600 hover:bg-red-700 text-white text-xs font-bold py-2.5 rounded-xl transition-colors shadow-lg shadow-red-600/30"
                >
                  Upgrade to VIP
                </Link>
              </div>
            )}
          </div>

          {/* Quick Shortcuts */}
          <div className="glass-card rounded-2xl p-6 border border-white/10 space-y-4">
            <h3 className="text-sm font-semibold text-white">Account Shortcuts</h3>
            <div className="space-y-2 text-sm">
              <Link
                href="/watchlist"
                className="flex items-center justify-between p-3 rounded-xl bg-white/5 hover:bg-white/10 transition-colors text-gray-300 hover:text-white"
              >
                <span>My Watchlist</span>
                <span className="text-xs text-gray-500">→</span>
              </Link>
              <Link
                href="/account/billing"
                className="flex items-center justify-between p-3 rounded-xl bg-white/5 hover:bg-white/10 transition-colors text-gray-300 hover:text-white"
              >
                <span>Invoice Downloads</span>
                <span className="text-xs text-gray-500">→</span>
              </Link>
              <Link
                href="/profiles"
                className="flex items-center justify-between p-3 rounded-xl bg-white/5 hover:bg-white/10 transition-colors text-gray-300 hover:text-white"
              >
                <span>Manage Sub-Profiles</span>
                <span className="text-xs text-gray-500">→</span>
              </Link>
            </div>
          </div>
        </div>

        {/* Right Column: Profile Edit & Password Change */}
        <div className="lg:col-span-2 space-y-8">
          {/* Personal Information Form */}
          <div className="glass-card rounded-2xl p-6 sm:p-8 border border-white/10">
            <h2 className="text-xl font-bold text-white mb-2">Personal Information</h2>
            <p className="text-sm text-gray-400 mb-6">Update your personal contact details and display name.</p>

            {profileMessage && (
              <div
                className={`flex items-center space-x-2 p-4 rounded-xl text-sm mb-6 ${
                  profileMessage.type === 'success'
                    ? 'bg-green-500/10 text-green-400 border border-green-500/20'
                    : 'bg-red-500/10 text-red-400 border border-red-500/20'
                }`}
              >
                {profileMessage.type === 'success' ? <CheckCircle2 className="w-5 h-5" /> : <AlertCircle className="w-5 h-5" />}
                <span>{profileMessage.text}</span>
              </div>
            )}

            <form onSubmit={handleUpdateProfile} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1.5">
                  Full Name
                </label>
                <input
                  type="text"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  required
                  className="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:border-red-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1.5">
                  Email Address
                </label>
                <input
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  required
                  className="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:border-red-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1.5">
                  Phone Number (Optional)
                </label>
                <input
                  type="tel"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="+1 (555) 000-0000"
                  className="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:border-red-500"
                />
              </div>

              <div className="pt-2">
                <button
                  type="submit"
                  disabled={profileLoading}
                  className="flex items-center space-x-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white font-semibold text-sm px-6 py-2.5 rounded-xl transition-colors shadow-lg shadow-red-600/30"
                >
                  <Save className="w-4 h-4" />
                  <span>{profileLoading ? 'Saving...' : 'Save Profile'}</span>
                </button>
              </div>
            </form>
          </div>

          {/* Change Password Form */}
          <div className="glass-card rounded-2xl p-6 sm:p-8 border border-white/10">
            <div className="flex items-center space-x-2 mb-2">
              <KeyRound className="w-5 h-5 text-gray-400" />
              <h2 className="text-xl font-bold text-white">Change Password</h2>
            </div>
            <p className="text-sm text-gray-400 mb-6">Ensure your account is using a long, random password to stay secure.</p>

            {passwordMessage && (
              <div
                className={`flex items-center space-x-2 p-4 rounded-xl text-sm mb-6 ${
                  passwordMessage.type === 'success'
                    ? 'bg-green-500/10 text-green-400 border border-green-500/20'
                    : 'bg-red-500/10 text-red-400 border border-red-500/20'
                }`}
              >
                {passwordMessage.type === 'success' ? <CheckCircle2 className="w-5 h-5" /> : <AlertCircle className="w-5 h-5" />}
                <span>{passwordMessage.text}</span>
              </div>
            )}

            <form onSubmit={handleChangePassword} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1.5">
                  Current Password
                </label>
                <input
                  type="password"
                  value={currentPassword}
                  onChange={(e) => setCurrentPassword(e.target.value)}
                  required
                  className="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:border-red-500"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1.5">
                    New Password
                  </label>
                  <input
                    type="password"
                    value={newPassword}
                    onChange={(e) => setNewPassword(e.target.value)}
                    required
                    placeholder="Min 6 characters"
                    className="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:border-red-500"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1.5">
                    Confirm New Password
                  </label>
                  <input
                    type="password"
                    value={confirmPassword}
                    onChange={(e) => setConfirmPassword(e.target.value)}
                    required
                    className="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:border-red-500"
                  />
                </div>
              </div>

              <div className="pt-2">
                <button
                  type="submit"
                  disabled={passwordLoading}
                  className="flex items-center space-x-2 bg-white/10 hover:bg-white/20 disabled:opacity-50 text-white font-semibold text-sm px-6 py-2.5 rounded-xl transition-colors border border-white/10"
                >
                  <Lock className="w-4 h-4" />
                  <span>{passwordLoading ? 'Updating...' : 'Update Password'}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  );
}

