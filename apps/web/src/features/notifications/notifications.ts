import type { NotificationItem } from '@/types';

export interface AppNotification {
  id: string;
  title: string;
  message: string;
  createdAt: string | null;
  isRead: boolean;
  type: string;
  contentId: number | string | null;
  tvShowId: number | string | null;
  href: string;
}

export interface NotificationsResponse {
  notification_data?: NotificationItem[];
  notifications?: NotificationItem[];
  data?: NotificationItem[];
  unread_notification_count?: number;
}

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' && !Array.isArray(value)
    ? (value as Record<string, unknown>)
    : {};
}

function safeInternalHref(value: unknown): string | null {
  if (typeof value !== 'string' || !value.startsWith('/') || value.startsWith('//') || value.includes('\\')) return null;
  return value;
}

export function normalizeNotification(value: NotificationItem, index: number): AppNotification {
  const item = asRecord(value);
  const payload = asRecord(item.data);
  const nested = asRecord(payload.data);
  const type = String(item.notification_type || payload.notification_type || nested.notification_type || 'general');
  const contentId = (item.content_id ?? payload.id ?? nested.id ?? null) as number | string | null;
  const tvShowId = (item.tv_show_id ?? payload.tv_show_id ?? nested.tv_show_id ?? null) as number | string | null;
  const customHref = safeInternalHref(item.href ?? payload.href ?? nested.href ?? nested.url);

  let href = '/notifications';
  if (customHref) href = customHref;
  else if (type === 'episode_add' || type === 'season_add' || type.includes('tv_show')) {
    href = tvShowId ? `/tv-shows/${tvShowId}` : '/tv-shows';
  } else if (type === 'movie_add' && contentId) {
    href = `/movies/${contentId}`;
  }

  const date = item.date_time ?? item.created_at;
  return {
    id: String(item.notification_id ?? item.id ?? `notification-${index}`),
    title: String(item.title ?? payload.subject ?? payload.title ?? nested.title ?? nested.name ?? 'NOOR update'),
    message: String(item.message ?? item.description ?? payload.description ?? payload.message ?? nested.message ?? nested.description ?? ''),
    createdAt: typeof date === 'string' ? date : null,
    isRead: Boolean(item.is_already_read ?? item.read_at ?? item.read),
    type,
    contentId,
    tvShowId,
    href,
  };
}

export function getNotificationItems(response: NotificationsResponse): AppNotification[] {
  const items = response.notification_data ?? response.notifications ?? response.data ?? [];
  return Array.isArray(items) ? items.map(normalizeNotification) : [];
}

export function formatNotificationTime(value: string | null): string {
  if (!value) return '';
  const timestamp = Date.parse(value);
  if (Number.isNaN(timestamp)) return '';
  const seconds = Math.round((timestamp - Date.now()) / 1000);
  const units: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 31_536_000], ['month', 2_592_000], ['week', 604_800],
    ['day', 86_400], ['hour', 3_600], ['minute', 60], ['second', 1],
  ];
  const formatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
  for (const [unit, size] of units) {
    if (Math.abs(seconds) >= size || unit === 'second') return formatter.format(Math.round(seconds / size), unit);
  }
  return '';
}
