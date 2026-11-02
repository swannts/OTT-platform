'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';
import { api } from '@/lib/api';
import {
  Users,
  Plus,
  Trash2,
  Check,
  Shield,
  CreditCard,
  User as UserIcon,
  Smile,
  X,
} from 'lucide-react';

interface ProfileItem {
  id: string | number;
  name: string;
  avatar?: string;
  is_child?: boolean;
}

const AVATAR_COLORS = [
  'bg-red-600',
  'bg-blue-600',
  'bg-emerald-600',
  'bg-amber-600',
  'bg-purple-600',
  'bg-pink-600',
];

export default function ProfilesPage() {
  const { user, isLoading } = useAuth();
  const router = useRouter();

  const [profiles, setProfiles] = useState<ProfileItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [isManaging, setIsManaging] = useState(false);
  const [showAddModal, setShowAddModal] = useState(false);

  const [newName, setNewName] = useState('');
  const [isChild, setIsChild] = useState(false);
  const [selectedColor, setSelectedColor] = useState(AVATAR_COLORS[0]);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (!isLoading && !user) {
      router.push('/login?redirect=/profiles');
      return;
    }
    if (user) {
      loadProfiles();
    }
  }, [user, isLoading, router]);

  const loadProfiles = async () => {
    setLoading(true);
    try {
      const res = await api.getProfiles();
      const list = Array.isArray(res) ? res : [];
      if (list.length === 0 && user) {
        // Fallback default main profile from authenticated user
        setProfiles([
          { id: user.id, name: user.name, is_child: false },
        ]);
      } else {
        setProfiles(list);
      }
    } catch {
      if (user) {
        setProfiles([{ id: user.id, name: user.name, is_child: false }]);
      }
    } finally {
      setLoading(false);
    }
  };

  const handleSelectProfile = async (profile: ProfileItem) => {
    if (isManaging) return;
    try {
      await api.selectProfile(profile.id);
    } catch {
      // Ignore fallback
    }
    router.push('/');
  };

  const handleCreateProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newName.trim()) return;
    setSubmitting(true);
    try {
      await api.createProfile({
        name: newName.trim(),
        is_child: isChild,
      });
      setShowAddModal(false);
      setNewName('');
      setIsChild(false);
      await loadProfiles();
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : 'Failed to create profile');
    } finally {
      setSubmitting(false);
    }
  };

  const handleDeleteProfile = async (profileId: string | number) => {
    if (!confirm('Are you sure you want to delete this profile?')) return;
    try {
      await api.deleteProfile(profileId);
      await loadProfiles();
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : 'Failed to delete profile');
    }
  };

  if (isLoading) {
    return (
      <div className="min-h-screen pt-32 text-center text-white space-y-4">
        <div className="w-10 h-10 border-4 border-[#E5B869] border-t-transparent rounded-full animate-spin mx-auto" />
        <p className="text-sm text-gray-400">Loading viewing profiles...</p>
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

      {loading ? (
        <div className="text-gray-500 py-12">Loading profiles...</div>
      ) : (
        <div className="flex flex-wrap items-center justify-center gap-6 sm:gap-8 max-w-3xl mb-12">
          {profiles.map((p, idx) => {
            const colorClass = AVATAR_COLORS[idx % AVATAR_COLORS.length];
            return (
              <div key={p.id} className="flex flex-col items-center group relative">
                <button
                  onClick={() => handleSelectProfile(p)}
                  className={`w-28 h-28 sm:w-32 sm:h-32 rounded-2xl ${colorClass} flex flex-col items-center justify-center text-white text-3xl font-black shadow-xl transition-all duration-300 ${
                    isManaging
                      ? 'opacity-80 hover:opacity-100 hover:scale-105 border-2 border-white/40'
                      : 'hover:scale-105 hover:ring-4 hover:ring-white/40'
                  }`}
                >
                  {p.avatar ? (
                    <img src={p.avatar} alt={p.name} className="w-full h-full object-cover rounded-2xl" />
                  ) : (
                    <span>{p.name.charAt(0).toUpperCase()}</span>
                  )}
                  {p.is_child && (
                    <span className="text-[10px] uppercase font-bold tracking-wider bg-black/60 px-2 py-0.5 rounded-full mt-1">
                      Kids
                    </span>
                  )}
                </button>

                {isManaging && profiles.length > 1 && (
                  <button
                    onClick={() => handleDeleteProfile(p.id)}
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
                onClick={() => setShowAddModal(true)}
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
      )}

      {/* Action Toggle */}
      <div className="flex items-center space-x-4">
        <button
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
          <div className="glass-card max-w-md w-full rounded-2xl p-6 sm:p-8 border border-white/10 relative">
            <button
              onClick={() => setShowAddModal(false)}
              className="absolute top-4 right-4 text-gray-400 hover:text-white"
            >
              <X className="w-5 h-5" />
            </button>

            <h3 className="text-xl font-bold text-white mb-1">Add Profile</h3>
            <p className="text-xs text-gray-400 mb-6">Create a profile for someone else watching on this account.</p>

            <form onSubmit={handleCreateProfile} className="space-y-5">
              <div>
                <label className="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1.5">
                  Profile Name
                </label>
                <input
                  type="text"
                  value={newName}
                  onChange={(e) => setNewName(e.target.value)}
                  placeholder="e.g. Sarah"
                  required
                  autoFocus
                  className="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:border-red-500"
                />
              </div>

              <div className="flex items-center justify-between p-3.5 rounded-xl bg-white/5 border border-white/10">
                <div>
                  <p className="text-sm font-semibold text-white">Kid's Profile?</p>
                  <p className="text-xs text-gray-400">Only show content suitable for children</p>
                </div>
                <label className="relative inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={isChild}
                    onChange={(e) => setIsChild(e.target.checked)}
                    className="sr-only peer"
                  />
                  <div className="w-11 h-6 bg-white/20 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
                </label>
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
                  disabled={submitting || !newName.trim()}
                  className="bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white font-semibold text-xs px-5 py-2.5 rounded-xl transition-colors shadow-lg shadow-red-600/30"
                >
                  {submitting ? 'Creating...' : 'Create Profile'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

