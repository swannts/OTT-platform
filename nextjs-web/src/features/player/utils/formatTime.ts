/**
 * Formats a given time in seconds to a standard video timestamp string (MM:SS or HH:MM:SS).
 */
export function formatTime(seconds: number | undefined | null): string {
  if (seconds === undefined || seconds === null || isNaN(seconds) || !isFinite(seconds) || seconds < 0) {
    return '00:00';
  }

  const totalSec = Math.floor(seconds);
  const hrs = Math.floor(totalSec / 3600);
  const mins = Math.floor((totalSec % 3600) / 60);
  const secs = totalSec % 60;

  const paddedMins = String(mins).padStart(2, '0');
  const paddedSecs = String(secs).padStart(2, '0');

  if (hrs > 0) {
    return `${hrs}:${paddedMins}:${paddedSecs}`;
  }

  return `${paddedMins}:${paddedSecs}`;
}

/**
 * Calculates remaining time formatted as -MM:SS or -HH:MM:SS.
 */
export function formatRemainingTime(currentTime: number, duration: number): string {
  if (!duration || duration <= 0 || isNaN(duration) || !isFinite(duration)) {
    return '00:00';
  }
  const remaining = Math.max(0, duration - currentTime);
  return `-${formatTime(remaining)}`;
}

