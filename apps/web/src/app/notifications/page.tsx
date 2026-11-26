'use client';

import React, { useState, useEffect, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';
import { api } from '@/lib/api';
import { Spinner } from '@/components/ui';
import { formatDhakaDateTime } from '@/lib/datetime';
import { getNotificationItems, type AppNotification, type NotificationsResponse } from '@/features/notifications/notifications';
import {
  Bell,
  Trash2,
  Film,
  Tv,
  ExternalLink,
} from 'lucide-react';

export default function NotificationsPage() {
  const { user, isLoading } = useAuth();
  const router = useRouter();

  const [notifications, setNotifications] = useState<AppNotification[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const loadNotifications = useCallback(async (showLoading = true) => {
    if (showLoading) setLoading(true);
    setError('');
    try {
      const res = await api.getNotifications(false) as NotificationsResponse;
      setNotifications(getNotificationItems(res));
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Notifications could not be loaded.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (isLoading) return;
    if (!isLoading && !user) {
      router.push('/login?redirect=/notifications');
      return;
    }
    const initialLoad = window.setTimeout(() => void loadNotifications(), 0);
    return () => window.clearTimeout(initialLoad);
  }, [user, isLoading, router, loadNotifications]);

  const handleMarkAllRead = async () => {
    try {
      await api.markAllNotificationsRead();
      setNotifications((items) => items.map((item) => ({ ...item, isRead: true })));
      setError('');
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Notifications could not be updated.');
    }
  };

  const handleDelete = async (id: string) => {
    try {
      await api.deleteNotification(id);
      setNotifications((prev) => prev.filter((n) => n.id !== id));
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Notification could not be dismissed.');
    }
  };

  const handleOpenNotification = async (item: AppNotification) => {
    if (!item.isRead) {
      try {
        await api.markNotificationRead(item.id);
        setNotifications((items) => items.map((notification) => notification.id === item.id ? { ...notification, isRead: true } : notification));
      } catch (cause) {
        setError(cause instanceof Error ? cause.message : 'Notification could not be marked as read.');
        return;
      }
    }
    router.push(item.href);
  };

  if (isLoading) {
    return (
      <div className="min-h-screen pt-32 text-center text-white space-y-4">
        <Spinner size="lg" label="Loading notifications..." />
      </div>
    );
  }

  if (!user) return null;

  return (
    <div className="min-h-screen pt-24 pb-16 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-6 mb-8 border-b border-white/10 gap-4">
        <div>
          <div className="flex items-center space-x-3">
            <Bell className="w-7 h-7 text-red-500" />
            <h1 className="text-3xl font-extrabold text-white tracking-tight">Notifications</h1>
          </div>
          <p className="text-gray-400 text-sm mt-1">Updates on new releases, upcoming episodes, and account activity</p>
        </div>

        {notifications.some((item) => !item.isRead) && (
          <button
            onClick={handleMarkAllRead}
            className="self-start sm:self-auto text-xs font-semibold text-gray-300 hover:text-white bg-white/10 hover:bg-white/20 px-4 py-2 rounded-xl transition-colors border border-white/10"
          >
            Mark All as Read
          </button>
        )}
      </div>

      {error && <div role="alert" className="mb-5 flex items-center justify-between rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200"><span>{error}</span><button type="button" onClick={() => void loadNotifications()} className="font-semibold underline">Retry</button></div>}

      {loading ? (
        <div className="text-center py-16 text-gray-500 text-sm">Loading notifications...</div>
      ) : notifications.length === 0 ? (
          <div className="text-center py-20 glass-card rounded-2xl border border-white/10 p-8">
            <Bell className="w-12 h-12 text-gray-600 mx-auto mb-3" />
            <h3 className="text-lg font-bold text-white mb-1">{error ? 'Could not load notifications' : 'No notifications yet'}</h3>
            <p className="text-xs text-gray-400 max-w-sm mx-auto">
            {error ? 'Retry the request to check your latest updates.' : 'You’re all caught up! New releases, episodes, and account updates will appear here.'}
            </p>
        </div>
      ) : (
        <div className="space-y-3">
          {notifications.map((item) => {
            return (
              <div
                key={item.id}
                className={`glass-card rounded-2xl p-4 sm:p-5 border border-white/10 flex items-start justify-between gap-4 hover:border-white/20 transition-colors ${!item.isRead ? 'bg-white/[0.03]' : ''}`}
              >
                <div className="flex items-start space-x-3.5">
                  <div className="w-10 h-10 rounded-xl bg-red-600/20 border border-red-500/30 flex items-center justify-center text-red-500 flex-shrink-0 mt-0.5">
                    {item.type.includes('tv') || item.type.includes('episode') || item.type.includes('season') ? (
                      <Tv className="w-5 h-5" />
                    ) : (
                      <Film className="w-5 h-5" />
                    )}
                  </div>

                  <div>
                    <h3 className="text-sm font-bold text-white">{item.title}</h3>
                    {item.message && (
                      <p className="text-xs text-gray-300 mt-1 line-clamp-2 leading-relaxed">
                        {item.message}
                      </p>
                    )}
                    <span className="text-[11px] text-gray-500 mt-2 block">
                      {item.createdAt ? formatDhakaDateTime(item.createdAt) : 'Recently'}
                    </span>
                  </div>
                </div>

                <div className="flex items-center space-x-2 flex-shrink-0">
                  {item.href !== '/notifications' && <button type="button" onClick={() => void handleOpenNotification(item)} className="text-xs font-semibold text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/20 px-3 py-1.5 rounded-lg border border-red-500/20 transition-colors flex items-center space-x-1"><span>Open</span><ExternalLink className="w-3 h-3" /></button>}
                  <button
                    onClick={() => handleDelete(item.id)}
                    className="p-1.5 text-gray-500 hover:text-gray-300 transition-colors"
                    title="Dismiss notification"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
