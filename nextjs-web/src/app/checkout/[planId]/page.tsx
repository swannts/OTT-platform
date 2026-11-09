'use client';

import React, { useEffect, useState, useMemo } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { PriceCalculation, PaymentMethodsData, AppConfiguration } from '@/types';
import { useAuth, useLanguage } from '@/providers';
import { Spinner } from '@/components/ui';
import { Crown, Tag, Shield, CreditCard, Lock, ArrowRight, Wallet, CheckCircle2 } from 'lucide-react';
import { SHOWCASE_MODE } from '@/lib/showcase';

interface GatewayOption {
  id: string;
  name: string;
  description: string;
  icon: typeof CreditCard;
}

export default function CheckoutPage() {
  const params = useParams();
  const router = useRouter();
  const { user } = useAuth();
  const { t } = useLanguage();
  const planId = parseInt(params?.planId as string) || 1;

  const [calculation, setCalculation] = useState<PriceCalculation | null>(null);
  const [appConfig, setAppConfig] = useState<AppConfiguration | null>(null);
  const [activeGateways, setActiveGateways] = useState<GatewayOption[]>([]);
  const [selectedGateway, setSelectedGateway] = useState<string>('stripe');
  const [couponCode, setCouponCode] = useState('');
  const [appliedCoupon, setAppliedCoupon] = useState<string | null>(null);
  const [couponError, setCouponError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isProcessing, setIsProcessing] = useState(false);

  // Currency resolver
  const currencySymbol = useMemo(() => {
    return (
      calculation?.currency ||
      appConfig?.currency?.currency_symbol ||
      appConfig?.currency?.currency_code ||
      '$'
    );
  }, [calculation, appConfig]);

  // Load available gateways and app configuration
  useEffect(() => {
    async function loadConfigAndGateways() {
      try {
        const [methods, config] = await Promise.all([
          api.getPaymentMethods().catch(() => ({} as PaymentMethodsData)),
          api.getAppConfiguration().catch(() => ({} as AppConfiguration)),
        ]);

        if (config) setAppConfig(config);

        const available: GatewayOption[] = [];

        if (methods.stripe_pay?.stripe_publickey) {
          available.push({
            id: 'stripe',
            name: 'Credit / Debit Card',
            description: 'Visa, Mastercard, Amex via Stripe',
            icon: CreditCard,
          });
        }
        if (methods.paypal_pay?.paypal_clientid) {
          available.push({
            id: 'paypal',
            name: 'PayPal',
            description: 'Fast and secure checkout with PayPal',
            icon: Shield,
          });
        }
        if (methods.razor_pay?.razorpay_publickey) {
          available.push({
            id: 'razorpay',
            name: 'Razorpay',
            description: 'UPI, NetBanking, Cards & Wallets',
            icon: Wallet,
          });
        }
        if (methods.paystack_pay?.paystack_publickey) {
          available.push({
            id: 'paystack',
            name: 'Paystack',
            description: 'Debit Card, Bank Transfer, Mobile Money',
            icon: CreditCard,
          });
        }
        if (methods.flutterwave_pay?.flutterwave_publickey) {
          available.push({
            id: 'flutterwave',
            name: 'Flutterwave',
            description: 'Cards, Mobile Money & Bank Account',
            icon: Wallet,
          });
        }

        // Fallback default gateway if none explicitly enabled in test DB
        if (available.length === 0) {
          available.push({
            id: 'stripe',
            name: 'Credit / Debit Card',
            description: 'Secure card checkout',
            icon: CreditCard,
          });
        }

        setActiveGateways(available);
        setSelectedGateway(available[0]?.id || 'stripe');
      } catch {
        // Fallback gracefully
        setActiveGateways([
          {
            id: 'stripe',
            name: 'Credit / Debit Card',
            description: 'Secure card checkout',
            icon: CreditCard,
          },
        ]);
      }
    }

    loadConfigAndGateways();
  }, []);

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
    } catch (err: unknown) {
      if (code) {
        setCouponError(err instanceof Error ? err.message : t('invalid_coupon', 'Invalid promo code'));
      } else {
        setCalculation(null);
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
        payment_method: selectedGateway,
        coupon_code: appliedCoupon || undefined,
        redirect_url: `${window.location.origin}/payment-success`,
      });

      if (res.is_free) {
        router.push('/payment-success?free=1');
      } else if (res.redirect_url) {
        window.location.href = res.redirect_url;
      }
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : t('payment_failed', 'Payment initiation failed. Please try again.'));
    } finally {
      setIsProcessing(false);
    }
  };

  if (isLoading) {
    return (
      <div className="min-h-screen pt-36 text-center text-white space-y-4">
        <Spinner size="lg" label={t('loading_checkout', 'Calculating secure checkout pricing...')} />
      </div>
    );
  }

  if (!calculation) {
    return (
      <div className="min-h-screen pt-36 text-center text-white px-4 space-y-4">
        <h2 className="text-xl font-bold">{t('unable_load_pricing', 'Unable to load plan pricing')}</h2>
        <p className="text-sm text-gray-400">
          {t('could_not_retrieve_pricing', 'Could not retrieve pricing details for the requested plan.')}
        </p>
        <button
          type="button"
          onClick={() => loadPricing()}
          className="px-6 py-2.5 rounded-full bg-[#E5B869] text-black font-bold text-xs hover:bg-[#f3cf8a] transition-all cursor-pointer"
        >
          {t('try_again', 'Try Again')}
        </button>
      </div>
    );
  }

  return (
    <div className="min-h-screen pt-24 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto pb-24">
      {SHOWCASE_MODE && (
        <p className="mb-6 rounded-xl border border-amber-400/30 bg-amber-400/10 px-4 py-3 text-sm text-amber-100" role="status">
          Showcase checkout: submitting only simulates success. No payment details are collected and no charge is made.
        </p>
      )}
      <div className="mb-8">
        <h1 className="text-2xl sm:text-3xl font-extrabold text-white">
          {t('complete_order', 'Complete Your Order')}
        </h1>
        <p className="text-xs sm:text-sm text-gray-400 mt-1">
          {t('checkout_subtitle', 'Review your VIP subscription plan and proceed to secure checkout.')}
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-8 items-start">
        {/* Left 2 Cols: Order Summary & Dynamic Payment Gateway Selection */}
        <div className="md:col-span-2 space-y-6">
          {/* Plan Details Card */}
          <div className="glass-card p-6 rounded-2xl space-y-4 border border-white/10 bg-[#090e15]/90 backdrop-blur-xl">
            <div className="flex items-center justify-between pb-4 border-b border-white/10">
              <div className="flex items-center space-x-3">
                <div className="w-11 h-11 rounded-2xl bg-[#E5B869]/20 border border-[#E5B869]/40 text-[#E5B869] flex items-center justify-center font-bold shadow-lg">
                  <Crown className="w-6 h-6" />
                </div>
                <div>
                  <h3 className="font-bold text-white text-base">
                    {calculation.plan_name || 'VIP Membership Plan'}
                  </h3>
                  <span className="text-xs text-gray-400">
                    {t('unlimited_streaming', 'Unlimited 4K Ultra HD Streaming across all devices')}
                  </span>
                </div>
              </div>
              <span className="text-xl font-black text-white">
                {currencySymbol}
                {calculation.original_price}
              </span>
            </div>

            {/* Dynamic Payment Gateways */}
            <div className="space-y-3 pt-2">
              <h4 className="text-xs font-bold text-gray-300 uppercase tracking-wider">
                {t('select_payment_method', 'Select Payment Method')}
              </h4>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {activeGateways.map((gw) => {
                  const Icon = gw.icon;
                  const isSelected = selectedGateway === gw.id;
                  return (
                    <button
                      key={gw.id}
                      type="button"
                      onClick={() => setSelectedGateway(gw.id)}
                      className={`p-4 rounded-xl border flex items-start space-x-3 text-left transition-all cursor-pointer relative ${
                        isSelected
                          ? 'border-[#E5B869] bg-[#E5B869]/15 shadow-md shadow-[#E5B869]/10'
                          : 'border-white/10 bg-white/[0.03] hover:bg-white/[0.07] hover:border-white/20'
                      }`}
                    >
                      <div
                        className={`w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 ${
                          isSelected
                            ? 'bg-[#E5B869] text-black'
                            : 'bg-white/5 text-gray-300'
                        }`}
                      >
                        <Icon className="w-5 h-5" />
                      </div>
                      <div className="min-w-0 flex-1">
                        <div className="flex items-center justify-between">
                          <p className="font-semibold text-xs text-white truncate">{gw.name}</p>
                          {isSelected && <CheckCircle2 className="w-4 h-4 text-[#E5B869]" />}
                        </div>
                        <p className="text-[11px] text-gray-400 mt-0.5 line-clamp-1">{gw.description}</p>
                      </div>
                    </button>
                  );
                })}
              </div>
            </div>
          </div>

          {/* Promo Code Section */}
          <div className="glass-card p-6 rounded-2xl space-y-3 border border-white/10 bg-[#090e15]/90 backdrop-blur-xl">
            <h4 className="text-xs font-bold text-gray-300 uppercase tracking-wider flex items-center space-x-1.5">
              <Tag className="w-3.5 h-3.5 text-[#E5B869]" />
              <span>{t('promo_code', 'Promo Code / Discount Coupon')}</span>
            </h4>

            {appliedCoupon ? (
              <div className="flex items-center justify-between bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-4 py-2.5 rounded-xl text-xs">
                <span>
                  {t('coupon_applied', 'Coupon')} &ldquo;{appliedCoupon}&rdquo; {t('applied_successfully', 'applied successfully!')}
                </span>
                <button
                  type="button"
                  onClick={handleRemoveCoupon}
                  className="text-red-400 hover:text-red-300 hover:underline font-bold cursor-pointer"
                >
                  {t('remove', 'Remove')}
                </button>
              </div>
            ) : (
              <form onSubmit={handleApplyCoupon} className="flex gap-2">
                <input
                  type="text"
                  value={couponCode}
                  onChange={(e) => setCouponCode(e.target.value.toUpperCase())}
                  placeholder={t('enter_coupon', 'Enter code (e.g. NOOR20)')}
                  className="flex-1 bg-white/5 border border-white/10 text-white text-xs px-4 py-2.5 rounded-xl focus:outline-none focus:border-[#E5B869] uppercase font-mono"
                />
                <button
                  type="submit"
                  className="bg-white/10 hover:bg-white/20 text-white text-xs font-bold px-5 py-2.5 rounded-xl transition-colors border border-white/10 cursor-pointer"
                >
                  {t('apply', 'Apply')}
                </button>
              </form>
            )}

            {couponError && (
              <p className="text-xs text-red-400 font-medium">{couponError}</p>
            )}
          </div>
        </div>

        {/* Right Col: Price Breakdown & Checkout Action */}
        <div className="glass-card p-6 rounded-2xl space-y-4 border border-white/10 bg-[#090e15]/90 backdrop-blur-xl">
          <h3 className="font-bold text-white text-base pb-3 border-b border-white/10">
            {t('order_summary', 'Order Summary')}
          </h3>

          <div className="space-y-2.5 text-xs text-gray-300">
            <div className="flex justify-between">
              <span>{t('original_price', 'Original Price')}</span>
              <span>
                {currencySymbol}
                {calculation.original_price}
              </span>
            </div>

            {calculation.plan_discount_amount ? (
              <div className="flex justify-between text-emerald-400 font-medium">
                <span>{t('plan_discount', 'Plan Discount')}</span>
                <span>
                  -{currencySymbol}
                  {calculation.plan_discount_amount}
                </span>
              </div>
            ) : null}

            {calculation.coupon_discount_amount ? (
              <div className="flex justify-between text-emerald-400 font-medium">
                <span>{t('coupon_discount', 'Coupon Discount')}</span>
                <span>
                  -{currencySymbol}
                  {calculation.coupon_discount_amount}
                </span>
              </div>
            ) : null}

            {calculation.taxes?.map((tItem, idx) => (
              <div key={idx} className="flex justify-between text-gray-400">
                <span>{tItem.title}</span>
                <span>
                  {currencySymbol}
                  {tItem.tax_amount}
                </span>
              </div>
            ))}

            <div className="pt-3 border-t border-white/10 flex justify-between items-baseline text-white">
              <span className="font-bold text-sm">{t('total_due', 'Total Due Today')}</span>
              <span className="font-black text-2xl text-[#E5B869]">
                {currencySymbol}
                {calculation.total_amount}
              </span>
            </div>
          </div>

          <button
            type="button"
            onClick={handleCheckout}
            disabled={isProcessing || isLoading}
            className="w-full bg-[#E5B869] hover:bg-[#f3cf8a] disabled:opacity-50 text-black font-extrabold py-3.5 rounded-xl shadow-lg shadow-[#E5B869]/25 flex items-center justify-center space-x-2 text-sm transition-all cursor-pointer hover:scale-[1.01] active:scale-95 mt-4"
          >
            {isProcessing ? (
              <span>{t('connecting_gateway', 'Connecting to secure gateway...')}</span>
            ) : (
              <>
                <Lock className="w-4 h-4" />
                <span>{t('pay_securely', 'Pay Securely Now')}</span>
                <ArrowRight className="w-4 h-4" />
              </>
            )}
          </button>

          <p className="text-[11px] text-gray-400 text-center flex items-center justify-center space-x-1 pt-2">
            <Shield className="w-3.5 h-3.5 text-[#E5B869]" />
            <span>{t('encrypted_checkout', '256-bit SSL encrypted & authentic checkout')}</span>
          </p>
        </div>
      </div>
    </div>
  );
}
