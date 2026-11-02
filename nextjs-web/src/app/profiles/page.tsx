'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';
import { Spinner } from '@/components/ui';
import { Plus, Trash2, Shield, X, AlertCircle } from 'lucide-react';
import { useMultiProfiles, type ProfileItem } from '@/features/account';

const AVATAR_COLORS = [
  'bg-red-600',
  'bg-blue-600',
  'bg-emerald-600',
  'bg-amber-600',
  'bg-purple-600',
  'bg-pink-600',
];

export default function ProfilesPage() {
  const { user, isLoading: authLoading } = useAuth();
  const router = useRouter();

  const [isManaging, setIsManaging] = useState(false);
  const [showAddModal, setShowAddModal] = useState(false);
  const [newName, setNewName] = useState('');
  const [isChild, setIsChild] = useState(false);
  const [pinInput, setPinInput] = useState('');
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  const {
    profiles,
    currentProfile,
    isLoading: profilesLoading,
    actionLoading,
    pinTargetProfile,
    selectProfile,
    saveProfile,
    deleteProfile,
    cancelPinVerification,
  } = useMultiProfiles();

  useEffect(() => {
    if (!authLoading && !user) {
      router.push('/login?redirect=/profiles');
    }
  }, [user, authLoading, router]);

  const handleSelect = async (p: ProfileItem) => {
    if (isManaging) return;
    const res = await selectProfile(p);
    if (res.success) {
      router.push('/');
    }
  };

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg(null);
    if (!newName.trim()) return;

    const res = await saveProfile({
      name: newName.trim(),
      is_child_profile: isChild,
    });

    if (res.success) {
      setShowAddModal(false);
      setNewName('');
      setIsChild(false);
    } else {
      setErrorMsg(res.error || 'Failed to create profile');
    }
  };

  const handlePinSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!pinTargetProfile) return;
    setErrorMsg(null);

    const res = await selectProfile(pinTargetProfile, pinInput);
    if (res.success) {
      setPinInput('');
      router.push('/');
    } else {
      setErrorMsg(res.error || 'Invalid PIN');
    }
  };

  if (authLoading || (profilesLoading && profiles.length === 0)) {
    return (
      <div className="min-h-screen pt-32 text-center text-white space-y-4">
        <Spinner size="lg" label="Loading viewing profiles..." />
      </div>
    );
  }

  if (!user) return null;

  return (
    <div className="min-h-screen pt-24 pb-16 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto flex flex-col items-center justify-center">
      <div className="text-center mb-10">
        <h1 className="text-3xl sm:text-4xl font-black text-white tracking-tight">
          {isManaging ? 'Manage Profiles' : "Who's Watching?"}
        </h1>
        <p className="text-gray-400 text-sm mt-2">
          {isManaging
            ? 'Select a profile to edit or delete, or add a new family member.'
            : 'Select your viewing profile to see personalized recommendations.'}
        </p>
      </div>

      <div className="flex flex-wrap items-center justify-center gap-6 sm:gap-8 max-w-3xl mb-12">
        {profiles.map((p, idx) => {
          const colorClass = AVATAR_COLORS[idx % AVATAR_COLORS.length];
          const isKids = Boolean(p.is_child_profile || p.is_child);
          const isActive = currentProfile?.id === p.id;

          return (
            <div key={p.id} className="flex flex-col items-center group relative">
              <button
                type="button"
                onClick={() => handleSelect(p)}
                className={`w-28 h-28 sm:w-32 sm:h-32 rounded-2xl ${
                  p.avatar && p.avatar.startsWith('bg-')
                    ? p.avatar
                    : p.avatar && p.avatar.startsWith('from-')
                    ? `bg-gradient-to-tr ${p.avatar}`
                    : colorClass
                } flex flex-col items-center justify-center text-white text-3xl font-black shadow-xl transition-all duration-300 overflow-hidden ${
                  isManaging
                    ? 'opacity-80 hover:opacity-100 hover:scale-105 border-2 border-white/40'
                    : isActive
                    ? 'ring-4 ring-[#E5B869] scale-105'
                    : 'hover:scale-105 hover:ring-4 hover:ring-white/40'
                }`}
              >
                {p.avatar &&
                (p.avatar.startsWith('http://') ||
                  p.avatar.startsWith('https://') ||
                  p.avatar.startsWith('/')) ? (
                  /* eslint-disable-next-line @next/next/no-img-element */
                  <img src={p.avatar} alt={p.name} className="w-full h-full object-cover" />
                ) : (
                  <span>{p.name.charAt(0).toUpperCase()}</span>
                )}
                {isKids && (
                  <span className="text-[10px] uppercase font-bold tracking-wider bg-black/60 px-2 py-0.5 rounded-full mt-1">
                    Kids
                  </span>
                )}
              </button>

              {isManaging && profiles.length > 1 && !isActive && (
                <button
                  type="button"
                  onClick={() => {
                    if (confirm(`Are you sure you want to delete profile "${p.name}"?`)) {
                      deleteProfile(p.id);
                    }
                  }}
                  className="absolute -top-2 -right-2 w-7 h-7 rounded-full bg-red-600 hover:bg-red-700 text-white flex items-center justify-center shadow-lg transition-transform hover:scale-110"
                  title="Delete profile"
                >
                  <Trash2 className="w-3.5 h-3.5" />
                </button>
              )}

              <span className="text-sm font-semibold text-gray-300 group-hover:text-white mt-3 text-center truncate max-w-[120px]">
                {p.name}
              </span>
            </div>
          );
        })}

        {/* Add Profile Card */}
        {profiles.length < 5 && (
          <div className="flex flex-col items-center group">
            <button
              type="button"
              onClick={() => {
                setErrorMsg(null);
                setShowAddModal(true);
              }}
              className="w-28 h-28 sm:w-32 sm:h-32 rounded-2xl border-2 border-dashed border-white/20 hover:border-white/50 bg-white/5 hover:bg-white/10 flex flex-col items-center justify-center text-gray-400 hover:text-white transition-all duration-300 hover:scale-105"
            >
              <Plus className="w-8 h-8" />
            </button>
            <span className="text-sm font-semibold text-gray-400 group-hover:text-white mt-3">
              Add Profile
            </span>
          </div>
        )}
      </div>

      {/* Action Toggle */}
      <div className="flex items-center space-x-4">
        <button
          type="button"
          onClick={() => setIsManaging(!isManaging)}
          className={`text-xs uppercase tracking-widest font-bold px-6 py-2.5 rounded-xl border transition-all ${
            isManaging
              ? 'bg-white text-black border-white hover:bg-gray-200'
              : 'text-gray-400 border-white/20 hover:border-white/60 hover:text-white'
          }`}
        >
          {isManaging ? 'Done' : 'Manage Profiles'}
        </button>

        <Link
          href="/account"
          className="text-xs uppercase tracking-widest font-bold px-6 py-2.5 rounded-xl text-gray-400 border border-white/20 hover:border-white/60 hover:text-white transition-all"
        >
          Account Settings
        </Link>
      </div>

      {/* Add Profile Modal */}
      {showAddModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
          <div className="bg-gray-900 max-w-md w-full rounded-2xl p-6 sm:p-8 border border-white/10 relative shadow-2xl">
            <button
              type="button"
              onClick={() => setShowAddModal(false)}
              className="absolute top-4 right-4 text-gray-400 hover:text-white"
            >
              <X className="w-5 h-5" />
            </button>

            <h3 className="text-xl font-bold text-white mb-1">Add Profile</h3>
            <p className="text-xs text-gray-400 mb-6">Create a profile for someone else watching on this account.</p>

            {errorMsg && (
              <div className="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs flex items-center gap-2">
                <AlertCircle className="w-4 h-4 shrink-0" />
                <span>{errorMsg}</span>
              </div>
            )}

            <form onSubmit={handleCreate} className="space-y-5">
              <div>
                <label className="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1.5">
                  Profile Name
                </label>
                <input
                  type="text"
                  maxLength={12}
                  value={newName}
                  onChange={(e) => setNewName(e.target.value)}
                  placeholder="e.g. Sarah"
                  required
                  autoFocus
                  className="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:border-[#E5B869]"
                />
              </div>

              <div className="flex items-center justify-between p-3.5 rounded-xl bg-white/5 border border-white/10">
                <div>
                  <p className="text-sm font-semibold text-white">Kid&apos;s Profile?</p>
                  <p className="text-xs text-gray-400">Only show content suitable for children</p>
                </div>
                <input
                  type="checkbox"
                  checked={isChild}
                  onChange={(e) => setIsChild(e.target.checked)}
                  className="w-5 h-5 accent-[#E5B869] rounded cursor-pointer"
                />
              </div>

              <div className="flex items-center justify-end space-x-3 pt-2">
                <button
                  type="button"
                  onClick={() => setShowAddModal(false)}
                  className="px-4 py-2 text-xs font-semibold text-gray-400 hover:text-white"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={actionLoading || !newName.trim()}
                  className="bg-[#E5B869] hover:bg-[#d9ab5c] text-black font-bold text-xs px-5 py-2.5 rounded-xl transition-all shadow-md shadow-[#E5B869]/20 disabled:opacity-50"
                >
                  {actionLoading ? 'Creating...' : 'Create Profile'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* PIN Verification Modal */}
      {pinTargetProfile && (
        <div className="fixed inset-0 z-50 bg-black/85 backdrop-blur-md flex items-center justify-center p-4">
          <div className="bg-gray-900 border border-[#E5B869]/30 rounded-2xl max-w-sm w-full p-6 shadow-2xl text-center">
            <div className="w-12 h-12 bg-[#E5B869]/20 text-[#E5B869] rounded-2xl flex items-center justify-center mx-auto mb-4">
              <Shield className="w-6 h-6" />
            </div>

            <h3 className="text-lg font-bold text-white mb-1">Parental PIN Required</h3>
            <p className="text-gray-400 text-xs mb-4">
              Enter your 4-digit PIN to access &quot;{pinTargetProfile.name}&quot;.
            </p>

            {errorMsg && (
              <div className="mb-4 p-2 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs">
                {errorMsg}
              </div>
            )}

            <form onSubmit={handlePinSubmit} className="space-y-4">
              <input
                type="password"
                maxLength={4}
                autoFocus
                required
                value={pinInput}
                onChange={(e) => setPinInput(e.target.value.replace(/\D/g, ''))}
                placeholder="••••"
                className="w-32 mx-auto text-center tracking-[0.5em] text-2xl font-mono bg-black/60 border border-white/20 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-[#E5B869]"
              />

              <div className="flex gap-2 justify-center pt-2">
                <button
                  type="button"
                  onClick={cancelPinVerification}
                  className="px-4 py-2 text-xs text-gray-400 hover:text-white"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={actionLoading || pinInput.length !== 4}
                  className="bg-[#E5B869] hover:bg-[#d9ab5c] text-black font-bold px-5 py-2 text-xs rounded-xl transition-all disabled:opacity-50"
                >
                  {actionLoading ? 'Verifying...' : 'Unlock Profile'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
