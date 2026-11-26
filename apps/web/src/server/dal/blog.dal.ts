import { api } from '@/lib/api';
import { cookies } from 'next/headers';
import {
  BlogCategory,
  BlogQueryParams,
  BlogListResponse,
  BlogDetailResponse,
} from '@/types';

async function getRequestLocale(): Promise<'en' | 'bn'> {
  const locale = (await cookies()).get('noor_locale')?.value;
  return locale === 'en' ? 'en' : 'bn';
}

export async function getBlogsDal(params: BlogQueryParams = {}): Promise<BlogListResponse> {
  try {
    const res = await api.getBlogs(params, await getRequestLocale());
    if (res && Array.isArray(res.data)) {
      return res;
    }
  } catch (error) {
    console.error('[DAL] getBlogsDal error:', error);
  }

  return {
    current_page: params.page || 1,
    data: [],
    last_page: 1,
    per_page: params.per_page || 9,
    total: 0,
  };
}

export async function getBlogPostDal(slugOrId: string | number): Promise<BlogDetailResponse | null> {
  try {
    const res = await api.getBlogPost(slugOrId, await getRequestLocale());
    if (res?.post) {
      return res;
    }
  } catch (error) {
    console.error('[DAL] getBlogPostDal error:', error);
  }

  return null;
}

export async function getBlogCategoriesDal(): Promise<BlogCategory[]> {
  try {
    const res = await api.getBlogCategories(await getRequestLocale());
    if (Array.isArray(res)) {
      return res;
    }
  } catch (error) {
    console.error('[DAL] getBlogCategoriesDal error:', error);
  }

  return [];
}
