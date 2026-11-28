import { normalizeMediaItem } from '@/lib/api';
import type { HomeInitialData } from '@/server/dal/home.dal';
import type { LiveChannel } from '@/types';
import { MediaRow } from '@/components/shared';
import HeroBanner from './HeroBanner';
import LiveNowSection from './LiveNowSection';
import ContinueWatchingRow from './ContinueWatchingRow';

interface HomeSectionProps {
  initialContent: HomeInitialData;
  locale: 'en' | 'bn';
}

export default function HomeSection({ initialContent, locale }: HomeSectionProps) {
  const sections = (initialContent.homepageConfig?.sections ?? [])
    .filter((section) => section.enabled)
    .map((section) => ({
      ...section,
      title: locale === 'bn'
        ? section.title_bn || section.title
        : section.title || section.title_bn,
      items: section.items ?? [],
    }));

  const hasCatalogContent = initialContent.banners.length > 0 || sections.some((section) => section.items.length > 0);

  if (!hasCatalogContent) {
    return (
      <section className="relative flex min-h-[70vh] items-center justify-center overflow-hidden px-6 text-center">
        <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(229,184,105,0.12),transparent_55%)]" />
        <div className="relative max-w-xl">
          <h1 className="font-serif-title text-3xl font-bold text-white sm:text-4xl">
            {locale === 'bn' ? 'কনটেন্ট পাওয়া যাচ্ছে না' : 'No content is available right now'}
          </h1>
          <p className="mt-4 text-sm leading-7 text-gray-400 sm:text-base">
            {locale === 'bn'
              ? 'নতুন সিনেমা, সিরিজ এবং লাইভ চ্যানেলের জন্য পরে আবার দেখুন।'
              : 'Please check back soon for movies, series, and live channels.'}
          </p>
        </div>
      </section>
    );
  }

  return (
    <div className="min-h-screen pb-16">
      <HeroBanner items={initialContent.banners} />
      <div className="relative z-20 mx-auto max-w-[1360px] space-y-14 px-8 pt-10 sm:space-y-16 sm:px-12 sm:pt-14 md:px-16 lg:px-20 xl:px-24">
        <ContinueWatchingRow />
        {sections.map((section) => {
          if (section.key === 'live-now') {
            return (
              <LiveNowSection
                key={section.key}
                channels={section.items as LiveChannel[]}
                premiumCard={initialContent.homepageConfig.premium_card}
                title={section.title}
              />
            );
          }

          const items = section.items.map(normalizeMediaItem);
          if (items.length === 0) return null;

          return (
            <MediaRow
              key={section.key}
              title={section.title}
              items={items}
              moreHref={section.more_href}
              showRank={section.ranked}
              aspectRatio={section.ranked ? 'poster' : 'backdrop'}
            />
          );
        })}
      </div>
    </div>
  );
}
