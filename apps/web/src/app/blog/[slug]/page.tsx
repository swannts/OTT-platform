import React from 'react';
import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { getBlogPostDal } from '@/server/dal';
import { BlogPostDetail } from '@/features/blog';

interface BlogPostPageProps {
  params: Promise<{ slug: string }>;
}

export const revalidate = 60; // Cache for 60s

export async function generateMetadata({ params }: BlogPostPageProps): Promise<Metadata> {
  const { slug } = await params;
  const data = await getBlogPostDal(slug);

  if (!data?.post) {
    return {
      title: 'Story Not Found | NOOR Journal',
      description: 'The requested Islamic Cinema article could not be found.',
    };
  }

  const { post } = data;
  return {
    title: `${post.title} | NOOR Journal`,
    description: post.summary || 'Read this inspiring article on NOOR Islamic Cinema.',
    keywords: post.tags || ['Islamic cinema', 'NOOR Journal'],
    openGraph: {
      title: `${post.title} | NOOR Journal`,
      description: post.summary,
      url: `https://noorcinema.com/blog/${post.slug}`,
      siteName: 'NOOR Islamic Cinema',
      type: 'article',
      publishedTime: post.published_at,
      authors: [post.author_name],
      images: post.image_url
        ? [
            {
              url: post.image_url,
              width: 1200,
              height: 630,
              alt: post.title,
            },
          ]
        : [],
    },
    twitter: {
      card: 'summary_large_image',
      title: `${post.title} | NOOR Journal`,
      description: post.summary,
      images: post.image_url ? [post.image_url] : [],
    },
    alternates: {
      canonical: `/blog/${post.slug}`,
    },
  };
}

export default async function BlogPostPage({ params }: BlogPostPageProps) {
  const { slug } = await params;
  const data = await getBlogPostDal(slug);

  if (!data?.post) {
    notFound();
  }

  const { post, related } = data;

  // Schema.org BlogPosting
  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'BlogPosting',
    headline: post.title,
    description: post.summary,
    image: post.image_url,
    datePublished: post.published_at || post.created_at,
    author: {
      '@type': 'Person',
      name: post.author_name,
    },
    publisher: {
      '@type': 'Organization',
      name: 'NOOR Islamic Cinema',
      url: 'https://noorcinema.com',
    },
    mainEntityOfPage: {
      '@type': 'WebPage',
      '@id': `https://noorcinema.com/blog/${post.slug}`,
    },
  };

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />
      <BlogPostDetail post={post} related={related} />
    </>
  );
}

