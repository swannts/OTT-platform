'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { BlogPost } from '@/types';
import { Clock, Calendar, ArrowRight, Sparkles } from 'lucide-react';
import { DEFAULT_FALLBACK_IMAGE } from '@/constants';

interface BlogHeroProps {
  post: BlogPost;
}

export default function BlogHero({ post }: BlogHeroProps) {
  const [imgSrc, setImgSrc] = useState(post.image_url || DEFAULT_FALLBACK_IMAGE);

  const formattedDate = post.published_at
    ? new Date(post.published_at).toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
      })
    : 'Recent Story';

  return (
    <div className="relative rounded-3xl overflow-hidden border border-white/10 group mb-12 shadow-[0_20px_50px_rgba(0,0,0,0.8)]">
      {/* Background Image Container */}
      <div className="relative aspect-[16/9] md:aspect-[21/9] w-full overflow-hidden">
        <img
          src={imgSrc}
          alt={post.title}
          onError={() => setImgSrc(DEFAULT_FALLBACK_IMAGE)}
          className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-[#05080c] via-[#05080c]/60 to-transparent" />
        <div className="absolute inset-0 bg-gradient-to-r from-[#05080c] via-[#05080c]/40 to-transparent hidden md:block" />
      </div>

      {/* Floating Info Overlay */}
      <div className="absolute inset-0 flex flex-col justify-end p-6 sm:p-8 md:p-12 max-w-3xl">
        <div className="space-y-4">
          {/* Badges */}
          <div className="flex flex-wrap items-center gap-2">
            <span className="inline-flex items-center space-x-1.5 px-3 py-1 rounded-full text-xs font-bold bg-[#E5B869] text-black shadow-lg">
              <Sparkles className="w-3 h-3" />
              <span>Featured Story</span>
            </span>
            <span className="px-3 py-1 rounded-full text-xs font-semibold bg-white/10 backdrop-blur-md text-gray-200 border border-white/15">
              {post.category}
            </span>
          </div>

          {/* Heading */}
          <h2 className="text-2xl sm:text-3xl md:text-4xl font-extrabold text-white tracking-tight leading-tight group-hover:text-[#E5B869] transition-colors">
            <Link href={`/blog/${post.slug}`}>{post.title}</Link>
          </h2>

          {/* Excerpt */}
          {post.summary && (
            <p className="text-sm md:text-base text-gray-300 line-clamp-2 md:line-clamp-3 leading-relaxed font-light">
              {post.summary}
            </p>
          )}

          {/* Metadata & CTA */}
          <div className="flex flex-wrap items-center justify-between gap-4 pt-2 border-t border-white/10">
            <div className="flex items-center space-x-3 text-xs text-gray-300">
              {post.author_avatar && (
                <img
                  src={post.author_avatar}
                  alt={post.author_name}
                  className="w-7 h-7 rounded-full object-cover ring-2 ring-[#E5B869]/50"
                />
              )}
              <span className="font-semibold text-white">{post.author_name}</span>
              <span>&bull;</span>
              <span className="flex items-center space-x-1 text-gray-400">
                <Clock className="w-3 h-3 text-[#E5B869]" />
                <span>{post.reading_time} min read</span>
              </span>
              <span>&bull;</span>
              <span className="text-gray-400 hidden sm:inline">{formattedDate}</span>
            </div>

            <Link
              href={`/blog/${post.slug}`}
              className="inline-flex items-center space-x-2 px-5 py-2.5 rounded-full bg-white/10 hover:bg-[#E5B869] text-white hover:text-black font-bold text-xs border border-white/20 hover:border-transparent transition-all shadow-md active:scale-95 cursor-pointer"
            >
              <span>Read Full Story</span>
              <ArrowRight className="w-3.5 h-3.5" />
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}

