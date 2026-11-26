'use client';

import { useEffect, useCallback } from 'react';

import { paths } from 'src/routes/paths';
import { useRouter, usePathname, useSearchParams } from 'src/routes/hooks';

import { CONFIG } from 'src/config-global';

import { SplashScreen } from 'src/components/loading-screen';

import { useAuthContext } from '../hooks';

// ----------------------------------------------------------------------

export function AuthGuard({ children }) {
  const router = useRouter();

  const pathname = usePathname();

  const searchParams = useSearchParams();

  const { authenticated, loading } = useAuthContext();

  const createQueryString = useCallback(
    (name, value) => {
      const params = new URLSearchParams(searchParams.toString());
      params.set(name, value);

      return params.toString();
    },
    [searchParams]
  );

  const redirectToSignIn = useCallback(() => {
    const { method } = CONFIG.auth;

    const signInPath = {
      jwt: paths.auth.jwt.signIn,
      auth0: paths.auth.auth0.signIn,
      amplify: paths.auth.amplify.signIn,
      firebase: paths.auth.firebase.signIn,
      supabase: paths.auth.supabase.signIn,
    }[method];

    router.replace(`${signInPath}?${createQueryString('returnTo', pathname)}`);
  }, [createQueryString, pathname, router]);

  useEffect(() => {
    if (!loading && !authenticated) redirectToSignIn();
  }, [authenticated, loading, redirectToSignIn]);

  if (loading || !authenticated) {
    return <SplashScreen />;
  }

  return <>{children}</>;
}
