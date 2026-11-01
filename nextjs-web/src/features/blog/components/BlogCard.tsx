'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { BlogPost } from '@/types';
import { Clock, Eye, Calendar, ArrowRight } from 'lucide-react';
import { DEFAULT_FALLBACK_IMAGE } from '@/constants';

interface BlogCardProps {
  post: BlogPost;
}

export default function BlogCard({ post }: BlogCardProps) {
  const [imgSrc, setImgSrc] = useState(post.image_url || DEFAULT_FALLBACK_IMAGE);

  const formattedDate = post.published_at
    ? new Date(post.published_at).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
      })
    : 'Recent';

  return (
    <article className="group flex flex-col bg-white/[0.02] hover:bg-white/[0.05] border border-white/10 hover:border-[#E5B869]/50 rounded-2xl overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_12px_30px_rgba(0,0,0,0.6)]">
      {/* Thumbnail */}
      <Link href={`/blog/${post.slug}`} className="relative aspect-[16/10] overflow-hidden block">
        <img
          src={imgSrc}
          alt={post.title}
          onError={() => setImgSrc(DEFAULT_FALLBACK_IMAGE)}
          className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-[#05080c] via-transparent to-transparent opacity-60 group-hover:opacity-40 transition-opacity" />

        {/* Category Badge */}
        <span className="absolute top-3 left-3 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-black/70 backdrop-blur-md text-[#E5B869] border border-[#E5B869]/40 shadow-sm">
          {post.category}
        </span>
      </Link>

      {/* Content */}
      <div className="flex flex-col flex-1 p-5 space-y-3">
        {/* Meta Bar */}
        <div className="flex items-center space-x-3 text-[11px] text-gray-400">
          <span className="flex items-center space-x-1">
            <Clock className="w-3 h-3 text-[#E5B869]" />
            <span>{post.reading_time} min read</span>
          </span>
          <span>&bull;</span>
          <span className="flex items-center space-x-1">
            <Calendar className="w-3 h-3 text-gray-500" />
            <span>{formattedDate}</span>
          </span>
        </div>

        {/* Title */}
        <h3 className="text-base sm:text-lg font-bold text-white group-hover:text-[#E5B869] transition-colors line-clamp-2 leading-snug">
          <Link href={`/blog/${post.slug}`}>{post.title}</Link>
        </h3>

        {/* Excerpt */}
        {post.summary && (
          <p className="text-xs text-gray-400 line-clamp-2 leading-relaxed flex-1">
            {post.summary}
          </p>
        )}

        {/* Author & Read More */}
        <div className="flex items-center justify-between pt-3 border-t border-white/5 mt-auto">
          <div className="flex items-center space-x-2 min-w-0">
            {post.author_avatar ? (
              <img
                src={post.author_avatar}
                alt={post.author_name}
                className="w-6 h-6 rounded-full object-cover ring-1 ring-white/20 flex-shrink-0"
              />
            ) : (
              <div className="w-6 h-6 rounded-full bg-[#E5B869]/20 text-[#E5B869] text-[10px] font-bold flex items-center justify-center flex-shrink-0">
                {post.author_name.charAt(0)}
              </div>
            )}
            <span className="text-xs text-gray-300 font-medium truncate">{post.author_name}</span>
          </div>

          <Link
            href={`/blog/${post.slug}`}
            className="inline-flex items-center space-x-1 text-xs font-semibold text-[#E5B869] group-hover:translate-x-0.5 transition-transform"
          >
            <span>Read</span>
            <ArrowRight className="w-3 h-3" />
          </Link>
        </div>
      </div>
    </article>
  );
}
