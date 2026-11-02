export interface User {
  id: string | number;
  name: string;
  email: string;
  full_name?: string;
  user_type?: string;
  is_subscribe?: number;
  phone?: string;
  avatar?: string;
  profile_image?: string;
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

export interface LoginResponse extends AuthResponse {
  api_token?: string;
  token?: string;
  id?: string | number;
  name?: string;
  email?: string;
  user_type?: string;
  is_subscribe?: number;
  [key: string]: unknown;
}

export interface MediaItem {
  id: string | number;
  name: string;
  title?: string;
  type: 'movie' | 'tvshow' | 'episode' | 'video' | 'livetv';
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
  access?: 'free' | 'paid' | 'pay-per-view' | 'unlock';
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

export interface SeoMetadata {
  meta_title?: string;
  meta_description?: string;
  meta_keywords?: string[] | string;
  seo_image?: string | null;
  canonical_url?: string | null;
  google_site_verification?: string | null;
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
  seo?: SeoMetadata;
  meta_title?: string;
  meta_description?: string;
  meta_keywords?: string[] | string;
  seo_image?: string | null;
  canonical_url?: string | null;
  reviews?: ReviewListResponse;
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
  poster_url?: string;
  poster_tv_image?: string;
  thumbnail_image?: string;
  stream_type?: string;
  server_url?: string;
  server_url1?: string;
  stream_url?: string;
  embedded?: string;
  has_content_access?: boolean;
  details?: {
    name?: string;
    category?: string;
    description?: string;
    poster_url?: string;
    poster_tv_image?: string;
    server_url?: string;
    stream_url?: string;
    has_content_access?: boolean | number;
    [key: string]: unknown;
  };
  [key: string]: unknown;
}

export interface LiveTvPlaybackDetails {
  details: {
    has_content_access?: boolean | number;
    server_url?: string | null;
    stream_type?: string | null;
  };
  video_qualities: VideoQuality[];
}

export interface PlanLimitation {
  id: number;
  limitation_title: string;
  limitation_value: boolean | number;
  slug: string;
  message?: string;
  limit?: { value?: string } | Record<string, unknown>;
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
  title?: string;
  body?: string;
  description?: string;
  read?: boolean;
  read_at?: string | null;
  created_at?: string;
  data?: Record<string, unknown>;
  [key: string]: unknown;
}

export interface Episode {
  id: number | string;
  name: string;
  episode_number?: number;
  season_id?: number | string;
  season_number?: number;
  tv_show_id?: number | string;
  poster_image?: string;
  thumbnail_image?: string;
  description?: string;
  duration?: string;
  access?: 'free' | 'paid' | 'unlock';
  has_content_access?: boolean;
  video_url?: string;
  stream_type?: string;
  qualities?: VideoQuality[];
}

export interface LiveCategory {
  id: number | string;
  name: string;
  channel_data?: LiveChannel[];
  channels?: LiveChannel[];
}

export interface BillingSubscriptionRecord {
  id: number;
  plan_name?: string;
  amount: number;
  currency?: string;
  start_date?: string;
  end_date?: string;
  status: string;
  payment_type?: string;
  invoice_id?: number;
  [key: string]: unknown;
}

export interface BillingPayPerViewRecord {
  id: number;
  content_name?: string;
  amount: number;
  currency?: string;
  date?: string;
  status: string;
  payment_type?: string;
  invoice_id?: number;
  [key: string]: unknown;
}

export interface BillingHistoryResponse {
  subscriptions: BillingSubscriptionRecord[];
  pay_per_view: BillingPayPerViewRecord[];
}

export interface CastDetails {
  id: number | string;
  name: string;
  type?: string;
  designation?: string;
  image?: string;
  profile_image?: string;
  bio?: string;
  filmography?: MediaItem[];
  entertainments?: unknown[];
  [key: string]: unknown;
}

export interface SearchDataResult {
  movies?: MediaItem[];
  tv_shows?: MediaItem[];
  videos?: MediaItem[];
  channels?: LiveChannel[];
  data?: MediaItem[];
  [key: string]: unknown;
}

export type DashboardMediaSection = { data?: MediaItem[]; [key: string]: unknown } | MediaItem[];

export interface DashboardData {
  slider?: MediaItem[];
  banner?: MediaItem[];
  top_10?: DashboardMediaSection;
  latest_movie?: DashboardMediaSection;
  popular_movie?: DashboardMediaSection;
  pay_per_view?: DashboardMediaSection;
  tv_show?: DashboardMediaSection;
  latest_movies?: MediaItem[];
  popular_movies?: MediaItem[];
  trending_movies?: MediaItem[];
  top_rated_movies?: MediaItem[];
  tv_shows?: MediaItem[];
  latest_tvshows?: MediaItem[];
  popular_tvshows?: MediaItem[];
  continue_watch?: MediaItem[];
  recommended?: MediaItem[];
  category_data?: LiveCategory[];
  [key: string]: unknown;
}

export type {
  BlogPost,
  BlogCategory,
  BlogQueryParams,
  BlogListResponse,
  BlogDetailResponse,
} from '@/features/blog/types/blog.types';

export interface PaymentMethodsData {
  razor_pay?: { razorpay_publickey?: string };
  stripe_pay?: { stripe_publickey?: string };
  paystack_pay?: { paystack_publickey?: string };
  paypal_pay?: { paypal_clientid?: string };
  flutterwave_pay?: { flutterwave_publickey?: string };
  in_app_purchase?: { entitlement_id?: string; apple_api_key?: string; google_api_key?: string };
}

export interface AppConfiguration {
  currency?: {
    currency_name?: string;
    currency_symbol?: string;
    currency_code?: string;
    currency_position?: 'left' | 'right';
    no_of_decimal?: number;
    thousand_separator?: string;
    decimal_separator?: string;
  };
  site_name?: string;
  site_description?: string;
  [key: string]: unknown;
}

export interface ReviewItem {
  id: number | string;
  rating: number;
  review?: string;
  username?: string;
  user_name?: string;
  profile_image?: string;
  created_at?: string;
  updated_at?: string;
}

export interface ReviewListResponse {
  total_reviews?: number;
  my_review?: ReviewItem | null;
  other_reviews?: ReviewItem[];
  data?: ReviewItem[];
}



