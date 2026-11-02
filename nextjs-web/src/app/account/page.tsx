'use client';

import React, { useEffect, Suspense } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';
import { Spinner } from '@/components/ui';
import { LogOut } from 'lucide-react';
import {
  AccountNavTabs,
  ProfileSection,
  ViewerProfilesSection,
  SecuritySection,
  DevicesSection,
  BillingSection,
  PreferencesSection,
  useAccountData,
  useMultiProfiles,
  type AccountTab,
} from '@/features/account';

function AccountDashboardContent() {
  const { user, subscription, logout, isLoading: authLoading, refreshUser } = useAuth();
  const router = useRouter();
  const searchParams = useSearchParams();

  // Active tab derived directly from URL search params (Rule 5: no unnecessary effect/state sync)
  const activeTab: AccountTab = (searchParams?.get('tab') as AccountTab) || 'profile';

  const handleTabChange = (tab: AccountTab) => {
    router.replace(`/account?tab=${tab}`, { scroll: false });
  };

  // Auth Guard
  useEffect(() => {
    if (!authLoading && !user) {
      router.push('/login?redirect=/account');
    }
  }, [user, authLoading, router]);

  // Feature Hooks
  const {
    data,
    devices,
    isLoading: accountLoading,
    actionLoading: accountActionLoading,
    updateProfile,
    changePassword,
    updateParentalLock,
    logoutDevice,
    logoutAllDevices,
    refresh: refreshAccount,
  } = useAccountData();

  const {
    profiles,
    currentProfile,
    isLoading: profilesLoading,
    actionLoading: profileActionLoading,
    pinTargetProfile,
    selectProfile,
    saveProfile,
    deleteProfile,
    cancelPinVerification,
  } = useMultiProfiles();

  if (authLoading || (accountLoading && !data)) {
    return (
      <div className="min-h-screen pt-32 text-center text-white space-y-4">
        <Spinner size="lg" label="Loading your account dashboard..." />
      </div>
    );
  }

  if (!user) return null;

  const isVIP = Boolean(user.is_subscribe || subscription?.has_active_subscription);

  return (
    <div className="min-h-screen pt-24 pb-20 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto">
      {/* Page Header */}
      <div className="border-b border-white/10 pb-6 mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-3xl font-extrabold text-white tracking-tight">Account & Settings</h1>
          <p className="text-gray-400 text-sm mt-1">
            Manage your personal profile, viewer spaces, security controls, and active devices.
          </p>
        </div>

        <button
          type="button"
          onClick={() => {
            logout();
            router.push('/');
          }}
          className="self-start sm:self-auto flex items-center space-x-2 text-sm text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/20 px-4 py-2.5 rounded-xl transition-colors border border-red-500/20 font-semibold"
        >
          <LogOut className="w-4 h-4" />
          <span>Sign Out</span>
        </button>
      </div>

      {/* Navigation Tabs */}
      <AccountNavTabs
        activeTab={activeTab}
        onTabChange={handleTabChange}
        devicesCount={devices.others.length + (devices.current ? 1 : 0)}
        profilesCount={profiles.length}
        isVIP={isVIP}
      />

      {/* Tab Panels */}
      {activeTab === 'profile' && (
        <ProfileSection
          user={{
            ...user,
            first_name: data?.user?.first_name,
            last_name: data?.user?.last_name,
            mobile: data?.user?.mobile,
            gender: data?.user?.gender,
            date_of_birth: data?.user?.date_of_birth,
          }}
          subscription={subscription}
          onUpdate={updateProfile}
          isUpdating={accountActionLoading}
          onNavigateTab={handleTabChange}
        />
      )}

      {activeTab === 'profiles' && (
        <ViewerProfilesSection
          profiles={profiles}
          currentProfile={currentProfile}
          onSelectProfile={selectProfile}
          onSaveProfile={saveProfile}
          onDeleteProfile={deleteProfile}
          pinTargetProfile={pinTargetProfile}
          onCancelPin={cancelPinVerification}
          isLoading={profilesLoading}
          actionLoading={profileActionLoading}
          isVIP={isVIP}
        />
      )}

      {activeTab === 'preferences' && <PreferencesSection />}

      {activeTab === 'security' && (
        <SecuritySection
          isParentalLockEnabled={Boolean(user.is_parental_lock_enable)}
          onUpdateParentalLock={updateParentalLock}
          onChangePassword={changePassword}
          actionLoading={accountActionLoading}
        />
      )}

      {activeTab === 'devices' && (
        <DevicesSection
          currentDevice={devices.current}
          otherDevices={devices.others}
          onLogoutDevice={logoutDevice}
          onLogoutAll={logoutAllDevices}
          isLoading={accountLoading}
          actionLoading={accountActionLoading}
        />
      )}

      {activeTab === 'billing' && (
        <BillingSection
          subscription={subscription}
          onRefresh={async () => {
            await refreshUser();
            await refreshAccount();
          }}
        />
      )}
    </div>
  );
}

export default function AccountPage() {
  return (
    <Suspense
      fallback={
        <div className="min-h-screen pt-32 text-center text-white space-y-4">
          <Spinner size="lg" label="Loading account..." />
        </div>
      }
    >
      <AccountDashboardContent />
    </Suspense>
  );
}
