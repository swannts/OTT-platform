'use client';

import React, { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { PriceCalculation } from '@/types';
import { useAuth } from '@/context/AuthContext';
import { Crown, Check, Tag, Shield, CreditCard, Lock, ArrowRight } from 'lucide-react';

export default function CheckoutPage() {
  const params = useParams();
  const router = useRouter();
  const { user } = useAuth();
  const planId = parseInt(params?.planId as string) || 1;

  const [calculation, setCalculation] = useState<PriceCalculation | null>(null);
  const [couponCode, setCouponCode] = useState('');
  const [appliedCoupon, setAppliedCoupon] = useState<string | null>(null);
  const [couponError, setCouponError] = useState<string | null>(null);
  const [paymentMethod, setPaymentMethod] = useState<'stripe' | 'razorpay' | 'paystack' | 'paypal'>('stripe');
  const [isLoading, setIsLoading] = useState(true);
  const [isProcessing, setIsProcessing] = useState(false);

  // Load live pricing breakdown
  const loadPricing = async (code?: string) => {
    setIsLoading(true);
    setCouponError(null);
    try {
      const calc = await api.calculatePrice(planId, code);
      setCalculation(calc);
      if (code) {
        setAppliedCoupon(code);
      }
    } catch (err: any) {
      if (code) {
        setCouponError(err.message || 'Invalid coupon code');
      } else {
        // Fallback pricing simulation
        setCalculation({
          plan_id: planId,
          plan_name: 'VIP Subscription',
          original_price: 9.99,
          plan_discount_amount: 0,
          price_after_plan_discount: 9.99,
          coupon: null,
          coupon_discount_amount: 0,
          subtotal: 9.99,
          total_tax: 0.80,
          taxes: [{ title: 'VAT (8%)', type: 'percentage', value: 8, tax_amount: 0.80 }],
          total_amount: 10.79,
          currency: 'USD',
        });
      }
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    loadPricing();
  }, [planId]);

  const handleApplyCoupon = (e: React.FormEvent) => {
    e.preventDefault();
    if (couponCode.trim()) {
      loadPricing(couponCode.trim());
    }
  };

  const handleRemoveCoupon = () => {
    setCouponCode('');
    setAppliedCoupon(null);
    loadPricing();
  };

  const handleCheckout = async () => {
    if (!user) {
      router.push(`/login?redirect=/checkout/${planId}`);
      return;
    }

    setIsProcessing(true);
    try {
      const res = await api.processSubscriptionPayment({
        plan_id: planId,
        payment_method: paymentMethod,
        coupon_code: appliedCoupon || undefined,
        redirect_url: `${window.location.origin}/payment-success`,
      });

      if (res.is_free) {
        router.push('/payment-success?free=1');
      } else if (res.redirect_url) {
        // Redirect to external Stripe / Gateway checkout session
        window.location.href = res.redirect_url;
      }
    } catch (err: any) {
      alert(err.message || 'Payment initiation failed. Please try again.');
    } finally {
      setIsProcessing(false);
    }
  };

  return (
    <div className="min-h-screen pt-24 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto pb-24">
      <div className="mb-8">
        <h1 className="text-2xl sm:text-3xl font-extrabold text-white">Complete Your Order</h1>
        <p className="text-xs sm:text-sm text-gray-400 mt-1">
          Review your subscription plan and proceed to secure checkout.
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-8 items-start">
        {/* Left 2 Cols: Order Summary & Payment Gateway Choice */}
        <div className="md:col-span-2 space-y-6">
          {/* Plan Details Card */}
          <div className="glass-card p-6 rounded-2xl space-y-4">
            <div className="flex items-center justify-between pb-4 border-b border-white/10">
              <div className="flex items-center space-x-3">
                <div className="w-10 h-10 rounded-xl bg-red-600/20 text-red-500 flex items-center justify-center font-bold">
                  <Crown className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="font-bold text-white text-base">
                    {calculation?.plan_name || 'Selected Plan'}
                  </h3>
                  <span className="text-xs text-gray-400">Unlimited Multi-Device Streaming</span>
                </div>
              </div>
              <span className="text-xl font-black text-white">
                ${calculation?.original_price || '9.99'}
              </span>
            </div>

            {/* Payment Gateway Selector */}
            <div className="space-y-3 pt-2">
              <h4 className="text-xs font-bold text-gray-300 uppercase tracking-wider">
                Select Payment Method
              </h4>

              <div className="grid grid-cols-2 gap-3">
                <button
                  type="button"
                  onClick={() => setPaymentMethod('stripe')}
                  className={`p-4 rounded-xl border flex flex-col items-center justify-center space-y-2 text-xs font-semibold transition-all ${
                    paymentMethod === 'stripe'
                      ? 'border-red-500 bg-red-600/10 text-white shadow-md'
                      : 'border-white/10 bg-white/5 text-gray-300 hover:border-white/20'
                  }`}
                >
                  <CreditCard className="w-6 h-6 text-red-500" />
                  <span>Credit / Debit Card (Stripe)</span>
                </button>

                <button
                  type="button"
                  onClick={() => setPaymentMethod('paypal')}
                  className={`p-4 rounded-xl border flex flex-col items-center justify-center space-y-2 text-xs font-semibold transition-all ${
                    paymentMethod === 'paypal'
                      ? 'border-red-500 bg-red-600/10 text-white shadow-md'
                      : 'border-white/10 bg-white/5 text-gray-300 hover:border-white/20'
                  }`}
                >
                  <Shield className="w-6 h-6 text-blue-400" />
                  <span>PayPal Checkout</span>
                </button>
              </div>
            </div>
          </div>

          {/* Coupon Code Section */}
          <div className="glass-card p-6 rounded-2xl space-y-3">
            <h4 className="text-xs font-bold text-gray-300 uppercase tracking-wider flex items-center space-x-1.5">
              <Tag className="w-3.5 h-3.5 text-red-500" />
              <span>Promo Code / Discount Coupon</span>
            </h4>

            {appliedCoupon ? (
              <div className="flex items-center justify-between bg-green-500/10 border border-green-500/30 text-green-400 px-4 py-2.5 rounded-xl text-xs">
                <span>Coupon &ldquo;{appliedCoupon}&rdquo; applied successfully!</span>
                <button
                  onClick={handleRemoveCoupon}
                  className="text-red-400 hover:underline font-bold"
                >
                  Remove
                </button>
              </div>
            ) : (
              <form onSubmit={handleApplyCoupon} className="flex gap-2">
                <input
                  type="text"
                  value={couponCode}
                  onChange={(e) => setCouponCode(e.target.value.toUpperCase())}
                  placeholder="Enter code (e.g. VIP20)"
                  className="flex-1 bg-white/5 border border-white/10 text-white text-xs px-4 py-2.5 rounded-xl focus:outline-none focus:border-red-500 uppercase font-mono"
                />
                <button
                  type="submit"
                  className="bg-white/10 hover:bg-white/20 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition-colors border border-white/10"
                >
                  Apply
                </button>
              </form>
            )}

            {couponError && (
              <p className="text-xs text-red-400 font-medium">{couponError}</p>
            )}
          </div>
        </div>

        {/* Right Col: Price Breakdown & Checkout Action */}
        <div className="glass-card p-6 rounded-2xl space-y-4">
          <h3 className="font-bold text-white text-base pb-3 border-b border-white/10">
            Order Summary
          </h3>

          <div className="space-y-2.5 text-xs text-gray-300">
            <div className="flex justify-between">
              <span>Original Price</span>
              <span>${calculation?.original_price || '0.00'}</span>
            </div>

            {calculation?.plan_discount_amount ? (
              <div className="flex justify-between text-green-400">
                <span>Plan Discount</span>
                <span>-${calculation.plan_discount_amount}</span>
              </div>
            ) : null}

            {calculation?.coupon_discount_amount ? (
              <div className="flex justify-between text-green-400">
                <span>Coupon Discount</span>
                <span>-${calculation.coupon_discount_amount}</span>
              </div>
            ) : null}

            {calculation?.taxes?.map((t, idx) => (
              <div key={idx} className="flex justify-between text-gray-400">
                <span>{t.title}</span>
                <span>${t.tax_amount}</span>
              </div>
            ))}

            <div className="pt-3 border-t border-white/10 flex justify-between items-baseline text-white">
              <span className="font-bold text-sm">Total Due Today</span>
              <span className="font-black text-2xl text-red-500">
                ${calculation?.total_amount || '0.00'}
              </span>
            </div>
          </div>

          <button
            onClick={handleCheckout}
            disabled={isProcessing || isLoading}
            className="w-full bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-red-600/40 flex items-center justify-center space-x-2 text-sm transition-transform active:scale-95 mt-4"
          >
            {isProcessing ? (
              <span>Connecting to gateway...</span>
            ) : (
              <>
                <Lock className="w-4 h-4" />
                <span>Pay Securely</span>
                <ArrowRight className="w-4 h-4" />
              </>
            )}
          </button>

          <p className="text-[11px] text-gray-500 text-center flex items-center justify-center space-x-1 pt-2">
            <Shield className="w-3.5 h-3.5" />
            <span>256-bit SSL encrypted & secure checkout</span>
          </p>
        </div>
      </div>
    </div>
  );
}

