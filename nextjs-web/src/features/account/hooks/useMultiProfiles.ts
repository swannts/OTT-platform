'use client';

import { useState, useEffect, useCallback } from 'react';
import { useAuth } from '@/context/AuthContext';
import { accountApi } from '../api/account.client';
import type { ProfileItem } from '../types/account.types';
import type { MultiProfileInput } from '../schemas/account.schema';

export function useMultiProfiles() {
  const { user } = useAuth();
  const [profiles, setProfiles] = useState<ProfileItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState(false);

  // Pin verification state for child -> protected parent profile switch
  const [pinTargetProfile, setPinTargetProfile] = useState<ProfileItem | null>(null);

  const fetchProfiles = useCallback(async () => {
    if (!user) return;
    try {
      const res = await accountApi.getProfiles();
      const list = Array.isArray(res) ? res : [];
      if (list.length === 0) {
        setProfiles([
          {
            id: user.id,
            name: user.name || 'Main Profile',
            avatar: user.avatar || user.profile_image,
            is_child_profile: false,
            is_active: true,
          },
        ]);
      } else {
        setProfiles(list);
      }
      setError(null);
    } catch {
      setProfiles([
        {
          id: user.id,
          name: user.name || 'Main Profile',
          avatar: user.avatar || user.profile_image,
          is_child_profile: false,
          is_active: true,
        },
      ]);
    } finally {
      setIsLoading(false);
    }
  }, [user]);

  useEffect(() => {
    let ignore = false;
    async function init() {
      if (!user) return;
      try {
        const res = await accountApi.getProfiles();
        if (!ignore) {
          const list = Array.isArray(res) ? res : [];
          if (list.length === 0) {
            setProfiles([
              {
                id: user.id,
                name: user.name || 'Main Profile',
                avatar: user.avatar || user.profile_image,
                is_child_profile: false,
                is_active: true,
              },
            ]);
          } else {
            setProfiles(list);
          }
          setError(null);
        }
      } catch {
        if (!ignore) {
          setProfiles([
            {
              id: user.id,
              name: user.name || 'Main Profile',
              avatar: user.avatar || user.profile_image,
              is_child_profile: false,
              is_active: true,
            },
          ]);
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

  const currentProfile = profiles.find((p) => p.is_active) || profiles[0] || null;

  const saveProfile = async (input: MultiProfileInput & { id?: number | string }) => {
    setActionLoading(true);
    try {
      await accountApi.saveProfile(input);
      await fetchProfiles();
      return { success: true };
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Failed to save profile';
      return { success: false, error: msg };
    } finally {
      setActionLoading(false);
    }
  };

  const deleteProfile = async (profileId: number | string) => {
    setActionLoading(true);
    try {
      await accountApi.deleteProfile(profileId);
      await fetchProfiles();
      return { success: true };
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Failed to delete profile';
      return { success: false, error: msg };
    } finally {
      setActionLoading(false);
    }
  };

  const selectProfile = async (profile: ProfileItem, pin?: string) => {
    const isCurrentChild = Boolean(currentProfile?.is_child_profile || currentProfile?.is_child);
    const isTargetProtected = Boolean(profile.is_protected_profile || !profile.is_child_profile);
    const requiresPin = isCurrentChild && isTargetProtected && user?.is_parental_lock_enable;

    if (requiresPin && !pin) {
      setPinTargetProfile(profile);
      return { requiresPin: true };
    }

    setActionLoading(true);
    try {
      await accountApi.selectProfile(profile.id, pin);
      setPinTargetProfile(null);
      await fetchProfiles();
      return { success: true };
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Invalid PIN or failed to select profile';
      return { success: false, error: msg };
    } finally {
      setActionLoading(false);
    }
  };

  const cancelPinVerification = () => {
    setPinTargetProfile(null);
  };

  return {
    profiles,
    currentProfile,
    isLoading,
    actionLoading,
    error,
    pinTargetProfile,
    refresh: fetchProfiles,
    saveProfile,
    deleteProfile,
    selectProfile,
    cancelPinVerification,
  };
}
