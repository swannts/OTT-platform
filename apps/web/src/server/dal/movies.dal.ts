import { api, normalizeMediaItem } from '@/lib/api';
import { MediaItem } from '@/types';

export async function getMoviesDal(params = { page: 1 }): Promise<MediaItem[]> {
  try {
    const res = await api.getMovies(params);
    const list = Array.isArray(res) ? res : res?.data || [];
    return Array.isArray(list) ? list.map(normalizeMediaItem) : [];
  } catch (error) {
    console.error('[DAL] getMoviesDal error:', error);
    return [];
  }
}

export async function getMovieDetailsDal(id: string | number): Promise<MediaItem | null> {
  try {
    const res = await api.getContentDetails(id, 'movie');
    return res ? normalizeMediaItem(res) : null;
  } catch (error) {
    console.error(`[DAL] getMovieDetailsDal(${id}) error:`, error);
    return null;
  }
}

