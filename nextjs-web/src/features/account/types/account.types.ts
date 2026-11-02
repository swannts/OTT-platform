import type { User, BillingSubscriptionRecord, BillingPayPerViewRecord } from '@/types';

export type AccountTab = 'profile' | 'profiles' | 'security' | 'devices' | 'billing' | 'preferences';

export interface DeviceSession {
  id: number | string;
  device_id: string;
  device_name: string;
  platform: string;
  session_id?: string;
  last_activity?: string;
  created_at?: string;
  active_profile?: number | string;
  is_current?: boolean;
}

export interface ProfileItem {
  id: number | string;
  user_id?: number | string;
  name: string;
  avatar?: string;
  is_active?: boolean | number;
  is_child_profile?: boolean | number;
  is_child?: boolean;
  profile_pin?: string | null;
  is_protected_profile?: boolean | number;
}

export interface AccountSettingData {
  user: User & {
    first_name?: string;
    last_name?: string;
    mobile?: string;
    gender?: string;
    date_of_birth?: string;
    email_verified_at?: string;
  };
  your_device?: DeviceSession | null;
  other_device?: DeviceSession[];
  plan_details?: {
    id?: number;
    plan_name?: string;
    name?: string;
    start_date?: string;
    end_date?: string;
    price?: number | string;
    currency?: string;
    status?: string;
  } | null;
}

export interface BillingHistoryData {
  subscriptions: BillingSubscriptionRecord[];
  pay_per_view: BillingPayPerViewRecord[];
}

export interface AvatarPreset {
  id: string;
  name: string;
  url: string;
  bgColor: string;
}
