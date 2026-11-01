export interface User {
  id: string | number;
  name: string;
  email: string;
  user_type?: string;
  is_subscribe?: number;
  phone?: string;
  avatar?: string;
  is_parental_lock_enable?: number;
}

export interface AuthResponse {
  status: boolean;
  message?: string;
  data?: {
    id: string | number;
    name: string;
    email: string;
    token: string;
    user_type?: string;
    is_subscribe?: number;
  };
}

export interface MediaItem {
  id: string | number;
  name: string;
  title?: string;
  type: 'movie' | 'tvshow' | 'episode' | 'video';
  description?: string;
  poster_image?: string;
  poster_url?: string;
  thumbnail_image?: string;
  banner_image?: string;
  release_year?: string | number;
  release_date?: string;
  duration?: string;
  imdb_rating?: string | number;
  rating?: number;
  access?: 'free' | 'paid' | 'pay-per-view';
  plan_level?: number;
  genres?: string[] | { id: string | number; name: string }[];
  genre_data?: string[];
  is_watchlist?: boolean;
  has_content_access?: boolean;
  video_upload_type?: string;
  video_url_input?: string;
}

export interface VideoQuality {
  id?: string | number;
  quality: string;
  url: string;
  url_type: string;
}

export interface ContentDetails {
  id: string | number;
  name: string;
  type: string;
  description: string;
  poster_image: string;
  thumbnail_image?: string;
  banner_image?: string;
  release_year?: string | number;
  duration?: string;
  imdb_rating?: string | number;
  access: string;
  has_content_access: boolean;
  is_watchlist?: boolean;
  required_plan_level?: number;
  genre_data?: string[];
  casts?: CastMember[];
  directors?: CastMember[];
  clips?: ClipItem[];
  more_items?: MediaItem[];
  video_qualities?: VideoQuality[];
  video_default_quality?: VideoQuality;
  seasons?: SeasonItem[];
  watched_time?: string | number;
}

export interface SeasonItem {
  id: string | number;
  name: string;
  season_number: number;
  episodes: EpisodeItem[];
}

export interface EpisodeItem {
  id: string | number;
  name: string;
  episode_number: number;
  description?: string;
  poster_image?: string;
  duration?: string;
  has_content_access?: boolean;
}

export interface ClipItem {
  id: string | number;
  title: string;
  url: string;
  type: string;
  poster_url?: string;
}

export interface CastMember {
  id: string | number;
  name: string;
  character_name?: string;
  role?: string;
  profile_image?: string;
}

export interface LiveChannel {
  id: string | number;
  name: string;
  slug?: string;
  access?: string;
  category?: string;
  description?: string;
  poster_image?: string;
  thumbnail_image?: string;
  stream_type?: string;
  server_url?: string;
  server_url1?: string;
  embedded?: string;
  has_content_access?: boolean;
}

export interface PlanLimitation {
  id: number;
  limitation_title: string;
  limitation_value: boolean | number;
  slug: string;
  message?: string;
  limit?: { value?: string } | any;
}

export interface Plan {
  plan_id: number;
  id?: number | string;
  name: string;
  identifier: string;
  price: number;
  discount?: number;
  discount_percentage?: number;
  total_price: number;
  level: number;
  duration: string;
  duration_value: number;
  description?: string;
  plan_type?: PlanLimitation[];
}

export interface SubscriptionStatus {
  has_active_subscription: boolean;
  days_left?: number;
  is_expired?: boolean;
  subscription?: {
    id: number;
    plan_id: number;
    name: string;
    status: string;
    start_date: string;
    end_date: string;
    amount: number;
    total_amount: number;
  } | null;
}

export interface PriceCalculation {
  plan_id: number;
  plan_name: string;
  original_price: number;
  plan_discount_amount: number;
  price_after_plan_discount: number;
  coupon?: {
    id: number;
    coupon_code: string;
    discount_type: string;
    discount: number;
    discount_amount: number;
  } | null;
  coupon_discount_amount: number;
  subtotal: number;
  total_tax: number;
  taxes: {
    title: string;
    type: string;
    value: number;
    tax_amount: number;
  }[];
  total_amount: number;
  currency: string;
}

export interface BillingHistoryItem {
  id: number;
  type: 'subscription' | 'pay_per_view';
  plan_name?: string;
  content_name?: string;
  amount: number;
  currency: string;
  start_date?: string;
  end_date?: string;
  date?: string;
  status: string;
  payment_type?: string;
  invoice_id: number;
}

export interface UserMultiProfile {
  id: number;
  name: string;
  avatar?: string;
  is_child?: boolean;
}

export interface NotificationItem {
  id: string;
  title: string;
  body: string;
  read: boolean;
  created_at: string;
  data?: any;
}


