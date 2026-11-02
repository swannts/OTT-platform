import { api, normalizeMediaItem } from '@/lib/api';
import { MediaItem, Episode } from '@/types';

export interface TvShowsCursorResult {
  items: MediaItem[];
  nextCursor: string | null;
  hasMore: boolean;
}

export async function getTvShowsCursorDal(params: {
  cursor?: string | null;
  per_page?: number;
} = {}): Promise<TvShowsCursorResult> {
  try {
    const res = await api.getTvShows({
      cursor: params.cursor,
      per_page: params.per_page || 12,
    });
    const list = Array.isArray(res) ? res : res?.data || [];
    const items = Array.isArray(list) ? list.map(normalizeMediaItem) : [];
    const nextCursor = !Array.isArray(res) && typeof res?.next_cursor === 'string' ? res.next_cursor : null;
    const hasMore = !Array.isArray(res) ? Boolean(res?.has_more ?? res?.hasMore ?? false) : false;
    return { items, nextCursor, hasMore };
  } catch (error) {
    console.error('[DAL] getTvShowsCursorDal error:', error);
    return { items: [], nextCursor: null, hasMore: false };
  }
}

export async function getTvShowsDal(params = { page: 1 }): Promise<MediaItem[]> {
  try {
    const res = await api.getTvShows(params);
    const list = Array.isArray(res) ? res : res?.data || [];
    return Array.isArray(list) ? list.map(normalizeMediaItem) : [];
  } catch (error) {
    console.error('[DAL] getTvShowsDal error:', error);
    return [];
  }
}

export async function getTvShowDetailsDal(id: string | number): Promise<MediaItem | null> {
  try {
    const res = await api.getContentDetails(id, 'tvshow');
    return res ? normalizeMediaItem(res) : null;
  } catch (error) {
    console.error(`[DAL] getTvShowDetailsDal(${id}) error:`, error);
    return null;
  }
}

export async function getTvShowEpisodesDal(id: string | number): Promise<Episode[]> {
  try {
    const res = await api.getEpisodes(id);
    const list = Array.isArray(res) ? res : res?.data || [];
    return (list as Episode[]) || [];
  } catch (error) {
    console.error(`[DAL] getTvShowEpisodesDal(${id}) error:`, error);
    return [];
  }
}
