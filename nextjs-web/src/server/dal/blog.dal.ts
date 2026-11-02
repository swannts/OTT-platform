import { api } from '@/lib/api';
import {
  BlogCategory,
  BlogQueryParams,
  BlogListResponse,
  BlogDetailResponse,
} from '@/types';

export async function getBlogsDal(params: BlogQueryParams = {}): Promise<BlogListResponse> {
  try {
    const res = await api.getBlogs(params);
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
    const res = await api.getBlogPost(slugOrId);
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
    const res = await api.getBlogCategories();
    if (Array.isArray(res)) {
      return res;
    }
  } catch (error) {
    console.error('[DAL] getBlogCategoriesDal error:', error);
  }

  return [];
}
