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

function normalizeUser(raw: any): User | null {
  if (!raw || typeof raw !== 'object') return null;
  const userObj = raw.data || raw;
  const name =
    userObj.name ||
    userObj.full_name ||
    [userObj.first_name, userObj.last_name].filter(Boolean).join(' ') ||
    userObj.email?.split('@')[0] ||
    'User';

  const avatar = userObj.avatar || userObj.profile_image || userObj.file_url || '';

  return {
    id: userObj.id || 1,
    name,
    email: userObj.email || '',
    avatar,
    profile_image: avatar,
    user_type: userObj.user_type || 'user',
    is_subscribe: userObj.is_subscribe ?? 0,
    phone: userObj.phone || userObj.mobile || '',
    is_parental_lock_enable: userObj.is_parental_lock_enable ?? 0,
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
        const token = localStorage.getItem('token');

        if (token) {
          if (storedUser) {
            setUser(normalizeUser(JSON.parse(storedUser)));
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
          } catch {
            // Profile fetch failure
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
