import React from 'react';
import type { Metadata } from 'next';
import { api } from '@/lib/api';
import { Plan } from '@/types';
import PricingSection from '@/sections/pricing/PricingSection';

export const revalidate = 300; // Cache pricing for 5 minutes

export const metadata: Metadata = {
  title: 'Subscription Plans & VIP Membership | NOOR Islamic Cinema',
  description:
    'Join NOOR VIP for unlimited 4K Ultra HD streaming of Islamic movies, sacred series, and live Holy Mosque broadcasts. Ad-free, cancel anytime.',
  keywords: [
    'NOOR subscription',
    'VIP Islamic streaming',
    'Halal cinema subscription',
    'Islamic TV plans',
    '4K Islamic streaming',
    'NOOR Islamic Cinema',
  ],
  openGraph: {
    title: 'Subscription Plans & VIP Membership | NOOR Islamic Cinema',
    description:
      'Join NOOR VIP for unlimited 4K Ultra HD streaming of Islamic movies, sacred series, and live Holy Mosque broadcasts.',
    url: 'https://noorcinema.com/pricing',
    siteName: 'NOOR Islamic Cinema',
    type: 'website',
  },
  alternates: {
    canonical: '/pricing',
  },
};

export default async function PricingPage() {
  let plans: Plan[] = [];

  try {
    const res = await api.getPlans();
    if (Array.isArray(res) && res.length > 0) {
      plans = res;
    }
  } catch (err) {
    console.error('Failed to fetch pricing plans on server:', err);
  }

  if (plans.length === 0) {
    plans = [
      {
        plan_id: 1,
        name: 'Basic Stream',
        identifier: 'basic',
        price: 4.99,
        total_price: 4.99,
        level: 1,
        duration: 'month',
        duration_value: 1,
        description: 'Essential entertainment on mobile & desktop in 1080p HD.',
        plan_type: [
          { id: 1, limitation_title: '1 Simultaneous Device', limitation_value: 1, slug: 'device-limit' },
          { id: 2, limitation_title: 'HD 1080p Quality', limitation_value: true, slug: 'video-quality' },
          { id: 3, limitation_title: 'Ad-Supported', limitation_value: true, slug: 'ads' },
        ],
      },
      {
        plan_id: 2,
        name: 'Premium VIP',
        identifier: 'premium',
        price: 9.99,
        total_price: 9.99,
        level: 2,
        duration: 'month',
        duration_value: 1,
        description: 'Ultra 4K HDR streaming with 4 simultaneous screens, ad-free experience, and offline downloads.',
        plan_type: [
          { id: 1, limitation_title: '4 Simultaneous Devices', limitation_value: 4, slug: 'device-limit' },
          { id: 2, limitation_title: '4K Ultra HD + HDR', limitation_value: true, slug: 'video-quality' },
          { id: 3, limitation_title: '100% Ad-Free Experience', limitation_value: false, slug: 'ads' },
          { id: 4, limitation_title: 'Offline Video Downloads', limitation_value: true, slug: 'download-status' },
        ],
      },
      {
        plan_id: 3,
        name: 'Annual VIP Pass',
        identifier: 'annual',
        price: 89.99,
        discount_percentage: 25,
        total_price: 89.99,
        level: 3,
        duration: 'year',
        duration_value: 1,
        description: 'Full year of unlimited VIP streaming across all devices with 25% annual discount.',
        plan_type: [
          { id: 1, limitation_title: '6 Simultaneous Devices', limitation_value: 6, slug: 'device-limit' },
          { id: 2, limitation_title: 'Dolby Atmos & 4K Vision', limitation_value: true, slug: 'video-quality' },
          { id: 3, limitation_title: '100% Ad-Free Experience', limitation_value: false, slug: 'ads' },
          { id: 4, limitation_title: 'Early Access to New Releases', limitation_value: true, slug: 'early-access' },
        ],
      },
    ];
  }

  // Schema.org PriceSpecification OfferCatalog
  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: 'NOOR VIP Membership',
    description: 'Ad-free unlimited streaming of Islamic movies, sacred series, and live Holy Mosque broadcasts.',
    offers: {
      '@type': 'AggregateOffer',
      priceCurrency: 'USD',
      lowPrice: '4.99',
      highPrice: '89.99',
      offerCount: plans.length,
      offers: plans.map((p) => ({
        '@type': 'Offer',
        name: p.name,
        price: p.price,
        priceCurrency: 'USD',
        description: p.description,
        url: `https://noorcinema.com/pricing`,
      })),
    },
  };

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />
      <PricingSection initialPlans={plans} />
    </>
  );
}
