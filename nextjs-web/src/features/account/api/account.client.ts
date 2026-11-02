import { api } from '@/lib/api';
import type {
  AccountSettingData,
  ProfileItem,
  BillingHistoryData,
} from '../types/account.types';
import type {
  UpdateProfileInput,
  ChangePasswordInput,
  MultiProfileInput,
} from '../schemas/account.schema';

export const accountApi = {
  /**
   * Fetch full account setting data including current device and other sessions
   */
  async getAccountSettings(): Promise<AccountSettingData> {
    const res = (await api.getAccountSettings()) as Record<string, unknown>;
    const data = (res?.data || res || {}) as Record<string, unknown>;
    return {
      user: (data?.user || data) as AccountSettingData['user'],
      your_device: (data?.your_device || null) as AccountSettingData['your_device'],
      other_device: Array.isArray(data?.other_device) ? (data.other_device as AccountSettingData['other_device']) : [],
      plan_details: (data?.plan_details || null) as AccountSettingData['plan_details'],
    };
  },

  /**
   * Update personal profile information
   */
  async updateProfile(input: UpdateProfileInput & { avatar?: string }) {
    return api.updateProfile(input);
  },

  /**
   * Change user account password
   */
  async changePassword(input: ChangePasswordInput) {
    return api.changePassword({
      old_password: input.old_password,
      new_password: input.new_password,
    });
  },

  /**
   * Enable or disable 4-digit parental control PIN
   */
  async updateParentalLock(enabled: boolean, pin?: string) {
    return api.updateParentalLock(enabled, pin);
  },

  /**
   * List all viewer profiles under current user
   */
  async getProfiles(): Promise<ProfileItem[]> {
    const res = await api.getProfiles();
    const list = Array.isArray(res) ? res : (res as { data?: ProfileItem[] })?.data || [];
    return list as ProfileItem[];
  },

  /**
   * Create or update a viewer profile
   */
  async saveProfile(input: MultiProfileInput & { id?: number | string }) {
    return api.createProfile({
      name: input.name,
      is_child: input.is_child_profile,
      avatar: input.avatar,
      ...(input.id ? { id: input.id } : {}),
    });
  },

  /**
   * Delete a viewer profile
   */
  async deleteProfile(profileId: string | number) {
    return api.deleteProfile(profileId);
  },

  /**
   * Switch the active profile session
   */
  async selectProfile(profileId: string | number, pin?: string) {
    return api.selectProfile(profileId, pin);
  },

  /**
   * Revoke session for a specific connected device
   */
  async logoutDevice(deviceId: string | number): Promise<void> {
    await api.logoutDevice(deviceId);
  },

  /**
   * Revoke sessions on all other devices
   */
  async logoutAllDevices(): Promise<void> {
    await api.logoutAllDevices();
  },

  /**
   * Fetch subscription and pay-per-view billing invoices
   */
  async getBillingHistory(): Promise<BillingHistoryData> {
    const res = await api.getBillingHistory();
    return {
      subscriptions: res?.subscriptions || [],
      pay_per_view: res?.pay_per_view || [],
    };
  },

  /**
   * Cancel VIP subscription
   */
  async cancelSubscription() {
    return api.cancelSubscription();
  },

  /**
   * Permanently delete user account
   */
  async deleteAccount() {
    return api.deleteAccount();
  },
};
