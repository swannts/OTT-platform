import {
  AuthResponse,
  LoginResponse,
  ContentDetails,
  LiveChannel,
  LiveTvPlaybackDetails,
  MediaItem,
  Plan,
  PriceCalculation,
  SubscriptionStatus,
  User,
  BlogQueryParams,
  BlogListResponse,
  BlogDetailResponse,
  BlogCategory,
  UserMultiProfile,
  BillingHistoryResponse,
  CastDetails,
  Episode,
  LiveCategory,
  SearchDataResult,
  DashboardData,
  NotificationItem,
  SeoMetadata,
  PaymentMethodsData,
  AppConfiguration,
  ReviewItem,
  ReviewListResponse,
} from '@/types';

export function normalizeMediaItem(raw: unknown): MediaItem {
  if (!raw || typeof raw !== 'object') {
    return {
      id: '',
      name: 'Untitled',
      title: 'Untitled',
      type: 'movie',
      access: 'free',
    };
  }
  const r = raw as Record<string, unknown>;
  const details = (r.details && typeof r.details === 'object' ? r.details : {}) as Record<string, unknown>;
  const genres = details.genres || r.genres || r.genre_data || [];
  const genreList = Array.isArray(genres)
    ? genres.map((g: unknown) => (typeof g === 'string' ? g : (g && typeof g === 'object' && 'name' in g ? String((g as { name: unknown }).name) : '')))
    : [];

  const rawType = (details.type || r.type || 'movie') as string;
  const validTypes = ['movie', 'tvshow', 'episode', 'video', 'livetv'] as const;
  const resolvedType = validTypes.includes(rawType as (typeof validTypes)[number])
    ? (rawType as (typeof validTypes)[number])
    : 'movie';

  const rawAccess = (details.access || r.movie_access || r.access || 'free') as string;
  const validAccess = ['free', 'paid', 'unlock'] as const;
  const resolvedAccess = validAccess.includes(rawAccess as (typeof validAccess)[number])
    ? (rawAccess as (typeof validAccess)[number])
    : 'free';

  return {
    id: (details.id as number | string) || (r.id as number | string) || (r.type_id as number | string) || '',
    name: (details.name as string) || (r.name as string) || (r.title as string) || 'Untitled',
    title: (details.name as string) || (r.name as string) || (r.title as string) || 'Untitled',
    type: resolvedType,
    description: (details.description as string) || (r.description as string) || (r.short_desc as string) || '',
    poster_image: (r.poster_image as string) || (r.poster_url as string) || (r.thumbnail_image as string) || (r.poster_tv_image as string),
    thumbnail_image: (r.poster_tv_image as string) || (r.thumbnail_image as string) || (r.poster_image as string),
    banner_image: (r.poster_tv_image as string) || (r.poster_image as string) || (r.banner_image as string),
    duration: (details.duration as string) || (r.duration as string) || '',
    release_year: details.release_date
      ? String(details.release_date).substring(0, 4)
      : r.release_date
      ? String(r.release_date).substring(0, 4)
      : (r.release_year as string | number | undefined),
    imdb_rating: (details.imdb_rating as string | number) || (r.imdb_rating as string | number) || (r.rating as string | number),
    access: resolvedAccess,
    genres: genreList,
    genre_data: genreList,
    has_content_access: Boolean(details.has_content_access ?? r.has_content_access),
    is_watchlist: Boolean(details.is_in_watchlist ?? r.is_watch_list ?? r.is_watchlist),
  };
}

const getApiBaseUrl = (): string => {
  // Server-side: use INTERNAL_API_URL or fallback to localhost
  if (typeof window === 'undefined') {
    if (process.env.INTERNAL_API_URL) {
      return process.env.INTERNAL_API_URL;
    }
    return process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';
  }
  // Client-side inside user's browser
  return process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';
};

class ApiClient {
  private currentLocale: string = 'en';

  public setLocale(locale: string) {
    this.currentLocale = locale;
  }

  public getLocale(): string {
    if (typeof window !== 'undefined') {
      try {
        const stored = localStorage.getItem('noor_locale');
        if (stored) return stored;
      } catch {
        // ignore
      }
    }
    return this.currentLocale;
  }

