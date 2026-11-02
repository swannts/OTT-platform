'use client';

import { useState, useEffect, useCallback } from 'react';
import { useAuth } from '@/context/AuthContext';
import { accountApi } from '../api/account.client';
import type { AccountSettingData } from '../types/account.types';
import type { UpdateProfileInput, ChangePasswordInput } from '../schemas/account.schema';

export function useAccountData() {
  const { user, refreshUser } = useAuth();
  const [data, setData] = useState<AccountSettingData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  const fetchSettings = useCallback(async () => {
    if (!user) return;
    try {
      const res = await accountApi.getAccountSettings();
      setData(res);
      setError(null);
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Failed to load account settings';
      setError(msg);
      setData({
        user,
        your_device: null,
        other_device: [],
        plan_details: null,
      });
    } finally {
      setIsLoading(false);
    }
  }, [user]);

  useEffect(() => {
    let ignore = false;
    async function init() {
      if (!user) return;
      try {
        const res = await accountApi.getAccountSettings();
        if (!ignore) {
          setData(res);
          setError(null);
        }
      } catch (err) {
        if (!ignore) {
          const msg = err instanceof Error ? err.message : 'Failed to load account settings';
          setError(msg);
          setData({
            user,
            your_device: null,
            other_device: [],
            plan_details: null,
          });
        }
      } finally {
        if (!ignore) {
          setIsLoading(false);
        }
      }
    }
    init();
    return () => {
      ignore = true;
    };
  }, [user]);

  const updateProfile = async (input: UpdateProfileInput & { avatar?: string }) => {
    setActionLoading(true);
    try {
      await accountApi.updateProfile(input);
      await refreshUser();
      await fetchSettings();
      return { success: true };
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Failed to update profile';
      return { success: false, error: msg };
    } finally {
      setActionLoading(false);
    }
  };

  const changePassword = async (input: ChangePasswordInput) => {
    setActionLoading(true);
    try {
      await accountApi.changePassword(input);
      return { success: true };
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Failed to change password';
      return { success: false, error: msg };
    } finally {
      setActionLoading(false);
    }
  };

  const updateParentalLock = async (enabled: boolean, pin?: string) => {
    setActionLoading(true);
    try {
      await accountApi.updateParentalLock(enabled, pin);
      await refreshUser();
      await fetchSettings();
      return { success: true };
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Failed to update parental lock';
      return { success: false, error: msg };
    } finally {
      setActionLoading(false);
    }
  };

  const logoutDevice = async (deviceId: string | number) => {
    setActionLoading(true);
    try {
      await accountApi.logoutDevice(deviceId);
      if (data) {
        setData({
          ...data,
          other_device: (data.other_device || []).filter(
            (d) => d.id !== deviceId && d.device_id !== String(deviceId)
          ),
        });
      }
      return { success: true };
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Failed to logout device';
      return { success: false, error: msg };
    } finally {
      setActionLoading(false);
    }
  };

  const logoutAllDevices = async () => {
    setActionLoading(true);
    try {
      await accountApi.logoutAllDevices();
      if (data) {
        setData({
          ...data,
          other_device: [],
        });
      }
      return { success: true };
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Failed to logout all devices';
      return { success: false, error: msg };
    } finally {
      setActionLoading(false);
    }
  };

  return {
    data,
    user: data?.user || user,
    devices: {
      current: data?.your_device || null,
      others: data?.other_device || [],
    },
    planDetails: data?.plan_details || null,
    isLoading,
    actionLoading,
    error,
    refresh: fetchSettings,
    updateProfile,
    changePassword,
    updateParentalLock,
    logoutDevice,
    logoutAllDevices,
  };
}
