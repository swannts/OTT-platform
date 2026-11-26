/**
 * YouTube Connector Utility
 * Parses YouTube URLs, extracts video IDs, generates privacy-enhanced embed URLs,
 * and retrieves thumbnail assets.
 */

export interface YouTubePlayerOptions {
  autoplay?: boolean;
  startTime?: number;
  rel?: boolean;
  controls?: boolean;
  mute?: boolean;
  enableJsApi?: boolean;
  modestBranding?: boolean;
}

/**
 * Extracts the 11-character YouTube video ID from various YouTube URL formats or raw ID.
 */
export function extractYouTubeId(urlOrId: string | undefined | null): string | null {
  if (!urlOrId || typeof urlOrId !== 'string') return null;

  const trimmed = urlOrId.trim();

  // If already an 11-character YouTube video ID
  if (/^[a-zA-Z0-9_-]{11}$/.test(trimmed)) {
    return trimmed;
  }

  // Regex covering standard watch, shortened, embed, shorts, and live URLs
  const patterns = [
    /(?:youtube\.com\/watch\?v=|youtube\.com\/watch\?.+&v=)([^&#?]{11})/,
    /(?:youtu\.be\/)([^&#?]{11})/,
    /(?:youtube\.com\/embed\/)([^&#?]{11})/,
    /(?:youtube-nocookie\.com\/embed\/)([^&#?]{11})/,
    /(?:youtube\.com\/shorts\/)([^&#?]{11})/,
    /(?:youtube\.com\/live\/)([^&#?]{11})/,
    /(?:youtube\.com\/v\/)([^&#?]{11})/,
  ];

  for (const pattern of patterns) {
    const match = trimmed.match(pattern);
    if (match && match[1]) {
      return match[1];
    }
  }

  return null;
}

/**
 * Determines whether a given URL or URL type represents a YouTube video.
 */
export function isYouTubeVideo(url?: string | null, urlType?: string | null): boolean {
  if (!url && !urlType) return false;

  // Direct check for YouTube domain / patterns
  if (url) {
    const trimmed = url.trim().toLowerCase();
    if (
      trimmed.includes('youtube.com') ||
      trimmed.includes('youtu.be') ||
      trimmed.includes('youtube-nocookie.com')
    ) {
      return true;
    }
    // If URL is explicitly HLS / mp4, it is definitely not YouTube
    if (trimmed.includes('.m3u8') || trimmed.includes('.mp4')) {
      return false;
    }
  }

  const normalizedType = (urlType || '').toLowerCase().trim();
  if (normalizedType === 'youtube') return true;

  return false;
}

/**
 * Generates a privacy-friendly YouTube embed URL with optimal streaming parameters.
 */
export function getYouTubeEmbedUrl(
  urlOrId: string,
  options: YouTubePlayerOptions = {}
): string | null {
  const videoId = extractYouTubeId(urlOrId);
  if (!videoId) return null;

  const {
    autoplay = true,
    startTime = 0,
    rel = false,
    controls = true,
    mute = false,
    enableJsApi = true,
    modestBranding = true,
  } = options;

  const params = new URLSearchParams();
  if (autoplay) params.set('autoplay', '1');
  if (startTime > 0) params.set('start', Math.floor(startTime).toString());
  params.set('rel', rel ? '1' : '0');
  params.set('controls', controls ? '1' : '0');
  params.set('modestbranding', modestBranding ? '1' : '0');
  if (enableJsApi) params.set('enablejsapi', '1');
  if (mute) params.set('mute', '1');
  params.set('iv_load_policy', '3'); // Hide annotations
  params.set('playsinline', '1');

  if (typeof window !== 'undefined') {
    params.set('origin', window.location.origin);
  }

  return `https://www.youtube-nocookie.com/embed/${videoId}?${params.toString()}`;
}

/**
 * Returns the highest resolution available thumbnail for a YouTube video.
 */
export function getYouTubeThumbnail(videoId: string, quality: 'default' | 'hq' | 'maxres' = 'maxres'): string {
  const id = extractYouTubeId(videoId) || videoId;
  if (quality === 'maxres') {
    return `https://img.youtube.com/vi/${id}/maxresdefault.jpg`;
  }
  if (quality === 'hq') {
    return `https://img.youtube.com/vi/${id}/hqdefault.jpg`;
  }
  return `https://img.youtube.com/vi/${id}/default.jpg`;
}
