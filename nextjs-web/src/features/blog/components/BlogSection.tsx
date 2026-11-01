'use client';

import React from 'react';
import { BlogPost, BlogCategory } from '@/types';
import BlogHero from './BlogHero';
import BlogGrid from './BlogGrid';
import { BookOpen, Sparkles } from 'lucide-react';

interface BlogSectionProps {
  initialPosts: BlogPost[];
  categories: BlogCategory[];
}

export default function BlogSection({ initialPosts, categories }: BlogSectionProps) {
  // Find top featured post
  const featuredPost = initialPosts.find((p) => p.is_featured) || initialPosts[0];
  const regularPosts = initialPosts.filter((p) => p.id !== featuredPost?.id);

  return (
    <div className="min-h-screen pt-24 pb-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
      {/* Page Header */}
      <div className="pb-8 mb-6 border-b border-white/10 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
          <div className="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-[#E5B869]/10 border border-[#E5B869]/30 text-[#E5B869] text-xs font-bold mb-3">
            <Sparkles className="w-3.5 h-3.5" />
            <span>NOOR Journal & Insights</span>
          </div>
          <h1 className="text-3xl sm:text-4xl md:text-5xl font-extrabold text-white tracking-tight">
            Stories, History & Sacred Cinema
          </h1>
          <p className="text-gray-400 text-sm sm:text-base mt-2 max-w-2xl leading-relaxed">
            Delve into insightful essays on Islamic history, cinematic craft, prophetic heritage, and family education written by leading scholars and creators.
          </p>
        </div>
      </div>

      {/* Hero Featured Article */}
      {featuredPost && <BlogHero post={featuredPost} />}

      {/* Grid of Remaining Stories */}
      <div>
        <div className="flex items-center space-x-2 mb-6">
          <BookOpen className="w-5 h-5 text-[#E5B869]" />
          <h2 className="text-xl font-bold text-white tracking-tight">Latest Articles & Chronicles</h2>
        </div>
        <BlogGrid initialPosts={regularPosts.length > 0 ? regularPosts : initialPosts} categories={categories} />
      </div>
    </div>
  );
}
