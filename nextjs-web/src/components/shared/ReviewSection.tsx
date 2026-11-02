'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { useAuth, useLanguage } from '@/providers';
import { api } from '@/lib/api';
import { ReviewItem, ReviewListResponse } from '@/types';
import { Star, MessageSquare, Trash2, Send, LogIn, CheckCircle2 } from 'lucide-react';

interface ReviewSectionProps {
  contentId: string | number;
  contentType: 'movie' | 'tvshow';
  initialReviews?: ReviewListResponse;
}

export default function ReviewSection({
  contentId,
  initialReviews,
}: ReviewSectionProps) {
  const { user } = useAuth();
  const { t } = useLanguage();

  const [reviews, setReviews] = useState<ReviewItem[]>(initialReviews?.other_reviews || []);
  const [myReview, setMyReview] = useState<ReviewItem | null>(initialReviews?.my_review || null);
  const [totalCount, setTotalCount] = useState<number>(
    initialReviews?.total_reviews ?? (initialReviews?.other_reviews?.length || 0)
  );

  const [rating, setRating] = useState<number>(myReview?.rating || 5);
  const [hoverRating, setHoverRating] = useState<number>(0);
  const [comment, setComment] = useState<string>(myReview?.review || '');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const handleSubmitReview = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!user) return;
    if (rating < 1 || rating > 5) return;

    setIsSubmitting(true);
    setErrorMessage(null);
    setSuccessMessage(null);

    try {
      const res = await api.saveReview({
        entertainment_id: contentId,
        rating,
        review: comment.trim() || undefined,
      });

      const savedItem: ReviewItem = res.data || {
        id: myReview?.id || Date.now(),
        rating,
        review: comment.trim(),
        username: user.name,
        profile_image: user.avatar,
        updated_at: 'Just now',
      };

      setMyReview(savedItem);
      if (!myReview) {
        setTotalCount((prev) => prev + 1);
      }
      setSuccessMessage(t('review_posted', 'Your review has been successfully published!'));
      setTimeout(() => setSuccessMessage(null), 4000);
    } catch (err: unknown) {
      setErrorMessage(err instanceof Error ? err.message : t('review_failed', 'Failed to publish review.'));
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleDeleteReview = async () => {
    if (!myReview?.id) return;
    try {
      await api.deleteReview(myReview.id);
      setMyReview(null);
      setComment('');
      setRating(5);
      setTotalCount((prev) => Math.max(0, prev - 1));
    } catch {
      // ignore
    }
  };

  // Derive average rating score
  const allRatings = [
    ...(myReview ? [myReview.rating] : []),
    ...reviews.map((r) => r.rating),
  ];
  const averageScore = allRatings.length > 0
    ? (allRatings.reduce((a, b) => a + b, 0) / allRatings.length).toFixed(1)
    : null;

  return (
    <div className="bg-[#0c1218] border border-white/10 rounded-2xl p-6 sm:p-8 shadow-xl space-y-6">
      {/* Header with aggregate score */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-white/10">
        <div>
          <div className="flex items-center space-x-2.5">
            <MessageSquare className="w-5 h-5 text-[#E5B869]" />
            <h2 className="text-lg sm:text-xl font-bold text-white tracking-tight">
              {t('audience_reviews', 'Viewer Reviews & Ratings')}
            </h2>
          </div>
          <p className="text-xs text-gray-400 mt-1">
            {t('share_your_thoughts', 'Join the community discussion and share your reflections on this title.')}
          </p>
        </div>

        {averageScore && (
          <div className="flex items-center space-x-3 bg-white/[0.04] border border-white/10 px-4 py-2 rounded-xl">
            <div className="flex items-center text-[#E5B869]">
              <Star className="w-5 h-5 fill-[#E5B869] text-[#E5B869]" />
              <span className="text-lg font-black ml-1.5 text-white">{averageScore}</span>
              <span className="text-xs text-gray-400 ml-1">/ 5</span>
            </div>
            <span className="text-gray-500 text-xs">&bull;</span>
            <span className="text-xs text-gray-300 font-medium">
              {totalCount} {t('reviews_count_suffix', 'reviews')}
            </span>
          </div>
        )}
      </div>

      {/* Review Submission Form or Sign-in Prompt */}
      {user ? (
        <form onSubmit={handleSubmitReview} className="glass-card p-5 rounded-xl border border-white/10 space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <span className="text-xs font-semibold text-gray-300 uppercase tracking-wider">
              {myReview ? t('update_your_rating', 'Update Your Rating') : t('rate_this_title', 'Rate This Title')}
            </span>

            {/* Interactive 5-star selector */}
            <div className="flex items-center space-x-1">
              {[1, 2, 3, 4, 5].map((star) => {
                const isFilled = (hoverRating || rating) >= star;
                return (
                  <button
                    key={star}
                    type="button"
                    onMouseEnter={() => setHoverRating(star)}
                    onMouseLeave={() => setHoverRating(0)}
                    onClick={() => setRating(star)}
                    className="p-1 text-[#E5B869] transition-transform hover:scale-125 cursor-pointer focus:outline-none"
                    aria-label={`Rate ${star} stars`}
                  >
                    <Star
                      className={`w-5 h-5 ${
                        isFilled
                          ? 'fill-[#E5B869] text-[#E5B869]'
                          : 'text-gray-600 hover:text-[#E5B869]'
                      }`}
                    />
                  </button>
                );
              })}
              <span className="text-xs font-bold text-[#E5B869] ml-2">
                {rating} / 5
              </span>
            </div>
          </div>

          <textarea
            rows={3}
            value={comment}
            onChange={(e) => setComment(e.target.value)}
            placeholder={t('write_review_placeholder', 'Share your spiritual reflections, cinematography impressions, or takeaways...')}
            className="w-full bg-white/[0.04] border border-white/10 rounded-xl p-3 text-xs sm:text-sm text-white placeholder-gray-500 focus:outline-none focus:border-[#E5B869] transition-colors resize-none"
          />

          {successMessage && (
            <div className="flex items-center space-x-2 text-emerald-400 text-xs bg-emerald-500/10 border border-emerald-500/30 px-3 py-2 rounded-lg">
              <CheckCircle2 className="w-4 h-4 flex-shrink-0" />
              <span>{successMessage}</span>
            </div>
          )}

          {errorMessage && (
            <p className="text-xs text-red-400 font-medium">{errorMessage}</p>
          )}

          <div className="flex items-center justify-between pt-1">
            {myReview ? (
              <button
                type="button"
                onClick={handleDeleteReview}
                className="inline-flex items-center space-x-1.5 text-xs text-red-400 hover:text-red-300 transition-colors cursor-pointer"
              >
                <Trash2 className="w-3.5 h-3.5" />
                <span>{t('delete_review', 'Delete My Review')}</span>
              </button>
            ) : <span />}

            <button
              type="submit"
              disabled={isSubmitting}
              className="inline-flex items-center space-x-1.5 px-5 py-2.5 rounded-full bg-[#E5B869] hover:bg-[#f3cf8a] disabled:opacity-50 text-black text-xs font-bold shadow-md transition-all cursor-pointer hover:scale-[1.02] active:scale-95"
            >
              <Send className="w-3.5 h-3.5" />
              <span>{isSubmitting ? t('submitting', 'Posting...') : (myReview ? t('update_review', 'Update Review') : t('post_review', 'Post Review'))}</span>
            </button>
          </div>
        </form>
      ) : (
        <div className="p-5 rounded-xl border border-white/10 bg-white/[0.02] flex flex-col sm:flex-row items-center justify-between gap-4 text-center sm:text-left">
          <div>
            <p className="text-xs sm:text-sm font-semibold text-white">
              {t('sign_in_to_review', 'Have you watched this title?')}
            </p>
            <p className="text-xs text-gray-400 mt-0.5">
              {t('sign_in_review_desc', 'Sign in to rate and share your thoughts with fellow viewers.')}
            </p>
          </div>
          <Link
            href="/login"
            className="inline-flex items-center space-x-1.5 px-4 py-2 rounded-full bg-[#E5B869] hover:bg-[#f3cf8a] text-black text-xs font-bold shadow-md transition-all cursor-pointer whitespace-nowrap"
          >
            <LogIn className="w-3.5 h-3.5" />
            <span>{t('sign_in', 'Sign In')}</span>
          </Link>
        </div>
      )}

      {/* Community Reviews List */}
      <div className="space-y-4 pt-2">
        <h3 className="text-xs font-bold text-gray-400 uppercase tracking-wider">
          {t('community_reviews', 'Community Reviews')} ({reviews.length + (myReview ? 1 : 0)})
        </h3>

        {/* Display own review on top if present */}
        {myReview && (
          <div className="p-4 rounded-xl border border-[#E5B869]/40 bg-[#E5B869]/5 space-y-2">
            <div className="flex items-center justify-between">
              <div className="flex items-center space-x-2.5">
                <div className="w-8 h-8 rounded-full bg-[#E5B869]/20 border border-[#E5B869]/40 flex items-center justify-center text-xs font-bold text-[#E5B869]">
                  {myReview.username ? myReview.username.slice(0, 2).toUpperCase() : 'ME'}
                </div>
                <div>
                  <div className="flex items-center space-x-2">
                    <span className="text-xs font-bold text-white">{myReview.username || user?.name}</span>
                    <span className="text-[10px] uppercase font-bold text-[#E5B869] bg-[#E5B869]/10 border border-[#E5B869]/30 px-1.5 py-0.2 rounded">
                      {t('your_review', 'You')}
                    </span>
                  </div>
                  <span className="text-[10px] text-gray-400">{myReview.updated_at || 'Recently'}</span>
                </div>
              </div>

              <div className="flex items-center space-x-0.5 text-[#E5B869]">
                {[...Array(5)].map((_, i) => (
                  <Star
                    key={i}
                    className={`w-3.5 h-3.5 ${
                      i < myReview.rating
                        ? 'fill-[#E5B869] text-[#E5B869]'
                        : 'text-gray-600'
                    }`}
                  />
                ))}
              </div>
            </div>

            {myReview.review && (
              <p className="text-xs text-gray-200 leading-relaxed pl-10">
                {myReview.review}
              </p>
            )}
          </div>
        )}

        {/* Other reviews */}
        {reviews.length > 0 ? (
          <div className="space-y-3">
            {reviews.map((r, idx) => (
              <div key={r.id || idx} className="p-4 rounded-xl border border-white/5 bg-white/[0.02] space-y-2">
                <div className="flex items-center justify-between">
                  <div className="flex items-center space-x-2.5">
                    {r.profile_image ? (
                      <img
                        src={r.profile_image}
                        alt={r.username || r.user_name || 'User'}
                        className="w-8 h-8 rounded-full object-cover"
                      />
                    ) : (
                      <div className="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-xs font-bold text-gray-300">
                        {(r.username || r.user_name || 'U').slice(0, 2).toUpperCase()}
                      </div>
                    )}
                    <div>
                      <p className="text-xs font-semibold text-white">
                        {r.username || r.user_name || 'Community Member'}
                      </p>
                      <span className="text-[10px] text-gray-500">
                        {r.created_at || r.updated_at || 'Recent review'}
                      </span>
                    </div>
                  </div>

                  <div className="flex items-center space-x-0.5 text-[#E5B869]">
                    {[...Array(5)].map((_, i) => (
                      <Star
                        key={i}
                        className={`w-3.5 h-3.5 ${
                          i < r.rating
                            ? 'fill-[#E5B869] text-[#E5B869]'
                            : 'text-gray-600'
                        }`}
                      />
                    ))}
                  </div>
                </div>

                {r.review && (
                  <p className="text-xs text-gray-300 leading-relaxed pl-10">
                    {r.review}
                  </p>
                )}
              </div>
            ))}
          </div>
        ) : !myReview ? (
          <div className="text-center py-6 text-gray-500 text-xs">
            {t('no_reviews_yet', 'No reviews posted yet. Be the first to share your review!')}
          </div>
        ) : null}
      </div>
    </div>
  );
}