  private getToken(): string | null {
    if (typeof window === 'undefined') return null;
    try {
      const local = localStorage.getItem('token');
      if (local) return local;
      const match = document.cookie.match(/(?:^|;\s*)noor_token=([^;]+)/);
      return match ? decodeURIComponent(match[1]) : null;
    } catch {
      return null;
    }
  }

  public setToken(token: string) {
    if (typeof window !== 'undefined') {
      try {
        localStorage.setItem('token', token);
        const isSecure = window.location.protocol === 'https:';
        document.cookie = `noor_token=${encodeURIComponent(token)}; path=/; max-age=2592000; SameSite=Lax${isSecure ? '; Secure' : ''}`;
      } catch {
        // ignore storage errors
      }
    }
  }

  public removeToken() {
    if (typeof window !== 'undefined') {
      try {
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        document.cookie = 'noor_token=; path=/; max-age=0; SameSite=Lax';
      } catch {
        // ignore
      }
    }
  }

  private async request<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
    const baseUrl = getApiBaseUrl();
    const url = `${baseUrl}${endpoint.startsWith('/') ? endpoint : `/${endpoint}`}`;
    const token = this.getToken();
    const locale = this.getLocale();

    const headers: Record<string, string> = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'app-locale': locale,
      'Accept-Language': locale,
      ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
      ...((options.headers as Record<string, string>) || {}),
    };

    const res = await fetch(url, {
      ...options,
      headers,
    });

    const data = await res.json().catch(() => null);

    if (!res.ok) {
      const errorMsg = data?.message || data?.error || `Request failed with status ${res.status}`;
      throw new Error(errorMsg);
    }

    // If backend returns { status: false, message: '...' }
    if (data && typeof data === 'object' && 'status' in data && data.status === false) {
      throw new Error(data.message || 'Operation failed');
    }

    // Check for standard API response format { status: true, data: ... }
    if (data && typeof data === 'object' && 'data' in data && 'status' in data) {
      const inner = data.data as Record<string, unknown> | null;
      if (inner && typeof inner === 'object' && 'status' in inner && inner.status === false) {
        throw new Error((inner.message as string) || 'Resource not found');
      }
      return data.data as T;
    }

    return data as T;
  }

  // ── Authentication & Account ───────────────────────────────────────────────
  async login(credentials: { email_or_username?: string; email?: string; password?: string }): Promise<LoginResponse> {
    const payload = {
      username: credentials.email_or_username || credentials.email,
      email: credentials.email,
      password: credentials.password,
    };
    const res = await this.request<LoginResponse>('/login', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
    const dataObj = res?.data as { api_token?: string; token?: string } | undefined;
    const token = res?.api_token || res?.token || dataObj?.api_token || dataObj?.token;
    if (token) {
      this.setToken(token);
      if (typeof window !== 'undefined') {
        localStorage.setItem('user', JSON.stringify(res));
      }
    }
    return res;
  }

  async register(data: { name: string; email: string; password?: string }): Promise<AuthResponse & { api_token?: string; token?: string; data?: { api_token?: string; token?: string } }> {
    const res = await this.request<AuthResponse & { api_token?: string; token?: string; data?: { api_token?: string; token?: string } }>('/register', {
      method: 'POST',
      body: JSON.stringify(data),
    });
    const dataObj = res?.data as { api_token?: string; token?: string } | undefined;
    const token = res?.api_token || res?.token || dataObj?.api_token || dataObj?.token;
    if (token) {
      this.setToken(token);
      if (typeof window !== 'undefined') {
        localStorage.setItem('user', JSON.stringify(res));
      }
    }
    return res;
  }

  async logout(): Promise<void> {
    try {
      await this.request('/logout');
    } catch {
      // Ignore network failures on logout
    } finally {
      this.removeToken();
    }
  }

  async getProfile(): Promise<User> {
    return this.request<User>('/v3/profile-details');
  }

  async updateProfile(data: { name?: string; email?: string; phone?: string; avatar?: string }): Promise<{ message?: string; user?: User }> {
    return this.request<{ message?: string; user?: User }>('/update-profile', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async changePassword(data: { old_password: string; new_password: string }): Promise<{ message?: string }> {
    return this.request<{ message?: string }>('/change-password', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateParentalLock(enabled: boolean, pin?: string): Promise<{ message?: string }> {
    return this.request<{ message?: string }>('/update-parental-lock', {
      method: 'POST',
      body: JSON.stringify({ is_parental_lock_enable: enabled ? 1 : 0, pin }),
    });
  }

  // ── Multi Profiles ─────────────────────────────────────────────────────────
  async getProfiles(): Promise<UserMultiProfile[]> {
    return this.request<UserMultiProfile[]>('/user-profile-list');
  }

  async createProfile(data: { name: string; is_child?: boolean; avatar?: string }): Promise<UserMultiProfile> {
    return this.request<UserMultiProfile>('/save-userprofile', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async deleteProfile(profileId: string | number): Promise<{ message?: string }> {
    return this.request<{ message?: string }>('/delete-userprofile', {
      method: 'POST',
      body: JSON.stringify({ id: profileId }),
    });
  }

  async selectProfile(profileId: string | number): Promise<{ message?: string; profile?: UserMultiProfile }> {
    return this.request<{ message?: string; profile?: UserMultiProfile }>(`/select-userprofile/${profileId}`, {
      method: 'POST',
    });
  }

  // ── Billing & Invoices ────────────────────────────────────────────────────
  async getBillingHistory(): Promise<BillingHistoryResponse> {
    return this.request<BillingHistoryResponse>('/v3/user/billing-history');
  }

  // ── Notifications ─────────────────────────────────────────────────────────
  async getNotifications(markRead: boolean = false): Promise<{ data?: NotificationItem[]; notifications?: NotificationItem[]; notification_data?: NotificationItem[]; [key: string]: unknown }> {
    return this.request<{ data?: NotificationItem[]; notifications?: NotificationItem[]; notification_data?: NotificationItem[]; [key: string]: unknown }>(`/notification-list${markRead ? '?type=mark_as_read' : ''}`);
  }

  async deleteNotification(id: string | number): Promise<{ message?: string }> {
    return this.request<{ message?: string }>('/v3/delete-notification', {
      method: 'POST',
      body: JSON.stringify({ id }),
    });
  }

  // ── Cast & Crew ───────────────────────────────────────────────────────────
  async getCastDetails(id: number | string, type: string = 'actor'): Promise<CastDetails> {
    return this.request<CastDetails>(`/v3/cast-details?id=${id}&type=${type}`);
  }

  async getVideos(page: number = 1): Promise<{ data?: MediaItem[]; current_page?: number; last_page?: number }> {
    return this.request<{ data?: MediaItem[]; current_page?: number; last_page?: number }>(`/v3/video-list?page=${page}`);
  }

  // ── Blog & Articles ────────────────────────────────────────────────────────
  async getBlogs(params: BlogQueryParams = {}): Promise<BlogListResponse> {
    const q = new URLSearchParams();
    if (params.page) q.append('page', params.page.toString());
    if (params.per_page) q.append('per_page', params.per_page.toString());
    if (params.category && params.category !== 'all') q.append('category', params.category);
    if (params.search) q.append('search', params.search);
    if (params.featured) q.append('featured', '1');
    if (params.tag) q.append('tag', params.tag);

    const query = q.toString() ? `?${q.toString()}` : '';
    return this.request<BlogListResponse>(`/v3/blogs${query}`);
  }

  async getBlogPost(slugOrId: string | number): Promise<BlogDetailResponse> {
    return this.request<BlogDetailResponse>(`/v3/blogs/${slugOrId}`);
  }

  async getBlogCategories(): Promise<BlogCategory[]> {
    return this.request<BlogCategory[]>('/v3/blog-categories');
  }

  // ── Discovery & Catalog ───────────────────────────────────────────────────
  async getBannerData(bannerFor: string = 'home'): Promise<MediaItem[]> {
    const res = await this.request<{ slider?: unknown[]; [key: string]: unknown } | unknown[]>(`/v3/banner-data?banner_for=${bannerFor}`);
    const items = (res && !Array.isArray(res) && Array.isArray((res as { slider?: unknown[] }).slider))
      ? (res as { slider: unknown[] }).slider
      : (Array.isArray(res) ? res : []);
    return items.map(normalizeMediaItem);
  }

  async getDashboard(): Promise<{ data?: DashboardData } & DashboardData> {
    return this.request<{ data?: DashboardData } & DashboardData>('/v3/dashboard-detail');
  }

  async getDashboardDetailData(page: number = 1): Promise<DashboardData> {
    return this.request<DashboardData>(`/v3/dashboard-detail-data?page=${page}`);
  }

  async getMovies(params: { page?: number; genre_id?: number; search?: string } = {}): Promise<{ data?: MediaItem[]; [key: string]: unknown } | MediaItem[]> {
    const q = new URLSearchParams();
    if (params.page) q.append('page', params.page.toString());
    if (params.genre_id) q.append('genre_id', params.genre_id.toString());
    if (params.search) q.append('search', params.search);
    return this.request<{ data?: MediaItem[]; [key: string]: unknown } | MediaItem[]>(`/v3/movie-list?${q.toString()}`);
  }

  async getTvShows(params: { page?: number; genre_id?: number; search?: string } = {}): Promise<{ data?: MediaItem[]; [key: string]: unknown } | MediaItem[]> {
    const q = new URLSearchParams();
    if (params.page) q.append('page', params.page.toString());
    if (params.genre_id) q.append('genre_id', params.genre_id.toString());
    if (params.search) q.append('search', params.search);
    q.append('per_page', '100');
    return this.request<{ data?: MediaItem[]; [key: string]: unknown } | MediaItem[]>(`/v3/tvshow-list?${q.toString()}`);
  }

  async getContentDetails(id: number | string, type: 'movie' | 'tvshow' | 'episode' | 'video'): Promise<ContentDetails> {
    const raw = await this.request<Record<string, unknown>>(`/v3/content-details?id=${id}&type=${type}`);
    if (!raw || raw.status === false) {
      throw new Error('Content not found');
    }
    const details = (raw?.details as Record<string, unknown>) || raw;
    if (!details.name && !raw.name) {
      throw new Error('Content not found');
    }
    const more = ((raw?.suggested_content || raw?.more_items || []) as unknown[]).map(normalizeMediaItem);
    const casts = ((raw?.actors || raw?.casts || []) as Array<Record<string, unknown>>);
    const directors = ((raw?.directors || []) as Array<Record<string, unknown>>);

    const seoRaw = (raw?.seo as Record<string, unknown>) || (details?.seo as Record<string, unknown>) || {};
    const seoObj: SeoMetadata = {
      meta_title: (seoRaw.meta_title as string) || (details.meta_title as string) || undefined,
      meta_description: (seoRaw.meta_description as string) || (details.meta_description as string) || undefined,
      meta_keywords: (seoRaw.meta_keywords as string[] | string) || (details.meta_keywords as string[] | string) || undefined,
      seo_image: (seoRaw.seo_image as string) || (details.seo_image as string) || null,
      canonical_url: (seoRaw.canonical_url as string) || (details.canonical_url as string) || null,
      google_site_verification: (seoRaw.google_site_verification as string) || (details.google_site_verification as string) || null,
    };

    const reviewRaw = (raw?.review as Record<string, unknown>) || (details?.review as Record<string, unknown>) || {};
    const reviewData: ReviewListResponse = {
      total_reviews: typeof reviewRaw.total_reviews === 'number' ? reviewRaw.total_reviews : 0,
      my_review: (reviewRaw.my_review as ReviewItem) || null,
      other_reviews: Array.isArray(reviewRaw.other_reviews) ? (reviewRaw.other_reviews as ReviewItem[]) : [],
    };

    return {
      id: (raw?.id as string | number) || (details?.id as string | number) || id,
      name: String(details.name || raw?.name),
      type: String(details.type || raw?.type || type),
      description: String(details.description || raw?.description || ''),
      poster_image: String(details.poster_image || raw?.poster_image || ''),
      thumbnail_image: String(details.thumbnail_image || raw?.thumbnail_image || ''),
      banner_image: String(details.thumbnail_image || details.poster_image || raw?.poster_image || ''),
      release_year: details.release_date ? String(details.release_date).substring(0, 4) : (raw?.release_year as string | number | undefined),
      duration: String(details.duration || raw?.duration || ''),
      imdb_rating: (details.imdb_rating as string | number | undefined) || (raw?.imdb_rating as string | number | undefined) || '',
      access: String(details.access || raw?.access || 'free'),
      has_content_access: Boolean(details.has_content_access ?? raw?.has_content_access ?? false),
      genre_data: Array.isArray(details.genres) ? (details.genres as string[]) : ((raw?.genre_data as string[]) || []),
      casts: casts.map((c) => ({
        id: (c.id as string | number) || '',
        name: String(c.name || ''),
        role: String(c.type || c.role || 'Cast'),
        profile_image: typeof c.profile_image === 'string' ? c.profile_image : undefined,
      })),
      directors: directors.map((d) => ({
        id: (d.id as string | number) || '',
        name: String(d.name || ''),
        role: 'Director',
        profile_image: typeof d.profile_image === 'string' ? d.profile_image : undefined,
      })),
      clips: (raw?.trailer_data as ContentDetails['clips']) || [],
      more_items: more,
      video_qualities: (raw?.video_qualities as ContentDetails['video_qualities']) || [],
      video_default_quality: (raw?.video_qualities as ContentDetails['video_qualities'])?.[0],
      seasons: (raw?.seasons as ContentDetails['seasons']) || (details?.season_data as ContentDetails['seasons']) || [],
      seo: seoObj,
      meta_title: seoObj.meta_title,
      meta_description: seoObj.meta_description,
      meta_keywords: seoObj.meta_keywords,
      seo_image: seoObj.seo_image,
      canonical_url: seoObj.canonical_url,
      reviews: reviewData,
    };
  }

  async getSeoSettings(): Promise<SeoMetadata> {
    return this.request<SeoMetadata>('/v3/seo-settings');
  }

  async getEpisodes(tvShowId: number | string, seasonId?: number | string): Promise<{ data?: Episode[]; [key: string]: unknown } | Episode[]> {
    const q = new URLSearchParams({
      tv_show_id: tvShowId.toString(),
      tvshow_id: tvShowId.toString(),
    });
    if (seasonId) q.append('season_id', seasonId.toString());
    return this.request<{ data?: Episode[]; [key: string]: unknown } | Episode[]>(`/v3/episode-list?${q.toString()}`);
  }

  async getLiveTvDashboard(): Promise<{ slider: LiveChannel[]; category_data: LiveCategory[] }> {
    return this.request<{ slider: LiveChannel[]; category_data: LiveCategory[] }>('/v3/livetv-dashboard');
  }

  async getLiveTvDetails(channelId: number | string): Promise<LiveTvPlaybackDetails> {
    return this.request<LiveTvPlaybackDetails>(`/v3/livetv-details?channel_id=${channelId}`);
  }

  async search(query: string): Promise<SearchDataResult> {
    return this.request<SearchDataResult>(`/v3/get-search-data?search=${encodeURIComponent(query)}`);
  }

  // ── Watchlist & Continue Watching ─────────────────────────────────────────
  async getWatchlist(): Promise<MediaItem[]> {
    return this.request<MediaItem[]>('/v3/watch-list');
  }

  async saveWatchlist(entertainmentId: string | number, type: string): Promise<{ status: boolean; message?: string }> {
    return this.request('/save-watchlist', {
      method: 'POST',
      body: JSON.stringify({ entertainment_id: entertainmentId, type }),
    });
  }

  async removeWatchlist(entertainmentId: string | number): Promise<{ status: boolean; message?: string }> {
    return this.request('/delete-watchlist', {
      method: 'POST',
      body: JSON.stringify({ entertainment_id: entertainmentId }),
    });
  }

  async getContinueWatching(): Promise<MediaItem[]> {
    return this.request<MediaItem[]>('/v3/continuewatch-list');
  }

  async saveContinueWatching(contentId: string | number, type: string, watchedTime: number, totalTime?: number): Promise<{ status: boolean; message?: string }> {
    return this.request('/save-continuewatch', {
      method: 'POST',
      body: JSON.stringify({
        entertainment_id: contentId,
        type,
        watched_time: watchedTime,
        total_time: totalTime,
      }),
    });
  }

  // ── Subscriptions & Pricing ───────────────────────────────────────────────
  async getPlans(): Promise<Plan[]> {
    return this.request<Plan[]>('/v3/subscription/plans');
  }

  async calculatePrice(planId: number, couponCode?: string): Promise<PriceCalculation> {
    return this.request<PriceCalculation>('/v3/subscription/calculate-price', {
      method: 'POST',
      body: JSON.stringify({ plan_id: planId, coupon_code: couponCode }),
    });
  }

  async validateCoupon(couponCode: string, planId?: number): Promise<{ status: boolean; message?: string; data?: Record<string, unknown> }> {
    return this.request('/v3/coupon/validate', {
      method: 'POST',
      body: JSON.stringify({ coupon_code: couponCode, plan_id: planId }),
    });
  }

  async processSubscriptionPayment(params: {
    plan_id: number;
    payment_method: string;
    coupon_code?: string;
    redirect_url?: string;
  }): Promise<{ redirect_url?: string; session_id?: string; order_id?: string; key?: string; is_free?: boolean }> {
    return this.request('/v3/subscription/process-payment', {
      method: 'POST',
      body: JSON.stringify(params),
    });
  }

  async verifySubscriptionPayment(params: {
    gateway: string;
    session_id?: string;
    payment_id?: string;
    order_id?: string;
    signature?: string;
    reference?: string;
  }): Promise<{ status: boolean; message?: string; [key: string]: unknown }> {
    return this.request('/v3/subscription/verify-payment', {
      method: 'POST',
      body: JSON.stringify(params),
    });
  }

  async getUserSubscription(): Promise<SubscriptionStatus> {
    return this.request<SubscriptionStatus>('/v3/user/subscription');
  }

  async cancelSubscription(): Promise<{ status: boolean; message?: string }> {
    return this.request('/v3/subscription/cancel', {
      method: 'POST',
    });
  }

  // ── Pay-Per-View ──────────────────────────────────────────────────────────
  async calculatePayPerViewPrice(contentId: string | number, type: string, couponCode?: string): Promise<{
    price: number;
    total_amount: number;
    discount?: number;
    currency?: string;
    [key: string]: unknown;
  }> {
    return this.request('/v3/pay-per-view/calculate-price', {
      method: 'POST',
      body: JSON.stringify({ movie_id: contentId, type, coupon_code: couponCode }),
    });
  }

  async processPayPerViewPayment(params: {
    movie_id: string | number;
    type: string;
    payment_method: string;
    redirect_url?: string;
  }): Promise<{ redirect_url?: string; session_id?: string; order_id?: string; [key: string]: unknown }> {
    return this.request('/v3/pay-per-view/process-payment', {
      method: 'POST',
      body: JSON.stringify(params),
    });
  }

  async verifyPayPerViewPayment(gateway: string, sessionId: string): Promise<{ status: boolean; message?: string; [key: string]: unknown }> {
    return this.request('/v3/pay-per-view/verify-payment', {
      method: 'POST',
      body: JSON.stringify({ gateway, session_id: sessionId }),
    });
  }

  // ── Payment Methods & Gateways ───────────────────────────────────────────
  async getPaymentMethods(): Promise<PaymentMethodsData> {
    const res = await this.request<{ status?: boolean; data?: PaymentMethodsData }>('/v3/payment-methods');
    return res?.data || (res as unknown as PaymentMethodsData) || {};
  }

  // ── Reviews & Ratings ─────────────────────────────────────────────────────
  async saveReview(params: {
    entertainment_id: string | number;
    rating: number;
    review?: string;
  }): Promise<{ status: boolean; message?: string; data?: ReviewItem }> {
    return this.request('/save-review', {
      method: 'POST',
      body: JSON.stringify(params),
    });
  }

  async deleteReview(id: string | number): Promise<{ status: boolean; message?: string }> {
    return this.request('/delete-review', {
      method: 'POST',
      body: JSON.stringify({ id }),
    });
  }

  // ── App Settings & Static Pages ───────────────────────────────────────────
  async getAppConfiguration(): Promise<AppConfiguration> {
    return this.request<AppConfiguration>('/v3/app-configuration');
  }

  async getPage(slug: string): Promise<{ title?: string; name?: string; description?: string; content?: string; [key: string]: unknown }> {
    return this.request<{ title?: string; name?: string; description?: string; content?: string; [key: string]: unknown }>(`/v3/pages/${slug}`);
  }
}

export const api = new ApiClient();

