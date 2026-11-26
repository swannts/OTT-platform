import { api } from '@/lib/api';
import { Plan } from '@/types';

export async function getPricingPlansDal(): Promise<Plan[]> {
  try {
    const res = await api.getPlans();
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
