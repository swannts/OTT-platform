'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { Crown, Clock, CheckCircle2, AlertTriangle, Film, Receipt, ChevronRight } from 'lucide-react';
import { Spinner } from '@/components/ui';
import { accountApi } from '../api/account.client';
import { formatDhakaDate, formatDhakaDateTime, formatDaysRemaining } from '@/lib/datetime';
import type { BillingSubscriptionRecord, BillingPayPerViewRecord, SubscriptionStatus } from '@/types';

interface BillingSectionProps {
  subscription: SubscriptionStatus | null;
  onRefresh: () => Promise<void>;
}

export function BillingSection({ subscription, onRefresh }: BillingSectionProps) {
  const [history, setHistory] = useState<{
    subscriptions: BillingSubscriptionRecord[];
    pay_per_view: BillingPayPerViewRecord[];
  }>({
    subscriptions: [],
    pay_per_view: [],
  });
  const [loading, setLoading] = useState(true);
  const [cancelling, setCancelling] = useState(false);
  const [cancelMessage, setCancelMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  useEffect(() => {
    async function loadData() {
      setLoading(true);
      try {
        const res = await accountApi.getBillingHistory();
        setHistory({
          subscriptions: res?.subscriptions || [],
          pay_per_view: res?.pay_per_view || [],
        });
      } catch (err) {
        console.error('Failed to load billing history', err);
      } finally {
        setLoading(false);
      }
    }
    loadData();
  }, []);

  const handleCancel = async () => {
    if (!confirm('Are you sure you want to cancel your VIP membership? You will lose access to premium movies, TV shows, and 4K streams.')) {
      return;
    }
    setCancelling(true);
    setCancelMessage(null);
    try {
      await accountApi.cancelSubscription();
      setCancelMessage({ type: 'success', text: 'Your subscription has been cancelled.' });
      await onRefresh();
      const res = await accountApi.getBillingHistory();
      setHistory({
        subscriptions: res?.subscriptions || [],
        pay_per_view: res?.pay_per_view || [],
      });
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Failed to cancel subscription';
      setCancelMessage({ type: 'error', text: msg });
    } finally {
      setCancelling(false);
    }
  };

  const isSubscribed = Boolean(subscription?.has_active_subscription);
  const activePlanName = subscription?.subscription?.name || 'NOOR VIP Membership';
  const renewalDate = subscription?.subscription?.end_date;

  return (
    <div className="space-y-8">
      {cancelMessage && (
        <div
          className={`p-4 rounded-xl flex items-center space-x-3 text-sm border ${
            cancelMessage.type === 'success'
              ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400'
              : 'bg-red-500/10 border-red-500/20 text-red-400'
          }`}
        >
          {cancelMessage.type === 'success' ? (
            <CheckCircle2 className="w-5 h-5 shrink-0" />
          ) : (
            <AlertTriangle className="w-5 h-5 shrink-0" />
          )}
          <span>{cancelMessage.text}</span>
        </div>
      )}

      {/* Membership Plan Banner */}
      <div className="bg-gradient-to-r from-[#E5B869]/15 via-white/[0.03] to-transparent border border-[#E5B869]/30 rounded-2xl p-6 sm:p-8 backdrop-blur-xl">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
          <div className="space-y-2">
            <div className="flex items-center space-x-3">
              <div className="w-10 h-10 rounded-xl bg-[#E5B869]/20 text-[#E5B869] flex items-center justify-center">
                <Crown className="w-5 h-5" />
              </div>
              <div>
                <span className="text-xs font-bold text-[#E5B869] tracking-wider uppercase">
                  {isSubscribed ? 'Active Membership' : 'Free Tier'}
                </span>
                <h3 className="text-2xl font-extrabold text-white">
                  {isSubscribed ? activePlanName : 'Standard Free Account'}
                </h3>
              </div>
            </div>

            {isSubscribed ? (
              <div className="flex flex-wrap items-center gap-3 pt-1 text-xs sm:text-sm text-gray-400">
                {renewalDate && (
                  <span className="flex items-center gap-1.5">
                    <Clock className="w-4 h-4 text-gray-500" />
                    <span>Next renewal:</span>
                    <strong className="text-white font-semibold">
                      {formatDhakaDate(renewalDate)}
                    </strong>
                  </span>
                )}
                {subscription?.days_left !== undefined && subscription?.days_left !== null && (
                  <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-[#E5B869]/15 text-[#E5B869] border border-[#E5B869]/30">
                    {formatDaysRemaining(subscription.days_left)} remaining
                  </span>
                )}
              </div>
            ) : (
              <p className="text-gray-400 text-xs sm:text-sm pt-1">
                Upgrade to unlock high-definition originals, ad-free streaming, and multi-screen access.
              </p>
            )}
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <Link
              href="/pricing"
              className="bg-[#E5B869] hover:bg-[#d9ab5c] text-black font-bold text-sm px-5 py-2.5 rounded-xl transition-all shadow-md shadow-[#E5B869]/20 flex items-center gap-1.5"
            >
              <span>{isSubscribed ? 'Change Plan' : 'Upgrade to VIP'}</span>
              <ChevronRight className="w-4 h-4" />
            </Link>

            {isSubscribed && (
              <button
                type="button"
                disabled={cancelling}
                onClick={handleCancel}
                className="text-xs text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/20 px-4 py-2.5 rounded-xl border border-red-500/20 font-semibold transition-colors"
              >
                {cancelling ? 'Cancelling...' : 'Cancel Membership'}
              </button>
            )}
          </div>
        </div>
      </div>

      {/* Invoices & History Section */}
      <div className="bg-white/[0.03] border border-white/10 rounded-2xl p-6 sm:p-8 backdrop-blur-xl">
        <h4 className="text-lg font-bold text-white mb-2 flex items-center space-x-2">
          <Receipt className="w-5 h-5 text-[#E5B869]" />
          <span>Billing History & Receipts</span>
        </h4>
        <p className="text-gray-400 text-xs mb-6">
          Track recent subscription charges and rental transactions.
        </p>

        {loading ? (
          <div className="py-8 flex justify-center">
            <Spinner size="md" label="Loading invoices..." />
          </div>
        ) : history.subscriptions.length === 0 && history.pay_per_view.length === 0 ? (
          <div className="py-8 text-center text-gray-500 text-xs">
            No invoices or billing records found for this account.
          </div>
        ) : (
          <div className="space-y-6">
            {/* Subscriptions Table */}
            {history.subscriptions.length > 0 && (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs">
                  <thead>
                    <tr className="border-b border-white/10 text-gray-400 uppercase tracking-wider">
                      <th className="py-3 px-4">Date</th>
                      <th className="py-3 px-4">Description</th>
                      <th className="py-3 px-4">Amount</th>
                      <th className="py-3 px-4">Payment Method</th>
                      <th className="py-3 px-4 text-right">Status</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-white/5 text-gray-300">
                    {history.subscriptions.map((sub) => (
                      <tr key={sub.id} className="hover:bg-white/[0.02] transition-colors">
                        <td className="py-3.5 px-4 font-mono text-gray-400">
                          {formatDhakaDate(sub.start_date || '')}
                        </td>
                        <td className="py-3.5 px-4 font-semibold text-white">
                          {sub.plan_name || 'VIP Subscription'}
                        </td>
                        <td className="py-3.5 px-4 font-bold text-white">
                          {sub.currency || '$'}{sub.amount || 0}
                        </td>
                        <td className="py-3.5 px-4 capitalize">
                          {sub.payment_type || 'Online'}
                        </td>
                        <td className="py-3.5 px-4 text-right">
                          <span
                            className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${
                              sub.status === 'paid' || sub.status === 'active' || sub.status === 'success'
                                ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                                : 'bg-white/10 text-gray-400'
                            }`}
                          >
                            {sub.status || 'Completed'}
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            {/* Pay Per View Records */}
            {history.pay_per_view.length > 0 && (
              <div className="pt-4 border-t border-white/10">
                <h5 className="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3 flex items-center gap-1.5">
                  <Film className="w-4 h-4 text-[#E5B869]" />
                  <span>Pay-Per-View Rentals</span>
                </h5>
                <div className="space-y-2">
                  {history.pay_per_view.map((ppv) => (
                    <div
                      key={ppv.id}
                      className="flex items-center justify-between p-3.5 rounded-xl bg-white/[0.02] border border-white/5 text-xs text-gray-300"
                    >
                      <div className="flex items-center space-x-3">
                        <Film className="w-4 h-4 text-gray-500" />
                        <div>
                          <p className="text-white font-semibold">{ppv.content_name || 'Movie Rental'}</p>
                          <p className="text-gray-500 text-[10px]">
                            {formatDhakaDateTime(ppv.date || '')}
                          </p>
                        </div>
                      </div>
                      <div className="text-right">
                        <span className="font-bold text-white block">
                          {ppv.currency || '$'}{ppv.amount || 0}
                        </span>
                        <span className="text-[10px] text-emerald-400 font-medium">Unlocked</span>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
