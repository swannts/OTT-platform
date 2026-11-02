'use client';

import React, { useState, useEffect, useRef } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/providers';
import { api } from '@/lib/api';
import { NotificationItem } from '@/types';
import {
  Bell,
  Check,
  CheckCheck,
  Trash2,
  Film,
  Tv,
  Radio,
  Crown,
  Sparkles,
  ExternalLink,
} from 'lucide-react';

export interface AppNotification {
  id: string;
  title: string;
  message: string;
  time: string;
  isRead: boolean;
  type: 'live' | 'series' | 'movie' | 'vip' | 'general';
  href: string;
}

const DEFAULT_NOTIFICATIONS: AppNotification[] = [
  {
    id: 'notif-1',
    title: 'Holy Mosque 24/7 Live Stream',
    message: 'Live stream from Makkah Al-Mukarramah is now streaming in 4K Ultra HD.',
    time: '15m ago',
    isRead: false,
    type: 'live',
    href: '/live-tv',
  },
  {
    id: 'notif-2',
    title: 'New Episode: Prophetic Chronicles',
    message: 'Episode 7: "The Covenant at Aqabah" is now available to stream.',
    time: '2h ago',
    isRead: false,
    type: 'series',
    href: '/tv-shows',
  },
  {
    id: 'notif-3',
    title: 'Special Ramadan Release',
    message: 'Historic docuseries "Golden Age of Cordoba" added to Sacred Documentaries.',
    time: '1d ago',
    isRead: true,
    type: 'movie',
    href: '/movies',
  },
  {
    id: 'notif-4',
    title: 'NOOR VIP Membership Privilege',
    message: 'Enjoy 100% ad-free viewing, Dolby audio, and 4 simultaneous screens.',
    time: '2d ago',
    isRead: true,
    type: 'vip',
    href: '/pricing',
  },
];

