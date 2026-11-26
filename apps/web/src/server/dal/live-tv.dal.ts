import { api } from '@/lib/api';
import { LiveChannel, LiveCategory } from '@/types';

export async function getLiveTvChannelsDal(): Promise<LiveChannel[]> {
  try {
    const res = await api.getLiveTvDashboard();
    const rawChannels: LiveChannel[] = [];

    if (res?.slider && Array.isArray(res.slider)) {
      rawChannels.push(...res.slider);
    }

    if (res?.category_data && Array.isArray(res.category_data)) {
      res.category_data.forEach((cat: LiveCategory) => {
        if (cat.channel_data && Array.isArray(cat.channel_data)) {
          rawChannels.push(...cat.channel_data);
        }
      });
    }

    const seen = new Set();
    const unique: LiveChannel[] = [];
    for (const c of rawChannels) {
      const name = c?.details?.name || c?.name;
      if (!c || !name || seen.has(c.id)) continue;
      seen.add(c.id);
      unique.push({
        id: c.id,
        name,
        name_bn: c.details?.name_bn || c.name_bn,
        name_en: c.details?.name_en || c.name_en,
        description: c.details?.description || c.description || '',
        description_bn: c.details?.description_bn || c.description_bn,
        description_en: c.details?.description_en || c.description_en,
        category: c.details?.category || c.category,
        poster_image: c.poster_url || c.details?.poster_url || '',
      });
    }
    return unique;
  } catch (error) {
    console.error('[DAL] getLiveTvChannelsDal error:', error);
    return [];
  }
}
