'use client';

import React, { useState } from 'react';
import Image from 'next/image';
import { User as UserIcon, CheckCircle2, AlertCircle, Save, Camera, Mail, Phone, Calendar, Sparkles } from 'lucide-react';
import { Spinner } from '@/components/ui';
import type { User } from '@/types';
import type { UpdateProfileInput } from '../schemas/account.schema';

interface ProfileSectionProps {
  user: User & {
    first_name?: string;
    last_name?: string;
    mobile?: string;
    gender?: string;
    date_of_birth?: string;
    email_verified_at?: string;
  };
  onUpdate: (data: UpdateProfileInput & { avatar?: string }) => Promise<{ success: boolean; error?: string }>;
  isUpdating: boolean;
}

const AVATAR_PRESETS = [
  { id: 'avatar_1', color: 'from-amber-500 to-red-600', label: 'Crimson Star' },
  { id: 'avatar_2', color: 'from-blue-500 to-indigo-600', label: 'Sapphire Wave' },
  { id: 'avatar_3', color: 'from-emerald-500 to-teal-700', label: 'Emerald Glow' },
  { id: 'avatar_4', color: 'from-purple-500 to-pink-600', label: 'Amethyst Night' },
  { id: 'avatar_5', color: 'from-orange-500 to-amber-600', label: 'Golden Dawn' },
  { id: 'avatar_6', color: 'from-cyan-500 to-blue-700', label: 'Cyan Aurora' },
];

