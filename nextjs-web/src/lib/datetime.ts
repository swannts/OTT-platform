/**
 * Centralized Date & Time Formatter for NOOR OTT
 * Configured specifically for Dhaka Time (Asia/Dhaka, UTC+6) with 12-hour AM/PM format.
 */

export const DHAKA_TIMEZONE = 'Asia/Dhaka';

/**
 * Parses any incoming date string/number/Date safely into a Date object.
 * Handles MySQL date strings like "2026-11-02 17:54:01" cleanly across all browsers.
 */
export function parseDate(value: string | number | Date | null | undefined): Date | null {
  if (!value) return null;
  if (value instanceof Date) return isNaN(value.getTime()) ? null : value;

  if (typeof value === 'number') {
    const d = new Date(value);
    return isNaN(d.getTime()) ? null : d;
  }

  // Handle MySQL datetime "YYYY-MM-DD HH:MM:SS" by replacing space with 'T'
  let dateStr = String(value).trim();
  if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/.test(dateStr)) {
    // If no timezone is specified, treat as UTC or local ISO
    dateStr = dateStr.replace(' ', 'T') + 'Z';
  }

  const d = new Date(dateStr);
  return isNaN(d.getTime()) ? null : d;
}

/**
 * Format time only in Dhaka Timezone with 12-hour AM/PM format (e.g. "03:15 PM")
 */
export function formatDhakaTime(
  value: string | number | Date | null | undefined,
  includeSeconds = false
): string {
  const date = parseDate(value);
  if (!date) return '';

  return new Intl.DateTimeFormat('en-US', {
    timeZone: DHAKA_TIMEZONE,
    hour: 'numeric',
    minute: '2-digit',
    second: includeSeconds ? '2-digit' : undefined,
    hour12: true,
  }).format(date);
}

/**
 * Format date only in Dhaka Timezone (e.g. "Nov 3, 2026")
 */
export function formatDhakaDate(
  value: string | number | Date | null | undefined,
  format: 'short' | 'medium' | 'long' = 'medium'
): string {
  const date = parseDate(value);
  if (!date) return '';

  const options: Intl.DateTimeFormatOptions = {
    timeZone: DHAKA_TIMEZONE,
  };

  if (format === 'short') {
    options.month = 'numeric';
    options.day = 'numeric';
    options.year = '2-digit';
  } else if (format === 'medium') {
    options.month = 'short';
    options.day = 'numeric';
    options.year = 'numeric';
  } else {
    options.month = 'long';
    options.day = 'numeric';
    options.year = 'numeric';
  }

  return new Intl.DateTimeFormat('en-US', options).format(date);
}

/**
 * Format date and time in Dhaka Timezone with AM/PM (e.g. "Nov 3, 2026, 03:15 PM")
 */
export function formatDhakaDateTime(
  value: string | number | Date | null | undefined,
  includeSeconds = false
): string {
  const date = parseDate(value);
  if (!date) return '';

  return new Intl.DateTimeFormat('en-US', {
    timeZone: DHAKA_TIMEZONE,
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    second: includeSeconds ? '2-digit' : undefined,
    hour12: true,
  }).format(date);
}

/**
 * Safely format remaining days as an integer with proper pluralization (e.g. "365 days", "1 day", "৩৬৫ দিন").
 * Never displays ugly floating-point decimals like "364.97763850403936".
 */
export function formatDaysRemaining(value: number | string | null | undefined, locale?: string): string {
  if (value === null || value === undefined || value === '') {
    return locale === 'bn' ? '০ দিন' : '0 days';
  }
  const parsed = typeof value === 'string' ? parseFloat(value) : Number(value);
  if (isNaN(parsed) || parsed <= 0) {
    return locale === 'bn' ? '০ দিন' : '0 days';
  }
  const rounded = Math.ceil(parsed);
  if (locale === 'bn') {
    const bnDigits = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
    const bnStr = String(rounded).replace(/[0-9]/g, (d) => bnDigits[Number(d)]);
    return `${bnStr} দিন`;
  }
  return `${rounded} ${rounded === 1 ? 'day' : 'days'}`;
}

/**
 * Returns a human-friendly relative time or full Dhaka date-time with AM/PM.
 */
export function formatDhakaTimeAgo(value: string | number | Date | null | undefined): string {
  const date = parseDate(value);
  if (!date) return '';

  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffSec = Math.floor(diffMs / 1000);
  const diffMin = Math.floor(diffSec / 60);
  const diffHours = Math.floor(diffMin / 60);
  const diffDays = Math.floor(diffHours / 24);

  if (diffSec < 45) return 'Just now';
  if (diffMin < 60) return `${diffMin}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays === 1) return `Yesterday at ${formatDhakaTime(date)}`;
  if (diffDays < 7) return `${diffDays}d ago`;

  return formatDhakaDateTime(date);
}

