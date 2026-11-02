import React from 'react';
import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { api } from '@/lib/api';
import { ContentDetails, EpisodeItem } from '@/types';
import { TvShowDetailSection } from '@/features/tv-shows';

export const revalidate = 60; // ISR cache for 60 seconds

interface PageProps {
  params: Promise<{ id: string }>;
  searchParams?: Promise<{ [key: string]: string | string[] | undefined }>;
}

interface RawEpisodeResponse {
  id: string | number;
  name?: string;
  episode_number?: number;
  description?: string;
  duration?: string;
  poster_image?: string;
  poster_tv_image?: string;
  has_content_access?: boolean;
  details?: {
    name?: string;
    description?: string;
    short_description?: string;
    duration?: string;
    poster_image?: string;
    has_content_access?: boolean;
  };
}

async function getTvShowData(id: string): Promise<{ show: ContentDetails; episodes: EpisodeItem[] } | null> {
  let show: ContentDetails | null = null;
  let episodes: EpisodeItem[] = [];

  try {
    const showData = await api.getContentDetails(id, 'tvshow');
    if (showData && showData.name) {
      show = showData;
    }
  } catch (err) {
    console.error(`Failed to fetch tvshow ${id} on server:`, err);
  }

  try {
    const epData = await api.getEpisodes(id);
    const list = Array.isArray(epData) ? epData : ((epData as { data?: RawEpisodeResponse[] })?.data || []);
    if (Array.isArray(list) && list.length > 0) {
      episodes = (list as RawEpisodeResponse[]).map((ep, idx) => ({
        id: ep.id,
        name: ep.name || ep.details?.name || `Episode ${idx + 1}`,
        episode_number: typeof ep.episode_number === 'number' ? ep.episode_number : (idx + 1),
        description: ep.description || ep.details?.description || ep.details?.short_description || '',
        poster_image: ep.poster_image || ep.details?.poster_image || ep.poster_tv_image || show?.poster_image || '',
        duration: ep.duration || ep.details?.duration || '',
        has_content_access: Boolean(ep.has_content_access ?? ep.details?.has_content_access ?? true),
      }));
    }
  } catch (err) {
    console.error(`Failed to fetch episodes for ${id}:`, err);
  }

  if (!show) {
    return null;
  }

  return { show, episodes };
}

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { id } = await params;
  const data = await getTvShowData(id);

  if (!data?.show) {
    return {
      title: 'Series Not Found | NOOR Islamic Cinema',
      description: 'The requested Islamic TV show or docuseries could not be found on NOOR.',
    };
  }

  const { show } = data;
  const title = show.seo?.meta_title || show.meta_title || `${show.name} - Stream Series on NOOR Islamic Cinema`;
  const description =
    show.seo?.meta_description ||
    show.meta_description ||
    show.description ||
    'Watch inspiring Islamic TV shows, Prophetic chronicles, and historical docuseries on NOOR.';

  const rawKeywords = show.seo?.meta_keywords || show.meta_keywords || show.genre_data;
  const keywords = Array.isArray(rawKeywords)
    ? rawKeywords
    : typeof rawKeywords === 'string'
      ? rawKeywords.split(',').map((k) => k.trim()).filter(Boolean)
      : [show.name, ...(show.genre_data || [])];

  const imageUrl = show.seo?.seo_image || show.banner_image || show.poster_image;
  const canonicalUrl = show.seo?.canonical_url || show.canonical_url || `/tv-shows/${id}`;

  return {
    title,
    description,
    keywords,
    openGraph: {
      title,
      description,
      url: canonicalUrl,
      siteName: 'NOOR Islamic Cinema',
      type: 'video.tv_show',
      images: imageUrl
        ? [
            {
              url: imageUrl,
              width: 1200,
              height: 630,
              alt: show.name,
            },
          ]
        : [],
      locale: 'en_US',
    },
    twitter: {
      card: 'summary_large_image',
      title,
      description,
      images: imageUrl ? [imageUrl] : [],
    },
    alternates: {
      canonical: canonicalUrl,
    },
    verification: {
      ...(show.seo?.google_site_verification ? { google: show.seo.google_site_verification } : {}),
    },
  };
}

export default async function TvShowDetailPage({ params }: PageProps) {
  const { id } = await params;
  const data = await getTvShowData(id);

  if (!data?.show) {
    notFound();
  }

  const { show, episodes } = data;

  // Schema.org TVSeries Structured JSON-LD
  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'TVSeries',
    name: show.name,
    description: show.description,
    image: show.poster_image || show.banner_image,
    datePublished: show.release_year ? `${show.release_year}-01-01` : '2026-01-01',
    genre: show.genre_data,
    numberOfEpisodes: episodes.length,
    inLanguage: 'en',
    provider: {
      '@type': 'Organization',
      name: 'NOOR Islamic Cinema',
      url: 'https://noorcinema.com',
    },
    ...(show.imdb_rating && {
      aggregateRating: {
        '@type': 'AggregateRating',
        ratingValue: show.imdb_rating,
        bestRating: '10',
        ratingCount: '2100',
      },
    }),
    containsSeason: [
      {
        '@type': 'TVSeason',
        seasonNumber: 1,
        numberOfEpisodes: episodes.length,
        episode: episodes.map((ep, idx) => ({
          '@type': 'TVEpisode',
          episodeNumber: ep.episode_number || idx + 1,
          name: ep.name,
          description: ep.description,
          duration: ep.duration,
        })),
      },
    ],
  };

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />
      <TvShowDetailSection initialShow={show} initialEpisodes={episodes} />
    </>
  );
}
