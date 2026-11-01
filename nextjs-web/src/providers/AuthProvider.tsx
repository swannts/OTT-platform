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
      setUser(profile);
      if (typeof window !== 'undefined') {
        localStorage.setItem('user', JSON.stringify(profile));
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
            setUser(JSON.parse(storedUser));
          }
          // Fetch live user profile and subscription status
          try {
            const profile = await api.getProfile();
            setUser(profile);
            localStorage.setItem('user', JSON.stringify(profile));
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
        setUser(authData as unknown as User);
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
        setUser(authData as unknown as User);
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
