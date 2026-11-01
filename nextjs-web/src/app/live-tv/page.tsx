'use client';

import React, { useEffect } from 'react';
import { useAppDispatch, useAppSelector } from '@/store/hooks';
import {
  fetchLiveTvDashboard,
  setSelectedChannel,
} from '@/store/slices/liveTvSlice';
import VideoPlayer from '@/components/player/VideoPlayer';
import { Radio, Tv, Sparkles } from 'lucide-react';

export default function LiveTvPage() {
  const dispatch = useAppDispatch();
  const { channels, selectedChannel, isLoading } = useAppSelector(
    (state) => state.liveTv
  );

  useEffect(() => {
    dispatch(fetchLiveTvDashboard());
  }, [dispatch]);

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
                  onClick={() => dispatch(setSelectedChannel(channel))}
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

