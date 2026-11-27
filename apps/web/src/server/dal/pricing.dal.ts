import { api } from '@/lib/api';
import { cookies } from 'next/headers';
import { Plan } from '@/types';

export async function getPricingPlansDal(): Promise<Plan[]> {
  try {
    const locale = (await cookies()).get('noor_locale')?.value === 'en' ? 'en' : 'bn';
    const res = await api.getPlans(locale);
    if (Array.isArray(res) && res.length > 0) {
      return res;
    }
    const anyRes = res as unknown as { data?: Plan[] };
    if (anyRes?.data && Array.isArray(anyRes.data) && anyRes.data.length > 0) {
      return anyRes.data;
    }
  } catch (error) {
    console.error('[DAL] getPricingPlansDal error:', error);
  }

  return [];
}
