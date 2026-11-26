'use client';

import React, { useState } from 'react';
import { Sliders, Bell, Volume2, Subtitles, CheckCircle2, Save, Film } from 'lucide-react';

export interface PlaybackPreferences {
  audioLanguage: string;
  subtitleLanguage: string;
  streamingQuality: 'auto' | '1080p' | '720p' | '480p';
  autoplayNext: boolean;
  autoplayPreviews: boolean;
}

export interface NotificationPreferences {
  newReleases: boolean;
  watchlistUpdates: boolean;
  securityAlerts: boolean;
  billingReminders: boolean;
}

const DEFAULT_PLAYBACK: PlaybackPreferences = {
  audioLanguage: 'en',
  subtitleLanguage: 'off',
  streamingQuality: 'auto',
  autoplayNext: true,
  autoplayPreviews: true,
};

const DEFAULT_NOTIFICATIONS: NotificationPreferences = {
  newReleases: true,
  watchlistUpdates: true,
  securityAlerts: true,
  billingReminders: true,
};

const LANGUAGES = [
  { code: 'en', label: 'English' },
  { code: 'ar', label: 'Arabic (العربية)' },
  { code: 'bn', label: 'Bengali (বাংলা)' },
  { code: 'fr', label: 'French (Français)' },
  { code: 'es', label: 'Spanish (Español)' },
  { code: 'ur', label: 'Urdu (اردو)' },
];

const SUBTITLE_OPTIONS = [
  { code: 'off', label: 'Off' },
  ...LANGUAGES,
];

