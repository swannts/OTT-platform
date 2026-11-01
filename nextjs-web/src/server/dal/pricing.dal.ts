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

  // Enterprise fallback plans with comprehensive limitations
  return [
    {
      plan_id: 1,
      id: 1,
      name: 'Basic Stream',
      identifier: 'basic',
      price: 4.99,
      total_price: 4.99,
      level: 1,
      duration: 'month',
      duration_value: 1,
      description: 'Essential entertainment on mobile & desktop in 1080p HD.',
      plan_type: [
        { id: 1, limitation_title: '1 Simultaneous Device', limitation_value: 1, slug: 'device-limit' },
        { id: 2, limitation_title: 'HD 1080p Quality', limitation_value: true, slug: 'video-quality' },
        { id: 3, limitation_title: 'Ad-Supported', limitation_value: true, slug: 'ads' },
      ],
    },
    {
      plan_id: 2,
      id: 2,
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
        { id: 3, limitation_title: '100% Ad-Free Experience', limitation_value: false, slug: 'ads' },
        { id: 4, limitation_title: 'Offline Video Downloads', limitation_value: true, slug: 'download-status' },
      ],
    },
    {
      plan_id: 3,
      id: 3,
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
        { id: 1, limitation_title: '6 Simultaneous Devices', limitation_value: 6, slug: 'device-limit' },
        { id: 2, limitation_title: 'Dolby Atmos & 4K Vision', limitation_value: true, slug: 'video-quality' },
        { id: 3, limitation_title: '100% Ad-Free Experience', limitation_value: false, slug: 'ads' },
        { id: 4, limitation_title: 'Early Access to New Releases', limitation_value: true, slug: 'early-access' },
      ],
    },
  ];
}
