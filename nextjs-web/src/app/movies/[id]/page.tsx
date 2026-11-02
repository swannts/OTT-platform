import React from 'react';
import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { api } from '@/lib/api';
import { ContentDetails } from '@/types';
import { MovieDetailSection } from '@/features/movies';

export const revalidate = 60; // ISR cache for 60 seconds

interface PageProps {
  params: Promise<{ id: string }>;
  searchParams?: Promise<{ [key: string]: string | string[] | undefined }>;
}

async function getMovieData(id: string): Promise<ContentDetails | null> {
  try {
    const movie = await api.getContentDetails(id, 'movie');
    if (movie && movie.name) return movie;
  } catch (error) {
    console.error(`Failed to fetch movie ${id} on server:`, error);
  }

  return null;
}

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { id } = await params;
  const movie = await getMovieData(id);

  if (!movie) {
    return {
      title: 'Movie Not Found | NOOR Islamic Cinema',
      description: 'The requested Islamic movie or documentary could not be found on NOOR.',
    };
  }

  const title = `${movie.name} - Watch on NOOR Islamic Cinema`;
  const description =
    movie.description || 'Watch sacred Islamic movies, historical epics, and faith-inspired documentaries on NOOR.';
  const imageUrl =
    movie.banner_image ||
    movie.poster_image ||
    'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1200&auto=format&fit=crop&q=80';

  return {
    title,
    description,
    keywords: [
      movie.name,
      ...(movie.genre_data || []),
      'Islamic movie',
      'Halal cinema',
      'Islamic streaming',
      'NOOR Islamic Cinema',
    ],
    openGraph: {
      title,
      description,
      url: `https://noorcinema.com/movies/${id}`,
      siteName: 'NOOR Islamic Cinema',
      type: 'video.movie',
      images: [
        {
          url: imageUrl,
          width: 1200,
          height: 630,
          alt: movie.name,
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
      canonical: `/movies/${id}`,
    },
  };
}

export default async function MovieDetailPage({ params }: PageProps) {
  const { id } = await params;
  const movie = await getMovieData(id);

  if (!movie) {
    notFound();
  }

  // Schema.org Movie Rich Result
  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'Movie',
    name: movie.name,
    description: movie.description,
    image: movie.poster_image || movie.banner_image,
    datePublished: movie.release_year ? `${movie.release_year}-01-01` : '2026-01-01',
    duration: movie.duration,
    genre: movie.genre_data,
    inLanguage: 'en',
    provider: {
      '@type': 'Organization',
      name: 'NOOR Islamic Cinema',
      url: 'https://noorcinema.com',
    },
    ...(movie.imdb_rating && {
      aggregateRating: {
        '@type': 'AggregateRating',
        ratingValue: movie.imdb_rating,
        bestRating: '10',
        ratingCount: '1500',
      },
    }),
    ...(movie.casts &&
      movie.casts.length > 0 && {
        actor: movie.casts.map((c) => ({
          '@type': 'Person',
          name: c.name,
        })),
      }),
  };

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />
      <MovieDetailSection initialMovie={movie} />
    </>
  );
}
