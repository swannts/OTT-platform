'use client';

import React, { createContext, useContext, useEffect, useCallback, useMemo, useSyncExternalStore } from 'react';
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
const LOCALE_CHANGE_EVENT = 'noor-locale-change';

function subscribeToLocaleChanges(onChange: () => void) {
  window.addEventListener('storage', onChange);
  window.addEventListener(LOCALE_CHANGE_EVENT, onChange);
  return () => {
    window.removeEventListener('storage', onChange);
    window.removeEventListener(LOCALE_CHANGE_EVENT, onChange);
  };
}

function getStoredLocale(): Locale {
  try {
    const savedLocale = localStorage.getItem(STORAGE_KEY);
    if (savedLocale === 'en' || savedLocale === 'bn') return savedLocale;
  } catch {
    // Fall back to the cookie when localStorage is unavailable.
  }

  return document.cookie.match(/(?:^|;\s*)noor_locale=(en|bn)(?:;|$)/)?.[1] === 'en' ? 'en' : 'bn';
}

function getDefaultLocale(): Locale {
  return 'bn';
}

export function LanguageProvider({ children }: { children: React.ReactNode }) {
  const locale = useSyncExternalStore(subscribeToLocaleChanges, getStoredLocale, getDefaultLocale);

  useEffect(() => {
    api.setLocale(locale);
    document.documentElement.lang = locale;
  }, [locale]);

  const setLocale = useCallback((newLocale: Locale) => {
    api.setLocale(newLocale);
    document.documentElement.lang = newLocale;

    try {
      localStorage.setItem(STORAGE_KEY, newLocale);
    } catch {
      // Cookie persistence can still work when localStorage is unavailable.
    }
    document.cookie = `${STORAGE_KEY}=${newLocale}; path=/; max-age=31536000; SameSite=Lax`;
    window.dispatchEvent(new Event(LOCALE_CHANGE_EVENT));
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

