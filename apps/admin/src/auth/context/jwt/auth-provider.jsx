'use client';

import { useMemo, useEffect, useCallback } from 'react';

import { useSetState } from 'src/hooks/use-set-state';

import axios, { endpoints } from 'src/utils/axios';

import { AuthContext } from '../auth-context';

// ----------------------------------------------------------------------

export function AuthProvider({ children }) {
  const { state, setState } = useSetState({
    user: null,
    loading: true,
  });

  const checkUserSession = useCallback(async () => {
    try {
      const res = await axios.get(endpoints.auth.me);
      setState({ user: res.data.user, loading: false });
    } catch (error) {
      if (error.response?.status !== 401) console.error(error);
      setState({ user: null, loading: false });
    }
  }, [setState]);

  useEffect(() => {
    checkUserSession();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    const refreshWhenVisible = () => {
      if (document.visibilityState === 'visible') checkUserSession();
    };
    const interval = window.setInterval(checkUserSession, 60_000);
    window.addEventListener('focus', refreshWhenVisible);

    return () => {
      window.clearInterval(interval);
      window.removeEventListener('focus', refreshWhenVisible);
    };
  }, [checkUserSession]);

  // ----------------------------------------------------------------------

  const checkAuthenticated = state.user ? 'authenticated' : 'unauthenticated';

  const status = state.loading ? 'loading' : checkAuthenticated;

  const memoizedValue = useMemo(
    () => ({
      user: state.user
        ? {
            ...state.user,
            role: state.user?.role,
          }
        : null,
      checkUserSession,
      loading: status === 'loading',
      authenticated: status === 'authenticated',
      unauthenticated: status === 'unauthenticated',
    }),
    [checkUserSession, state.user, status]
  );

  return <AuthContext.Provider value={memoizedValue}>{children}</AuthContext.Provider>;
}
