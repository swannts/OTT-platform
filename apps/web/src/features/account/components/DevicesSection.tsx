'use client';

import React, { useState } from 'react';
import { Smartphone, Monitor, Tv, Laptop, LogOut, CheckCircle2, AlertCircle, ShieldAlert, Clock } from 'lucide-react';
import { Spinner } from '@/components/ui';
import type { DeviceSession } from '../types/account.types';

interface DevicesSectionProps {
  currentDevice: DeviceSession | null;
  otherDevices: DeviceSession[];
  onLogoutDevice: (id: string | number) => Promise<{ success: boolean; error?: string }>;
  onLogoutAll: () => Promise<{ success: boolean; error?: string }>;
  isLoading: boolean;
  actionLoading: boolean;
}

function DeviceIconRenderer({ platform = '', deviceName = '', className = 'w-5 h-5' }: { platform?: string; deviceName?: string; className?: string }) {
  const text = `${platform} ${deviceName}`.toLowerCase();
  if (text.includes('tv') || text.includes('androidtv') || text.includes('firetv') || text.includes('roku')) {
    return <Tv className={className} />;
  }
  if (text.includes('mobile') || text.includes('android') || text.includes('iphone') || text.includes('phone')) {
    return <Smartphone className={className} />;
  }
  if (text.includes('mac') || text.includes('windows') || text.includes('linux')) {
    return <Laptop className={className} />;
  }
  return <Monitor className={className} />;
}

export function DevicesSection({
  currentDevice,
  otherDevices,
  onLogoutDevice,
  onLogoutAll,
  isLoading,
  actionLoading,
}: DevicesSectionProps) {
  const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  const handleLogout = async (deviceId: string | number, name: string) => {
    if (!confirm(`Are you sure you want to log out "${name}"? The user will be signed out immediately.`)) {
      return;
    }
    setFeedback(null);
    const res = await onLogoutDevice(deviceId);
    if (res.success) {
      setFeedback({ type: 'success', text: `Signed out of ${name}.` });
    } else {
      setFeedback({ type: 'error', text: res.error || 'Failed to sign out device.' });
    }
  };

  const handleLogoutAll = async () => {
    if (!confirm('This will sign out your account from all other TVs, mobile phones, and web browsers. Continue?')) {
      return;
    }
    setFeedback(null);
    const res = await onLogoutAll();
    if (res.success) {
      setFeedback({ type: 'success', text: 'Successfully signed out of all other devices.' });
    } else {
      setFeedback({ type: 'error', text: res.error || 'Failed to sign out all devices.' });
    }
  };

  return (
    <div className="space-y-8">
      {/* Intro Card */}
      <div className="bg-gradient-to-r from-white/[0.04] to-white/[0.01] border border-white/10 rounded-2xl p-6 sm:p-8 backdrop-blur-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h3 className="text-lg font-bold text-white flex items-center space-x-2">
            <Smartphone className="w-5 h-5 text-[#E5B869]" />
            <span>Manage Access & Devices</span>
          </h3>
          <p className="text-gray-400 text-xs sm:text-sm mt-1 max-w-xl">
            View all web browsers, smartphones, and smart TVs actively signed in to your account. Revoke access from devices you don&apos;t recognize to protect your subscription.
          </p>
        </div>

        {otherDevices.length > 0 && (
          <button
            type="button"
            disabled={actionLoading}
            onClick={handleLogoutAll}
            className="flex items-center space-x-2 bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition-all self-start sm:self-auto shrink-0"
          >
            <LogOut className="w-4 h-4" />
            <span>Sign Out All Other Devices</span>
          </button>
        )}
      </div>

      {feedback && (
        <div
          className={`p-4 rounded-xl flex items-center space-x-3 text-sm border ${
            feedback.type === 'success'
              ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400'
              : 'bg-red-500/10 border-red-500/20 text-red-400'
          }`}
        >
          {feedback.type === 'success' ? (
            <CheckCircle2 className="w-5 h-5 shrink-0" />
          ) : (
            <AlertCircle className="w-5 h-5 shrink-0" />
          )}
          <span>{feedback.text}</span>
        </div>
      )}

      {isLoading ? (
        <div className="py-12 flex justify-center">
          <Spinner size="lg" label="Checking connected devices..." />
        </div>
      ) : (
        <div className="space-y-6">
          {/* Current Device Highlight */}
          <div>
            <h4 className="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">
              This Device
            </h4>
            <div className="bg-gradient-to-r from-[#E5B869]/10 to-transparent border border-[#E5B869]/30 rounded-2xl p-5 flex items-center justify-between gap-4">
              <div className="flex items-center space-x-4">
                <div className="w-12 h-12 rounded-xl bg-[#E5B869]/20 text-[#E5B869] flex items-center justify-center shrink-0">
                  <DeviceIconRenderer platform={currentDevice?.platform} deviceName={currentDevice?.device_name} className="w-6 h-6" />
                </div>
                <div>
                  <div className="flex items-center space-x-2">
                    <h5 className="text-white font-bold text-base">
                      {currentDevice?.device_name || 'Web Browser'}
                    </h5>
                    <span className="flex items-center space-x-1 bg-emerald-500/20 text-emerald-400 text-[10px] font-bold uppercase px-2 py-0.5 rounded-full border border-emerald-500/30">
                      <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
                      <span>Active Now</span>
                    </span>
                  </div>
                  <p className="text-gray-400 text-xs mt-0.5">
                    {currentDevice?.platform || 'Desktop Browser'} • IP: {currentDevice?.device_id || 'Current Session'}
                  </p>
                </div>
              </div>
            </div>
          </div>

          {/* Other Devices */}
          <div>
            <h4 className="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">
              Other Connected Devices ({otherDevices.length})
            </h4>

            {otherDevices.length === 0 ? (
              <div className="bg-white/[0.02] border border-white/5 rounded-2xl p-8 text-center">
                <ShieldAlert className="w-8 h-8 text-gray-500 mx-auto mb-2" />
                <p className="text-gray-400 text-sm font-medium">No other devices are currently signed in.</p>
                <p className="text-gray-500 text-xs mt-1">
                  When you sign in on Smart TVs or the mobile app, they will appear here.
                </p>
              </div>
            ) : (
              <div className="space-y-3">
                {otherDevices.map((device) => (
                  <div
                    key={device.id}
                    className="bg-white/[0.03] border border-white/10 rounded-2xl p-4 sm:p-5 flex items-center justify-between gap-4 hover:border-white/20 transition-all"
                  >
                    <div className="flex items-center space-x-4">
                      <div className="w-10 h-10 rounded-xl bg-white/5 text-gray-300 flex items-center justify-center shrink-0">
                        <DeviceIconRenderer platform={device.platform} deviceName={device.device_name} className="w-5 h-5" />
                      </div>
                      <div>
                        <h5 className="text-white font-semibold text-sm">
                          {device.device_name || 'Connected Device'}
                        </h5>
                        <p className="text-gray-400 text-xs mt-0.5 flex items-center gap-2">
                          <span>{device.platform || 'Client'}</span>
                          {device.last_activity && (
                            <>
                              <span>•</span>
                              <span className="flex items-center gap-1 text-gray-400">
                                <Clock className="w-3 h-3" />
                                <span>Active recently</span>
                              </span>
                            </>
                          )}
                        </p>
                      </div>
                    </div>

                    <button
                      type="button"
                      disabled={actionLoading}
                      onClick={() => handleLogout(device.id, device.device_name || 'device')}
                      className="text-xs text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/20 px-3 py-1.5 rounded-lg transition-colors border border-red-500/20 font-medium shrink-0"
                    >
                      Sign Out
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
