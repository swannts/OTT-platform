'use client';

import React, { useState, useMemo } from 'react';
import { BlogPost, BlogCategory } from '@/types';
import BlogCard from './BlogCard';
import { Search, X, BookOpen, Filter } from 'lucide-react';

interface BlogGridProps {
  initialPosts: BlogPost[];
  categories: BlogCategory[];
}

export default function BlogGrid({ initialPosts, categories }: BlogGridProps) {
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState<string>('');

  // Combine categories into list
  const categoryList = useMemo(() => {
    return ['all', ...categories.map((c) => c.category)];
  }, [categories]);

  // Filter posts
  const filteredPosts = useMemo(() => {
    return initialPosts.filter((post) => {
      const matchesCategory =
        selectedCategory === 'all' ||
        post.category.toLowerCase() === selectedCategory.toLowerCase();

      const term = searchQuery.toLowerCase().trim();
      const matchesSearch =
        !term ||
        post.title.toLowerCase().includes(term) ||
        (post.summary && post.summary.toLowerCase().includes(term)) ||
        post.category.toLowerCase().includes(term) ||
        post.author_name.toLowerCase().includes(term) ||
        (post.tags && post.tags.some((t) => t.toLowerCase().includes(term)));

      return matchesCategory && matchesSearch;
    });
  }, [initialPosts, selectedCategory, searchQuery]);

  return (
    <div className="space-y-8">
      {/* Controls: Search and Categories */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-white/10">
        {/* Category Pills */}
        <div className="flex items-center space-x-2 overflow-x-auto pb-2 md:pb-0 scrollbar-none">
          {categoryList.map((cat) => {
            const isSelected = selectedCategory === cat;
            const label = cat === 'all' ? 'All Stories' : cat;

            return (
              <button
                key={cat}
                type="button"
                onClick={() => setSelectedCategory(cat)}
                className={`px-4 py-2 rounded-full text-xs font-semibold whitespace-nowrap transition-all duration-200 cursor-pointer active:scale-95 ${
                  isSelected
                    ? 'bg-[#E5B869] text-black shadow-[0_0_15px_rgba(229,184,105,0.4)]'
                    : 'bg-white/5 hover:bg-white/10 text-gray-300 hover:text-white border border-white/5'
                }`}
              >
                {label}
              </button>
            );
          })}
        </div>

        {/* Search Bar */}
        <div className="relative w-full md:w-72">
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Search articles, topics..."
            className="w-full bg-white/[0.06] border border-white/10 focus:border-[#E5B869]/60 text-white placeholder-gray-400 text-xs rounded-full pl-9 pr-8 py-2.5 focus:outline-none transition-all"
          />
          <Search className="w-3.5 h-3.5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
          {searchQuery && (
            <button
              type="button"
              onClick={() => setSearchQuery('')}
              className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white cursor-pointer"
              aria-label="Clear search"
            >
              <X className="w-3 h-3" />
            </button>
          )}
        </div>
      </div>

      {/* Grid of Articles */}
      {filteredPosts.length > 0 ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
          {filteredPosts.map((post) => (
            <BlogCard key={post.id} post={post} />
          ))}
        </div>
      ) : (
        /* Empty State */
        <div className="text-center py-20 bg-white/[0.02] border border-white/5 rounded-3xl p-8 space-y-4">
          <BookOpen className="w-12 h-12 text-gray-600 mx-auto" />
          <h3 className="text-lg font-bold text-white">No articles found</h3>
          <p className="text-xs text-gray-400 max-w-sm mx-auto">
            We couldn't find any stories matching your filters. Try selecting a different category or clearing your search.
          </p>
          <button
            type="button"
            onClick={() => {
              setSelectedCategory('all');
              setSearchQuery('');
            }}
            className="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white text-xs font-semibold transition-colors cursor-pointer"
          >
            Reset All Filters
          </button>
        </div>
      )}
    </div>
  );
}
