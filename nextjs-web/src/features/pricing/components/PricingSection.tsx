'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { Plan, PlanLimitation } from '@/types';
import { Crown, Check, Zap, Sparkles } from 'lucide-react';
import { Heading, Text, Badge } from '@/components/ui';

interface PricingSectionProps {
  initialPlans: Plan[];
}

export default function PricingSection({ initialPlans }: PricingSectionProps) {
  const [plans] = useState<Plan[]>(initialPlans);
  const [billingCycle, setBillingCycle] = useState<'month' | 'year'>('month');

  return (
    <div className="min-h-screen pt-24 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto pb-24 select-none">
      {/* Header */}
      <div className="text-center max-w-3xl mx-auto mb-16 space-y-4">
        <div className="inline-flex items-center space-x-2 bg-[#E5B869]/10 border border-[#E5B869]/30 text-[#E5B869] text-xs font-bold px-3.5 py-1.5 rounded-full uppercase tracking-wider">
          <Sparkles className="w-3.5 h-3.5 fill-[#E5B869]" />
          <span>VIP Halal Streaming</span>
        </div>
        <Heading as="h1" size="hero" className="tracking-tight drop-shadow-md">
          Choose Your Plan for Pure Islamic Entertainment
        </Heading>
        <Text variant="subtitle" size="base">
          Cancel anytime. Zero lock-in contracts. Stream spiritual series and live sacred broadcasts in 4K UHD.
        </Text>
      </div>

      {/* Plan Cards Grid */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-8 items-stretch">
        {plans.map((plan) => {
          const isFeatured = plan.identifier === 'premium' || plan.name?.toLowerCase().includes('vip');
          return (
            <div
              key={plan.plan_id || plan.id}
              className={`relative rounded-3xl p-8 flex flex-col justify-between transition-all duration-300 ${
                isFeatured
                  ? 'bg-gradient-to-b from-[#18202c] via-[#0e1622] to-[#070a0e] border-2 border-[#E5B869] shadow-[0_0_40px_rgba(229,184,105,0.15)] scale-105 z-10'
                  : 'bg-[#0c1218] border border-white/10 hover:border-white/20 shadow-xl'
              }`}
            >
              {isFeatured && (
                <div className="absolute -top-4 left-1/2 -translate-x-1/2 bg-[#E5B869] text-black font-extrabold text-[11px] px-4 py-1 rounded-full uppercase tracking-widest shadow-md">
                  Most Popular
                </div>
              )}

              <div>
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-xl font-bold text-white">{plan.name}</h3>
                  {isFeatured && <Crown className="w-5 h-5 text-[#E5B869]" />}
                </div>

                <div className="flex items-baseline mb-4">
                  <span className="text-4xl sm:text-5xl font-black text-white">
                    ৳{plan.price}
                  </span>
                  <span className="text-gray-400 text-sm ml-2 font-medium">
                    /{plan.duration_value} {plan.duration}
                  </span>
                </div>

                <p className="text-xs text-gray-400 mb-8 leading-relaxed">
                  {plan.description || 'Access sacred Islamic features, TV series, and live streaming channels.'}
                </p>

                {/* Features List */}
                <ul className="space-y-3.5 mb-8">
                  {(plan.plan_type || []).map((feat: PlanLimitation, idx: number) => (
                    <li key={idx} className="flex items-center text-xs text-gray-300">
                      <div className="w-4 h-4 rounded-full bg-[#E5B869]/20 text-[#E5B869] flex items-center justify-center mr-3 flex-shrink-0">
                        <Check className="w-2.5 h-2.5" />
                      </div>
                      <span>{feat.limitation_title}</span>
                    </li>
                  ))}
                </ul>
              </div>

              <Link
                href={`/checkout/${plan.plan_id || plan.id}`}
                className={`w-full py-3.5 rounded-full font-bold text-center text-sm transition-all shadow-md block cursor-pointer active:scale-95 ${
                  isFeatured
                    ? 'bg-[#E5B869] hover:bg-[#f3cf8a] text-black shadow-[#E5B869]/20'
                    : 'bg-white/10 hover:bg-white/20 text-white border border-white/10'
                }`}
              >
                Get Started
              </Link>
            </div>
          );
        })}
      </div>
    </div>
  );
}

