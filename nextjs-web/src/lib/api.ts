import {
  AuthResponse,
  ContentDetails,
  LiveChannel,
  MediaItem,
  Plan,
  PriceCalculation,
  SubscriptionStatus,
  User,
} from '@/types';

export function normalizeMediaItem(raw: any): MediaItem {
  if (!raw) return raw;
  const details = raw.details || {};
  const genres = details.genres || raw.genres || raw.genre_data || [];
  const genreList = Array.isArray(genres)
    ? genres.map((g: any) => (typeof g === 'string' ? g : g.name || ''))
    : [];

  return {
    id: details.id || raw.id || raw.type_id || '',
    name: details.name || raw.name || raw.title || 'Untitled',
    title: details.name || raw.name || raw.title || 'Untitled',
    type: (details.type || raw.type || 'movie') as any,
    description: details.description || raw.description || raw.short_desc || '',
    poster_image: raw.poster_image || raw.poster_url || raw.thumbnail_image || raw.poster_tv_image,
    thumbnail_image: raw.poster_tv_image || raw.thumbnail_image || raw.poster_image,
    banner_image: raw.poster_tv_image || raw.poster_image || raw.banner_image,
    duration: details.duration || raw.duration || '',
    release_year: details.release_date
      ? details.release_date.substring(0, 4)
      : raw.release_date
      ? String(raw.release_date).substring(0, 4)
      : raw.release_year,
    imdb_rating: details.imdb_rating || raw.imdb_rating || raw.rating,
    access: (details.access || raw.movie_access || raw.access || 'free') as any,
    genres: genreList,
    genre_data: genreList,
    has_content_access: Boolean(details.has_content_access ?? raw.has_content_access),
    is_watchlist: Boolean(details.is_in_watchlist ?? raw.is_watch_list ?? raw.is_watchlist),
  };
}

const getApiBaseUrl = (): string => {
  // Server-side inside Docker network
  if (typeof window === 'undefined') {
    return process.env.INTERNAL_API_URL || 'http://backend:8000/api';
  }
  // Client-side inside user's browser
  return process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';
};

class ApiClient {
  private getToken(): string | null {
    if (typeof window === 'undefined') return null;
    return localStorage.getItem('token') || null;
  }

  public setToken(token: string) {
    if (typeof window !== 'undefined') {
      localStorage.setItem('token', token);
    }
  }

  public removeToken() {
    if (typeof window !== 'undefined') {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
    }
  }

  private async request<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
    const baseUrl = getApiBaseUrl();
    const url = `${baseUrl}${endpoint.startsWith('/') ? endpoint : `/${endpoint}`}`;
    const token = this.getToken();

