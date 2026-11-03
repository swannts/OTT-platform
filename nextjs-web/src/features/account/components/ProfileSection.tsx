'use client';

import React, { useState, useRef, useEffect } from 'react';
import Link from 'next/link';
import { User as UserIcon, CheckCircle2, AlertCircle, Save, Camera, Mail, Phone, Calendar, Sparkles, Crown, Upload, X } from 'lucide-react';
import { Spinner } from '@/components/ui';
import { useLanguage } from '@/providers';
import { formatDhakaDate, formatDaysRemaining } from '@/lib/datetime';
import type { User, SubscriptionStatus } from '@/types';
import type { UpdateProfileInput } from '../schemas/account.schema';
import type { AccountTab } from '../types/account.types';

interface ProfileSectionProps {
  user: User & {
    first_name?: string;
    last_name?: string;
    mobile?: string;
    gender?: string;
    date_of_birth?: string;
    email_verified_at?: string;
  };
  subscription?: SubscriptionStatus | null;
  onUpdate: (data: UpdateProfileInput & { avatar?: string; avatarFile?: File | null }) => Promise<{ success: boolean; error?: string }>;
  isUpdating: boolean;
  onNavigateTab?: (tab: AccountTab) => void;
}

const AVATAR_PRESETS = [
  { id: 'avatar_1', color: 'from-amber-500 to-red-600', label: 'Crimson Star' },
  { id: 'avatar_2', color: 'from-blue-500 to-indigo-600', label: 'Sapphire Wave' },
  { id: 'avatar_3', color: 'from-emerald-500 to-teal-700', label: 'Emerald Glow' },
  { id: 'avatar_4', color: 'from-purple-500 to-pink-600', label: 'Amethyst Night' },
  { id: 'avatar_5', color: 'from-orange-500 to-amber-600', label: 'Golden Dawn' },
  { id: 'avatar_6', color: 'from-cyan-500 to-blue-700', label: 'Cyan Aurora' },
];

