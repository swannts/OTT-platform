'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { ContentDetails, EpisodeItem } from '@/types';
import {
  Play,
  Star,
  Calendar,
  Tv,
  Plus,
  Check,
  Clock,
  Share2,
  ShieldCheck,
  Layers,
  Sparkles,
  ChevronRight,
  Info,
} from 'lucide-react';
import { useAuth } from '@/context/AuthContext';
import { api } from '@/lib/api';
import { ReviewSection } from '@/components/shared';
import { SafeImage } from '@/components/ui';
import { DEFAULT_FALLBACK_IMAGE } from '@/constants';

interface TvShowDetailSectionProps {
  initialShow: ContentDetails;
  initialEpisodes: EpisodeItem[];
}

export default function TvShowDetailSection({
  initialShow,
  initialEpisodes,
}: TvShowDetailSectionProps) {
  const { user } = useAuth();
  const [show] = useState<ContentDetails>(initialShow);
  const [episodes] = useState<EpisodeItem[]>(initialEpisodes);
  const [inWatchlist, setInWatchlist] = useState<boolean>(initialShow.is_watchlist || false);
  const [isUpdatingWatchlist, setIsUpdatingWatchlist] = useState(false);
  const [copiedShare, setCopiedShare] = useState(false);
  const [selectedSeason, setSelectedSeason] = useState<number>(1);

  const backdropUrl =
    (show.banner_image && show.banner_image.trim()) ||
    (show.poster_image && show.poster_image.trim()) ||
    DEFAULT_FALLBACK_IMAGE;

  const firstEpId = episodes[0]?.id;

  const handleToggleWatchlist = async () => {
    if (!user) {
      setInWatchlist(!inWatchlist);
      return;
    }
    setIsUpdatingWatchlist(true);
    try {
      if (inWatchlist) {
        await api.removeWatchlist(show.id);
        setInWatchlist(false);
      } else {
        await api.saveWatchlist(show.id, 'tvshow');
        setInWatchlist(true);
      }
    } catch (e) {
      console.error('Failed to toggle watchlist:', e);
      setInWatchlist(!inWatchlist);
    } finally {
      setIsUpdatingWatchlist(false);
    }
  };

  const handleShare = async () => {
    try {
      if (typeof navigator !== 'undefined' && navigator.clipboard) {
        await navigator.clipboard.writeText(window.location.href);
        setCopiedShare(true);
        setTimeout(() => setCopiedShare(false), 2500);
      }
    } catch {
      // Fallback ignore
    }
  };

  const genres = Array.isArray(show.genre_data)
    ? show.genre_data
    : typeof show.genre_data === 'string'
      ? (show.genre_data as string).split(',').map((g) => g.trim())
      : ['Islamic History', 'Docuseries'];

  return (
    <div className="min-h-screen pb-24 select-none bg-[#070a0e] text-white">
      {/* 1. Immersive Cinema Hero Banner */}
      <div className="relative w-full min-h-[64vh] sm:min-h-[74vh] lg:min-h-[80vh] flex items-end overflow-hidden pb-12 sm:pb-16 pt-24 sm:pt-28">
        {/* Dynamic Backdrop */}
        <div className="absolute inset-0 z-0">
          <img
            src={backdropUrl}
            alt={show.name}
            className="w-full h-full object-cover object-center filter brightness-[0.75] contrast-[1.05] transform scale-[1.01] transition-transform duration-1000"
          />
          {/* Multi-layered cinematic gradients */}
          <div className="absolute inset-0 bg-gradient-to-t from-[#070a0e] via-[#070a0e]/75 to-transparent" />
          <div className="absolute inset-0 bg-gradient-to-r from-[#070a0e] via-[#070a0e]/90 to-transparent w-full md:w-4/5 lg:w-3/5" />
          <div className="absolute inset-0 bg-radial-gradient from-transparent via-[#070a0e]/40 to-[#070a0e] pointer-events-none" />
        </div>

        {/* Content Details */}
        <div className="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
          <div className="max-w-3xl space-y-4 sm:space-y-5">
            {/* Badges Row */}
            <div className="flex flex-wrap items-center gap-2 sm:gap-2.5 text-xs font-semibold">
              <span className="inline-flex items-center space-x-1.5 bg-[#E5B869] text-black px-3 py-1 rounded-full font-extrabold uppercase tracking-wider text-[11px] shadow-lg shadow-[#E5B869]/20">
                <Sparkles className="w-3.5 h-3.5 fill-black" />
                <span>Featured Islamic Series</span>
              </span>

              {show.imdb_rating && (
                <div className="flex items-center text-[#E5B869] bg-black/70 backdrop-blur-md px-2.5 py-1 rounded-full border border-[#E5B869]/30 shadow-sm">
                  <Star className="w-3.5 h-3.5 fill-current mr-1 text-[#E5B869]" />
                  <span className="font-bold">{show.imdb_rating}</span>
                  <span className="text-gray-400 text-[10px] ml-1">IMDb</span>
                </div>
              )}

              {show.release_year && (
                <div className="flex items-center text-gray-300 bg-white/10 backdrop-blur-md px-2.5 py-1 rounded-full border border-white/10">
                  <Calendar className="w-3.5 h-3.5 mr-1 text-gray-400" />
                  <span>{show.release_year}</span>
                </div>
              )}

              <div className="flex items-center text-emerald-400 bg-emerald-950/60 backdrop-blur-md px-2.5 py-1 rounded-full border border-emerald-500/30 text-[11px] font-semibold">
                <ShieldCheck className="w-3.5 h-3.5 mr-1" />
                <span>100% Halal Verified</span>
              </div>

              <div className="flex items-center text-gray-300 bg-white/10 backdrop-blur-md px-2.5 py-1 rounded-full border border-white/10 text-[11px]">
                <Layers className="w-3.5 h-3.5 mr-1 text-[#E5B869]" />
                <span>Season 1 • {episodes.length} Episodes</span>
              </div>
            </div>

            {/* Title */}
            <h1 className="font-serif-title text-3xl sm:text-5xl lg:text-6xl font-black tracking-tight text-white leading-[1.1] drop-shadow-xl">
              {show.name}
            </h1>

            {/* Genres */}
            <div className="flex flex-wrap gap-1.5 sm:gap-2">
              {genres.map((g, idx) => (
                <span
                  key={idx}
                  className="text-xs text-gray-300 bg-white/5 border border-white/10 px-3 py-1 rounded-lg backdrop-blur-sm"
                >
                  {g}
                </span>
              ))}
              <span className="text-xs text-gray-400 bg-white/5 border border-white/10 px-3 py-1 rounded-lg backdrop-blur-sm">
                English / Arabic Audio
              </span>
            </div>

            {/* Synopsis */}
            <p className="text-sm sm:text-base text-gray-200 leading-relaxed font-normal max-w-2xl drop-shadow">
              {show.description}
            </p>

            {/* Primary Action Buttons */}
            <div className="flex flex-wrap items-center gap-3 pt-2">
              {firstEpId && (
                <Link
                  href={`/watch/episode/${firstEpId}`}
                  className="inline-flex items-center space-x-2.5 bg-[#E5B869] hover:bg-[#f3cf8a] text-black font-extrabold px-8 py-3.5 rounded-full shadow-xl shadow-[#E5B869]/25 transition-all transform hover:scale-[1.02] active:scale-95 cursor-pointer text-sm sm:text-base"
                >
                  <Play className="w-5 h-5 fill-black" />
                  <span>Play Episode 1</span>
                </Link>
              )}

              <button
                type="button"
                onClick={handleToggleWatchlist}
                disabled={isUpdatingWatchlist}
                className="inline-flex items-center space-x-2 bg-white/10 hover:bg-white/20 text-white px-6 py-3.5 rounded-full border border-white/20 backdrop-blur-md transition-all text-sm font-semibold cursor-pointer active:scale-95 shadow-md"
              >
                {inWatchlist ? (
                  <Check className="w-4 h-4 text-[#E5B869]" />
                ) : (
                  <Plus className="w-4 h-4" />
                )}
                <span>{inWatchlist ? 'In Watchlist' : 'Add to Watchlist'}</span>
              </button>

              <button
                type="button"
                onClick={handleShare}
                className="inline-flex items-center space-x-2 bg-white/5 hover:bg-white/10 text-gray-300 hover:text-white px-5 py-3.5 rounded-full border border-white/10 backdrop-blur-md transition-all text-sm font-medium cursor-pointer active:scale-95"
                title="Share series link"
              >
                <Share2 className="w-4 h-4" />
                <span>{copiedShare ? 'Link Copied!' : 'Share'}</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* 2. Main Content: Season & Episodes Showcase */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 sm:mt-12 space-y-12">
        {/* Season Selector & Episodes Header */}
        <div className="border-b border-white/10 pb-6 space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div className="flex items-center space-x-3">
              <div className="w-10 h-10 rounded-xl bg-[#E5B869]/15 border border-[#E5B869]/30 flex items-center justify-center text-[#E5B869]">
                <Tv className="w-5 h-5" />
              </div>
              <div>
                <h2 className="text-xl sm:text-2xl font-black text-white tracking-tight">
                  Episodes & Chapters
                </h2>
                <p className="text-xs text-gray-400">
                  Select an episode to view synopsis, duration, and begin instant streaming
                </p>
              </div>
            </div>

            <div className="flex items-center space-x-2">
              <span className="text-xs font-semibold text-[#E5B869] bg-[#E5B869]/10 border border-[#E5B869]/30 px-3.5 py-1.5 rounded-full">
                {episodes.length} Episodes Available
              </span>
            </div>
          </div>

          {/* Season Tabs Navigation */}
          <div className="flex items-center space-x-2 pt-2">
            <button
              type="button"
              onClick={() => setSelectedSeason(1)}
              className={`px-5 py-2 rounded-full text-xs font-bold transition-all ${
                selectedSeason === 1
                  ? 'bg-[#E5B869] text-black shadow-lg shadow-[#E5B869]/20'
                  : 'bg-white/5 text-gray-400 hover:text-white hover:bg-white/10 border border-white/5'
              }`}
            >
              Season 1
            </button>
          </div>
        </div>

        {/* 3. Cinema-Grade Episode Cards List */}
        {episodes.length === 0 ? (
          <div className="py-16 text-center text-gray-400 text-sm bg-white/[0.02] border border-white/5 rounded-3xl p-8">
            <Info className="w-8 h-8 text-gray-500 mx-auto mb-3" />
            <p className="text-white font-semibold">No episodes currently found.</p>
            <p className="text-xs text-gray-400 mt-1">Episodes are being prepared for release.</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-4 sm:gap-6">
            {episodes.map((ep, idx) => {
              const epNum = typeof ep.episode_number === 'number' ? ep.episode_number : idx + 1;
              const epThumb =
                (ep.poster_image && ep.poster_image.trim()) ||
                backdropUrl ||
                DEFAULT_FALLBACK_IMAGE;

              return (
                <Link
                  key={ep.id || idx}
                  href={`/watch/episode/${ep.id || epNum}`}
                  className="group relative flex flex-col md:flex-row items-stretch gap-5 sm:gap-6 p-4 sm:p-5 rounded-2xl bg-[#0c1218]/90 border border-white/[0.07] hover:border-[#E5B869]/50 hover:bg-[#111922] transition-all duration-300 shadow-xl overflow-hidden cursor-pointer"
                >
                  {/* Thumbnail Container (16:9 Cinema Aspect) */}
                  <div className="relative w-full md:w-72 lg:w-80 flex-shrink-0 aspect-video rounded-xl overflow-hidden bg-black/60 border border-white/10 group-hover:border-[#E5B869]/40 transition-colors shadow-inner">
                    <img
                      src={epThumb}
                      alt={ep.name}
                      className="w-full h-full object-cover object-center transform transition-transform duration-500 group-hover:scale-105"
                      loading="lazy"
                    />

                    {/* Gradient overlay */}
                    <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent pointer-events-none" />

                    {/* Episode Number Pill */}
                    <div className="absolute top-2.5 left-2.5 z-10 bg-black/85 backdrop-blur-md border border-[#E5B869]/40 text-[#E5B869] text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded-md tracking-wider shadow">
                      EPISODE {epNum}
                    </div>

                    {/* Duration Badge */}
                    {ep.duration && (
                      <div className="absolute bottom-2.5 right-2.5 z-10 flex items-center space-x-1 bg-black/80 backdrop-blur-md text-white text-[10px] font-bold px-2 py-0.5 rounded-md border border-white/10 shadow">
                        <Clock className="w-3 h-3 text-[#E5B869]" />
                        <span>{ep.duration}</span>
                      </div>
                    )}

                    {/* Play Overlay Button */}
                    <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-black/40 backdrop-blur-[2px]">
                      <div className="w-12 h-12 rounded-full bg-[#E5B869] text-black flex items-center justify-center shadow-lg shadow-[#E5B869]/40 transform scale-90 group-hover:scale-100 transition-transform">
                        <Play className="w-5 h-5 fill-current ml-0.5" />
                      </div>
                    </div>
                  </div>

                  {/* Metadata & Synopsis Column */}
                  <div className="flex-1 flex flex-col justify-between py-1 min-w-0">
                    <div className="space-y-2">
                      <div className="flex items-start justify-between gap-3">
                        <h3 className="text-base sm:text-lg font-black text-white group-hover:text-[#E5B869] transition-colors leading-snug">
                          {ep.name}
                        </h3>
                      </div>

                      {ep.description ? (
                        <p className="text-xs sm:text-sm text-gray-300 leading-relaxed font-normal">
                          {ep.description}
                        </p>
                      ) : (
                        <p className="text-xs text-gray-500 italic">
                          Documentary chapter following historical events and scholarly insights.
                        </p>
                      )}
                    </div>

                    {/* Action & Quality Footer */}
                    <div className="flex items-center justify-between pt-4 mt-2 border-t border-white/5">
                      <div className="flex items-center space-x-3 text-xs text-gray-400">
                        <span className="inline-flex items-center font-bold text-[#E5B869] group-hover:underline">
                          Watch Episode <ChevronRight className="w-3.5 h-3.5 ml-0.5" />
                        </span>
                        <span className="hidden sm:inline text-gray-600">•</span>
                        <span className="hidden sm:inline bg-white/5 px-2 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider text-gray-300 border border-white/5">
                          HD 1080p
                        </span>
                        <span className="hidden sm:inline bg-white/5 px-2 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider text-gray-300 border border-white/5">
                          Halal Certified
                        </span>
                      </div>

                      {ep.duration && (
                        <span className="text-xs text-gray-400 font-medium">
                          {ep.duration}
                        </span>
                      )}
                    </div>
                  </div>
                </Link>
              );
            })}
          </div>
        )}

        {/* 4. Series Information Grid (Cast, Scholars, Overview) */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 pt-6 border-t border-white/10">
          <div className="p-6 rounded-2xl bg-white/[0.02] border border-white/5 space-y-2">
            <h4 className="text-xs font-bold uppercase tracking-wider text-[#E5B869]">
              Scholarly Advisors & Historians
            </h4>
            <p className="text-sm text-gray-300 leading-relaxed">
              Dr. Tariq Ramadan, Shaykh Yasir Qadhi, Dr. Jonathan Brown, Imam Zaid Shakir
            </p>
          </div>

          <div className="p-6 rounded-2xl bg-white/[0.02] border border-white/5 space-y-2">
            <h4 className="text-xs font-bold uppercase tracking-wider text-[#E5B869]">
              Available Audio & Subtitles
            </h4>
            <p className="text-sm text-gray-300 leading-relaxed">
              Audio: Arabic, English, Bengali, Urdu
              <br />
              Subtitles: English [CC], Arabic, Bengali, French
            </p>
          </div>

          <div className="p-6 rounded-2xl bg-white/[0.02] border border-white/5 space-y-2">
            <h4 className="text-xs font-bold uppercase tracking-wider text-[#E5B869]">
              Maturity & Content Guidance
            </h4>
            <p className="text-sm text-gray-300 leading-relaxed">
              100% Family Safe • Zero inappropriate depictions • Authentic Islamic historiography
            </p>
          </div>
        </div>

        {/* 5. Viewer Reviews & Star Ratings */}
        <ReviewSection
          contentId={show.id}
          contentType="tvshow"
          initialReviews={show.reviews}
        />
      </div>
    </div>
  );
}
