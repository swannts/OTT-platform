'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';
import { api } from '@/lib/api';
import { BillingSubscriptionRecord, BillingPayPerViewRecord } from '@/types';
import {
  CreditCard,
  User as UserIcon,
  Shield,
  Users,
  Download,
  Crown,
  AlertTriangle,
  Film,
  CheckCircle2,
  Clock,
} from 'lucide-react';

export default function BillingPage() {
  const { user, subscription, refreshUser } = useAuth();
  const router = useRouter();

  const [history, setHistory] = useState<{
    subscriptions: BillingSubscriptionRecord[];
    pay_per_view: BillingPayPerViewRecord[];
  }>({
    subscriptions: [],
    pay_per_view: [],
  });
  const [loading, setLoading] = useState(true);
  const [cancelling, setCancelling] = useState(false);
  const [cancelMessage, setCancelMessage] = useState<string | null>(null);

  useEffect(() => {
    if (!user) {
      router.push('/login');
      return;
    }

    async function loadData() {
      setLoading(true);
      try {
        const res = await api.getBillingHistory();
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
  }, [user, router]);

  const handleCancelSubscription = async () => {
    if (!confirm('Are you sure you want to cancel your VIP membership? You will lose access to premium content.')) {
      return;
    }
    setCancelling(true);
    setCancelMessage(null);
    try {
      await api.cancelSubscription();
      setCancelMessage('Your subscription has been cancelled.');
      await refreshUser();
      const res = await api.getBillingHistory();
      setHistory({
        subscriptions: res?.subscriptions || [],
        pay_per_view: res?.pay_per_view || [],
      });
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to cancel subscription.';
      setCancelMessage(message);
    } finally {
      setCancelling(false);
    }
  };

  if (!user) return null;

  return (
    <div className="min-h-screen pt-24 pb-16 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto">
      {/* Header */}
      <div className="border-b border-white/10 pb-6 mb-8">
        <h1 className="text-3xl font-extrabold text-white tracking-tight">Billing & Transactions</h1>
        <p className="text-gray-400 text-sm mt-1">Review active membership, invoices, and pay-per-view rentals</p>
      </div>

      {/* Navigation Sub-Tabs */}
      <div className="flex flex-wrap gap-2 mb-8 border-b border-white/10 pb-4">
        <Link
          href="/account"
          className="flex items-center space-x-2 px-4 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/5 text-sm font-medium transition-colors"
        >
          <UserIcon className="w-4 h-4" />
          <span>General Info</span>
        </Link>
        <Link
          href="/account/billing"
          className="flex items-center space-x-2 px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold shadow-md"
        >
          <CreditCard className="w-4 h-4" />
          <span>Billing & History</span>
        </Link>
        <Link
          href="/account/security"
          className="flex items-center space-x-2 px-4 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/5 text-sm font-medium transition-colors"
        >
          <Shield className="w-4 h-4" />
          <span>Security & PIN</span>
        </Link>
        <Link
          href="/profiles"
          className="flex items-center space-x-2 px-4 py-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/5 text-sm font-medium transition-colors"
        >
          <Users className="w-4 h-4" />
          <span>Profiles</span>
        </Link>
      </div>

      <div className="space-y-8">
        {/* Active Subscription Banner */}
        <div className="glass-card rounded-2xl p-6 sm:p-8 border border-white/10 relative overflow-hidden">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div className="flex items-start space-x-4">
              <div className="w-12 h-12 rounded-xl bg-amber-500/20 border border-amber-500/40 flex items-center justify-center text-amber-400">
                <Crown className="w-6 h-6" />
              </div>
              <div>
                <span className="text-xs uppercase tracking-wider font-bold text-gray-400">Current Plan</span>
                <h2 className="text-2xl font-black text-white mt-0.5">
                  {subscription?.has_active_subscription
                    ? subscription.subscription?.name || 'VIP Membership'
                    : 'Free Tier'}
                </h2>
                {subscription?.has_active_subscription ? (
                  <p className="text-sm text-gray-300 mt-1">
                    Renews / Expires:{' '}
                    <span className="text-white font-semibold">
                      {subscription.subscription?.end_date || 'Active'}
                    </span>{' '}
                    ({subscription.days_left} days remaining)
                  </p>
                ) : (
                  <p className="text-sm text-gray-400 mt-1">Upgrade today to stream ad-free 4K video content.</p>
                )}
              </div>
            </div>

            <div className="flex items-center space-x-3">
              {subscription?.has_active_subscription ? (
                <button
                  onClick={handleCancelSubscription}
                  disabled={cancelling}
                  className="text-xs text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/20 px-4 py-2.5 rounded-xl border border-red-500/20 transition-colors"
                >
                  {cancelling ? 'Cancelling...' : 'Cancel Subscription'}
                </button>
              ) : (
                <Link
                  href="/pricing"
                  className="bg-red-600 hover:bg-red-700 text-white text-xs font-bold px-6 py-2.5 rounded-xl transition-colors shadow-lg shadow-red-600/30"
                >
                  Subscribe Now
                </Link>
              )}
            </div>
          </div>

          {cancelMessage && (
            <div className="mt-4 p-3 rounded-xl bg-white/10 text-xs text-gray-200 border border-white/10">
              {cancelMessage}
            </div>
          )}
        </div>

        {/* Subscription Transactions History */}
        <div className="glass-card rounded-2xl p-6 sm:p-8 border border-white/10">
          <h3 className="text-lg font-bold text-white mb-1">Subscription Invoices</h3>
          <p className="text-xs text-gray-400 mb-6">List of past membership charges and payment invoices</p>

          {loading ? (
            <div className="text-center py-8 text-gray-500 text-sm">Loading billing history...</div>
          ) : history.subscriptions.length === 0 ? (
            <div className="text-center py-8 text-gray-500 text-sm">No subscription invoices found.</div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead>
                  <tr className="border-b border-white/10 text-xs text-gray-400 uppercase tracking-wider">
                    <th className="pb-3 font-semibold">Plan</th>
                    <th className="pb-3 font-semibold">Period</th>
                    <th className="pb-3 font-semibold">Amount</th>
                    <th className="pb-3 font-semibold">Status</th>
                    <th className="pb-3 font-semibold text-right">Invoice</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-white/5 text-gray-300">
                  {history.subscriptions.map((sub: BillingSubscriptionRecord) => (
                    <tr key={sub.id} className="hover:bg-white/5 transition-colors">
                      <td className="py-4 font-medium text-white">{sub.plan_name}</td>
                      <td className="py-4 text-xs text-gray-400">
                        {sub.start_date} to {sub.end_date}
                      </td>
                      <td className="py-4 font-semibold text-white">
                        {sub.currency || '$'}{Number(sub.amount || 0).toFixed(2)}
                      </td>
                      <td className="py-4">
                        <span
                          className={`text-[11px] font-bold px-2 py-0.5 rounded-full uppercase ${
                            sub.status === 'active'
                              ? 'bg-green-500/20 text-green-400'
                              : 'bg-gray-500/20 text-gray-400'
                          }`}
                        >
                          {sub.status}
                        </span>
                      </td>
                      <td className="py-4 text-right">
                        <a
                          href={`http://localhost:8000/api/download-invoice/${sub.invoice_id}`}
                          target="_blank"
                          rel="noreferrer"
                          className="inline-flex items-center space-x-1 text-xs text-red-400 hover:text-red-300"
                        >
                          <Download className="w-3.5 h-3.5" />
                          <span>PDF</span>
                        </a>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {/* Pay-Per-View Rentals History */}
        <div className="glass-card rounded-2xl p-6 sm:p-8 border border-white/10">
          <div className="flex items-center space-x-2 mb-1">
            <Film className="w-5 h-5 text-blue-400" />
            <h3 className="text-lg font-bold text-white">Pay-Per-View Rentals</h3>
          </div>
          <p className="text-xs text-gray-400 mb-6">Movies and exclusive releases unlocked on-demand</p>

          {loading ? (
            <div className="text-center py-8 text-gray-500 text-sm">Loading rentals...</div>
          ) : history.pay_per_view.length === 0 ? (
            <div className="text-center py-8 text-gray-500 text-sm">No pay-per-view rentals found.</div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead>
                  <tr className="border-b border-white/10 text-xs text-gray-400 uppercase tracking-wider">
                    <th className="pb-3 font-semibold">Title</th>
                    <th className="pb-3 font-semibold">Rental Date</th>
                    <th className="pb-3 font-semibold">Amount</th>
                    <th className="pb-3 font-semibold">Access</th>
                    <th className="pb-3 font-semibold text-right">Receipt</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-white/5 text-gray-300">
                  {history.pay_per_view.map((ppv: BillingPayPerViewRecord) => (
                    <tr key={ppv.id} className="hover:bg-white/5 transition-colors">
                      <td className="py-4 font-medium text-white">{ppv.content_name}</td>
                      <td className="py-4 text-xs text-gray-400">{ppv.date}</td>
                      <td className="py-4 font-semibold text-white">
                        {ppv.currency || '$'}{Number(ppv.amount || 0).toFixed(2)}
                      </td>
                      <td className="py-4">
                        <span className="text-[11px] font-bold px-2 py-0.5 rounded-full uppercase bg-blue-500/20 text-blue-400">
                          Unlocked
                        </span>
                      </td>
                      <td className="py-4 text-right">
                        <a
                          href={`http://localhost:8000/api/pay-per-view-invoice/${ppv.invoice_id}`}
                          target="_blank"
                          rel="noreferrer"
                          className="inline-flex items-center space-x-1 text-xs text-red-400 hover:text-red-300"
                        >
                          <Download className="w-3.5 h-3.5" />
                          <span>Receipt</span>
                        </a>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

