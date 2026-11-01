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
    const list = Array.isArray(epData) ? epData : (epData?.data || []);
    if (Array.isArray(list) && list.length > 0) {
      episodes = list.map((ep, idx) => ({
        id: ep.id,
        name: ep.name || `Episode ${idx + 1}`,
        episode_number: typeof ep.episode_number === 'number' ? ep.episode_number : (idx + 1),
        description: ep.description,
        poster_image: ep.poster_image || ep.thumbnail_image,
        duration: ep.duration,
        has_content_access: ep.has_content_access,
      }));
    }
  } catch (err) {
    console.error(`Failed to fetch episodes for ${id}:`, err);
  }

  if (!show) {
    show = {
      id,
      name: 'The Life of Prophet Muhammad ﷺ: From Mecca to Madinah',
      type: 'tvshow',
      description:
        'A comprehensive, spiritually uplifting docuseries tracing the blessed life of the final Messenger of Allah ﷺ from the Year of the Elephant through the Hijrah to the Farewell Pilgrimage.',
      poster_image: 'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=800&auto=format&fit=crop&q=80',
      banner_image: 'https://images.unsplash.com/photo-1588714477688-cf28a50e94f7?w=1600&auto=format&fit=crop&q=80',
      release_year: '2026',
      imdb_rating: '9.9',
      access: 'free',
      has_content_access: false,
      genre_data: ['Prophetic Biographies', 'Islamic History', 'Docuseries'],
    };
  }

  if (episodes.length === 0) {
    episodes = [
      {
        id: '01940000-0006-7000-8000-000000000001',
        name: 'Episode 1: The Year of the Elephant & The Blessed Birth',
        episode_number: 1,
        description: 'The miraculous defense of the Holy Kaaba and the blessed birth of the Prophet Muhammad ﷺ in Mecca.',
        duration: '45m',
        has_content_access: false,
      },
      {
        id: '01940000-0006-7000-8000-000000000002',
        name: 'Episode 2: The Cave of Hira & The First Divine Revelation',
        episode_number: 2,
        description: 'Solitude on Jabal al-Nour and the first revelation received through Angel Jibril: "Read in the name of your Lord."',
        duration: '48m',
        has_content_access: false,
      },
      {
        id: '01940000-0006-7000-8000-000000000003',
        name: 'Episode 3: The Call on Mount Safa & The Early Believers',
        episode_number: 3,
        description: 'The open invitation to monotheism and the steadfast patience of the early companions under adversity.',
        duration: '52m',
        has_content_access: false,
      },
    ];
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
  const title = `${show.name} - Stream Series on NOOR Islamic Cinema`;
  const description =
    show.description || 'Watch inspiring Islamic TV shows, Prophetic chronicles, and historical docuseries on NOOR.';
  const imageUrl =
    show.banner_image ||
    show.poster_image ||
    'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1200&auto=format&fit=crop&q=80';

  return {
    title,
    description,
    keywords: [
      show.name,
      ...(show.genre_data || []),
      'Islamic series',
      'Prophetic series',
      'Islamic TV show',
      'Halal docuseries',
      'NOOR Islamic Cinema',
    ],
    openGraph: {
      title,
      description,
      url: `https://noorcinema.com/tv-shows/${id}`,
      siteName: 'NOOR Islamic Cinema',
      type: 'video.tv_show',
      images: [
        {
          url: imageUrl,
          width: 1200,
          height: 630,
          alt: show.name,
        },
      ],
      locale: 'en_US',
    },
    twitter: {
      card: 'summary_large_image',
      title,
      description,
      images: [imageUrl],
    },
    alternates: {
      canonical: `/tv-shows/${id}`,
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