export function ProfileSection({
  user,
  subscription,
  onUpdate,
  isUpdating,
  onNavigateTab,
}: ProfileSectionProps) {
  const { t, locale } = useLanguage();
  const [firstName, setFirstName] = useState(user.first_name || user.name?.split(' ')[0] || '');
  const [lastName, setLastName] = useState(user.last_name || user.name?.split(' ').slice(1).join(' ') || '');
  const [email, setEmail] = useState(user.email || '');
  const [mobile, setMobile] = useState(user.mobile || user.phone || '');
  const [gender, setGender] = useState<'male' | 'female' | 'other' | ''>((user.gender as 'male' | 'female' | 'other') || '');
  const [dateOfBirth, setDateOfBirth] = useState(user.date_of_birth || '');

  // Normalize image URL (handle local 127.0.0.1 vs localhost cross-origin differences)
  const normalizedAvatar = React.useMemo(() => {
    const raw = user?.profile_image || user?.avatar;
    if (!raw || typeof raw !== 'string') return null;
    const trimmed = raw.trim();
    if (trimmed.startsWith('from-') || trimmed.startsWith('bg-')) return null;

    if (typeof window !== 'undefined') {
      if (window.location.hostname === 'localhost' && trimmed.includes('127.0.0.1:8000')) {
        return trimmed.replace('127.0.0.1:8000', 'localhost:8000');
      }
      if (window.location.hostname === '127.0.0.1' && trimmed.includes('localhost:8000')) {
        return trimmed.replace('localhost:8000', '127.0.0.1:8000');
      }
    }
    return trimmed;
  }, [user?.profile_image, user?.avatar]);

  // Check if saved avatar was a theme gradient preset
  const isPresetColor = Boolean(
    user?.avatar && (user.avatar.startsWith('from-') || user.avatar.startsWith('bg-'))
  );

  const [selectedAvatarColor, setSelectedAvatarColor] = useState<string>(() => {
    if (isPresetColor && user?.avatar) {
      return user.avatar;
    }
    return AVATAR_PRESETS[0].color;
  });

  const [hasImgError, setHasImgError] = useState(false);
  const [useThemeAvatar, setUseThemeAvatar] = useState(isPresetColor || !normalizedAvatar);

  // Reset image error if user avatar changes
  useEffect(() => {
    setHasImgError(false);
  }, [normalizedAvatar]);

  // Custom photo upload state
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [avatarFile, setAvatarFile] = useState<File | null>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);

  const isSubscribed = Boolean(user.is_subscribe || subscription?.has_active_subscription);
  const activePlanName = subscription?.subscription?.name || (isSubscribed ? 'VIP Pass' : 'Free Account');
  const renewalDate = subscription?.subscription?.end_date;

  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  const handleSelectThemeColor = (color: string) => {
    setSelectedAvatarColor(color);
    setUseThemeAvatar(true);
    handleRemoveStagedPhoto();
  };

  const handleUseCustomPhoto = () => {
    setUseThemeAvatar(false);
    setHasImgError(false);
    handleRemoveStagedPhoto();
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
      setMessage({ type: 'error', text: 'Please select an image file (PNG, JPG, or WebP).' });
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      setMessage({ type: 'error', text: 'Image file size must be less than 5MB.' });
      return;
    }

    setAvatarFile(file);
    const objectUrl = URL.createObjectURL(file);
    setPreviewUrl(objectUrl);
    setUseThemeAvatar(false);
    setHasImgError(false);
    setMessage({ type: 'success', text: `Selected "${file.name}". Click "Save Changes" to upload.` });
  };

  const handleRemoveStagedPhoto = () => {
    setAvatarFile(null);
    if (previewUrl) {
      URL.revokeObjectURL(previewUrl);
      setPreviewUrl(null);
    }
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

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
      avatar: useThemeAvatar ? selectedAvatarColor : undefined,
      avatarFile: avatarFile,
    });

    if (res.success) {
      setMessage({ type: 'success', text: 'Personal information & profile image updated successfully.' });
      setAvatarFile(null);
      setPreviewUrl(null);
    } else {
      setMessage({ type: 'error', text: res.error || 'Failed to update profile.' });
    }
  };

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
            {/* Hidden native file input */}
            <input
              type="file"
              ref={fileInputRef}
              accept="image/png,image/jpeg,image/webp"
              onChange={handleFileChange}
              className="hidden"
            />

            {previewUrl ? (
              <div className="w-24 h-24 rounded-2xl overflow-hidden border-2 border-emerald-400 shadow-lg shadow-emerald-500/20 relative">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src={previewUrl}
                  alt="New Avatar Preview"
                  className="w-full h-full object-cover"
                />
                <button
                  type="button"
                  onClick={handleRemoveStagedPhoto}
                  className="absolute top-1 right-1 p-1 bg-black/80 hover:bg-red-600 text-white rounded-lg transition-colors"
                  title="Remove staged photo"
                >
                  <X className="w-3.5 h-3.5" />
                </button>
              </div>
            ) : !useThemeAvatar && normalizedAvatar && !hasImgError ? (
              <div className="w-24 h-24 rounded-2xl overflow-hidden border-2 border-[#E5B869] shadow-lg shadow-[#E5B869]/20 relative">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src={normalizedAvatar}
                  alt={user.name || 'User'}
                  onError={() => setHasImgError(true)}
                  className="w-full h-full object-cover"
                />
              </div>
            ) : (
              <div
                className={`w-24 h-24 rounded-2xl bg-gradient-to-tr ${selectedAvatarColor} flex items-center justify-center text-white text-3xl font-extrabold shadow-lg shadow-black/40 border-2 border-white/20`}
              >
                {(firstName || user.name || 'U').charAt(0).toUpperCase()}
              </div>
            )}
            <button
              type="button"
              onClick={() => fileInputRef.current?.click()}
              title="Upload new avatar photo"
              className="absolute -bottom-2 -right-2 p-1.5 bg-[#E5B869] hover:bg-[#d9ab5c] text-black rounded-xl shadow-md cursor-pointer hover:scale-110 transition-transform"
            >
              <Camera className="w-4 h-4" />
            </button>
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

            <div className="mt-3 flex flex-wrap items-center justify-center sm:justify-start gap-3">
              <button
                type="button"
                onClick={() => fileInputRef.current?.click()}
                className="text-xs text-gray-300 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 px-3 py-1.5 rounded-xl transition-colors flex items-center gap-1.5"
              >
                <Upload className="w-3.5 h-3.5 text-[#E5B869]" />
                <span>Upload Custom Photo</span>
              </button>

              {normalizedAvatar && useThemeAvatar && !hasImgError && (
                <button
                  type="button"
                  onClick={handleUseCustomPhoto}
                  className="text-xs text-[#E5B869] hover:text-[#d9ab5c] bg-[#E5B869]/10 hover:bg-[#E5B869]/20 border border-[#E5B869]/30 px-3 py-1.5 rounded-xl transition-colors font-medium"
                >
                  Restore Custom Photo
                </button>
              )}

              {/* Quick avatar color picker presets */}
              <div className="flex items-center gap-2">
                <span className="text-xs text-gray-400 flex items-center gap-1 mr-1">
                  <Sparkles className="w-3.5 h-3.5 text-[#E5B869]" /> Theme:
                </span>
                {AVATAR_PRESETS.map((preset) => (
                  <button
                    key={preset.id}
                    type="button"
                    title={preset.label}
                    onClick={() => handleSelectThemeColor(preset.color)}
                    className={`w-6 h-6 rounded-full bg-gradient-to-tr ${preset.color} transition-transform hover:scale-125 border ${
                      selectedAvatarColor === preset.color && useThemeAvatar && !previewUrl
                        ? 'border-white ring-2 ring-[#E5B869] scale-110'
                        : 'border-transparent'
                    }`}
                  />
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Membership VIP Overview Banner */}
      <div className="bg-gradient-to-r from-[#E5B869]/15 via-white/[0.03] to-transparent border border-[#E5B869]/30 rounded-2xl p-6 sm:p-7 backdrop-blur-xl relative overflow-hidden">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-5">
          <div className="flex items-start space-x-4">
            <div className="w-12 h-12 rounded-2xl bg-[#E5B869]/20 border border-[#E5B869]/30 flex items-center justify-center text-[#E5B869] shrink-0 shadow-lg shadow-[#E5B869]/10">
              <Crown className="w-6 h-6" />
            </div>
            <div>
              <div className="flex items-center space-x-2.5">
                <span className="text-xs uppercase font-extrabold tracking-wider text-gray-400">
                  {t('membership', 'Membership')}
                </span>
                {isSubscribed ? (
                  <span className="bg-[#E5B869]/20 text-[#E5B869] border border-[#E5B869]/40 text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full tracking-wider">
                    {t('active', 'Active')}
                  </span>
                ) : (
                  <span className="bg-white/10 text-gray-400 text-[10px] font-bold uppercase px-2 py-0.5 rounded-full">
                    Free
                  </span>
                )}
              </div>
              <h3 className="text-2xl font-black text-white mt-1">
                {isSubscribed ? activePlanName : 'Standard Free Account'}
              </h3>
              <p className="text-xs text-gray-400 mt-1">
                {isSubscribed ? (
                  <>
                    {t('you_have', 'You have')}{' '}
                    <span className="text-white font-bold">
                      {formatDaysRemaining(subscription?.days_left, locale)}
                    </span>{' '}
                    {t('days_remaining', 'remaining')}{renewalDate ? ` • Renews ${formatDhakaDate(renewalDate)}` : ''}.
                  </>
                ) : (
                  'Upgrade today to stream ad-free 4K video content and access all premium channels.'
                )}
              </p>
            </div>
          </div>

          <div className="flex items-center space-x-3 self-start sm:self-center shrink-0">
            {onNavigateTab ? (
              <button
                type="button"
                onClick={() => onNavigateTab('billing')}
                className="bg-white/10 hover:bg-white/20 text-white font-semibold text-xs px-4 py-2.5 rounded-xl border border-white/10 transition-colors cursor-pointer"
              >
                {t('manage_membership', 'Manage Membership')}
              </button>
            ) : (
              <Link
                href="/account?tab=billing"
                className="bg-white/10 hover:bg-white/20 text-white font-semibold text-xs px-4 py-2.5 rounded-xl border border-white/10 transition-colors"
              >
                {t('manage_membership', 'Manage Membership')}
              </Link>
            )}
            {!isSubscribed && (
              <Link
                href="/pricing"
                className="bg-[#E5B869] hover:bg-[#d9ab5c] text-black font-bold text-xs px-4 py-2.5 rounded-xl transition-all shadow-md shadow-[#E5B869]/20"
              >
                Upgrade to VIP
              </Link>
            )}
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
