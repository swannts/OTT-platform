'use client';

import React, { useState, useRef, useEffect } from 'react';
import { useLanguage } from '@/providers';
import { Languages, Check } from 'lucide-react';

interface LanguageSwitcherProps {
  variant?: 'pill' | 'dropdown' | 'compact';
  className?: string;
}

export default function LanguageSwitcher({
  variant = 'compact',
  className = '',
}: LanguageSwitcherProps) {
  const { locale, setLocale, availableLocales } = useLanguage();
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    }
    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isOpen]);

  const activeLocale = availableLocales.find((l) => l.code === locale) || availableLocales[0];

  // Quick 1-click toggle for compact variant
  const handleToggle = () => {
    const nextLocale = locale === 'en' ? 'bn' : 'en';
    setLocale(nextLocale);
  };

  if (variant === 'pill') {
    return (
      <div className={`inline-flex items-center p-0.5 rounded-full bg-white/[0.06] border border-white/10 ${className}`}>
        {availableLocales.map((l) => {
          const isActive = l.code === locale;
          return (
            <button
              key={l.code}
              type="button"
              onClick={() => setLocale(l.code)}
              className={`px-2.5 py-1 text-xs font-semibold rounded-full transition-all cursor-pointer ${
                isActive
                  ? 'bg-[#E5B869] text-black shadow-sm'
                  : 'text-gray-400 hover:text-white'
              }`}
            >
              {l.label}
            </button>
          );
        })}
      </div>
    );
  }

  return (
    <div className={`relative flex-shrink-0 ${className}`} ref={dropdownRef}>
      <button
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        className={`flex items-center space-x-1.5 px-2.5 py-1.5 rounded-full transition-all cursor-pointer active:scale-95 text-xs font-medium border flex-shrink-0 ${
          isOpen
            ? 'bg-[#E5B869]/20 border-[#E5B869]/60 text-[#E5B869]'
            : 'bg-white/[0.04] hover:bg-white/10 border-white/10 text-gray-300 hover:text-white'
        }`}
        aria-label="Change Language"
        aria-expanded={isOpen}
      >
        <Languages className="w-3.5 h-3.5 text-[#E5B869] flex-shrink-0" />
        <span className="font-semibold">{activeLocale.shortLabel}</span>
      </button>

      {isOpen && (
        <div className="absolute right-0 mt-2 w-36 bg-[#090e15]/98 backdrop-blur-2xl rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.8)] border border-white/10 py-1.5 z-50 animate-hero-title">
          <div className="px-3 py-1 text-[10px] uppercase font-bold text-gray-500 tracking-wider">
            Language / ভাষা
          </div>
          {availableLocales.map((l) => {
            const isActive = l.code === locale;
            return (
              <button
                key={l.code}
                type="button"
                onClick={() => {
                  setLocale(l.code);
                  setIsOpen(false);
                }}
                className={`w-full flex items-center justify-between px-3 py-2 text-xs transition-colors cursor-pointer ${
                  isActive
                    ? 'text-[#E5B869] bg-white/[0.06] font-bold'
                    : 'text-gray-300 hover:text-white hover:bg-white/[0.04]'
                }`}
              >
                <div className="flex items-center space-x-2">
                  <span className="text-sm">{l.flag}</span>
                  <span>{l.label}</span>
                </div>
                {isActive && <Check className="w-3.5 h-3.5 text-[#E5B869]" />}
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}
