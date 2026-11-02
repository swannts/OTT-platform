'use client';

import React, { useState } from 'react';
import { Users, Plus, Trash2, Check, Shield, X, AlertCircle } from 'lucide-react';
import { Spinner } from '@/components/ui';
import type { ProfileItem } from '../types/account.types';
import type { MultiProfileInput } from '../schemas/account.schema';

interface ViewerProfilesSectionProps {
  profiles: ProfileItem[];
  currentProfile: ProfileItem | null;
  onSelectProfile: (profile: ProfileItem, pin?: string) => Promise<{ success?: boolean; requiresPin?: boolean; error?: string }>;
  onSaveProfile: (input: MultiProfileInput & { id?: number | string }) => Promise<{ success: boolean; error?: string }>;
  onDeleteProfile: (id: number | string) => Promise<{ success: boolean; error?: string }>;
  pinTargetProfile: ProfileItem | null;
  onCancelPin: () => void;
  isLoading: boolean;
  actionLoading: boolean;
  isVIP?: boolean;
}

const PRESET_AVATARS = [
  'bg-red-600',
  'bg-blue-600',
  'bg-emerald-600',
  'bg-amber-600',
  'bg-purple-600',
  'bg-pink-600',
  'bg-cyan-600',
];

function ProfileAvatarBox({ avatar, name, isKids }: { avatar?: string | null; name: string; isKids?: boolean }) {
  const [imgError, setImgError] = useState(false);
  const isImg = Boolean(
    avatar &&
      !imgError &&
      (avatar.startsWith('http://') || avatar.startsWith('https://') || avatar.startsWith('/'))
  );
  const bgClass =
    avatar && avatar.startsWith('bg-')
      ? avatar
      : avatar && avatar.startsWith('from-')
      ? `bg-gradient-to-tr ${avatar}`
      : 'bg-gradient-to-tr from-amber-600 to-red-600';

  return (
    <div className="relative">
      <div
        className={`w-20 h-20 rounded-2xl flex items-center justify-center text-white text-2xl font-black shadow-lg overflow-hidden border-2 border-white/10 ${
          isImg ? 'bg-black/40' : bgClass
        }`}
      >
        {isImg ? (
          /* eslint-disable-next-line @next/next/no-img-element */
          <img
            src={avatar!}
            alt={name}
            onError={() => setImgError(true)}
            className="w-full h-full object-cover"
          />
        ) : (
          name.charAt(0).toUpperCase()
        )}
      </div>
      {isKids && (
        <span className="absolute -bottom-2 inset-x-0 mx-auto w-max bg-emerald-500 text-black text-[10px] font-black uppercase px-2 py-0.5 rounded-full shadow-md">
          Kids
        </span>
      )}
    </div>
  );
}

