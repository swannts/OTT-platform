import React from 'react';
import type { Metadata } from 'next';
import Link from 'next/link';
import { api, normalizeMediaItem } from '@/lib/api';
import { MediaGrid } from '@/components/shared';
import { Award, ArrowLeft, BookOpen } from 'lucide-react';
import { notFound } from 'next/navigation';

export const revalidate = 60;

interface PageProps {
  params: Promise<{ id: string }>;
  searchParams?: Promise<{ [key: string]: string | string[] | undefined }>;
}

async function getCastData(id: string) {
  try {
    const res = await api.getCastDetails(id);
    if (res && res.name) return res;
  } catch (err) {
    console.error(`Failed to fetch cast ${id} on server:`, err);
  }

  return null;
}

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { id } = await params;
  const cast = await getCastData(id);

  if (!cast) {
    return {
      title: 'Scholar Not Found | NOOR',
      description: 'The requested scholar or speaker details could not be found.',
    };
  }

  const title = `${cast.name} - Islamic Scholar & Speaker | NOOR`;
  const description =
    cast.bio || `Watch lectures, historical docuseries, and prophetic discourses featuring ${cast.name} on NOOR.`;
  const image: string =
    (typeof cast.image === 'string' && cast.image) ||
    (typeof cast.profile_image === 'string' && cast.profile_image) ||
    '/showcase/posters/path-of-light.svg';

  return {
    title,
    description,
    keywords: [cast.name, 'Islamic scholar', 'Speaker', 'Lectures', 'NOOR Islamic Cinema'],
    openGraph: {
      title,
      description,
      url: `https://noorcinema.com/cast/${id}`,
      siteName: 'NOOR Islamic Cinema',
      type: 'profile',
      images: [
        {
          url: image,
          width: 800,
          height: 800,
          alt: cast.name,
        },
      ],
    },
    twitter: {
      card: 'summary',
      title,
      description,
      images: [image],
    },
    alternates: {
      canonical: `/cast/${id}`,
    },
  };
}

export default async function CastDetailPage({ params }: PageProps) {
  const { id } = await params;
  const cast = await getCastData(id);

  if (!cast) {
    notFound();
  }

  const rawWorks = cast.entertainments || cast.movies || cast.works || [];
  const filmography = Array.isArray(rawWorks) ? rawWorks.map(normalizeMediaItem) : [];

  // Schema.org Person JSON-LD
  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'Person',
    name: cast.name,
    jobTitle: cast.type || 'Islamic Scholar',
    description: cast.bio,
    image: cast.image || cast.profile_image,
    url: `https://noorcinema.com/cast/${id}`,
  };

  return (
    <div className="min-h-screen pt-24 pb-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto select-none">
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />

      {/* Back Button */}
      <div className="mb-6">
        <Link
          href="/movies"
          className="inline-flex items-center space-x-2 text-xs uppercase tracking-wider font-semibold text-gray-400 hover:text-[#E5B869] transition-colors cursor-pointer"
        >
          <ArrowLeft className="w-4 h-4" />
          <span>Back to Catalog</span>
        </Link>
      </div>

      {/* Profile Header */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-8 mb-12">
        {/* Photo */}
        <div className="flex justify-center md:justify-start">
          <div className="w-48 h-64 sm:w-56 sm:h-72 rounded-2xl overflow-hidden shadow-2xl border border-white/10 bg-neutral-900">
            <img
              src={
                (typeof cast.image === 'string' && cast.image) ||
                (typeof cast.profile_image === 'string' && cast.profile_image) ||
                '/showcase/posters/path-of-light.svg'
              }
              alt={cast.name}
              className="w-full h-full object-cover"
            />
          </div>
        </div>

        {/* Bio & Details */}
        <div className="md:col-span-3 flex flex-col justify-center">
          <div className="flex items-center space-x-2 text-xs font-semibold text-[#E5B869] uppercase tracking-wider mb-2">
            <Award className="w-4 h-4" />
            <span>{cast.type || 'Scholar / Speaker'}</span>
          </div>

          <h1 className="text-3xl sm:text-5xl font-black text-white tracking-tight mb-4 drop-shadow-md">
            {cast.name}
          </h1>

          {cast.bio && (
            <p className="text-gray-300 text-sm sm:text-base leading-relaxed mb-6 max-w-3xl">
              {cast.bio}
            </p>
          )}
        </div>
      </div>

      {/* Filmography Section */}
      <div className="border-t border-white/10 pt-8">
        <h2 className="text-xl sm:text-2xl font-bold text-white mb-6 flex items-center space-x-2">
          <BookOpen className="w-5 h-5 text-[#E5B869]" />
          <span>Featured Works & Series</span>
        </h2>

        <MediaGrid
          items={filmography}
          aspectRatio="backdrop"
          emptyState={
            <p className="text-sm text-gray-500">More titles featuring this scholar are currently in production.</p>
          }
        />
      </div>
    </div>
  );
}
