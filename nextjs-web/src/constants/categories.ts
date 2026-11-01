export interface ContentCategory {
  id: string;
  label: string;
  keywords?: string[];
}

export const TV_SERIES_CATEGORIES: ContentCategory[] = [
  { id: 'all', label: 'All Series' },
  {
    id: 'prophetic',
    label: 'Prophetic Chronicles',
    keywords: ['prophet', 'muhammad', 'messengers', 'adam', 'ibrahim'],
  },
  {
    id: 'history',
    label: 'Islamic History',
    keywords: ['andalus', 'ottoman', 'caliph', 'companions', 'civilization', 'women'],
  },
  {
    id: 'quran',
    label: 'Quran & Hadith',
    keywords: ['quran', 'hadith', 'tafseer', 'yusuf', 'nawawi'],
  },
  {
    id: 'kids',
    label: 'Kids & Family',
    keywords: ['kids', 'noor', 'bilal', 'animals', 'children'],
  },
  {
    id: 'soul',
    label: 'Spiritual Wisdom',
    keywords: ['soul', 'akhirah', 'repentance', 'wisdom'],
  },
];

export const MOVIE_CATEGORIES: ContentCategory[] = [
  { id: 'all', label: 'All Movies' },
  {
    id: 'sanctuary',
    label: 'Sacred Sanctuaries',
    keywords: ['kaaba', 'makkah', 'madinah', 'haram', 'sanctuary'],
  },
  {
    id: 'history',
    label: 'Historic Epics',
    keywords: ['andalus', 'ottoman', 'jerusalem', 'history', 'golden'],
  },
  {
    id: 'doc',
    label: 'Documentaries',
    keywords: ['documentary', 'chronicle', 'journey', 'life', 'science'],
  },
  {
    id: 'ramadan',
    label: 'Ramadan & Worship',
    keywords: ['ramadan', 'quran', 'fasting', 'reflections'],
  },
];
