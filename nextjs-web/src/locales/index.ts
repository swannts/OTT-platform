import { en, type TranslationKey } from './en';
import { bn } from './bn';

export type Locale = 'en' | 'bn';

export const locales: Record<Locale, Record<TranslationKey, string>> = {
  en,
  bn,
};

export const availableLocales: { code: Locale; label: string; shortLabel: string; flag: string }[] = [
  { code: 'en', label: 'English', shortLabel: 'EN', flag: '🇬🇧' },
  { code: 'bn', label: 'বাংলা', shortLabel: 'বাং', flag: '🇧🇩' },
];

export { en, bn };
export type { TranslationKey };
