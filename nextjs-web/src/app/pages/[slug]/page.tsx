'use client';

import React, { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import { api } from '@/lib/api';

export default function StaticPolicyPage() {
  const params = useParams();
  const slug = params?.slug as string;

  const [pageData, setPageData] = useState<any>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    async function loadPage() {
      if (!slug) return;
      setIsLoading(true);
      try {
        const data = await api.getPage(slug);
        setPageData(data);
      } catch {
        // Fallback policy content
        setPageData({
          name: slug.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
          description: `
            <p>Welcome to Swann OTT. This document outlines our guidelines, privacy protections, and user agreements.</p>
            <br />
            <h3>1. Service Terms</h3>
            <p>By accessing or using our streaming service, you agree to abide by our platform guidelines, copyright protections, and subscription terms.</p>
            <br />
            <h3>2. User Accounts</h3>
            <p>You are responsible for maintaining the confidentiality of your account credentials and personal profile.</p>
          `,
        });
      } finally {
        setIsLoading(false);
      }
    }

    loadPage();
  }, [slug]);

  if (isLoading || !pageData) {
    return (
      <div className="min-h-screen pt-32 max-w-4xl mx-auto px-4 text-center text-gray-400">
        Loading page...
      </div>
    );
  }

  return (
    <div className="min-h-screen pt-28 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto pb-24 text-gray-300">
      <h1 className="text-3xl sm:text-4xl font-extrabold text-white mb-6">
        {pageData.name || pageData.title}
      </h1>
      <div
        className="prose prose-invert max-w-none text-sm leading-relaxed glass-card p-6 sm:p-10 rounded-3xl"
        dangerouslySetInnerHTML={{ __html: pageData.description || '' }}
      />
    </div>
  );
}

