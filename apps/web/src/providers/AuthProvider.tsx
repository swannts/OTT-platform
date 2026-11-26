'use client';

import React, { createContext, useContext, useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { SubscriptionStatus, User } from '@/types';

interface AuthContextType {
  user: User | null;
  subscription: SubscriptionStatus | null;
  isLoading: boolean;
  login: (credentials: { email?: string; username?: string; password?: string }) => Promise<void>;
  register: (data: { name: string; email: string; password?: string }) => Promise<void>;
  logout: () => Promise<void>;
  refreshSubscription: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

function normalizeUser(raw: unknown): User | null {
  if (!raw || typeof raw !== 'object') return null;
  const rawObj = raw as Record<string, unknown>;
  const userObj = (rawObj.data && typeof rawObj.data === 'object' ? rawObj.data : rawObj) as Record<string, unknown>;
  const name =
    (userObj.name as string) ||
    (userObj.full_name as string) ||
    [(userObj.first_name as string), (userObj.last_name as string)].filter(Boolean).join(' ') ||
    (typeof userObj.email === 'string' ? userObj.email.split('@')[0] : '') ||
    'User';

  const avatar = (userObj.avatar as string) || (userObj.profile_image as string) || (userObj.file_url as string) || '';

  return {
    id: (userObj.id as number | string) || 1,
    name,
    email: (userObj.email as string) || '',
    avatar,
    profile_image: avatar,
    user_type: (userObj.user_type as string) || 'user',
    is_subscribe: typeof userObj.is_subscribe === 'number' ? userObj.is_subscribe : 0,
    phone: (userObj.phone as string) || (userObj.mobile as string) || '',
    is_parental_lock_enable: typeof userObj.is_parental_lock_enable === 'number' ? userObj.is_parental_lock_enable : (userObj.is_parental_lock_enable ? 1 : 0),
  };
}

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [subscription, setSubscription] = useState<SubscriptionStatus | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const refreshSubscription = async () => {
    try {
      const sub = await api.getUserSubscription();
      setSubscription(sub);
    } catch {
      setSubscription(null);
    }
  };

  const refreshUser = async () => {
    try {
      const profile = await api.getProfile();
      if (profile) {
        const norm = normalizeUser(profile);
        setUser(norm);
        if (typeof window !== 'undefined' && norm) {
          localStorage.setItem('user', JSON.stringify(norm));
        }
      }
    } catch {
      // Profile fetch failure
    }
  };

  useEffect(() => {
    const initializeAuth = async () => {
      try {
        const storedUser = localStorage.getItem('user');
        const token =
          localStorage.getItem('token') ||
          (typeof document !== 'undefined'
            ? document.cookie.match(/(?:^|;\s*)noor_token=([^;]+)/)?.[1]
            : null);

        if (token) {
          if (storedUser) {
            try {
              setUser(normalizeUser(JSON.parse(storedUser)));
            } catch {
              // Ignore JSON parse errors
            }
          }
          // Fetch live user profile and subscription status
          try {
            const profile = await api.getProfile();
            if (profile) {
              const norm = normalizeUser(profile);
              setUser(norm);
              if (norm) {
                localStorage.setItem('user', JSON.stringify(norm));
              }
            }
          } catch (err: unknown) {
            // If token is invalid or expired (401), remove token and reset user state
            const errMsg = err instanceof Error ? err.message : String(err);
            if (errMsg.includes('401') || errMsg.includes('Unauthenticated') || errMsg.includes('Unauthorized')) {
              api.removeToken();
              setUser(null);
              setSubscription(null);
            }
          }
          await refreshSubscription();
        }
      } catch {
        // Storage access error
      } finally {
        setIsLoading(false);
      }
    };

    initializeAuth();
  }, []);

  const login = async (credentials: { email?: string; username?: string; password?: string }) => {
    setIsLoading(true);
    try {
      const authData = await api.login(credentials);
      if (authData) {
        const norm = normalizeUser(authData);
        setUser(norm);
        if (typeof window !== 'undefined' && norm) {
          localStorage.setItem('user', JSON.stringify(norm));
        }
        await refreshSubscription();
      }
    } finally {
      setIsLoading(false);
    }
  };

  const register = async (data: { name: string; email: string; password?: string }) => {
    setIsLoading(true);
    try {
      const authData = await api.register(data);
      if (authData) {
        const norm = normalizeUser(authData);
        setUser(norm);
        if (typeof window !== 'undefined' && norm) {
          localStorage.setItem('user', JSON.stringify(norm));
        }
        await refreshSubscription();
      }
    } finally {
      setIsLoading(false);
    }
  };

  const logout = async () => {
    setIsLoading(true);
    try {
      await api.logout();
      setUser(null);
      setSubscription(null);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        subscription,
        isLoading,
        login,
        register,
        logout,
        refreshSubscription,
        refreshUser,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
