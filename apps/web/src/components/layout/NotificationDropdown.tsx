'use client';

import React, { useCallback, useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/providers';
import { api } from '@/lib/api';
import { getNotificationItems, formatNotificationTime, type AppNotification, type NotificationsResponse } from '@/features/notifications/notifications';
import {
  Bell,
  CheckCheck,
  Trash2,
  Film,
  Tv,
  Radio,
  Crown,
  Sparkles,
  ExternalLink,
  LoaderCircle,
} from 'lucide-react';

export default function NotificationDropdown() {
  const router = useRouter();
  const { user, isLoading: authLoading } = useAuth();
  const [isOpen, setIsOpen] = useState(false);
  const [notifications, setNotifications] = useState<AppNotification[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [busyId, setBusyId] = useState<string | null>(null);
  const [markingAll, setMarkingAll] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  const loadNotifications = useCallback(async () => {
    if (!user) {
      setNotifications([]);
      setUnreadCount(0);
      setError('');
      return;
    }
    setLoading(true);
    setError('');
    try {
      const response = await api.getNotifications(false) as NotificationsResponse;
      setNotifications(getNotificationItems(response));
      setUnreadCount(Number(response.unread_notification_count ?? 0));
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Notifications could not be loaded.');
    } finally {
      setLoading(false);
    }
  }, [user]);

  useEffect(() => {
    if (authLoading) return;
    const initialLoad = window.setTimeout(() => void loadNotifications(), 0);
    if (!user) {
      return () => window.clearTimeout(initialLoad);
    }
    const refresh = () => void loadNotifications();
    const interval = window.setInterval(refresh, 60_000);
    window.addEventListener('focus', refresh);
    return () => {
      window.clearTimeout(initialLoad);
      window.clearInterval(interval);
      window.removeEventListener('focus', refresh);
    };
  }, [authLoading, user, loadNotifications]);

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) setIsOpen(false);
    }
    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') setIsOpen(false);
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

  const handleMarkAllRead = async () => {
    if (markingAll || unreadCount === 0) return;
    setMarkingAll(true);
    setError('');
    try {
      await api.markAllNotificationsRead();
      setNotifications((items) => items.map((item) => ({ ...item, isRead: true })));
      setUnreadCount(0);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Notifications could not be updated.');
    } finally {
      setMarkingAll(false);
    }
  };

  const handleItemClick = async (notification: AppNotification) => {
    setIsOpen(false);
    if (!notification.isRead) {
      setBusyId(notification.id);
      try {
        await api.markNotificationRead(notification.id);
        setNotifications((items) => items.map((item) => item.id === notification.id ? { ...item, isRead: true } : item));
        setUnreadCount((count) => Math.max(0, count - 1));
      } catch (cause) {
        setError(cause instanceof Error ? cause.message : 'Notification could not be marked as read.');
        setIsOpen(true);
        return;
      } finally {
        setBusyId(null);
      }
    }
    router.push(notification.href);
  };

  const handleDeleteItem = async (event: React.MouseEvent, notification: AppNotification) => {
    event.stopPropagation();
    setBusyId(notification.id);
    setError('');
    try {
      await api.deleteNotification(notification.id);
      setNotifications((items) => items.filter((item) => item.id !== notification.id));
      if (!notification.isRead) setUnreadCount((count) => Math.max(0, count - 1));
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Notification could not be dismissed.');
    } finally {
      setBusyId(null);
    }
  };

  const getIcon = (type: string) => {
    if (type.includes('live')) return <Radio className="w-3.5 h-3.5 text-red-400" />;
    if (type.includes('episode') || type.includes('season') || type.includes('tv_show')) return <Tv className="w-3.5 h-3.5 text-[#E5B869]" />;
    if (type.includes('movie') || type.includes('video')) return <Film className="w-3.5 h-3.5 text-blue-400" />;
    if (type.includes('subscription') || type.includes('plan')) return <Crown className="w-3.5 h-3.5 text-amber-400" />;
    return <Sparkles className="w-3.5 h-3.5 text-emerald-400" />;
  };

  return (
    <div className="relative" ref={dropdownRef}>
      <button
        type="button"
        onClick={() => {
          setIsOpen((open) => !open);
          if (!isOpen) void loadNotifications();
        }}
        className={`w-9 h-9 rounded-full flex items-center justify-center transition-all relative cursor-pointer active:scale-95 flex-shrink-0 backdrop-blur-md ${
          isOpen
            ? 'bg-[#E5B869]/20 border border-[#E5B869]/60 text-[#E5B869]'
            : 'bg-white/[0.08] hover:bg-white/[0.12] border border-white/10 hover:border-white/20 text-gray-200 hover:text-white'
        }`}
        aria-label={`Notifications${unreadCount ? `, ${unreadCount} unread` : ''}`}
        aria-expanded={isOpen}
      >
        <Bell className="w-4 h-4 text-gray-200" strokeWidth={1.75} />
        {unreadCount > 0 && <span className="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-[#E5B869] ring-2 ring-[#070a0e] shadow-[0_0_8px_#E5B869]" />}
      </button>

      {isOpen && (
        <div className="absolute right-0 mt-2.5 w-80 sm:w-96 bg-[#090e15]/98 backdrop-blur-2xl rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.9)] border border-white/10 z-50 overflow-hidden animate-hero-title">
          <div className="flex items-center justify-between px-4 py-3 border-b border-white/10 bg-white/[0.02]">
            <div className="flex items-center gap-2">
              <span className="text-sm font-bold text-white">Notifications</span>
              {unreadCount > 0 && <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#E5B869]/20 text-[#E5B869] border border-[#E5B869]/30">{unreadCount} new</span>}
            </div>
            {user && unreadCount > 0 && (
              <button type="button" disabled={markingAll} onClick={handleMarkAllRead} className="flex items-center gap-1 text-[11px] font-medium text-gray-400 hover:text-[#E5B869] disabled:opacity-50">
                {markingAll ? <LoaderCircle className="w-3.5 h-3.5 animate-spin" /> : <CheckCheck className="w-3.5 h-3.5" />}
                <span>Mark all read</span>
              </button>
            )}
          </div>

          {error && <div role="alert" className="px-4 py-2 text-xs text-red-300 bg-red-500/10">{error}<button type="button" onClick={() => void loadNotifications()} className="ml-2 underline">Retry</button></div>}
          <div className="max-h-80 overflow-y-auto divide-y divide-white/5 scrollbar-thin scrollbar-thumb-white/10">
            {authLoading || loading ? (
              <div className="flex items-center justify-center gap-2 py-8 text-xs text-gray-400"><LoaderCircle className="h-4 w-4 animate-spin" />Loading notifications…</div>
            ) : !user ? (
              <div className="text-center py-8 px-4 text-xs text-gray-400">Sign in to see your notifications.</div>
            ) : notifications.length > 0 ? (
              notifications.slice(0, 5).map((notification) => (
                <div key={notification.id} className={`group relative flex items-start gap-3 p-3.5 transition-colors hover:bg-white/[0.04] ${!notification.isRead ? 'bg-[#E5B869]/[0.03]' : ''}`}>
                  <button type="button" onClick={() => void handleItemClick(notification)} className="flex min-w-0 flex-1 items-start gap-3 text-left" aria-label={`Open ${notification.title}`}>
                    <div className="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/5">{getIcon(notification.type)}</div>
                    <div className="min-w-0 flex-1">
                      <div className="mb-0.5 flex items-center justify-between gap-2">
                        <p className={`truncate text-xs font-semibold ${notification.isRead ? 'text-gray-300' : 'text-white'}`}>{notification.title}</p>
                        <span className="whitespace-nowrap text-[10px] text-gray-500">{formatNotificationTime(notification.createdAt)}</span>
                      </div>
                      {notification.message && <p className="line-clamp-2 text-[11px] leading-snug text-gray-400">{notification.message}</p>}
                    </div>
                  </button>
                  <div className="flex flex-col items-center justify-between self-stretch">
                    {!notification.isRead && <span className="mt-1 h-1.5 w-1.5 rounded-full bg-[#E5B869] shadow-[0_0_6px_#E5B869]" />}
                    <button type="button" disabled={busyId === notification.id} onClick={(event) => void handleDeleteItem(event, notification)} className="mt-auto p-1 text-gray-500 opacity-0 transition-opacity hover:text-red-400 group-hover:opacity-100 disabled:opacity-50" aria-label="Dismiss notification">
                      {busyId === notification.id ? <LoaderCircle className="h-3 w-3 animate-spin" /> : <Trash2 className="h-3 w-3" />}
                    </button>
                  </div>
                </div>
              ))
            ) : (
              <div className="space-y-2 px-4 py-8 text-center"><Bell className="mx-auto h-8 w-8 text-gray-600" /><p className="text-xs text-gray-400">No notifications yet.</p></div>
            )}
          </div>

          <div className="border-t border-white/10 bg-white/[0.02] p-2 text-center">
            <Link href={user ? '/notifications' : '/login?redirect=/notifications'} onClick={() => setIsOpen(false)} className="inline-flex w-full items-center justify-center gap-1.5 py-1.5 text-xs font-semibold text-gray-300 transition-colors hover:text-[#E5B869]">
              <span>{user ? 'View all notifications' : 'Sign in'}</span><ExternalLink className="h-3 w-3" />
            </Link>
          </div>
        </div>
      )}
    </div>
  );
}