export function ViewerProfilesSection({
  profiles,
  currentProfile,
  onSelectProfile,
  onSaveProfile,
  onDeleteProfile,
  pinTargetProfile,
  onCancelPin,
  isLoading,
  actionLoading,
}: ViewerProfilesSectionProps) {
  const [showAddModal, setShowAddModal] = useState(false);
  const [name, setName] = useState('');
  const [isChild, setIsChild] = useState(false);
  const [selectedColor, setSelectedColor] = useState(PRESET_AVATARS[0]);
  const [pinInput, setPinInput] = useState('');
  const [formError, setFormError] = useState<string | null>(null);

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    setFormError(null);
    if (!name.trim()) {
      setFormError('Please enter a profile name.');
      return;
    }

    const res = await onSaveProfile({
      name: name.trim(),
      is_child_profile: isChild,
      avatar: selectedColor,
    });

    if (res.success) {
      setName('');
      setIsChild(false);
      setShowAddModal(false);
    } else {
      setFormError(res.error || 'Failed to create profile.');
    }
  };

  const handlePinSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!pinTargetProfile) return;
    if (pinInput.length !== 4) {
      setFormError('PIN must be 4 digits.');
      return;
    }

    const res = await onSelectProfile(pinTargetProfile, pinInput);
    if (!res.success) {
      setFormError(res.error || 'Invalid PIN.');
    } else {
      setPinInput('');
      setFormError(null);
    }
  };

  return (
    <div className="space-y-8">
      {/* Header Info Banner */}
      <div className="bg-gradient-to-r from-white/[0.04] to-white/[0.01] border border-white/10 rounded-2xl p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h3 className="text-lg font-bold text-white flex items-center gap-2">
            <Users className="w-5 h-5 text-[#E5B869]" />
            <span>Viewer Profiles</span>
          </h3>
          <p className="text-gray-400 text-sm mt-1">
            Create independent watchlists, recommendations, and viewing histories for everyone in your household.
          </p>
        </div>

        <button
          type="button"
          onClick={() => {
            setFormError(null);
            setShowAddModal(true);
          }}
          className="flex items-center justify-center space-x-2 bg-[#E5B869] hover:bg-[#d9ab5c] text-black font-bold px-4 py-2.5 rounded-xl transition-all shadow-md shadow-[#E5B869]/20 self-start sm:self-auto shrink-0"
        >
          <Plus className="w-4 h-4" />
          <span>Add Profile</span>
        </button>
      </div>

      {/* Profiles Grid */}
      {isLoading ? (
        <div className="py-12 flex justify-center">
          <Spinner size="lg" label="Loading profiles..." />
        </div>
      ) : (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-6">
          {profiles.map((profile) => {
            const isActive = currentProfile?.id === profile.id;
            const isKids = Boolean(profile.is_child_profile || profile.is_child);

            return (
              <div
                key={profile.id}
                className={`relative group flex flex-col items-center p-5 rounded-2xl border transition-all duration-300 ${
                  isActive
                    ? 'bg-[#E5B869]/10 border-[#E5B869] shadow-xl shadow-[#E5B869]/10 scale-105'
                    : 'bg-white/[0.03] border-white/10 hover:border-white/30 hover:bg-white/[0.06]'
                }`}
              >
                {/* Active Indicator Badge */}
                {isActive && (
                  <span className="absolute top-2 right-2 flex items-center gap-1 bg-[#E5B869] text-black text-[10px] font-black uppercase px-2 py-0.5 rounded-full shadow-md">
                    <Check className="w-3 h-3 stroke-[3]" /> Active
                  </span>
                )}

                {/* Avatar Icon */}
                <button
                  type="button"
                  onClick={() => onSelectProfile(profile)}
                  disabled={actionLoading || isActive}
                  className="relative cursor-pointer transition-transform group-hover:scale-105 my-2"
                >
                  <ProfileAvatarBox
                    avatar={profile.avatar}
                    name={profile.name}
                    isKids={isKids}
                  />
                </button>

                {/* Name */}
                <h4 className="text-white font-bold text-sm mt-3 truncate max-w-full text-center">
                  {profile.name}
                </h4>

                {/* Actions */}
                <div className="mt-4 flex items-center gap-2">
                  {!isActive && (
                    <button
                      type="button"
                      onClick={() => onSelectProfile(profile)}
                      className="text-xs text-gray-300 hover:text-white bg-white/5 hover:bg-white/10 px-3 py-1 rounded-lg transition-colors border border-white/10"
                    >
                      Switch
                    </button>
                  )}
                  {profiles.length > 1 && !isActive && (
                    <button
                      type="button"
                      title="Delete profile"
                      onClick={() => {
                        if (confirm(`Are you sure you want to delete profile "${profile.name}"?`)) {
                          onDeleteProfile(profile.id);
                        }
                      }}
                      className="text-gray-400 hover:text-red-400 p-1 rounded-lg transition-colors"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Add Profile Modal */}
      {showAddModal && (
        <div className="fixed inset-0 z-50 bg-black/80 backdrop-blur-md flex items-center justify-center p-4">
          <div className="bg-gray-900 border border-white/20 rounded-2xl max-w-md w-full p-6 shadow-2xl relative">
            <button
              type="button"
              onClick={() => setShowAddModal(false)}
              className="absolute top-4 right-4 text-gray-400 hover:text-white"
            >
              <X className="w-5 h-5" />
            </button>

            <h3 className="text-xl font-bold text-white mb-2">Create Viewer Profile</h3>
            <p className="text-gray-400 text-xs mb-6">
              Add a personalized space with unique watchlists and content ratings.
            </p>

            {formError && (
              <div className="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs flex items-center gap-2">
                <AlertCircle className="w-4 h-4 shrink-0" />
                <span>{formError}</span>
              </div>
            )}

            <form onSubmit={handleCreate} className="space-y-5">
              <div>
                <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
                  Profile Name (max 12 chars)
                </label>
                <input
                  type="text"
                  maxLength={12}
                  required
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="e.g., Sarah"
                  className="w-full bg-black/50 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-[#E5B869]"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
                  Avatar Color
                </label>
                <div className="flex gap-2">
                  {PRESET_AVATARS.map((color) => (
                    <button
                      key={color}
                      type="button"
                      onClick={() => setSelectedColor(color)}
                      className={`w-8 h-8 rounded-xl ${color} transition-all ${
                        selectedColor === color ? 'ring-2 ring-[#E5B869] scale-110' : 'opacity-70 hover:opacity-100'
                      }`}
                    />
                  ))}
                </div>
              </div>

              <div className="flex items-center justify-between p-3 rounded-xl bg-white/[0.03] border border-white/10">
                <div>
                  <span className="text-sm font-semibold text-white block">Kid&apos;s Profile?</span>
                  <span className="text-xs text-gray-400">Only family-friendly and children&apos;s content.</span>
                </div>
                <input
                  type="checkbox"
                  checked={isChild}
                  onChange={(e) => setIsChild(e.target.checked)}
                  className="w-5 h-5 accent-[#E5B869] rounded cursor-pointer"
                />
              </div>

              <div className="pt-2 flex justify-end gap-3">
                <button
                  type="button"
                  onClick={() => setShowAddModal(false)}
                  className="px-4 py-2.5 text-sm text-gray-400 hover:text-white"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={actionLoading}
                  className="bg-[#E5B869] hover:bg-[#d9ab5c] text-black font-bold px-5 py-2.5 rounded-xl text-sm transition-all"
                >
                  {actionLoading ? 'Creating...' : 'Create Profile'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Parental PIN Dialog (Required when exiting Kids profile) */}
      {pinTargetProfile && (
        <div className="fixed inset-0 z-50 bg-black/85 backdrop-blur-md flex items-center justify-center p-4">
          <div className="bg-gray-900 border border-[#E5B869]/30 rounded-2xl max-w-sm w-full p-6 shadow-2xl text-center">
            <div className="w-12 h-12 bg-[#E5B869]/20 text-[#E5B869] rounded-2xl flex items-center justify-center mx-auto mb-4">
              <Shield className="w-6 h-6" />
            </div>

            <h3 className="text-lg font-bold text-white mb-1">Parental PIN Required</h3>
            <p className="text-gray-400 text-xs mb-4">
              Switching from Kids to &quot;{pinTargetProfile.name}&quot; requires your 4-digit parental lock PIN.
            </p>

            {formError && (
              <div className="mb-4 p-2.5 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-xs">
                {formError}
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
                  onClick={onCancelPin}
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
