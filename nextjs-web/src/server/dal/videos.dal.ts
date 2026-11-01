import { api, normalizeMediaItem } from '@/lib/api';
import { MediaItem } from '@/types';

export async function getVideosDal(page: number = 1): Promise<MediaItem[]> {
  try {
    const res = await api.getVideos(page);
    const list = Array.isArray(res) ? res : res?.data || [];
    return Array.isArray(list) ? list.map(normalizeMediaItem) : [];
  } catch (error) {
    console.error('[DAL] getVideosDal error:', error);
    return [];
  }
}