export function ProfileSection({ user, onUpdate, isUpdating }: ProfileSectionProps) {
  const [firstName, setFirstName] = useState(user.first_name || user.name?.split(' ')[0] || '');
  const [lastName, setLastName] = useState(user.last_name || user.name?.split(' ').slice(1).join(' ') || '');
  const [email, setEmail] = useState(user.email || '');
  const [mobile, setMobile] = useState(user.mobile || user.phone || '');
  const [gender, setGender] = useState<'male' | 'female' | 'other' | ''>((user.gender as 'male' | 'female' | 'other') || '');
  const [dateOfBirth, setDateOfBirth] = useState(user.date_of_birth || '');
  const [selectedAvatarColor, setSelectedAvatarColor] = useState(AVATAR_PRESETS[0].color);

  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setMessage(null);

    const res = await onUpdate({
      first_name: firstName.trim(),
      last_name: lastName.trim(),
      email: email.trim(),
      mobile: mobile.trim(),
      gender,
      date_of_birth: dateOfBirth,
    });

    if (res.success) {
      setMessage({ type: 'success', text: 'Personal information updated successfully.' });
    } else {
      setMessage({ type: 'error', text: res.error || 'Failed to update profile.' });
    }
  };

  const currentAvatar = user?.profile_image || user?.avatar;

  return (
    <div className="space-y-8">
      {/* Feedback Banner */}
      {message && (
        <div
          className={`p-4 rounded-xl flex items-center space-x-3 text-sm border transition-all ${
            message.type === 'success'
              ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400'
              : 'bg-red-500/10 border-red-500/20 text-red-400'
          }`}
        >
          {message.type === 'success' ? (
            <CheckCircle2 className="w-5 h-5 shrink-0" />
          ) : (
            <AlertCircle className="w-5 h-5 shrink-0" />
          )}
          <span>{message.text}</span>
        </div>
      )}

      {/* Avatar Showcase & Identity Card */}
      <div className="bg-gradient-to-r from-white/[0.05] to-white/[0.02] border border-white/10 rounded-2xl p-6 sm:p-8 backdrop-blur-xl">
        <div className="flex flex-col sm:flex-row items-center sm:items-start gap-6">
          <div className="relative group">
            {currentAvatar ? (
              <div className="w-24 h-24 rounded-2xl overflow-hidden border-2 border-[#E5B869] shadow-lg shadow-[#E5B869]/20 relative">
                <Image
                  src={currentAvatar}
                  alt={user.name || 'User'}
                  fill
                  sizes="96px"
                  className="object-cover"
                />
              </div>
            ) : (
              <div
                className={`w-24 h-24 rounded-2xl bg-gradient-to-tr ${selectedAvatarColor} flex items-center justify-center text-white text-3xl font-extrabold shadow-lg shadow-black/40 border-2 border-white/20`}
              >
                {(firstName || user.name || 'U').charAt(0).toUpperCase()}
              </div>
            )}
            <div className="absolute -bottom-2 -right-2 p-1.5 bg-[#E5B869] text-black rounded-xl shadow-md cursor-pointer hover:scale-110 transition-transform">
              <Camera className="w-4 h-4" />
            </div>
          </div>

          <div className="text-center sm:text-left flex-1">
            <div className="flex flex-wrap items-center justify-center sm:justify-start gap-3">
              <h2 className="text-2xl font-bold text-white">
                {firstName ? `${firstName} ${lastName}`.trim() : user.name}
              </h2>
              {user.is_subscribe ? (
                <span className="bg-[#E5B869] text-black text-xs font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full shadow-sm shadow-[#E5B869]/30">
                  VIP Subscriber
                </span>
              ) : (
                <span className="bg-white/10 text-gray-300 text-xs font-medium px-2.5 py-0.5 rounded-full">
                  Standard Member
                </span>
              )}
            </div>
            <p className="text-gray-400 text-sm mt-1">{user.email}</p>

            {/* Quick avatar color picker presets */}
            <div className="mt-4 flex items-center justify-center sm:justify-start gap-2">
              <span className="text-xs text-gray-400 flex items-center gap-1 mr-1">
                <Sparkles className="w-3.5 h-3.5 text-[#E5B869]" /> Theme:
              </span>
              {AVATAR_PRESETS.map((preset) => (
                <button
                  key={preset.id}
                  type="button"
                  title={preset.label}
                  onClick={() => setSelectedAvatarColor(preset.color)}
                  className={`w-6 h-6 rounded-full bg-gradient-to-tr ${preset.color} transition-transform hover:scale-125 border ${
                    selectedAvatarColor === preset.color
                      ? 'border-white ring-2 ring-[#E5B869] scale-110'
                      : 'border-transparent'
                  }`}
                />
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* Profile Edit Form */}
      <div className="bg-white/[0.03] border border-white/10 rounded-2xl p-6 sm:p-8 backdrop-blur-xl">
        <h3 className="text-lg font-bold text-white mb-6 flex items-center space-x-2">
          <UserIcon className="w-5 h-5 text-[#E5B869]" />
          <span>Personal Information</span>
        </h3>

        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
              <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
                First Name <span className="text-[#E5B869]">*</span>
              </label>
              <input
                type="text"
                required
                value={firstName}
                onChange={(e) => setFirstName(e.target.value)}
                placeholder="First name"
                className="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-[#E5B869] focus:ring-1 focus:ring-[#E5B869] transition-colors"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
                Last Name
              </label>
              <input
                type="text"
                value={lastName}
                onChange={(e) => setLastName(e.target.value)}
                placeholder="Last name"
                className="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-[#E5B869] focus:ring-1 focus:ring-[#E5B869] transition-colors"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
                Email Address <span className="text-[#E5B869]">*</span>
              </label>
              <div className="relative">
                <input
                  type="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="name@example.com"
                  className="w-full bg-black/40 border border-white/10 rounded-xl pl-11 pr-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-[#E5B869] focus:ring-1 focus:ring-[#E5B869] transition-colors"
                />
                <Mail className="w-4 h-4 text-gray-500 absolute left-4 top-4" />
              </div>
            </div>

            <div>
              <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
                Mobile / Phone
              </label>
              <div className="relative">
                <input
                  type="tel"
                  value={mobile}
                  onChange={(e) => setMobile(e.target.value)}
                  placeholder="+1 (555) 000-0000"
                  className="w-full bg-black/40 border border-white/10 rounded-xl pl-11 pr-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-[#E5B869] focus:ring-1 focus:ring-[#E5B869] transition-colors"
                />
                <Phone className="w-4 h-4 text-gray-500 absolute left-4 top-4" />
              </div>
            </div>

            <div>
              <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
                Gender
              </label>
              <select
                value={gender}
                onChange={(e) => setGender(e.target.value as 'male' | 'female' | 'other' | '')}
                className="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-[#E5B869] focus:ring-1 focus:ring-[#E5B869] transition-colors"
              >
                <option value="" className="bg-gray-900 text-gray-400">Select Gender</option>
                <option value="male" className="bg-gray-900 text-white">Male</option>
                <option value="female" className="bg-gray-900 text-white">Female</option>
                <option value="other" className="bg-gray-900 text-white">Other</option>
              </select>
            </div>

            <div>
              <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
                Date of Birth
              </label>
              <div className="relative">
                <input
                  type="date"
                  value={dateOfBirth}
                  onChange={(e) => setDateOfBirth(e.target.value)}
                  className="w-full bg-black/40 border border-white/10 rounded-xl pl-11 pr-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-[#E5B869] focus:ring-1 focus:ring-[#E5B869] transition-colors [color-scheme:dark]"
                />
                <Calendar className="w-4 h-4 text-gray-500 absolute left-4 top-4" />
              </div>
            </div>
          </div>

          <div className="pt-4 flex justify-end">
            <button
              type="submit"
              disabled={isUpdating}
              className="flex items-center space-x-2 bg-[#E5B869] hover:bg-[#d9ab5c] text-black font-bold px-6 py-3 rounded-xl transition-all shadow-md shadow-[#E5B869]/20 disabled:opacity-50 hover:scale-[1.01]"
            >
              {isUpdating ? (
                <>
                  <Spinner size="sm" />
                  <span>Saving Changes...</span>
                </>
              ) : (
                <>
                  <Save className="w-4 h-4" />
                  <span>Save Changes</span>
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
