'use client';

import React, { useEffect } from 'react';
import { useAppDispatch, useAppSelector } from '@/store/hooks';
import {
  setSelectedChannel,
  setInitialLiveTvData,
} from '@/store/slices/liveTvSlice';
import VideoPlayer from '@/components/player/VideoPlayer';
import { Radio, Tv } from 'lucide-react';
import { LiveChannel } from '@/types';

interface LiveTvClientProps {
  initialChannels: LiveChannel[];
}

export default function LiveTvClient({ initialChannels }: LiveTvClientProps) {
  const dispatch = useAppDispatch();
  const { channels: reduxChannels, selectedChannel } = useAppSelector(
    (state) => state.liveTv
  );

  useEffect(() => {
    if (initialChannels.length > 0) {
      dispatch(setInitialLiveTvData(initialChannels));
    }
  }, [dispatch, initialChannels]);

  const channels = reduxChannels.length > 0 ? reduxChannels : initialChannels;
  const activeChannel = selectedChannel || channels[0] || null;

  return (
    <div className="min-h-screen pt-24 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto pb-20 select-none">
      <div className="flex items-center space-x-3 mb-6">
        <div className="w-10 h-10 rounded-xl bg-[#E5B869]/20 text-[#E5B869] flex items-center justify-center">
          <Radio className="w-5 h-5 animate-pulse" />
        </div>
        <div>
          <h1 className="text-2xl sm:text-3xl font-extrabold text-white">Live Islamic Television</h1>
          <p className="text-xs sm:text-sm text-gray-400">
            24/7 direct sacred broadcasts from Makkah Al-Mukarramah, Madinah Al-Munawwarah, and educational Islamic networks.
          </p>
        </div>
      </div>

      {/* Main Grid: Player on left, Channel Selector on right */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Live Stream Player */}
        <div className="lg:col-span-2 space-y-4">
          {activeChannel ? (
            <div>
              <VideoPlayer
                contentId={activeChannel.id}
                contentType="livetv"
                title={activeChannel.name}
                videoUrl={activeChannel.server_url || activeChannel.server_url1}
                urlType={activeChannel.stream_type || 'HLS'}
                hasAccess={activeChannel.has_content_access !== false}
                poster={activeChannel.poster_image}
              />
              <div className="mt-4 bg-[#0c1218] border border-white/10 p-5 rounded-2xl space-y-2 shadow-lg">
                <div className="flex items-center justify-between">
                  <h2 className="text-xl font-bold text-white flex items-center space-x-2">
                    <span className="w-2.5 h-2.5 rounded-full bg-red-500 animate-ping inline-block mr-2" />
                    <span>{activeChannel.name}</span>
                  </h2>
                  {activeChannel.category && (
                    <span className="text-xs bg-white/10 text-gray-300 px-3 py-1 rounded-full font-medium border border-white/5">
                      {activeChannel.category}
                    </span>
                  )}
                </div>
                {activeChannel.description && (
                  <p className="text-xs sm:text-sm text-gray-300 leading-relaxed">
                    {activeChannel.description}
                  </p>
                )}
              </div>
            </div>
          ) : (
            <div className="aspect-video bg-[#0c1218] border border-white/10 rounded-2xl flex items-center justify-center text-gray-400">
              Select a channel to start streaming
            </div>
          )}
        </div>

        {/* Live Channel Guide Rail */}
        <div className="space-y-4">
          <h3 className="text-sm font-bold text-gray-300 uppercase tracking-wider flex items-center space-x-2">
            <Tv className="w-4 h-4 text-[#E5B869]" />
            <span>Channel Guide ({channels.length})</span>
          </h3>

          <div className="space-y-3 max-h-[70vh] overflow-y-auto pr-1">
            {channels.map((channel) => {
              const isSelected = activeChannel?.id === channel.id;
              return (
                <button
                  key={channel.id}
                  type="button"
                  onClick={() => dispatch(setSelectedChannel(channel))}
                  className={`w-full text-left p-3 rounded-xl transition-all flex items-center space-x-3.5 border cursor-pointer ${
                    isSelected
                      ? 'bg-[#E5B869]/15 border-[#E5B869]/50 shadow-md'
                      : 'bg-[#0c1218] hover:bg-white/5 border-white/[0.08]'
                  }`}
                >
                  <img
                    src={channel.poster_image || channel.thumbnail_image || 'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=100&auto=format&fit=crop&q=80'}
                    alt={channel.name}
                    className="w-14 h-14 rounded-lg object-cover flex-shrink-0 bg-neutral-900 border border-white/10"
                  />
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center space-x-1.5">
                      <span className="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse" />
                      <span className="text-[10px] text-red-400 font-bold uppercase tracking-wider">
                        Live
                      </span>
                    </div>
                    <h4 className="text-sm font-semibold text-white truncate mt-0.5">
                      {channel.name}
                    </h4>
                    {channel.category && (
                      <span className="text-[10px] text-gray-400 truncate block">
                        {channel.category}
                      </span>
                    )}
                  </div>
                </button>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
}

