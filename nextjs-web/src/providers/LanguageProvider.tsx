'use client';

import React, { createContext, useContext, useState, useEffect, useCallback, useMemo } from 'react';
import { locales, type Locale, type TranslationKey, availableLocales } from '@/locales';
import { api } from '@/lib/api';

interface LanguageContextType {
  locale: Locale;
  setLocale: (newLocale: Locale) => void;
  t: (key: TranslationKey, defaultText?: string) => string;
  availableLocales: typeof availableLocales;
  isBangla: boolean;
}

const LanguageContext = createContext<LanguageContextType | undefined>(undefined);

const STORAGE_KEY = 'noor_locale';

export function LanguageProvider({ children }: { children: React.ReactNode }) {
  const [locale, setLocaleState] = useState<Locale>('en');
  const [isMounted, setIsMounted] = useState(false);

  // Initialize from localStorage or cookie on mount
  useEffect(() => {
    setIsMounted(true);
    try {
      const savedLocale = localStorage.getItem(STORAGE_KEY) as Locale | null;
      if (savedLocale === 'en' || savedLocale === 'bn') {
        setLocaleState(savedLocale);
        api.setLocale(savedLocale);
        document.documentElement.lang = savedLocale;
      }
    } catch {
      // Ignore localStorage read errors in restricted environments
    }
  }, []);

  const setLocale = useCallback((newLocale: Locale) => {
    setLocaleState(newLocale);
    api.setLocale(newLocale);

    try {
      localStorage.setItem(STORAGE_KEY, newLocale);
      document.cookie = `${STORAGE_KEY}=${newLocale}; path=/; max-age=31536000; SameSite=Lax`;
      document.documentElement.lang = newLocale;
    } catch {
      // Ignore storage errors
    }
  }, []);

  const t = useCallback(
    (key: TranslationKey, defaultText?: string): string => {
      const activeDictionary = locales[locale];
      return activeDictionary?.[key] || defaultText || locales.en[key] || key;
    },
    [locale]
  );

  const value = useMemo(
    () => ({
      locale,
      setLocale,
      t,
      availableLocales,
      isBangla: locale === 'bn',
    }),
    [locale, setLocale, t]
  );

  return (
    <LanguageContext.Provider value={value}>
      {children}
    </LanguageContext.Provider>
  );
}

export function useLanguage(): LanguageContextType {
  const context = useContext(LanguageContext);
  if (!context) {
    throw new Error('useLanguage must be used within a LanguageProvider');
  }
  return context;
}
