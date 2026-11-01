'use client';

import React, { useEffect, useState, Suspense } from 'react';
import { useSearchParams } from 'next/navigation';
import { api } from '@/lib/api';
import { MediaItem } from '@/types';
import { MediaGrid } from '@/components/shared';
import { EmptyState } from '@/components/ui';
import { Search } from 'lucide-react';

function SearchPageContent() {
  const searchParams = useSearchParams();
  const q = searchParams?.get('q') || '';

  const [query, setQuery] = useState(q);
  const [results, setResults] = useState<MediaItem[]>([]);
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    async function performSearch() {
      if (!q.trim()) {
        setResults([]);
        return;
      }
      setIsLoading(true);
      try {
        const data = await api.search(q);
        if (data && Array.isArray(data)) {
          setResults(data);
        } else if (data?.data && Array.isArray(data.data)) {
          setResults(data.data);
        } else {
          setResults([]);
        }
      } catch {
        setResults([]);
      } finally {
        setIsLoading(false);
      }
    }

    performSearch();
  }, [q]);

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    if (query.trim()) {
      window.history.pushState({}, '', `/search?q=${encodeURIComponent(query.trim())}`);
      const event = new PopStateEvent('popstate');
      window.dispatchEvent(event);
    }
  };

  return (
    <div className="min-h-screen pt-24 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto pb-20">
      <div className="max-w-2xl mx-auto mb-10">
        <form onSubmit={handleSearch} className="relative">
          <input
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search titles, actors, genres..."
            className="w-full bg-white/5 border border-white/20 rounded-2xl px-5 py-4 pl-12 text-white placeholder-gray-400 focus:outline-none focus:border-red-500 shadow-xl text-base"
          />
          <Search className="w-5 h-5 text-gray-400 absolute left-4 top-4.5 pointer-events-none" />
        </form>
      </div>

      <div>
        <h2 className="text-xl font-bold text-white mb-6">
          {q ? `Search results for "${q}"` : 'Type in the search bar above'}
        </h2>

        <MediaGrid
          items={results}
          isLoading={isLoading}
          aspectRatio="backdrop"
          emptyState={
            q ? (
              <EmptyState
                icon={<Search className="w-10 h-10 text-gray-500" />}
                title={`No results found for "${q}"`}
                description="Try searching with different keywords, themes, or historical topics."
              />
            ) : null
          }
        />
      </div>
    </div>
  );
}

export default function SearchPage() {
  return (
    <Suspense fallback={<div className="min-h-screen pt-32 text-center text-white">Loading search...</div>}>
      <SearchPageContent />
    </Suspense>
  );
}

