import React from 'react';
import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { api } from '@/lib/api';

export const revalidate = 300; // Cache CMS pages for 5 minutes

interface PageProps {
  params: Promise<{ slug: string }>;
  searchParams?: Promise<{ [key: string]: string | string[] | undefined }>;
}

interface StaticPageData {
  name?: string;
  title?: string;
  description?: string;
  content?: string;
}

async function getPageData(slug: string): Promise<StaticPageData> {
  try {
    const data = await api.getPage(slug);
    if (data && (data.name || data.title)) {
      return {
        name: typeof data.name === 'string' ? data.name : undefined,
        title: typeof data.title === 'string' ? data.title : undefined,
        description: typeof data.description === 'string' ? data.description : (typeof data.content === 'string' ? data.content : undefined),
        content: typeof data.content === 'string' ? data.content : undefined,
      };
    }
  } catch (err) {
    console.error(`Failed to fetch page ${slug} on server:`, err);
  }

  const cleanName = slug.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
  return {
    name: cleanName,
    title: cleanName,
    description: `
      <p>Welcome to <strong>NOOR Islamic Cinema</strong>. This policy document outlines our service standards, ethical broadcasting values, halal content curation principles, and user agreements.</p>
      <br />
      <h3>1. Halal Content & Family Safety Guarantee</h3>
      <p>NOOR is committed to providing 100% halal, family-safe Islamic streaming content free from inappropriate imagery, profanity, or harmful values.</p>
      <br />
      <h3>2. Privacy & Data Integrity</h3>
      <p>We respect the sacred privacy of our subscribers. We do not sell or monetize personal data or viewing habits to third-party ad networks.</p>
      <br />
      <h3>3. Subscription Terms & Cancellations</h3>
      <p>Subscribers may manage or cancel their VIP subscriptions anytime through their account dashboard with zero hidden penalties.</p>
    `,
  };
}

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { slug } = await params;
  const page = await getPageData(slug);
  const title = `${page.name || page.title || 'Page'} | NOOR Islamic Cinema`;

  return {
    title,
    description: `Read the ${page.name || page.title} policy and guidelines for NOOR Islamic Cinema.`,
    alternates: {
      canonical: `/pages/${slug}`,
    },
  };
}

export default async function StaticPolicyPage({ params }: PageProps) {
  const { slug } = await params;
  const page = await getPageData(slug);

  if (!page) {
    notFound();
  }

  return (
    <div className="min-h-screen pt-28 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto pb-24 text-gray-300 select-none">
      <h1 className="text-3xl sm:text-4xl font-extrabold text-white mb-6 tracking-tight drop-shadow-md">
        {page.name || page.title}
      </h1>
      <div
        className="prose prose-invert max-w-none text-sm leading-relaxed bg-[#0c1218] border border-white/10 p-6 sm:p-10 rounded-3xl shadow-xl"
        dangerouslySetInnerHTML={{ __html: page.description || '' }}
      />
    </div>
  );
}
