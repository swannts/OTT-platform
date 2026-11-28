'use client';

import { useEffect } from 'react';
import { MediaRow } from '@/components/shared';
import { useAuth, useLanguage } from '@/providers';
import { useAppDispatch, useAppSelector } from '@/store/hooks';
import { fetchContinueWatching, selectHomeContinueWatching } from '@/store/slices/homeSlice';

export default function ContinueWatchingRow() {
  const dispatch = useAppDispatch();
  const { user } = useAuth();
  const { t } = useLanguage();
  const items = useAppSelector(selectHomeContinueWatching);

  useEffect(() => {
    if (user) void dispatch(fetchContinueWatching());
  }, [dispatch, user]);

  if (!user || items.length === 0) return null;

  return <MediaRow title={t('continue_watching', 'Continue Watching')} items={items} aspectRatio="backdrop" />;
}
