'use client';

import React from 'react';
import Link from 'next/link';
import Logo from './Logo';
import LanguageSwitcher from './LanguageSwitcher';
import { useLanguage } from '@/providers';

export default function Footer() {
  const { t } = useLanguage();

  return (
    <footer className="border-t border-white/5 bg-[#05080c] py-14 text-gray-400 text-xs mt-16 select-none">
      <div className="max-w-[1440px] mx-auto px-6 sm:px-10 md:px-14 lg:px-16 xl:px-24">
        <div className="grid grid-cols-2 md:grid-cols-5 gap-8 mb-10">
          {/* Brand Info */}
          <div className="col-span-2">
            <div className="mb-4">
              <Logo />
            </div>
            <p className="text-gray-400 leading-relaxed max-w-sm mb-4 text-xs">
              {t('tagline', 'NOOR is an Islamic streaming platform providing family-safe, faith-based educational, historical, and spiritual video content, sacred live broadcasts, and inspirational series.')}
            </p>
            <div className="mb-4">
              <LanguageSwitcher variant="pill" />
            </div>
            <p className="text-gray-500">
              © {new Date().getFullYear()} NOOR Media Network. {t('rights_reserved', 'All rights reserved.')}
            </p>
          </div>

          <div>
            <h4 className="text-white font-bold mb-3.5 text-sm tracking-wide">Sacred Streams</h4>
            <ul className="space-y-2.5">
              <li><Link href="/" className="hover:text-[#E5B869] transition-colors">{t('nav_home', 'Home')}</Link></li>
              <li><Link href="/live-tv" className="hover:text-[#E5B869] transition-colors">{t('makkah_live', 'Makkah & Madinah Live')}</Link></li>
              <li><Link href="/tv-shows" className="hover:text-[#E5B869] transition-colors">{t('prophetic_chronicles', 'Prophetic Series')}</Link></li>
              <li><Link href="/movies" className="hover:text-[#E5B869] transition-colors">{t('nav_documentaries', 'Documentaries')}</Link></li>
              <li><Link href="/videos" className="hover:text-[#E5B869] transition-colors">{t('nav_waz', 'Waz & Bayan')}</Link></li>
              <li><Link href="/blog" className="hover:text-[#E5B869] transition-colors">{t('nav_journal', 'Journal & Articles')}</Link></li>
            </ul>
          </div>

          <div>
            <h4 className="text-white font-bold mb-3.5 text-sm tracking-wide">Membership</h4>
            <ul className="space-y-2.5">
              <li><Link href="/pricing" className="hover:text-[#E5B869] transition-colors">{t('upgrade_to_vip', 'Go Premium (VIP)')}</Link></li>
              <li><Link href="/pricing" className="hover:text-[#E5B869] transition-colors">Family Plan</Link></li>
              <li><Link href="/account/billing" className="hover:text-[#E5B869] transition-colors">{t('billing_invoices', 'Billing & Invoices')}</Link></li>
              <li><Link href="/profiles" className="hover:text-[#E5B869] transition-colors">{t('nav_kids', 'Kids Safe Profiles')}</Link></li>
            </ul>
          </div>

          <div>
            <h4 className="text-white font-bold mb-3.5 text-sm tracking-wide">Legal & Policies</h4>
            <ul className="space-y-2.5">
              <li><Link href="/pages/privacy-policy" className="hover:text-[#E5B869] transition-colors">Privacy Policy</Link></li>
              <li><Link href="/pages/terms-and-conditions" className="hover:text-[#E5B869] transition-colors">Terms of Service</Link></li>
              <li><Link href="/pages/refund-policy" className="hover:text-[#E5B869] transition-colors">Refund Policy</Link></li>
              <li><Link href="/pages/faq" className="hover:text-[#E5B869] transition-colors">FAQ & Support</Link></li>
            </ul>
          </div>
        </div>
      </div>
    </footer>
  );
}
