import React from 'react';
import type { Metadata } from 'next';
import { getBlogsDal, getBlogCategoriesDal } from '@/server/dal';
import { BlogSection } from '@/features/blog';
import { api } from '@/lib/api';

export const revalidate = 60; // ISR revalidation every 60 seconds

export async function generateMetadata(): Promise<Metadata> {
  const seo = await api.getSeoSettings().catch(() => null);
  const title = seo?.meta_title
    ? `Journal & Stories | ${seo.meta_title}`
    : 'Islamic Cinema Journal & Sacred Stories | NOOR';
  const description =
    seo?.meta_description ||
    'Explore inspiring articles, behind-the-scenes insights, and historical chronicles celebrating Islamic cinema, culture, and values.';

  return {
    title,
    description,
    keywords:
      Array.isArray(seo?.meta_keywords) && seo.meta_keywords.length > 0
        ? ['Journal', ...seo.meta_keywords]
        : ['Islamic cinema blog', 'Halal entertainment articles', 'Islamic history chronicles', 'NOOR Journal'],
    openGraph: {
      title,
      description,
      url: '/blog',
      siteName: 'NOOR Islamic Cinema',
      type: 'website',
      images: seo?.seo_image ? [seo.seo_image] : [],
    },
    alternates: {
      canonical: '/blog',
    },
  };
}

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

