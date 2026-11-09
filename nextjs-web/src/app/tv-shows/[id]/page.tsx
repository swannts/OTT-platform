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
  episode_number?: number | string;
  season_id?: string | number;
  description?: string;
  short_desc?: string;
  duration?: string;
  poster_image?: string;
  poster_tv_image?: string;
  access?: string;
  release_date?: string;
  content_rating?: string;
  details?: {
    access?: string;
    release_date?: string;
    tv_show_data?: { season_id?: string | number };
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
    const epData = await api.getEpisodes(id, undefined, 100);
    const list = Array.isArray(epData) ? epData : ((epData as { data?: RawEpisodeResponse[] })?.data || []);
    if (Array.isArray(list) && list.length > 0) {
      episodes = (list as RawEpisodeResponse[]).map((ep) => ({
        id: ep.id,
        name: ep.name || '',
        ...(ep.episode_number !== undefined && ep.episode_number !== '' && Number.isFinite(Number(ep.episode_number))
          ? { episode_number: Number(ep.episode_number) }
          : {}),
        season_id: ep.season_id || ep.details?.tv_show_data?.season_id,
        description: ep.description || ep.short_desc || '',
        poster_image: ep.poster_image || ep.poster_tv_image || show?.poster_image || '',
        duration: ep.duration || '',
        access: ep.access || ep.details?.access,
        release_date: ep.release_date || ep.details?.release_date,
        content_rating: ep.content_rating,
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
    `View details and available episodes for ${show.name} on NOOR.`;

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
  const seasons = show.seasons || [];
  const hasSeasonEpisodeCounts = seasons.length > 0 && seasons.every((season) => season.total_episodes !== undefined);
  const totalEpisodeCount = hasSeasonEpisodeCounts
    ? seasons.reduce((total, season) => total + (season.total_episodes || 0), 0)
    : episodes.length;

  // Schema.org TVSeries Structured JSON-LD
  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'TVSeries',
    name: show.name,
    description: show.description,
    image: show.poster_image || show.banner_image,
    ...(show.release_year ? { datePublished: String(show.release_year) } : {}),
    genre: show.genre_data,
    numberOfEpisodes: totalEpisodeCount,
    ...(show.language ? { inLanguage: show.language } : {}),
    provider: {
      '@type': 'Organization',
      name: 'NOOR Islamic Cinema',
      url: 'https://noorcinema.com',
    },
    ...(seasons.length ? {
      containsSeason: seasons.map((season) => ({
        '@type': 'TVSeason',
        ...(season.name ? { name: season.name } : {}),
        ...(season.season_number !== undefined ? { seasonNumber: season.season_number } : {}),
        numberOfEpisodes: season.total_episodes ?? episodes.filter((episode) => String(episode.season_id) === String(season.id)).length,
        episode: episodes.filter((episode) => String(episode.season_id) === String(season.id) && episode.name).map((ep) => ({
          '@type': 'TVEpisode',
          ...(ep.episode_number !== undefined ? { episodeNumber: ep.episode_number } : {}),
          name: ep.name,
          description: ep.description,
          duration: ep.duration,
        })),
      })),
    } : {}),
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
