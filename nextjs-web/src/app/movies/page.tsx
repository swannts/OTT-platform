import React from 'react';
import type { Metadata } from 'next';
import { getMoviesDal } from '@/server/dal';
import { MoviesSection } from '@/features/movies';

export const revalidate = 60; // ISR cache for 60 seconds

export const metadata: Metadata = {
  title: 'Islamic Movies & Sacred Cinema | NOOR',
  description:
    'Explore our curated collection of inspirational Islamic movies, halal family cinema, historical epics, and sacred documentaries. Stream in HD with authentic Islamic storytelling.',
  keywords: [
    'Islamic movies',
    'Halal cinema',
    'Islamic films',
    'Makkah documentary',
    'Prophet stories',
    'Halal streaming',
    'NOOR Islamic Cinema',
  ],
  openGraph: {
    title: 'Islamic Movies & Sacred Cinema | NOOR',
    description:
      'Explore our curated collection of inspirational Islamic movies, halal family cinema, and sacred documentaries.',
    url: 'https://noorcinema.com/movies',
    siteName: 'NOOR Islamic Cinema',
    type: 'website',
    images: [
      {
        url: 'https://images.unsplash.com/photo-1541432901042-2d8bd64b4a9b?w=1200&auto=format&fit=crop&q=80',
        width: 1200,
        height: 630,
        alt: 'Islamic Cinema - NOOR',
      },
    ],
  },
  twitter: {
    card: 'summary_large_image',
    title: 'Islamic Movies & Sacred Cinema | NOOR',
    description:
      'Explore our curated collection of inspirational Islamic movies, halal family cinema, and sacred documentaries.',
    images: ['https://images.unsplash.com/photo-1541432901042-2d8bd64b4a9b?w=1200&auto=format&fit=crop&q=80'],
  },
  alternates: {
    canonical: '/movies',
  },
};

export default async function MoviesPage() {
  const movies = await getMoviesDal({ page: 1 });

  // Schema.org CollectionPage
  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'CollectionPage',
    name: 'Islamic Movies & Sacred Cinema',
    description: 'Explore inspirational Islamic cinema, documentaries, and historical features on NOOR.',
    url: 'https://noorcinema.com/movies',
    isPartOf: {
      '@type': 'WebSite',
      name: 'NOOR Islamic Cinema',
      url: 'https://noorcinema.com',
    },
    hasPart: movies.slice(0, 10).map((m) => ({
      '@type': 'Movie',
      name: m.name,
      description: m.description,
      image: m.poster_image,
      url: `https://noorcinema.com/movies/${m.id}`,
    })),
  };

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />
      <MoviesSection initialMovies={movies} />
    </>
  );
}