export default function NotificationDropdown() {
  const router = useRouter();
  const { user } = useAuth();
  const [isOpen, setIsOpen] = useState(false);
  const [notifications, setNotifications] = useState<AppNotification[]>([]);
  const dropdownRef = useRef<HTMLDivElement>(null);

  // Initialize notifications from localStorage or defaults / API
  useEffect(() => {
    const saved = localStorage.getItem('noor_notifications');
    if (saved) {
      try {
        setNotifications(JSON.parse(saved));
        return;
      } catch {
        // Fallback
      }
    }

    if (user) {
      api
        .getNotifications(false)
        .then((res) => {
          const rawItems = res?.notification_data || res?.notifications || [];
          if (Array.isArray(rawItems) && rawItems.length > 0) {
            const mapped: AppNotification[] = (rawItems as NotificationItem[]).map((n, idx: number) => {
              const dataObj = typeof n.data === 'object' && n.data !== null ? n.data : {};
              return {
                id: String(n.id || `api-${idx}`),
                title: String(n.title || dataObj.title || 'NOOR Cinema Update'),
                message: String(n.description || n.body || dataObj.description || 'New content added to your library.'),
                time: 'Just now',
                isRead: Boolean(n.read_at || n.read),
                type: 'general',
                href: '/notifications',
              };
            });
            setNotifications(mapped);
            localStorage.setItem('noor_notifications', JSON.stringify(mapped));
            return;
          }
          setNotifications(DEFAULT_NOTIFICATIONS);
          localStorage.setItem('noor_notifications', JSON.stringify(DEFAULT_NOTIFICATIONS));
        })
        .catch(() => {
          setNotifications(DEFAULT_NOTIFICATIONS);
          localStorage.setItem('noor_notifications', JSON.stringify(DEFAULT_NOTIFICATIONS));
        });
    } else {
      setNotifications(DEFAULT_NOTIFICATIONS);
      localStorage.setItem('noor_notifications', JSON.stringify(DEFAULT_NOTIFICATIONS));
    }
  }, [user]);

  // Click outside to close
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

  const unreadCount = notifications.filter((n) => !n.isRead).length;

  const handleMarkAllRead = async () => {
    const updated = notifications.map((n) => ({ ...n, isRead: true }));
    setNotifications(updated);
    localStorage.setItem('noor_notifications', JSON.stringify(updated));

    if (user) {
      try {
        await api.getNotifications(true);
      } catch {
        // ignore
      }
    }
  };

  const handleItemClick = (notif: AppNotification) => {
    const updated = notifications.map((n) => (n.id === notif.id ? { ...n, isRead: true } : n));
    setNotifications(updated);
    localStorage.setItem('noor_notifications', JSON.stringify(updated));
    setIsOpen(false);
    router.push(notif.href);
  };

  const handleDeleteItem = (e: React.MouseEvent, id: string) => {
    e.stopPropagation();
    const updated = notifications.filter((n) => n.id !== id);
    setNotifications(updated);
    localStorage.setItem('noor_notifications', JSON.stringify(updated));
  };

  const getIcon = (type: AppNotification['type']) => {
    switch (type) {
      case 'live':
        return <Radio className="w-3.5 h-3.5 text-red-400" />;
      case 'series':
        return <Tv className="w-3.5 h-3.5 text-[#E5B869]" />;
      case 'movie':
        return <Film className="w-3.5 h-3.5 text-blue-400" />;
      case 'vip':
        return <Crown className="w-3.5 h-3.5 text-amber-400" />;
      default:
        return <Sparkles className="w-3.5 h-3.5 text-emerald-400" />;
    }
  };

  return (
    <div className="relative" ref={dropdownRef}>
      {/* Bell Trigger Button */}
      <button
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        className={`w-9 h-9 rounded-full flex items-center justify-center transition-all relative cursor-pointer active:scale-95 flex-shrink-0 backdrop-blur-md ${
          isOpen
            ? 'bg-[#E5B869]/20 border border-[#E5B869]/60 text-[#E5B869]'
            : 'bg-white/[0.08] hover:bg-white/[0.12] border border-white/10 hover:border-white/20 text-gray-200 hover:text-white'
        }`}
        aria-label="Notifications"
        aria-expanded={isOpen}
      >
        <Bell className="w-4 h-4 text-gray-200" strokeWidth={1.75} />
        {unreadCount > 0 && (
          <span className="absolute top-1.5 right-1.5 flex h-2 w-2">
            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#E5B869] opacity-75" />
            <span className="relative inline-flex rounded-full h-2 w-2 bg-[#E5B869] ring-2 ring-[#070a0e] shadow-[0_0_8px_#E5B869]" />
          </span>
        )}
      </button>

      {/* Notifications Popover Dropdown */}
      {isOpen && (
        <div className="absolute right-0 mt-2.5 w-80 sm:w-96 bg-[#090e15]/98 backdrop-blur-2xl rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.9)] border border-white/10 z-50 overflow-hidden animate-hero-title">
          {/* Header */}
          <div className="flex items-center justify-between px-4 py-3 border-b border-white/10 bg-white/[0.02]">
            <div className="flex items-center space-x-2">
              <span className="text-sm font-bold text-white">Notifications</span>
              {unreadCount > 0 ? (
                <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#E5B869]/20 text-[#E5B869] border border-[#E5B869]/30">
                  {unreadCount} new
                </span>
              ) : (
                <span className="px-2 py-0.5 rounded-full text-[10px] font-medium bg-white/5 text-gray-400">
                  All caught up
                </span>
              )}
            </div>

            {unreadCount > 0 && (
              <button
                type="button"
                onClick={handleMarkAllRead}
                className="flex items-center space-x-1 text-[11px] font-medium text-gray-400 hover:text-[#E5B869] transition-colors cursor-pointer"
              >
                <CheckCheck className="w-3.5 h-3.5" />
                <span>Mark all read</span>
              </button>
            )}
          </div>

          {/* List */}
          <div className="max-h-80 overflow-y-auto divide-y divide-white/5 scrollbar-thin scrollbar-thumb-white/10">
            {notifications.length > 0 ? (
              notifications.map((notif) => (
                <div
                  key={notif.id}
                  onClick={() => handleItemClick(notif)}
                  className={`group relative flex items-start space-x-3 p-3.5 transition-colors cursor-pointer hover:bg-white/[0.04] ${
                    !notif.isRead ? 'bg-[#E5B869]/[0.03]' : ''
                  }`}
                >
                  {/* Category Icon Badge */}
                  <div className="w-8 h-8 rounded-xl bg-white/5 flex items-center justify-center flex-shrink-0 mt-0.5 border border-white/10 group-hover:border-[#E5B869]/40 transition-colors">
                    {getIcon(notif.type)}
                  </div>

                  {/* Content */}
                  <div className="flex-1 min-w-0 pr-4">
                    <div className="flex items-center justify-between mb-0.5">
                      <p
                        className={`text-xs font-semibold truncate ${
                          !notif.isRead ? 'text-white' : 'text-gray-300'
                        }`}
                      >
                        {notif.title}
                      </p>
                      <span className="text-[10px] text-gray-500 whitespace-nowrap ml-2">
                        {notif.time}
                      </span>
                    </div>
                    <p className="text-[11px] text-gray-400 leading-snug line-clamp-2">
                      {notif.message}
                    </p>
                  </div>

                  {/* Unread dot & Delete on hover */}
                  <div className="flex flex-col items-center justify-between self-stretch">
                    {!notif.isRead && (
                      <span className="w-1.5 h-1.5 rounded-full bg-[#E5B869] shadow-[0_0_6px_#E5B869]" />
                    )}
                    <button
                      type="button"
                      onClick={(e) => handleDeleteItem(e, notif.id)}
                      className="opacity-0 group-hover:opacity-100 p-1 text-gray-500 hover:text-red-400 transition-opacity mt-auto"
                      aria-label="Delete notification"
                    >
                      <Trash2 className="w-3 h-3" />
                    </button>
                  </div>
                </div>
              ))
            ) : (
              <div className="text-center py-8 px-4 space-y-2">
                <Bell className="w-8 h-8 text-gray-600 mx-auto" />
                <p className="text-xs text-gray-400">No notifications at the moment.</p>
              </div>
            )}
          </div>

          {/* Footer */}
          <div className="p-2 border-t border-white/10 bg-white/[0.02] text-center">
            <Link
              href="/notifications"
              onClick={() => setIsOpen(false)}
              className="inline-flex items-center justify-center space-x-1.5 w-full py-1.5 text-xs font-semibold text-gray-300 hover:text-[#E5B869] transition-colors"
            >
              <span>View all notifications</span>
              <ExternalLink className="w-3 h-3" />
            </Link>
          </div>
        </div>
      )}
    </div>
  );
}

