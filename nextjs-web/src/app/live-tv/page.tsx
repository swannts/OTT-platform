'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { LiveChannel } from '@/types';
import VideoPlayer from '@/components/player/VideoPlayer';
import { Radio, Tv, Sparkles } from 'lucide-react';

export default function LiveTvPage() {
  const [channels, setChannels] = useState<LiveChannel[]>([]);
  const [selectedChannel, setSelectedChannel] = useState<LiveChannel | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    async function loadLiveTv() {
      setIsLoading(true);
      try {
        const data = await api.getLiveTvDashboard();
        if (data?.slider && Array.isArray(data.slider) && data.slider.length > 0) {
          setChannels(data.slider);
          setSelectedChannel(data.slider[0]);
        }
      } catch {
        // Fallback demo channels
        const fallbackChannels: LiveChannel[] = [
          {
            id: 1,
            name: 'Swann Sports 1 HD',
            category: 'Sports',
            description: '24/7 Live Premier League, Champions League, and World Championship coverage.',
            poster_image: 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?w=500&auto=format&fit=crop&q=80',
            stream_type: 'HLS',
            server_url: 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
            has_content_access: true,
          },
          {
            id: 2,
            name: 'Global News 24',
            category: 'News',
            description: 'Breaking global headlines, live reports, financial analysis, and weather radar.',
            poster_image: 'https://images.unsplash.com/photo-1585829365295-ab7cd400c167?w=500&auto=format&fit=crop&q=80',
            stream_type: 'HLS',
            server_url: 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
            has_content_access: true,
          },
          {
            id: 3,
            name: 'Cinema World Premiere',
            category: 'Movies',
            description: 'Non-stop Hollywood blockbusters, award-winning indie cinema, and classic cinema.',
            poster_image: 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?w=500&auto=format&fit=crop&q=80',
            stream_type: 'HLS',
            server_url: 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
            has_content_access: true,
          },
        ];
        setChannels(fallbackChannels);
        setSelectedChannel(fallbackChannels[0]);
      } finally {
        setIsLoading(false);
      }
    }

    loadLiveTv();
  }, []);

  return (
    <div className="min-h-screen pt-24 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto pb-20">
      <div className="flex items-center space-x-3 mb-6">
        <div className="w-10 h-10 rounded-xl bg-red-600/20 text-red-500 flex items-center justify-center">
          <Radio className="w-5 h-5 animate-pulse" />
        </div>
        <div>
          <h1 className="text-2xl sm:text-3xl font-extrabold text-white">Live Television</h1>
          <p className="text-xs sm:text-sm text-gray-400">
            Real-time live streaming channels, news broadcasts, and sports networks.
          </p>
        </div>
      </div>

      {/* Main Grid: Player on left, Channel Selector on right */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Live Stream Player */}
        <div className="lg:col-span-2 space-y-4">
          {selectedChannel ? (
            <div>
              <VideoPlayer
                contentId={selectedChannel.id}
                contentType="livetv"
                title={selectedChannel.name}
                videoUrl={selectedChannel.server_url || selectedChannel.server_url1}
                urlType={selectedChannel.stream_type || 'HLS'}
                hasAccess={selectedChannel.has_content_access !== false}
                poster={selectedChannel.poster_image}
              />
              <div className="mt-4 glass-card p-5 rounded-2xl space-y-2">
                <div className="flex items-center justify-between">
                  <h2 className="text-xl font-bold text-white flex items-center space-x-2">
                    <span className="w-2.5 h-2.5 rounded-full bg-red-500 animate-ping inline-block mr-2" />
                    <span>{selectedChannel.name}</span>
                  </h2>
                  {selectedChannel.category && (
                    <span className="text-xs bg-white/10 text-gray-300 px-3 py-1 rounded-full font-medium">
                      {selectedChannel.category}
                    </span>
                  )}
                </div>
                {selectedChannel.description && (
                  <p className="text-xs sm:text-sm text-gray-300 leading-relaxed">
                    {selectedChannel.description}
                  </p>
                )}
              </div>
            </div>
          ) : (
            <div className="aspect-video bg-white/5 rounded-2xl flex items-center justify-center text-gray-400">
              Select a channel to start streaming
            </div>
          )}
        </div>

        {/* Live Channel Guide Rail */}
        <div className="space-y-4">
          <h3 className="text-sm font-bold text-gray-300 uppercase tracking-wider flex items-center space-x-2">
            <Tv className="w-4 h-4 text-red-500" />
            <span>Channel Guide ({channels.length})</span>
          </h3>

          <div className="space-y-3 max-h-[70vh] overflow-y-auto pr-1">
            {channels.map((channel) => {
              const isSelected = selectedChannel?.id === channel.id;
              return (
                <button
                  key={channel.id}
                  onClick={() => setSelectedChannel(channel)}
                  className={`w-full text-left p-3 rounded-xl transition-all flex items-center space-x-3.5 border ${
                    isSelected
                      ? 'bg-red-600/20 border-red-500/50 shadow-lg'
                      : 'glass-card hover:bg-white/10 border-white/5'
                  }`}
                >
                  <img
                    src={channel.poster_image || channel.thumbnail_image || 'https://dummyimage.com/100x100/1a1a1a/fff'}
                    alt={channel.name}
                    className="w-14 h-14 rounded-lg object-cover flex-shrink-0 bg-neutral-900 border border-white/10"
                  />
                  <div className="flex-1 min-w-0">
                    <h4 className={`text-sm font-bold truncate ${isSelected ? 'text-red-400' : 'text-white'}`}>
                      {channel.name}
                    </h4>
                    <p className="text-xs text-gray-400 mt-0.5 truncate">
                      {channel.category || 'Live Broadcast'}
                    </p>
                  </div>
                  {isSelected && (
                    <span className="w-2 h-2 rounded-full bg-red-500 animate-pulse flex-shrink-0" />
                  )}
                </button>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
}

