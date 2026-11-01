import { api, normalizeMediaItem } from '@/lib/api';
import { MediaItem, Episode } from '@/types';

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

