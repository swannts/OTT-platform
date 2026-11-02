'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';
import { api } from '@/lib/api';
import {
  Bell,
  CheckCircle2,
  Trash2,
  Film,
  Tv,
  Calendar,
  AlertCircle,
  ExternalLink,
} from 'lucide-react';

interface NotificationItem {
  id: string;
  created_at?: string;
  data?: {
    data?: {
      id?: number;
      name?: string;
      title?: string;
      notification_type?: string;
      tv_show_id?: number;
      description?: string;
    };
  };
}

export default function NotificationsPage() {
  const { user, isLoading } = useAuth();
  const router = useRouter();

  const [notifications, setNotifications] = useState<NotificationItem[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!isLoading && !user) {
      router.push('/login?redirect=/notifications');
      return;
    }
    if (user) {
      loadNotifications();
    }
  }, [user, isLoading, router]);

  const loadNotifications = async () => {
    setLoading(true);
    try {
      const res = await api.getNotifications(false);
      const items = (Array.isArray(res) ? res : res?.notifications || res?.data || []) as unknown as NotificationItem[];
      setNotifications(items);
    } catch {
      setNotifications([]);
    } finally {
      setLoading(false);
    }
  };

  const handleMarkAllRead = async () => {
    try {
      await api.getNotifications(true);
      await loadNotifications();
    } catch {
      // Ignore
    }
  };

  const handleDelete = async (id: string) => {
    try {
      await api.deleteNotification(id);
      setNotifications((prev) => prev.filter((n) => n.id !== id));
    } catch {
      // Fallback
      setNotifications((prev) => prev.filter((n) => n.id !== id));
    }
  };

  if (isLoading) {
    return (
      <div className="min-h-screen pt-32 text-center text-white space-y-4">
        <div className="w-10 h-10 border-4 border-[#E5B869] border-t-transparent rounded-full animate-spin mx-auto" />
        <p className="text-sm text-gray-400">Loading notifications...</p>
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
          <p className="text-gray-400 text-sm mt-1">Updates on new releases, upcoming episodes, and subscription status</p>
        </div>

        {notifications.length > 0 && (
          <button
            onClick={handleMarkAllRead}
            className="self-start sm:self-auto text-xs font-semibold text-gray-300 hover:text-white bg-white/10 hover:bg-white/20 px-4 py-2 rounded-xl transition-colors border border-white/10"
          >
            Mark All as Read
          </button>
        )}
      </div>

      {loading ? (
        <div className="text-center py-16 text-gray-500 text-sm">Loading notifications...</div>
      ) : notifications.length === 0 ? (
        <div className="text-center py-20 glass-card rounded-2xl border border-white/10 p-8">
          <Bell className="w-12 h-12 text-gray-600 mx-auto mb-3" />
          <h3 className="text-lg font-bold text-white mb-1">No New Notifications</h3>
          <p className="text-xs text-gray-400 max-w-sm mx-auto">
            You're all caught up! When new movies, series episodes, or account updates happen, you'll see them here.
          </p>
        </div>
      ) : (
        <div className="space-y-3">
          {notifications.map((item) => {
            const inner = item.data?.data || {};
            const type = inner.notification_type || 'general';
            const targetUrl =
              type === 'season_add' || type === 'episode_add'
                ? `/tv-shows/${inner.tv_show_id || inner.id}`
                : inner.id
                ? `/movies/${inner.id}`
                : null;

            return (
              <div
                key={item.id}
                className="glass-card rounded-2xl p-4 sm:p-5 border border-white/10 flex items-start justify-between gap-4 hover:border-white/20 transition-colors"
              >
                <div className="flex items-start space-x-3.5">
                  <div className="w-10 h-10 rounded-xl bg-red-600/20 border border-red-500/30 flex items-center justify-center text-red-500 flex-shrink-0 mt-0.5">
                    {type.includes('tv') || type.includes('episode') ? (
                      <Tv className="w-5 h-5" />
                    ) : (
                      <Film className="w-5 h-5" />
                    )}
                  </div>

                  <div>
                    <h3 className="text-sm font-bold text-white">
                      {inner.title || inner.name || 'New Release Available'}
                    </h3>
                    {inner.description && (
                      <p className="text-xs text-gray-300 mt-1 line-clamp-2 leading-relaxed">
                        {inner.description}
                      </p>
                    )}
                    <span className="text-[11px] text-gray-500 mt-2 block">
                      {item.created_at || 'Recently'}
                    </span>
                  </div>
                </div>

                <div className="flex items-center space-x-2 flex-shrink-0">
                  {targetUrl && (
                    <Link
                      href={targetUrl}
                      className="text-xs font-semibold text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/20 px-3 py-1.5 rounded-lg border border-red-500/20 transition-colors flex items-center space-x-1"
                    >
                      <span>Watch</span>
                      <ExternalLink className="w-3 h-3" />
                    </Link>
                  )}
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

