'use client';

import React from 'react';
import { User, Users, Shield, Smartphone, CreditCard, Sliders } from 'lucide-react';
import type { AccountTab } from '../types/account.types';

interface AccountNavTabsProps {
  activeTab: AccountTab;
  onTabChange: (tab: AccountTab) => void;
  devicesCount?: number;
  profilesCount?: number;
  isVIP?: boolean;
}

const TABS: { id: AccountTab; label: string; icon: React.ElementType }[] = [
  { id: 'profile', label: 'General Info', icon: User },
  { id: 'profiles', label: 'Viewer Profiles', icon: Users },
  { id: 'preferences', label: 'Preferences', icon: Sliders },
  { id: 'security', label: 'Security & PIN', icon: Shield },
  { id: 'devices', label: 'Connected Devices', icon: Smartphone },
  { id: 'billing', label: 'Billing & History', icon: CreditCard },
];

export function AccountNavTabs({
  activeTab,
  onTabChange,
  devicesCount,
  profilesCount,
  isVIP,
}: AccountNavTabsProps) {
  return (
    <div className="flex flex-wrap items-center gap-2 mb-8 border-b border-white/10 pb-4">
      {TABS.map((tab) => {
        const Icon = tab.icon;
        const isActive = activeTab === tab.id;

        return (
          <button
            key={tab.id}
            type="button"
            onClick={() => onTabChange(tab.id)}
            className={`flex items-center space-x-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200 ${
              isActive
                ? 'bg-[#E5B869] text-black shadow-lg shadow-[#E5B869]/25 scale-[1.02]'
                : 'text-gray-400 hover:text-white hover:bg-white/5 border border-transparent'
            }`}
          >
            <Icon className="w-4 h-4 shrink-0" />
            <span>{tab.label}</span>

            {/* Badges */}
            {tab.id === 'devices' && typeof devicesCount === 'number' && devicesCount > 0 && (
              <span
                className={`ml-1 text-xs px-2 py-0.5 rounded-full font-bold ${
                  isActive ? 'bg-black/20 text-black' : 'bg-white/10 text-gray-300'
                }`}
              >
                {devicesCount}
              </span>
            )}

            {tab.id === 'profiles' && typeof profilesCount === 'number' && profilesCount > 0 && (
              <span
                className={`ml-1 text-xs px-2 py-0.5 rounded-full font-bold ${
                  isActive ? 'bg-black/20 text-black' : 'bg-white/10 text-gray-300'
                }`}
              >
                {profilesCount}
              </span>
            )}

            {tab.id === 'billing' && isVIP && (
              <span
                className={`ml-1 text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded-full font-extrabold ${
                  isActive ? 'bg-black text-[#E5B869]' : 'bg-[#E5B869]/20 text-[#E5B869]'
                }`}
              >
                VIP
              </span>
            )}
          </button>
        );
      })}
    </div>
  );
}
