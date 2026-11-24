'use client';

import { useEffect } from 'react';

import { useRouter, useSearchParams } from 'src/routes/hooks';

import { CONFIG } from 'src/config-global';

import { SplashScreen } from 'src/components/loading-screen';

import { useAuthContext } from '../hooks';

// ----------------------------------------------------------------------

export function GuestGuard({ children }) {
  const router = useRouter();

  const searchParams = useSearchParams();

  const { loading, authenticated } = useAuthContext();

  const returnTo = searchParams.get('returnTo') || CONFIG.auth.redirectPath;

  useEffect(() => {
    if (!loading && authenticated) router.replace(returnTo);
  }, [authenticated, loading, returnTo, router]);

  if (loading || authenticated) {
    return <SplashScreen />;
  }

  return <>{children}</>;
}
