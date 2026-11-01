import React from 'react';
import type { Metadata } from 'next';
import { getPricingPlansDal } from '@/server/dal';
import { PricingSection } from '@/features/pricing';

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
  const plans = await getPricingPlansDal();

  // Schema.org PriceSpecification OfferCatalog
  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: 'NOOR VIP Membership',
    description: 'Ad-free unlimited streaming of Islamic movies, sacred series, and live Holy Mosque broadcasts.',
    offers: {
      '@type': 'AggregateOffer',
      priceCurrency: 'USD',
      lowPrice: plans.length > 0 ? String(Math.min(...plans.map((p) => p.price))) : '4.99',
      highPrice: plans.length > 0 ? String(Math.max(...plans.map((p) => p.price))) : '89.99',
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
