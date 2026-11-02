'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { BlogPost } from '@/types';
import BlogCard from './BlogCard';
import {
  ArrowLeft,
  Clock,
  Calendar,
  Eye,
  Share2,
  Copy,
  Check,
  BookOpen,
} from 'lucide-react';
import { DEFAULT_FALLBACK_IMAGE } from '@/constants';
import { formatDhakaDate } from '@/lib/datetime';

interface BlogPostDetailProps {
  post: BlogPost;
  related: BlogPost[];
}

export default function BlogPostDetail({ post, related }: BlogPostDetailProps) {
  const [copied, setCopied] = useState(false);
  const [imgSrc, setImgSrc] = useState(post.image_url || DEFAULT_FALLBACK_IMAGE);

  const formattedDate = post.published_at || post.created_at
    ? formatDhakaDate(post.published_at || post.created_at, 'long')
    : 'Recently Published';

  const handleCopyLink = () => {
    if (typeof window !== 'undefined') {
      navigator.clipboard.writeText(window.location.href);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    }
  };

  // Render paragraphs and markdown headers simply and safely
  const renderContent = (content: string) => {
    const lines = content.split('\n');
    return lines.map((line, idx) => {
      const trimmed = line.trim();
      if (!trimmed) {
        return <div key={idx} className="h-4" />;
      }
      if (trimmed.startsWith('# ')) {
        return (
          <h1 key={idx} className="text-2xl sm:text-3xl font-extrabold text-white mt-8 mb-4">
            {trimmed.replace('# ', '')}
          </h1>
        );
      }
      if (trimmed.startsWith('## ')) {
        return (
          <h2 key={idx} className="text-xl sm:text-2xl font-bold text-[#E5B869] mt-8 mb-3">
            {trimmed.replace('## ', '')}
          </h2>
        );
      }
      if (trimmed.startsWith('### ')) {
        return (
          <h3 key={idx} className="text-lg font-bold text-white mt-6 mb-2">
            {trimmed.replace('### ', '')}
          </h3>
        );
      }
      if (trimmed.startsWith('> ')) {
        return (
          <blockquote
            key={idx}
            className="border-l-4 border-[#E5B869] pl-4 py-2 my-6 italic text-gray-200 bg-white/[0.02] rounded-r-xl"
          >
            {trimmed.replace('> ', '')}
          </blockquote>
        );
      }
      if (trimmed.startsWith('- ') || trimmed.startsWith('* ')) {
        return (
          <li key={idx} className="ml-6 list-disc text-gray-300 my-1 leading-relaxed">
            {trimmed.replace(/^[-*]\s+/, '')}
          </li>
        );
      }
      return (
        <p key={idx} className="text-gray-300 text-base leading-relaxed my-3 font-normal">
          {trimmed}
        </p>
      );
    });
  };

  return (
    <article className="min-h-screen pt-24 pb-20 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto">
      {/* Back button */}
      <div className="mb-8">
        <Link
          href="/blog"
          className="inline-flex items-center space-x-2 text-xs font-semibold text-gray-400 hover:text-[#E5B869] transition-colors"
        >
          <ArrowLeft className="w-4 h-4" />
          <span>Back to All Stories</span>
        </Link>
      </div>

      {/* Header Info */}
      <header className="space-y-4 pb-8 border-b border-white/10">
        <div className="flex flex-wrap items-center gap-3">
          <span className="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-[#E5B869]/20 text-[#E5B869] border border-[#E5B869]/40">
            {post.category}
          </span>
          <span className="flex items-center space-x-1 text-xs text-gray-400">
            <Clock className="w-3.5 h-3.5 text-[#E5B869]" />
            <span>{post.reading_time} min read</span>
          </span>
          <span className="text-gray-600">&bull;</span>
          <span className="flex items-center space-x-1 text-xs text-gray-400">
            <Calendar className="w-3.5 h-3.5" />
            <span>{formattedDate}</span>
          </span>
          {post.views_count > 0 && (
            <>
              <span className="text-gray-600">&bull;</span>
              <span className="flex items-center space-x-1 text-xs text-gray-400">
                <Eye className="w-3.5 h-3.5" />
                <span>{post.views_count} views</span>
              </span>
            </>
          )}
        </div>

        <h1 className="text-3xl sm:text-4xl md:text-5xl font-extrabold text-white tracking-tight leading-tight">
          {post.title}
        </h1>

        {post.summary && (
          <p className="text-base sm:text-lg text-gray-300 leading-relaxed font-light">
            {post.summary}
          </p>
        )}

        {/* Author & Share Bar */}
        <div className="flex flex-wrap items-center justify-between gap-4 pt-4">
          <div className="flex items-center space-x-3">
            {post.author_avatar ? (
              <img
                src={post.author_avatar}
                alt={post.author_name}
                className="w-10 h-10 rounded-full object-cover ring-2 ring-[#E5B869]/50"
              />
            ) : (
              <div className="w-10 h-10 rounded-full bg-[#E5B869]/20 text-[#E5B869] font-bold text-sm flex items-center justify-center">
                {post.author_name.charAt(0)}
              </div>
            )}
            <div>
              <p className="text-sm font-bold text-white leading-tight">{post.author_name}</p>
              <p className="text-xs text-gray-400">NOOR Editorial Contributor</p>
            </div>
          </div>

          {/* Social Share Buttons */}
          <div className="flex items-center space-x-2">
            <button
              type="button"
              onClick={handleCopyLink}
              className="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-full bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-medium text-gray-300 hover:text-white transition-colors cursor-pointer"
              title="Copy Article Link"
            >
              {copied ? <Check className="w-3.5 h-3.5 text-emerald-400" /> : <Copy className="w-3.5 h-3.5" />}
              <span>{copied ? 'Copied!' : 'Copy Link'}</span>
            </button>
            <a
              href={`https://twitter.com/intent/tweet?text=${encodeURIComponent(post.title)}&url=${encodeURIComponent(typeof window !== 'undefined' ? window.location.href : '')}`}
              target="_blank"
              rel="noopener noreferrer"
              className="w-8 h-8 rounded-full flex items-center justify-center bg-white/5 hover:bg-white/10 border border-white/10 text-gray-300 hover:text-[#E5B869] transition-colors"
              aria-label="Share on X"
            >
              <svg className="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
              </svg>
            </a>
          </div>
        </div>
      </header>

      {/* Hero Image */}
      <div className="my-8 rounded-3xl overflow-hidden border border-white/10 shadow-2xl">
        <img
          src={imgSrc}
          alt={post.title}
          onError={() => setImgSrc(DEFAULT_FALLBACK_IMAGE)}
          className="w-full aspect-[16/9] object-cover"
        />
      </div>

      {/* Main Content */}
      <div className="prose prose-invert max-w-none mb-12">
        {renderContent(post.content)}
      </div>

      {/* Tags */}
      {post.tags && post.tags.length > 0 && (
        <div className="flex flex-wrap items-center gap-2 pt-6 pb-8 border-t border-white/10">
          <span className="text-xs text-gray-400 font-semibold mr-1">Tags:</span>
          {post.tags.map((tag) => (
            <span
              key={tag}
              className="px-3 py-1 rounded-full text-xs font-medium bg-white/5 border border-white/10 text-gray-300"
            >
              #{tag}
            </span>
          ))}
        </div>
      )}

      {/* Author Bio Box */}
      <div className="p-6 rounded-3xl bg-white/[0.02] border border-white/10 flex items-start space-x-4 mb-16">
        {post.author_avatar ? (
          <img
            src={post.author_avatar}
            alt={post.author_name}
            className="w-14 h-14 rounded-full object-cover ring-2 ring-[#E5B869]/40 flex-shrink-0"
          />
        ) : (
          <div className="w-14 h-14 rounded-full bg-[#E5B869]/20 text-[#E5B869] font-bold text-lg flex items-center justify-center flex-shrink-0">
            {post.author_name.charAt(0)}
          </div>
        )}
        <div className="space-y-1">
          <h4 className="text-base font-bold text-white">{post.author_name}</h4>
          <p className="text-xs text-[#E5B869]">Senior Scholar & Cinema Historian</p>
          <p className="text-xs text-gray-400 leading-relaxed pt-1">
            Writing regularly on Islamic cinematography, sacred history, and creative values for NOOR Cinema. Dedicated to uplifting family entertainment globally.
          </p>
        </div>
      </div>

      {/* Related Stories */}
      {related.length > 0 && (
        <section className="pt-10 border-t border-white/10">
          <div className="flex items-center space-x-2 mb-6">
            <BookOpen className="w-5 h-5 text-[#E5B869]" />
            <h2 className="text-xl font-bold text-white">Related Stories</h2>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
            {related.map((rel) => (
              <BlogCard key={rel.id} post={rel} />
            ))}
          </div>
        </section>
      )}
    </article>
  );
}
