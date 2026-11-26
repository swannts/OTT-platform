import { api } from '@/lib/api';

const STORAGE_PREFIX = 'noor_watch_progress_';

export interface StoredProgress {
  currentTime: number;
  duration: number;
  completed: boolean;
  updatedAt: number;
}

/**
 * Returns the stored playback progress in seconds.
 * Prefers valid backend watched_time if provided; falls back to local storage.
 * If playback was completed (>= 95% of duration), returns 0 to allow starting fresh.
 */
export function getSavedProgress(
  contentId: string | number,
  contentType: string,
  backendTime?: number | string | null
): number {
  const numericBackend =
    typeof backendTime === 'number'
      ? backendTime
      : typeof backendTime === 'string' && !isNaN(Number(backendTime))
      ? Number(backendTime)
      : null;

  if (numericBackend !== null && numericBackend > 5) {
    return Math.floor(numericBackend);
  }

  if (typeof window === 'undefined' || !contentId) {
    return 0;
  }

  try {
    const key = `${STORAGE_PREFIX}${contentType}_${contentId}`;
    const raw = localStorage.getItem(key);
    if (!raw) return 0;

    const data: StoredProgress = JSON.parse(raw);
    if (typeof data?.currentTime !== 'number' || data.currentTime <= 5) {
      return 0;
    }

    // If marked as completed or watched past 95% of duration, restart from 0
    if (data.completed || (data.duration > 0 && data.currentTime >= data.duration * 0.95)) {
      return 0;
    }

    return Math.floor(data.currentTime);
  } catch {
    return 0;
  }
}

/**
 * Persists playback progress locally in localStorage and synchronizes with the backend API.
 */
export function savePlaybackProgress(
  contentId: string | number,
  contentType: string,
  currentTime: number,
  duration?: number
): void {
  if (typeof window === 'undefined' || !contentId) return;

  const validCurrentTime = Math.max(0, Math.floor(currentTime || 0));
  const validDuration = Math.max(0, Math.floor(duration || 0));

  // Skip writing during initial setup (under 3s)
  if (validCurrentTime < 3) return;

  const isCompleted = validDuration > 0 && validCurrentTime >= validDuration * 0.95;

  const payload: StoredProgress = {
    currentTime: isCompleted ? 0 : validCurrentTime,
    duration: validDuration,
    completed: isCompleted,
    updatedAt: Date.now(),
  };

  try {
    const key = `${STORAGE_PREFIX}${contentType}_${contentId}`;
    localStorage.setItem(key, JSON.stringify(payload));
  } catch {
    // Graceful fallback for private browsing or quota limits
  }

  // Sync to backend API (swallows unauthenticated/network errors safely)
  api
    .saveContinueWatching(
      contentId,
      contentType,
      isCompleted ? 0 : validCurrentTime,
      validDuration || 3600
    )
    .catch(() => {});
}

/**
 * Clears saved playback progress both locally and on the server.
 */
export function clearPlaybackProgress(contentId: string | number, contentType: string): void {
  if (typeof window === 'undefined' || !contentId) return;

  try {
    const key = `${STORAGE_PREFIX}${contentType}_${contentId}`;
    localStorage.removeItem(key);
  } catch {}

  api.saveContinueWatching(contentId, contentType, 0, 0).catch(() => {});
}