    const headers: Record<string, string> = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
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
      return data.data as T;
    }

    return data as T;
  }

  // ── Authentication & Account ───────────────────────────────────────────────
  async login(credentials: { email_or_username?: string; email?: string; password?: string }): Promise<AuthResponse['data']> {
    const payload = {
      username: credentials.email_or_username || credentials.email,
      email: credentials.email,
      password: credentials.password,
    };
    const res = await this.request<any>('/login', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
    if (res?.token) {
      this.setToken(res.token);
      if (typeof window !== 'undefined') {
        localStorage.setItem('user', JSON.stringify(res));
      }
    }
    return res;
  }

  async register(data: { name: string; email: string; password?: string }): Promise<any> {
    const res = await this.request<any>('/register', {
      method: 'POST',
      body: JSON.stringify(data),
    });
    if (res?.token) {
      this.setToken(res.token);
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

  async updateProfile(data: { name?: string; email?: string; phone?: string; avatar?: string }): Promise<any> {
    return this.request('/update-profile', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async changePassword(data: { old_password: string; new_password: string }): Promise<any> {
    return this.request('/change-password', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async updateParentalLock(enabled: boolean, pin?: string): Promise<any> {
    return this.request('/update-parental-lock', {
      method: 'POST',
      body: JSON.stringify({ is_parental_lock_enable: enabled ? 1 : 0, pin }),
    });
  }

  // ── Multi Profiles ─────────────────────────────────────────────────────────
  async getProfiles(): Promise<any[]> {
    return this.request<any[]>('/user-profile-list');
  }

  async createProfile(data: { name: string; is_child?: boolean; avatar?: string }): Promise<any> {
    return this.request('/save-userprofile', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  }

  async deleteProfile(profileId: string | number): Promise<any> {
    return this.request('/delete-userprofile', {
      method: 'POST',
      body: JSON.stringify({ id: profileId }),
    });
  }

  async selectProfile(profileId: string | number): Promise<any> {
    return this.request(`/select-userprofile/${profileId}`, {
      method: 'POST',
    });
  }

  // ── Billing & Invoices ────────────────────────────────────────────────────
  async getBillingHistory(): Promise<{ subscriptions: any[]; pay_per_view: any[] }> {
    return this.request<{ subscriptions: any[]; pay_per_view: any[] }>('/v3/user/billing-history');
  }

  // ── Notifications ─────────────────────────────────────────────────────────
  async getNotifications(markRead: boolean = false): Promise<any> {
    return this.request<any>(`/notification-list${markRead ? '?type=mark_as_read' : ''}`);
  }

  async deleteNotification(id: string | number): Promise<any> {
    return this.request('/v3/delete-notification', {
      method: 'POST',
      body: JSON.stringify({ id }),
    });
  }

  // ── Cast & Crew ───────────────────────────────────────────────────────────
  async getCastDetails(id: number | string, type: string = 'actor'): Promise<any> {
    return this.request<any>(`/v3/cast-details?id=${id}&type=${type}`);
  }

  async getVideos(page: number = 1): Promise<any> {
    return this.request<any>(`/v3/video-list?page=${page}`);
  }

  // ── Discovery & Catalog ───────────────────────────────────────────────────
  async getBannerData(bannerFor: string = 'home'): Promise<MediaItem[]> {
    const res = await this.request<any>(`/v3/banner-data?banner_for=${bannerFor}`);
    const items = res?.slider || (Array.isArray(res) ? res : []);
    return items.map(normalizeMediaItem);
  }

  async getDashboard(): Promise<any> {
    return this.request<any>('/v3/dashboard-detail');
  }

  async getDashboardDetailData(page: number = 1): Promise<any> {
    return this.request<any>(`/v3/dashboard-detail-data?page=${page}`);
  }

  async getMovies(params: { page?: number; genre_id?: number; search?: string } = {}): Promise<any> {
    const q = new URLSearchParams();
    if (params.page) q.append('page', params.page.toString());
    if (params.genre_id) q.append('genre_id', params.genre_id.toString());
    if (params.search) q.append('search', params.search);
    return this.request<any>(`/v3/movie-list?${q.toString()}`);
  }

  async getTvShows(params: { page?: number; genre_id?: number; search?: string } = {}): Promise<any> {
    const q = new URLSearchParams();
    if (params.page) q.append('page', params.page.toString());
    if (params.genre_id) q.append('genre_id', params.genre_id.toString());
    if (params.search) q.append('search', params.search);
    return this.request<any>(`/v3/tvshow-list?${q.toString()}`);
  }

  async getContentDetails(id: number | string, type: 'movie' | 'tvshow' | 'episode' | 'video'): Promise<ContentDetails> {
    const raw = await this.request<any>(`/v3/content-details?id=${id}&type=${type}`);
    const details = raw?.details || {};
    const more = (raw?.suggested_content || raw?.more_items || []).map(normalizeMediaItem);
    const casts = raw?.actors || raw?.casts || [];
    const directors = raw?.directors || [];

    return {
      id: raw?.id || details?.id || id,
      name: details.name || raw?.name || 'Untitled',
      type: details.type || raw?.type || type,
      description: details.description || raw?.description || '',
      poster_image: details.poster_image || raw?.poster_image || '',
      thumbnail_image: details.thumbnail_image || raw?.thumbnail_image || '',
      banner_image: details.thumbnail_image || details.poster_image || raw?.poster_image || '',
      release_year: details.release_date ? String(details.release_date).substring(0, 4) : raw?.release_year,
      duration: details.duration || raw?.duration || '',
      imdb_rating: details.imdb_rating || raw?.imdb_rating || '',
      access: details.access || raw?.access || 'free',
      has_content_access: Boolean(details.has_content_access ?? raw?.has_content_access ?? true),
      genre_data: Array.isArray(details.genres) ? details.genres : (raw?.genre_data || []),
      casts: casts.map((c: any) => ({
        id: c.id,
        name: c.name,
        role: c.type || c.role || 'Cast',
        profile_image: c.profile_image,
      })),
      directors: directors.map((d: any) => ({
        id: d.id,
        name: d.name,
        role: 'Director',
        profile_image: d.profile_image,
      })),
      clips: raw?.trailer_data || [],
      more_items: more,
      video_qualities: raw?.video_qualities || [],
      video_default_quality: raw?.video_qualities?.[0],
      seasons: raw?.seasons || details?.season_data || [],
    };
  }

  async getEpisodes(tvShowId: number | string, seasonId?: number | string): Promise<any> {
    const q = new URLSearchParams({ tv_show_id: tvShowId.toString() });
    if (seasonId) q.append('season_id', seasonId.toString());
    return this.request<any>(`/v3/episode-list?${q.toString()}`);
  }

  async getLiveTvDashboard(): Promise<{ slider: LiveChannel[]; category_data: any[] }> {
    return this.request<{ slider: LiveChannel[]; category_data: any[] }>('/v3/livetv-dashboard');
  }

  async getLiveTvDetails(channelId: number | string): Promise<LiveChannel> {
    return this.request<LiveChannel>(`/v3/livetv-details?channel_id=${channelId}`);
  }

  async search(query: string): Promise<any> {
    return this.request<any>(`/v3/get-search-data?search=${encodeURIComponent(query)}`);
  }

  // ── Watchlist & Continue Watching ─────────────────────────────────────────
  async getWatchlist(): Promise<MediaItem[]> {
    return this.request<MediaItem[]>('/v3/watch-list');
  }

  async saveWatchlist(entertainmentId: string | number, type: string): Promise<any> {
    return this.request('/save-watchlist', {
      method: 'POST',
      body: JSON.stringify({ entertainment_id: entertainmentId, type }),
    });
  }

  async removeWatchlist(entertainmentId: string | number): Promise<any> {
    return this.request('/delete-watchlist', {
      method: 'POST',
      body: JSON.stringify({ entertainment_id: entertainmentId }),
    });
  }

  async getContinueWatching(): Promise<MediaItem[]> {
    return this.request<MediaItem[]>('/v3/continuewatch-list');
  }

  async saveContinueWatching(contentId: string | number, type: string, watchedTime: number, totalTime?: number): Promise<any> {
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

  async validateCoupon(couponCode: string, planId?: number): Promise<any> {
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
  }): Promise<any> {
    return this.request('/v3/subscription/verify-payment', {
      method: 'POST',
      body: JSON.stringify(params),
    });
  }

  async getUserSubscription(): Promise<SubscriptionStatus> {
    return this.request<SubscriptionStatus>('/v3/user/subscription');
  }

  async cancelSubscription(): Promise<any> {
    return this.request('/v3/subscription/cancel', {
      method: 'POST',
    });
  }

  // ── Pay-Per-View ──────────────────────────────────────────────────────────
  async calculatePayPerViewPrice(contentId: string | number, type: string, couponCode?: string): Promise<any> {
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
  }): Promise<any> {
    return this.request('/v3/pay-per-view/process-payment', {
      method: 'POST',
      body: JSON.stringify(params),
    });
  }

  async verifyPayPerViewPayment(gateway: string, sessionId: string): Promise<any> {
    return this.request('/v3/pay-per-view/verify-payment', {
      method: 'POST',
      body: JSON.stringify({ gateway, session_id: sessionId }),
    });
  }

  // ── App Settings & Static Pages ───────────────────────────────────────────
  async getAppConfiguration(): Promise<any> {
    return this.request<any>('/v3/app-configuration');
  }

  async getPage(slug: string): Promise<any> {
    return this.request<any>(`/v3/pages/${slug}`);
  }
}

export const api = new ApiClient();

