import React from 'react';
import type { Metadata } from 'next';
import { getBlogsDal, getBlogCategoriesDal } from '@/server/dal';
import { BlogSection } from '@/features/blog';

export const revalidate = 60; // ISR revalidation every 60 seconds

export const metadata: Metadata = {
  title: 'Islamic Cinema Journal & Sacred Stories | NOOR',
  description:
    'Explore inspiring articles, behind-the-scenes insights, and historical chronicles celebrating Islamic cinema, culture, and values.',
  keywords: [
    'Islamic cinema blog',
    'Halal entertainment articles',
    'Islamic history chronicles',
    'Prophetic storytelling',
    'NOOR Journal',
    'Behind the scenes Islamic cinema',
  ],
  openGraph: {
    title: 'Islamic Cinema Journal & Sacred Stories | NOOR',
    description:
      'Explore inspiring articles, behind-the-scenes insights, and historical chronicles celebrating Islamic cinema, culture, and values.',
    url: 'https://noorcinema.com/blog',
    siteName: 'NOOR Islamic Cinema',
    type: 'website',
    images: [
      {
        url: 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=1200&auto=format&fit=crop&q=85',
        width: 1200,
        height: 630,
        alt: 'NOOR Islamic Cinema Journal',
      },
    ],
  },
  twitter: {
    card: 'summary_large_image',
    title: 'Islamic Cinema Journal & Sacred Stories | NOOR',
    description:
      'Explore inspiring articles, behind-the-scenes insights, and historical chronicles on NOOR.',
    images: ['https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=1200&auto=format&fit=crop&q=85'],
  },
  alternates: {
    canonical: '/blog',
  },
};

export default async function BlogIndexPage() {
  const [blogsRes, categories] = await Promise.all([
    getBlogsDal({ per_page: 18 }),
    getBlogCategoriesDal(),
  ]);

  const posts = blogsRes?.data || [];

  // Schema.org Blog Microdata
  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'Blog',
    name: 'NOOR Islamic Cinema Journal',
    description: 'Essays on Islamic history, cinema craft, and sacred storytelling.',
    url: 'https://noorcinema.com/blog',
    publisher: {
      '@type': 'Organization',
      name: 'NOOR Islamic Cinema',
      url: 'https://noorcinema.com',
    },
    blogPost: posts.map((p) => ({
      '@type': 'BlogPosting',
      headline: p.title,
      description: p.summary,
      url: `https://noorcinema.com/blog/${p.slug}`,
      datePublished: p.published_at || p.created_at,
      author: {
        '@type': 'Person',
        name: p.author_name,
      },
    })),
  };

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />
      <BlogSection initialPosts={posts} categories={categories} />
    </>
  );
}

