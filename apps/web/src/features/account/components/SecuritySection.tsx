'use client';

import React, { useState } from 'react';
import { useRouter } from 'next/navigation';
import { Shield, KeyRound, AlertTriangle, CheckCircle2, AlertCircle, Trash2 } from 'lucide-react';
import { accountApi } from '../api/account.client';
import type { ChangePasswordInput } from '../schemas/account.schema';

interface SecuritySectionProps {
  isParentalLockEnabled: boolean;
  onUpdateParentalLock: (enabled: boolean, pin?: string) => Promise<{ success: boolean; error?: string }>;
  onChangePassword: (input: ChangePasswordInput) => Promise<{ success: boolean; error?: string }>;
  actionLoading: boolean;
}

export function SecuritySection({
  isParentalLockEnabled,
  onUpdateParentalLock,
  onChangePassword,
  actionLoading,
}: SecuritySectionProps) {
  const router = useRouter();

  // Password state
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [passwordMsg, setPasswordMsg] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  // Parental PIN state
  const [parentalLock, setParentalLock] = useState<boolean>(isParentalLockEnabled);
  const [pin, setPin] = useState('');
  const [pinMsg, setPinMsg] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  // Account deletion state
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [deleteConfirmText, setDeleteConfirmText] = useState('');
  const [deleting, setDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  const handlePasswordSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setPasswordMsg(null);

    if (newPassword.length < 6) {
      setPasswordMsg({ type: 'error', text: 'New password must be at least 6 characters.' });
      return;
    }
    if (newPassword !== confirmPassword) {
      setPasswordMsg({ type: 'error', text: 'New passwords do not match.' });
      return;
    }

    const res = await onChangePassword({
      old_password: currentPassword,
      new_password: newPassword,
      confirm_password: confirmPassword,
    });

    if (res.success) {
      setPasswordMsg({ type: 'success', text: 'Password changed successfully.' });
      setCurrentPassword('');
      setNewPassword('');
      setConfirmPassword('');
    } else {
      setPasswordMsg({ type: 'error', text: res.error || 'Failed to change password.' });
    }
  };

  const handlePinSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setPinMsg(null);

    if (parentalLock && pin.length !== 4) {
      setPinMsg({ type: 'error', text: 'PIN must be exactly 4 digits.' });
      return;
    }

    const res = await onUpdateParentalLock(parentalLock, parentalLock ? pin : undefined);
    if (res.success) {
      setPinMsg({
        type: 'success',
        text: parentalLock ? 'Parental Lock enabled with 4-digit PIN.' : 'Parental Lock has been disabled.',
      });
      setPin('');
    } else {
      setPinMsg({ type: 'error', text: res.error || 'Failed to update parental lock.' });
    }
  };

  const handleDeleteAccount = async () => {
    if (deleteConfirmText.toLowerCase() !== 'delete') {
      setDeleteError('Please type DELETE to confirm.');
      return;
    }

    setDeleting(true);
    setDeleteError(null);
    try {
      await accountApi.deleteAccount();
      router.push('/login');
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Failed to delete account';
      setDeleteError(msg);
      setDeleting(false);
    }
  };

  return (
    <div className="space-y-8">
      {/* Password Management */}
      <div className="bg-white/[0.03] border border-white/10 rounded-2xl p-6 sm:p-8 backdrop-blur-xl">
        <h3 className="text-lg font-bold text-white mb-2 flex items-center space-x-2">
          <KeyRound className="w-5 h-5 text-[#E5B869]" />
          <span>Change Password</span>
        </h3>
        <p className="text-gray-400 text-xs mb-6">
          Update your sign-in credentials to keep your account safe across all devices.
        </p>

        {passwordMsg && (
          <div
            className={`mb-6 p-4 rounded-xl flex items-center space-x-3 text-sm border ${
              passwordMsg.type === 'success'
                ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400'
                : 'bg-red-500/10 border-red-500/20 text-red-400'
            }`}
          >
            {passwordMsg.type === 'success' ? (
              <CheckCircle2 className="w-5 h-5 shrink-0" />
            ) : (
              <AlertCircle className="w-5 h-5 shrink-0" />
            )}
            <span>{passwordMsg.text}</span>
          </div>
        )}

        <form onSubmit={handlePasswordSubmit} className="space-y-5 max-w-xl">
          <div>
            <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
              Current Password
            </label>
            <input
              type="password"
              required
              value={currentPassword}
              onChange={(e) => setCurrentPassword(e.target.value)}
              placeholder="••••••••"
              className="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-[#E5B869] transition-colors"
            />
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
                New Password
              </label>
              <input
                type="password"
                required
                value={newPassword}
                onChange={(e) => setNewPassword(e.target.value)}
                placeholder="Min 6 characters"
                className="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-[#E5B869] transition-colors"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
                Confirm New Password
              </label>
              <input
                type="password"
                required
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                placeholder="Repeat new password"
                className="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-[#E5B869] transition-colors"
              />
            </div>
          </div>

          <button
            type="submit"
            disabled={actionLoading}
            className="bg-white/10 hover:bg-white/20 text-white font-semibold text-sm px-5 py-2.5 rounded-xl transition-all border border-white/10 disabled:opacity-50"
          >
            {actionLoading ? 'Updating Password...' : 'Update Password'}
          </button>
        </form>
      </div>

      {/* Parental PIN Controls */}
      <div className="bg-white/[0.03] border border-white/10 rounded-2xl p-6 sm:p-8 backdrop-blur-xl">
        <h3 className="text-lg font-bold text-white mb-2 flex items-center space-x-2">
          <Shield className="w-5 h-5 text-[#E5B869]" />
          <span>Parental Lock & Content Restrictions</span>
        </h3>
        <p className="text-gray-400 text-xs mb-6">
          Set up a 4-digit PIN to prevent children from switching to standard/adult profiles and viewing restricted content.
        </p>

        {pinMsg && (
          <div
            className={`mb-6 p-4 rounded-xl flex items-center space-x-3 text-sm border ${
              pinMsg.type === 'success'
                ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400'
                : 'bg-red-500/10 border-red-500/20 text-red-400'
            }`}
          >
            {pinMsg.type === 'success' ? (
              <CheckCircle2 className="w-5 h-5 shrink-0" />
            ) : (
              <AlertCircle className="w-5 h-5 shrink-0" />
            )}
            <span>{pinMsg.text}</span>
          </div>
        )}

        <form onSubmit={handlePinSubmit} className="space-y-6 max-w-xl">
          <div className="flex items-center justify-between p-4 rounded-xl bg-black/30 border border-white/10">
            <div>
              <span className="text-white font-semibold text-sm block">Enable Parental Lock</span>
              <span className="text-gray-400 text-xs">Require PIN to access non-child profiles</span>
            </div>
            <input
              type="checkbox"
              checked={parentalLock}
              onChange={(e) => setParentalLock(e.target.checked)}
              className="w-5 h-5 accent-[#E5B869] cursor-pointer"
            />
          </div>

          {parentalLock && (
            <div>
              <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
                4-Digit Security PIN
              </label>
              <input
                type="password"
                maxLength={4}
                required={parentalLock}
                value={pin}
                onChange={(e) => setPin(e.target.value.replace(/\D/g, ''))}
                placeholder="••••"
                className="w-32 text-center tracking-[0.5em] text-2xl font-mono bg-black/50 border border-white/10 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-[#E5B869]"
              />
            </div>
          )}

          <button
            type="submit"
            disabled={actionLoading}
            className="bg-[#E5B869] hover:bg-[#d9ab5c] text-black font-bold text-sm px-5 py-2.5 rounded-xl transition-all shadow-md shadow-[#E5B869]/20 disabled:opacity-50"
          >
            {actionLoading ? 'Saving PIN...' : 'Save PIN Preferences'}
          </button>
        </form>
      </div>

      {/* Danger Zone: Account Deletion */}
      <div className="bg-red-500/[0.04] border border-red-500/20 rounded-2xl p-6 sm:p-8 backdrop-blur-xl">
        <h3 className="text-lg font-bold text-red-400 mb-2 flex items-center space-x-2">
          <AlertTriangle className="w-5 h-5" />
          <span>Danger Zone: Delete Account</span>
        </h3>
        <p className="text-gray-400 text-xs mb-4">
          Permanently delete your user profile, active subscriptions, and all saved watchlists. This action cannot be undone.
        </p>

        <button
          type="button"
          onClick={() => setShowDeleteModal(true)}
          className="flex items-center space-x-2 text-sm text-red-400 hover:text-white bg-red-500/10 hover:bg-red-500/30 px-4 py-2.5 rounded-xl transition-colors border border-red-500/30 font-semibold"
        >
          <Trash2 className="w-4 h-4" />
          <span>Delete Account</span>
        </button>
      </div>

      {/* Delete Account Modal */}
      {showDeleteModal && (
        <div className="fixed inset-0 z-50 bg-black/80 backdrop-blur-md flex items-center justify-center p-4">
          <div className="bg-gray-900 border border-red-500/30 rounded-2xl max-w-md w-full p-6 shadow-2xl">
            <h3 className="text-xl font-bold text-white mb-2">Delete Your Account</h3>
            <p className="text-gray-400 text-xs mb-4">
              To confirm deletion, please type <strong className="text-red-400">DELETE</strong> in the box below.
            </p>

            {deleteError && (
              <div className="mb-4 p-2.5 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs">
                {deleteError}
              </div>
            )}

            <input
              type="text"
              value={deleteConfirmText}
              onChange={(e) => setDeleteConfirmText(e.target.value)}
              placeholder="DELETE"
              className="w-full bg-black/50 border border-white/20 rounded-xl px-4 py-3 text-white uppercase text-center font-bold tracking-wider mb-4 focus:outline-none focus:border-red-500"
            />

            <div className="flex gap-3 justify-end">
              <button
                type="button"
                onClick={() => setShowDeleteModal(false)}
                className="px-4 py-2 text-sm text-gray-400 hover:text-white"
              >
                Cancel
              </button>
              <button
                type="button"
                disabled={deleting || deleteConfirmText.toLowerCase() !== 'delete'}
                onClick={handleDeleteAccount}
                className="bg-red-600 hover:bg-red-700 text-white font-bold px-5 py-2 text-sm rounded-xl transition-all disabled:opacity-50"
              >
                {deleting ? 'Deleting...' : 'Confirm Delete'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
