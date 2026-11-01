'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import { Plan } from '@/types';
import { Crown, Check, Zap, Sparkles } from 'lucide-react';

export default function PricingPage() {
  const [plans, setPlans] = useState<Plan[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    async function loadPlans() {
      setIsLoading(true);
      try {
        const res = await api.getPlans();
        if (res && Array.isArray(res)) {
          setPlans(res);
        }
      } catch {
        // Fallback plans
        setPlans([
          {
            plan_id: 1,
            name: 'Basic Stream',
            identifier: 'basic',
            price: 4.99,
            total_price: 4.99,
            level: 1,
            duration: 'month',
            duration_value: 1,
            description: 'Essential entertainment on mobile & desktop in 1080p HD.',
            plan_type: [
              { id: 1, limitation_title: 'Simultaneous Devices', limitation_value: 1, slug: 'device-limit' },
              { id: 2, limitation_title: 'HD 1080p Quality', limitation_value: true, slug: 'video-quality' },
              { id: 3, limitation_title: 'Ad-Supported', limitation_value: true, slug: 'ads' },
            ],
          },
          {
            plan_id: 2,
            name: 'Premium VIP',
            identifier: 'premium',
            price: 9.99,
            total_price: 9.99,
            level: 2,
            duration: 'month',
            duration_value: 1,
            description: 'Ultra 4K HDR streaming with 4 simultaneous screens, ad-free experience, and offline downloads.',
            plan_type: [
              { id: 1, limitation_title: '4 Simultaneous Devices', limitation_value: 4, slug: 'device-limit' },
              { id: 2, limitation_title: '4K Ultra HD + HDR', limitation_value: true, slug: 'video-quality' },
              { id: 3, limitation_title: '100% Ad-Free', limitation_value: false, slug: 'ads' },
              { id: 4, limitation_title: 'Offline Video Downloads', limitation_value: true, slug: 'download-status' },
            ],
          },
          {
            plan_id: 3,
            name: 'Annual VIP Pass',
            identifier: 'annual',
            price: 89.99,
            discount_percentage: 25,
            total_price: 89.99,
            level: 3,
            duration: 'year',
            duration_value: 1,
            description: 'Full year of unlimited VIP streaming across all devices with 25% annual discount.',
            plan_type: [
              { id: 1, limitation_title: 'Unlimited Simultaneous Devices', limitation_value: 6, slug: 'device-limit' },
              { id: 2, limitation_title: 'Dolby Atmos & 4K Vision', limitation_value: true, slug: 'video-quality' },
              { id: 3, limitation_title: '100% Ad-Free Experience', limitation_value: false, slug: 'ads' },
              { id: 4, limitation_title: 'Early Access to New Releases', limitation_value: true, slug: 'early-access' },
            ],
          },
        ]);
      } finally {
        setIsLoading(false);
      }
    }

    loadPlans();
  }, []);

  return (
    <div className="min-h-screen pt-24 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto pb-24">
      {/* Header */}
      <div className="text-center max-w-3xl mx-auto mb-16 space-y-4">
        <div className="inline-flex items-center space-x-2 bg-red-600/10 border border-red-500/20 text-red-400 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">
          <Sparkles className="w-3.5 h-3.5" />
          <span>Flexible Streaming Plans</span>
        </div>
        <h1 className="text-3xl sm:text-5xl font-black text-white tracking-tight">
          Choose the Perfect Plan for Unlimited Entertainment
        </h1>
        <p className="text-sm sm:text-base text-gray-400">
          Cancel anytime. No lock-in contracts. Stream on your phone, tablet, laptop, and TV.
        </p>
      </div>

      {/* Plan Cards Grid */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-8 items-stretch">
        {plans.map((plan, idx) => {
          const isFeatured = idx === 1 || plan.level === 2;
          return (
            <div
              key={plan.plan_id}
              className={`relative rounded-3xl p-8 flex flex-col justify-between transition-all duration-300 ${
                isFeatured
                  ? 'bg-gradient-to-b from-red-950/40 via-neutral-900 to-black border-2 border-red-500/60 shadow-2xl shadow-red-900/30 md:-translate-y-4'
                  : 'glass-card border-white/10 hover:border-white/20'
              }`}
            >
              {isFeatured && (
                <div className="absolute -top-3.5 left-1/2 -translate-x-1/2 bg-red-600 text-white font-extrabold text-[11px] uppercase tracking-wider px-4 py-1 rounded-full shadow-lg">
                  Most Popular
                </div>
              )}

              <div>
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-xl font-bold text-white">{plan.name}</h3>
                  <Crown
                    className={`w-6 h-6 ${isFeatured ? 'text-amber-400' : 'text-gray-400'}`}
                  />
                </div>

                <div className="mb-6">
                  <span className="text-4xl sm:text-5xl font-black text-white">
                    ${plan.price}
                  </span>
                  <span className="text-xs text-gray-400 font-semibold uppercase ml-1.5">
                    / {plan.duration}
                  </span>
                </div>

                {plan.description && (
                  <p className="text-xs text-gray-400 leading-relaxed mb-6">
                    {plan.description}
                  </p>
                )}

                {/* Features List */}
                <div className="space-y-3 pt-4 border-t border-white/10 mb-8">
                  {plan.plan_type?.map((lim, i) => (
                    <div key={i} className="flex items-center space-x-2.5 text-xs text-gray-200">
                      <div className="w-4 h-4 rounded-full bg-red-600/20 text-red-500 flex items-center justify-center flex-shrink-0">
                        <Check className="w-2.5 h-2.5 stroke-[3]" />
                      </div>
                      <span>{lim.limitation_title}</span>
                    </div>
                  ))}
                </div>
              </div>

              <Link
                href={`/checkout/${plan.plan_id}`}
                className={`w-full text-center py-3.5 px-6 rounded-xl font-bold text-sm transition-all shadow-lg active:scale-95 ${
                  isFeatured
                    ? 'bg-red-600 hover:bg-red-700 text-white shadow-red-600/40'
                    : 'bg-white/10 hover:bg-white/20 text-white border border-white/10'
                }`}
              >
                Choose {plan.name}
              </Link>
            </div>
          );
        })}
      </div>
    </div>
  );
}