export function PreferencesSection() {
  const [playback, setPlayback] = useState<PlaybackPreferences>(() => {
    if (typeof window === 'undefined') return DEFAULT_PLAYBACK;
    try {
      const stored = localStorage.getItem('noor_playback_preferences');
      return stored ? JSON.parse(stored) : DEFAULT_PLAYBACK;
    } catch {
      return DEFAULT_PLAYBACK;
    }
  });

  const [notifications, setNotifications] = useState<NotificationPreferences>(() => {
    if (typeof window === 'undefined') return DEFAULT_NOTIFICATIONS;
    try {
      const stored = localStorage.getItem('noor_notification_preferences');
      return stored ? JSON.parse(stored) : DEFAULT_NOTIFICATIONS;
    } catch {
      return DEFAULT_NOTIFICATIONS;
    }
  });

  const [saveToast, setSaveToast] = useState(false);

  const handleSave = (e: React.FormEvent) => {
    e.preventDefault();
    try {
      localStorage.setItem('noor_playback_preferences', JSON.stringify(playback));
      localStorage.setItem('noor_notification_preferences', JSON.stringify(notifications));
      setSaveToast(true);
      setTimeout(() => setSaveToast(false), 3000);
    } catch {
      // Ignore storage error
    }
  };

  return (
    <form onSubmit={handleSave} className="space-y-8">
      {saveToast && (
        <div className="p-4 rounded-xl flex items-center space-x-3 text-sm border bg-emerald-500/10 border-emerald-500/20 text-emerald-400">
          <CheckCircle2 className="w-5 h-5 shrink-0" />
          <span>Preferences saved successfully. Your default playback and alert settings are updated.</span>
        </div>
      )}

      {/* Streaming & Playback Preferences */}
      <div className="bg-white/[0.03] border border-white/10 rounded-2xl p-6 sm:p-8 backdrop-blur-xl">
        <h3 className="text-lg font-bold text-white mb-2 flex items-center space-x-2">
          <Sliders className="w-5 h-5 text-[#E5B869]" />
          <span>Playback & Streaming Controls</span>
        </h3>
        <p className="text-gray-400 text-xs mb-6">
          Customize your default viewing experience across all web, tablet, and smart TV sessions.
        </p>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
          {/* Default Audio Language */}
          <div>
            <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2 flex items-center gap-1.5">
              <Volume2 className="w-4 h-4 text-[#E5B869]" />
              <span>Default Audio Language</span>
            </label>
            <select
              value={playback.audioLanguage}
              onChange={(e) => setPlayback({ ...playback, audioLanguage: e.target.value })}
              className="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-[#E5B869] transition-colors"
            >
              {LANGUAGES.map((lang) => (
                <option key={lang.code} value={lang.code} className="bg-gray-900 text-white">
                  {lang.label}
                </option>
              ))}
            </select>
          </div>

          {/* Default Subtitles */}
          <div>
            <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2 flex items-center gap-1.5">
              <Subtitles className="w-4 h-4 text-[#E5B869]" />
              <span>Default Subtitle Language</span>
            </label>
            <select
              value={playback.subtitleLanguage}
              onChange={(e) => setPlayback({ ...playback, subtitleLanguage: e.target.value })}
              className="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-[#E5B869] transition-colors"
            >
              {SUBTITLE_OPTIONS.map((sub) => (
                <option key={sub.code} value={sub.code} className="bg-gray-900 text-white">
                  {sub.label}
                </option>
              ))}
            </select>
          </div>

          {/* Video Streaming Quality */}
          <div className="sm:col-span-2">
            <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2 flex items-center gap-1.5">
              <Film className="w-4 h-4 text-[#E5B869]" />
              <span>Default Streaming Quality</span>
            </label>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
              {[
                { id: 'auto', label: 'Auto', desc: 'Adapts to network speed' },
                { id: '1080p', label: '1080p Full HD', desc: 'Best picture quality' },
                { id: '720p', label: '720p HD', desc: 'Balanced bandwidth' },
                { id: '480p', label: 'Data Saver', desc: 'Lowest data usage' },
              ].map((q) => (
                <button
                  key={q.id}
                  type="button"
                  onClick={() => setPlayback({ ...playback, streamingQuality: q.id as PlaybackPreferences['streamingQuality'] })}
                  className={`p-3.5 rounded-xl border text-left transition-all ${
                    playback.streamingQuality === q.id
                      ? 'bg-[#E5B869]/15 border-[#E5B869] text-white shadow-md shadow-[#E5B869]/10'
                      : 'bg-black/30 border-white/10 text-gray-400 hover:text-white hover:border-white/20'
                  }`}
                >
                  <p className="font-bold text-sm text-white">{q.label}</p>
                  <p className="text-[10px] text-gray-400 mt-0.5">{q.desc}</p>
                </button>
              ))}
            </div>
          </div>
        </div>

        {/* Toggles */}
        <div className="mt-6 pt-6 border-t border-white/10 space-y-4">
          <div className="flex items-center justify-between p-3.5 rounded-xl bg-black/30 border border-white/10">
            <div>
              <p className="text-white font-semibold text-sm">Autoplay Next Episode</p>
              <p className="text-gray-400 text-xs">Automatically play the next episode when watching TV shows</p>
            </div>
            <input
              type="checkbox"
              checked={playback.autoplayNext}
              onChange={(e) => setPlayback({ ...playback, autoplayNext: e.target.checked })}
              className="w-5 h-5 accent-[#E5B869] cursor-pointer"
            />
          </div>

          <div className="flex items-center justify-between p-3.5 rounded-xl bg-black/30 border border-white/10">
            <div>
              <p className="text-white font-semibold text-sm">Autoplay Video Previews</p>
              <p className="text-gray-400 text-xs">Play video trailers automatically when browsing featured titles</p>
            </div>
            <input
              type="checkbox"
              checked={playback.autoplayPreviews}
              onChange={(e) => setPlayback({ ...playback, autoplayPreviews: e.target.checked })}
              className="w-5 h-5 accent-[#E5B869] cursor-pointer"
            />
          </div>
        </div>
      </div>

      {/* Notification Preferences */}
      <div className="bg-white/[0.03] border border-white/10 rounded-2xl p-6 sm:p-8 backdrop-blur-xl">
        <h3 className="text-lg font-bold text-white mb-2 flex items-center space-x-2">
          <Bell className="w-5 h-5 text-[#E5B869]" />
          <span>Notification & Communication Preferences</span>
        </h3>
        <p className="text-gray-400 text-xs mb-6">
          Choose which notifications and updates you wish to receive from NOOR OTT.
        </p>

        <div className="space-y-4">
          <div className="flex items-center justify-between p-3.5 rounded-xl bg-black/30 border border-white/10">
            <div>
              <p className="text-white font-semibold text-sm">New Releases & Recommendations</p>
              <p className="text-gray-400 text-xs">Get notified when new movies, seasons, and trending lectures are released</p>
            </div>
            <input
              type="checkbox"
              checked={notifications.newReleases}
              onChange={(e) => setNotifications({ ...notifications, newReleases: e.target.checked })}
              className="w-5 h-5 accent-[#E5B869] cursor-pointer"
            />
          </div>

          <div className="flex items-center justify-between p-3.5 rounded-xl bg-black/30 border border-white/10">
            <div>
              <p className="text-white font-semibold text-sm">Watchlist Activity</p>
              <p className="text-gray-400 text-xs">Alerts when saved shows or movies in your watchlist become available</p>
            </div>
            <input
              type="checkbox"
              checked={notifications.watchlistUpdates}
              onChange={(e) => setNotifications({ ...notifications, watchlistUpdates: e.target.checked })}
              className="w-5 h-5 accent-[#E5B869] cursor-pointer"
            />
          </div>

          <div className="flex items-center justify-between p-3.5 rounded-xl bg-black/30 border border-white/10">
            <div>
              <p className="text-white font-semibold text-sm">Account & Security Alerts</p>
              <p className="text-gray-400 text-xs">Security warnings, new device logins, and password modification receipts</p>
            </div>
            <input
              type="checkbox"
              checked={notifications.securityAlerts}
              onChange={(e) => setNotifications({ ...notifications, securityAlerts: e.target.checked })}
              className="w-5 h-5 accent-[#E5B869] cursor-pointer"
            />
          </div>

          <div className="flex items-center justify-between p-3.5 rounded-xl bg-black/30 border border-white/10">
            <div>
              <p className="text-white font-semibold text-sm">Billing & Subscription Reminders</p>
              <p className="text-gray-400 text-xs">Upcoming plan renewal alerts and payment receipts</p>
            </div>
            <input
              type="checkbox"
              checked={notifications.billingReminders}
              onChange={(e) => setNotifications({ ...notifications, billingReminders: e.target.checked })}
              className="w-5 h-5 accent-[#E5B869] cursor-pointer"
            />
          </div>
        </div>
      </div>

      {/* Save Button */}
      <div className="flex justify-end">
        <button
          type="submit"
          className="flex items-center space-x-2 bg-[#E5B869] hover:bg-[#d9ab5c] text-black font-bold px-6 py-3 rounded-xl transition-all shadow-md shadow-[#E5B869]/20 hover:scale-[1.01]"
        >
          <Save className="w-4 h-4" />
          <span>Save Preferences</span>
        </button>
      </div>
    </form>
  );
}
