import React from 'react';
import type { Metadata } from 'next';
import { getPricingPlansDal } from '@/server/dal';
import { PricingSection } from '@/features/pricing';
import { api } from '@/lib/api';

export const revalidate = 300; // Cache pricing for 5 minutes

export async function generateMetadata(): Promise<Metadata> {
  const seo = await api.getSeoSettings().catch(() => null);
  const title = seo?.meta_title
    ? `VIP Membership & Plans | ${seo.meta_title}`
    : 'Subscription Plans & VIP Membership | NOOR Islamic Cinema';
  const description =
    seo?.meta_description ||
    'Join NOOR VIP for unlimited 4K Ultra HD streaming of Islamic movies, sacred series, and live Holy Mosque broadcasts. Ad-free, cancel anytime.';

  return {
    title,
    description,
    keywords:
      Array.isArray(seo?.meta_keywords) && seo.meta_keywords.length > 0
        ? seo.meta_keywords
        : ['NOOR subscription', 'VIP Islamic streaming', 'Islamic streaming plans', 'Halal cinema'],
    openGraph: {
      title,
      description,
      url: '/pricing',
      siteName: 'NOOR Islamic Cinema',
      type: 'website',
    },
    alternates: {
      canonical: '/pricing',
    },
  };
}

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
