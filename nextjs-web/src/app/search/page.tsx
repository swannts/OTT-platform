'use client';

import React, { useEffect, useState, Suspense } from 'react';
import { useSearchParams } from 'next/navigation';
import { api } from '@/lib/api';
import { MediaItem } from '@/types';
import MediaCard from '@/components/media/MediaCard';
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

        {isLoading ? (
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
            {[...Array(5)].map((_, i) => (
              <div key={i} className="aspect-[2/3] bg-white/5 rounded-xl animate-pulse" />
            ))}
          </div>
        ) : results.length > 0 ? (
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 sm:gap-6">
            {results.map((item) => (
              <MediaCard key={item.id} item={item} />
            ))}
          </div>
        ) : q ? (
          <div className="text-center py-20 text-gray-400">
            <p>No results found for &ldquo;{q}&rdquo;. Try another keyword or genre.</p>
          </div>
        ) : null}
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

