import type {
  BlogCategory,
  BlogDetailResponse,
  BlogListResponse,
  BlogPost,
  ContentDetails,
  Episode,
  LiveChannel,
  MediaItem,
  Plan,
  ReviewItem,
  SeasonItem,
  User,
  UserMultiProfile,
} from '@/types';
import backendData from '@/lib/showcase-backend-data.json';
import { SHOWCASE_HERO_ARTWORK } from '@/constants/showcase-artwork';

export const SHOWCASE_MODE = process.env.NEXT_PUBLIC_SHOWCASE_MODE !== 'false';

const posters = [
  '/showcase/posters/path-of-light.svg',
  '/showcase/posters/last-letter.svg',
  '/showcase/posters/traveller.svg',
  '/showcase/posters/across-river.svg',
  '/showcase/posters/seekers.svg',
  '/showcase/posters/light-kept.svg',
  '/showcase/posters/young-hearts.svg',
  '/showcase/posters/windows-history.svg',
];

function backendAccess(value: string): MediaItem['access'] {
  if (value === 'paid' || value === 'pay-per-view' || value === 'unlock') return value;
  return 'free';
}

function mapBackendMedia(item: (typeof backendData.catalog)[number]): MediaItem {
  const image = item.poster_image || item.thumbnail_image || posters[0];
  return {
    id: item.id,
    name: item.name,
    name_en: item.name,
    name_bn: item.name_bn || item.name,
    title: item.name,
    type: item.type === 'tvshow' ? 'tvshow' : 'movie',
    description: item.description,
    description_en: item.description,
    description_bn: item.description_bn,
    poster_image: image,
    poster_url: item.poster_image,
    thumbnail_image: item.thumbnail_image || image,
    banner_image: item.banner_image || image,
    blurhash: item.blurhash || undefined,
    release_date: item.release_date || undefined,
    release_year: item.release_year || undefined,
    duration: item.duration || undefined,
    imdb_rating: item.imdb_rating || undefined,
    language: item.language || undefined,
    content_rating: item.content_rating || undefined,
    access: backendAccess(item.access),
    genres: item.genres,
    genre_data: item.genres,
  };
}

export const SHOWCASE_MOVIES: MediaItem[] = backendData.catalog
  .filter((item) => item.type === 'movie')
  .map(mapBackendMedia);

export const SHOWCASE_SHOWS: MediaItem[] = backendData.catalog
  .filter((item) => item.type === 'tvshow')
  .map(mapBackendMedia);

export const SHOWCASE_BANNERS: MediaItem[] = backendData.home_banner_ids
  .map((id) => [...SHOWCASE_MOVIES, ...SHOWCASE_SHOWS].find((item) => item.id === id))
  .filter((item): item is MediaItem => Boolean(item))
  .map((item) => ({
    ...item,
    banner_image: SHOWCASE_HERO_ARTWORK[String(item.id)] || item.banner_image,
  }));

const makeEpisodes = (showId: string, _showName: string): Episode[] => backendData.episodes
  .filter((episode) => episode.tv_show_id === showId)
  .map((episode) => ({
    id: episode.id,
    name: episode.name,
    name_bn: episode.name_bn || episode.name,
    episode_number: episode.episode_number,
    season_id: episode.season_id,
    season_number: episode.season_number,
    tv_show_id: episode.tv_show_id,
    poster_image: episode.poster_image || posters[0],
    duration: episode.duration || undefined,
    access: episode.access === 'paid' || episode.access === 'unlock' ? episode.access : 'free',
    has_content_access: true,
    description: episode.description,
    description_bn: episode.description_bn,
    qualities: [{ quality: 'Auto', url: 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8', url_type: 'HLS' }],
  }));

export const SHOWCASE_VIDEOS: MediaItem[] = backendData.videos.map((item) => ({
  id: item.id,
  name: item.name,
  name_en: item.name,
  name_bn: item.name_bn || item.name,
  title: item.name,
  type: 'video',
  description: item.description,
  description_en: item.description,
  description_bn: item.description_bn,
  poster_image: item.poster_image || item.thumbnail_image,
  thumbnail_image: item.thumbnail_image || item.poster_image,
  banner_image: item.banner_image || item.poster_image,
  release_year: item.release_year || undefined,
  duration: item.duration || undefined,
  imdb_rating: item.imdb_rating || undefined,
  content_rating: item.content_rating || undefined,
  access: backendAccess(item.access),
}));

export const SHOWCASE_CHANNELS: LiveChannel[] = backendData.channels.map((channel) => ({
  id: channel.id,
  name: channel.name,
  name_en: channel.name,
  name_bn: channel.name_bn || channel.name,
  category: channel.category || undefined,
  description: channel.description,
  description_en: channel.description,
  description_bn: channel.description_bn,
  poster_image: channel.poster_image || posters[0],
  thumbnail_image: channel.thumbnail_image || channel.poster_image || posters[0],
  poster_tv_image: channel.poster_tv_image || undefined,
  slug: channel.slug,
  access: backendAccess(channel.access),
  stream_type: 'HLS',
  has_content_access: true,
}));

const demoStream = 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8';

const demoContentDetails = (item: MediaItem): ContentDetails => {
  const showId = String(item.id);
  const episodes = item.type === 'tvshow' ? makeEpisodes(showId, item.name) : [];
  const seasonGroups = new Map<number, Episode[]>();
  for (const episode of episodes) {
    const seasonNumber = episode.season_number || 1;
    seasonGroups.set(seasonNumber, [...(seasonGroups.get(seasonNumber) || []), episode]);
  }
  const seasons: SeasonItem[] = [...seasonGroups.entries()].map(([seasonNumber, seasonEpisodes]) => ({
    id: seasonEpisodes[0]?.season_id || `${showId}-season-${seasonNumber}`,
    name: `Season ${seasonNumber}`,
    season_number: seasonNumber,
    total_episodes: seasonEpisodes.length,
    episodes: seasonEpisodes.map((episode) => ({
      id: episode.id,
      name: episode.name,
      episode_number: episode.episode_number,
      season_id: episode.season_id,
      description: episode.description,
      description_bn: episode.description_bn,
      poster_image: episode.poster_image,
      duration: episode.duration,
      access: episode.access,
    })),
  }));
  return {
    id: item.id,
    name: item.name,
    name_bn: item.name_bn,
    name_en: item.name_en,
    type: item.type,
    description: item.description || '',
    description_bn: item.description_bn,
    description_en: item.description_en,
    poster_image: item.poster_image || '',
    thumbnail_image: item.poster_image,
    banner_image: item.banner_image || item.poster_image,
    release_year: item.release_year,
    duration: item.duration,
    imdb_rating: item.imdb_rating,
    language: item.language,
    content_rating: item.content_rating,
    access: item.access || 'free',
    has_content_access: true,
    is_watchlist: false,
    genre_data: item.genre_data || [],
    genre_items: (item.genre_data || []).map((name, index) => ({ id: `${showId}-genre-${index}`, name, name_en: name })),
    casts: [{ id: `${showId}-cast-1`, name: 'নওশাদ রহমান', role: 'Cast', profile_image: posters[5] }],
    directors: [{ id: `${showId}-director-1`, name: 'সামিয়া আহমেদ', role: 'Director', profile_image: posters[7] }],
    clips: [],
    more_items: [...SHOWCASE_MOVIES, ...SHOWCASE_SHOWS].filter((other) => other.id !== item.id).slice(0, 4),
    video_qualities: [{ quality: 'Auto', url: demoStream, url_type: 'HLS' }, { quality: 'HD', url: demoStream, url_type: 'HLS' }],
    video_default_quality: { quality: 'Auto', url: demoStream, url_type: 'HLS' },
    seasons,
    reviews: {
      total_reviews: 3,
      my_review: null,
      other_reviews: [
        { id: `${showId}-review-1`, rating: 5, review: 'পরিবারের সবাই মিলে উপভোগ করেছি।', username: 'নুসরাত আক্তার', created_at: '2025-04-12' },
        { id: `${showId}-review-2`, rating: 4, review: 'সুন্দর গল্প ও মন ছুঁয়ে যাওয়া দৃশ্য।', username: 'আরিফ হাসান', created_at: '2025-03-28' },
        { id: `${showId}-review-3`, rating: 5, review: 'আরও এমন কনটেন্ট দেখতে চাই।', username: 'সামিরা রহমান', created_at: '2025-02-10' },
      ],
    },
  };
};

const SHOWCASE_PLANS: Plan[] = [
  { id: 1, plan_id: 1, name: 'নূর+', identifier: 'showcase-plus', price: 199, total_price: 199, level: 1, duration: 'month', duration_value: 1, description: 'নূর-এর প্রদর্শনী মাসিক পরিকল্পনা।', plan_type: [] },
  { id: 2, plan_id: 2, name: 'পরিবার', identifier: 'showcase-family', price: 499, total_price: 499, level: 2, duration: 'month', duration_value: 1, description: 'পরিবারের জন্য প্রদর্শনী পরিকল্পনা।', plan_type: [] },
];

const SHOWCASE_BLOGS: BlogPost[] = [
  { id: 1, title: 'গল্প বলার শক্তি', slug: 'stories-that-bring-us-together', summary: 'একটি ভালো গল্প কীভাবে প্রজন্মকে কাছাকাছি আনে।', content: '<p>এই প্রদর্শনী নিবন্ধটি NOOR-এর জার্নাল অভিজ্ঞতা দেখানোর জন্য তৈরি।</p><p>গল্প আমাদের স্মৃতি, পরিবার এবং প্রতিবেশীদের সঙ্গে নতুন করে পরিচয় করিয়ে দেয়।</p>', image_url: posters[3], category: 'সংস্কৃতি', tags: ['গল্প', 'পরিবার'], author_name: 'NOOR Journal', reading_time: 4, views_count: 286, is_featured: true, status: 1, published_at: '2025-05-10' },
  { id: 2, title: 'শিশুদের সঙ্গে পর্দায় শেখা', slug: 'learning-together-on-screen', summary: 'পরিবারের সঙ্গে দেখার মতো বিষয় বেছে নেওয়ার কিছু ভাবনা।', content: '<p>এই প্রদর্শনী নিবন্ধটি একটি নমুনা জার্নাল পাতা।</p>', image_url: posters[6], category: 'পরিবার', tags: ['শিশু', 'শিক্ষা'], author_name: 'NOOR Journal', reading_time: 3, views_count: 174, is_featured: false, status: 1, published_at: '2025-04-22' },
  { id: 3, title: 'স্মৃতির শহর', slug: 'a-city-made-of-memories', summary: 'স্থানীয় ইতিহাস সংরক্ষণে সবার ছোট অবদান।', content: '<p>এই প্রদর্শনী নিবন্ধটি নমুনা কনটেন্ট।</p>', image_url: posters[7], category: 'ইতিহাস', tags: ['ইতিহাস'], author_name: 'NOOR Journal', reading_time: 5, views_count: 92, is_featured: false, status: 1, published_at: '2025-03-08' },
];

const DEMO_USER: User = { id: 'showcase-viewer', name: 'Showcase Viewer', email: 'viewer@noor.example', user_type: 'user', is_subscribe: 1, avatar: posters[5], profile_image: posters[5] };

function readStored<T>(key: string, fallback: T): T {
  if (typeof window === 'undefined') return fallback;
  try {
    const value = localStorage.getItem(`noor_showcase_${key}`);
    return value ? JSON.parse(value) as T : fallback;
  } catch {
    return fallback;
  }
}

function writeStored<T>(key: string, value: T): void {
  if (typeof window === 'undefined') return;
  try { localStorage.setItem(`noor_showcase_${key}`, JSON.stringify(value)); } catch { /* storage may be disabled */ }
}

function filterMedia(items: MediaItem[], params: URLSearchParams): MediaItem[] {
  const query = params.get('search')?.toLocaleLowerCase() || params.get('q')?.toLocaleLowerCase() || '';
  const genreId = params.get('genre_id');
  return items.filter((item) => {
    const matchesSearch = !query || `${item.name} ${item.name_bn || ''} ${item.name_en || ''} ${item.description || ''}`.toLocaleLowerCase().includes(query);
    const matchesGenre = !genreId || (item.genre_data || []).some((genre) => genre.toLocaleLowerCase().includes(genreId.toLocaleLowerCase()));
    return matchesSearch && matchesGenre;
  });
}

function allMedia(): MediaItem[] {
  const episodes = SHOWCASE_SHOWS.flatMap((show) => makeEpisodes(String(show.id), show.name).map((episode) => ({
    id: episode.id,
    name: episode.name,
    name_bn: episode.name,
    title: episode.name,
    type: 'episode' as const,
    description: episode.description,
    poster_image: episode.poster_image,
    duration: episode.duration,
    access: 'free' as const,
    has_content_access: true,
  })));
  return [...SHOWCASE_MOVIES, ...SHOWCASE_SHOWS, ...SHOWCASE_VIDEOS, ...episodes];
}

export function getShowcaseUser(): User { return DEMO_USER; }

/** Local replacement for the API transport; no network requests are made in showcase mode. */
export function getShowcaseResponse(endpoint: string, options: RequestInit = {}): unknown {
  const requestUrl = new URL(endpoint, 'http://showcase.local');
  const path = requestUrl.pathname;
  const params = requestUrl.searchParams;
  let body: Record<string, unknown> = {};
  if (typeof options.body === 'string') {
    try { body = JSON.parse(options.body) as Record<string, unknown>; } catch { body = {}; }
  } else if (typeof FormData !== 'undefined' && options.body instanceof FormData) {
    body = Object.fromEntries(options.body.entries());
  }
  const ok = { status: true, message: 'Showcase action simulated. No real account or transaction was changed.' };

  if (path === '/login' || path === '/register') {
    const name = String(body.name || DEMO_USER.name);
    const email = String(body.email || body.username || DEMO_USER.email);
    return { status: true, api_token: 'showcase-only-token', data: { ...DEMO_USER, name, email, token: 'showcase-only-token' } };
  }
  if (path === '/logout') return ok;
  if (path === '/v3/profile-details') return readStored<User>('profile', DEMO_USER);
  if (path === '/update-profile') {
    const profile = { ...readStored<User>('profile', DEMO_USER), ...body } as User;
    writeStored('profile', profile);
    return { message: 'Showcase profile updated.', user: profile };
  }
  if (path === '/v3/dashboard-detail') {
    return {
      top_10: { data: [...SHOWCASE_MOVIES].sort((a, b) => Number(b.imdb_rating) - Number(a.imdb_rating)).slice(0, 5) },
      latest_movie: { data: [...SHOWCASE_MOVIES].slice(0, 5) },
      popular_movie: { data: [...SHOWCASE_MOVIES].slice(1, 5) },
      pay_per_view: { data: SHOWCASE_MOVIES.filter((movie) => movie.access === 'paid') },
      tv_show: { data: SHOWCASE_SHOWS },
      continue_watch: readStored<MediaItem[]>('continue', []),
    };
  }
  if (path === '/v3/dashboard-detail-data') return { latest_movie: { data: SHOWCASE_MOVIES }, tv_show: { data: SHOWCASE_SHOWS } };
  if (path === '/v3/banner-data') return { slider: SHOWCASE_BANNERS };
  if (path === '/v3/movie-list') return { data: filterMedia(SHOWCASE_MOVIES, params) };
  if (path === '/v3/tvshow-list') return { data: filterMedia(SHOWCASE_SHOWS, params), has_more: false, next_cursor: null };
  if (path === '/v3/video-list') return { data: SHOWCASE_VIDEOS, current_page: 1, last_page: 1 };
  if (path === '/v3/content-details') {
    const type = params.get('type');
    const item = allMedia().find((media) => String(media.id) === params.get('id') && media.type === type);
    if (!item) return { status: false, message: 'Showcase title not found.' };
    const details = demoContentDetails(item);
    const localReview = readStored<Record<string, ReviewItem>>('reviews', {})[String(item.id)];
    return {
      ...details,
      review: {
        ...details.reviews,
        total_reviews: (details.reviews?.total_reviews || 0) + (localReview ? 1 : 0),
        my_review: localReview || null,
      },
    };
  }
  if (path === '/v3/episode-list') {
    const show = SHOWCASE_SHOWS.find((item) => String(item.id) === params.get('tv_show_id') || String(item.id) === params.get('tvshow_id')) || SHOWCASE_SHOWS[0];
    return { data: makeEpisodes(String(show.id), show.name) };
  }
  if (path === '/v3/livetv-dashboard') {
    const categoryNames = [...new Set(SHOWCASE_CHANNELS.map((channel) => channel.category).filter((name): name is string => Boolean(name)))];
    return {
      slider: SHOWCASE_CHANNELS,
      category_data: categoryNames.map((name, index) => ({
        id: `showcase-category-${index + 1}`,
        name,
        channel_data: SHOWCASE_CHANNELS.filter((channel) => channel.category === name),
      })),
    };
  }
  if (path === '/v3/livetv-details') {
    const channel = SHOWCASE_CHANNELS.find((item) => String(item.id) === params.get('channel_id')) || SHOWCASE_CHANNELS[0];
    return { id: channel.id, details: { ...channel, thumbnail_image: channel.thumbnail_image, has_content_access: true, server_url: demoStream, stream_type: 'HLS' }, video_qualities: [{ quality: 'Auto', url: demoStream, url_type: 'HLS' }] };
  }
  if (path === '/v3/get-search-data') {
    const results = filterMedia(allMedia(), params);
    return { movies: results.filter((item) => item.type === 'movie'), tv_shows: results.filter((item) => item.type === 'tvshow'), videos: results.filter((item) => item.type === 'video'), channels: SHOWCASE_CHANNELS.filter((item) => `${item.name} ${item.name_en}`.toLowerCase().includes((params.get('search') || '').toLowerCase())), data: results };
  }
  if (path === '/v3/watch-list') return readStored<string[]>('watchlist', []).map((id) => allMedia().find((item) => String(item.id) === id)).filter((item): item is MediaItem => Boolean(item));
  if (path === '/save-watchlist') {
    const ids = readStored<string[]>('watchlist', []);
    const id = String(body.entertainment_id || '');
    writeStored('watchlist', ids.includes(id) ? ids : [...ids, id]);
    return ok;
  }
  if (path === '/delete-watchlist') {
    writeStored('watchlist', readStored<string[]>('watchlist', []).filter((id) => id !== String(body.entertainment_id || '')));
    return ok;
  }
  if (path === '/v3/continuewatch-list') return readStored<MediaItem[]>('continue', []);
  if (path === '/save-continuewatch') {
    const contentId = String(body.entertainment_id || body.content_id || '');
    const item = allMedia().find((media) => String(media.id) === contentId);
    if (item) writeStored('continue', [item, ...readStored<MediaItem[]>('continue', []).filter((saved) => String(saved.id) !== contentId)].slice(0, 6));
    return ok;
  }
  if (path === '/v3/subscription/plans') return SHOWCASE_PLANS;
  if (path === '/v3/subscription/calculate-price') {
    const plan = SHOWCASE_PLANS.find((item) => item.plan_id === Number(body.plan_id)) || SHOWCASE_PLANS[0];
    return { plan_id: plan.plan_id, plan_name: plan.name, original_price: plan.price, plan_discount_amount: 0, price_after_plan_discount: plan.price, coupon_discount_amount: 0, subtotal: plan.price, total_tax: 0, taxes: [], total_amount: plan.price, currency: '৳', plan };
  }
  if (path === '/v3/pay-per-view/calculate-price') return { subtotal: 99, discount: 0, tax: 0, total_amount: 99, currency: 'BDT', currency_symbol: '৳' };
  if (path === '/v3/coupon/validate') return { status: false, message: 'Coupons are disabled in showcase mode.' };
  if (path.includes('/process-payment')) {
    const redirect = typeof body.redirect_url === 'string' ? body.redirect_url : '/payment-success';
    const separator = redirect.includes('?') ? '&' : '?';
    return { is_free: false, session_id: 'showcase-session', redirect_url: `${redirect}${separator}gateway=showcase&session_id=showcase-session` };
  }
  if (path.includes('/verify-payment')) return { status: true, message: 'Showcase checkout complete. No payment was processed.', transaction_id: 'SHOWCASE-NO-CHARGE' };
  if (path === '/v3/user/subscription') return { has_active_subscription: true, days_left: 24, is_expired: false, subscription: { id: 1, plan_id: 2, name: 'Showcase Family', status: 'active', start_date: '2025-01-01', end_date: '2027-01-01', amount: 0, total_amount: 0 } };
  if (path === '/v3/user/billing-history') return { subscriptions: [], pay_per_view: [] };
  if (path === '/v3/payment-methods') return {};
  if (path === '/v3/app-configuration') return { currency: { currency_name: 'Bangladeshi Taka', currency_symbol: '৳', currency_code: 'BDT', currency_position: 'left' } };
  if (path === '/v3/seo-settings') return {};
  if (path === '/save-review') {
    const review: ReviewItem = { id: `showcase-review-${Date.now()}`, rating: Number(body.rating) || 5, review: String(body.review || ''), username: DEMO_USER.name, profile_image: DEMO_USER.avatar, updated_at: new Date().toISOString() };
    const contentId = String(body.entertainment_id || '');
    const reviews = readStored<Record<string, ReviewItem>>('reviews', {});
    reviews[contentId] = review;
    writeStored('reviews', reviews);
    return { status: true, data: review };
  }
  if (path === '/delete-review') {
    const reviews = readStored<Record<string, ReviewItem>>('reviews', {});
    for (const [key, review] of Object.entries(reviews)) if (String(review.id) === String(body.id)) delete reviews[key];
    writeStored('reviews', reviews);
    return ok;
  }
  if (path === '/v3/blogs') {
    const query = params.get('search')?.toLowerCase() || '';
    const posts = SHOWCASE_BLOGS.filter((post) => !query || `${post.title} ${post.summary}`.toLowerCase().includes(query));
    const response: BlogListResponse = { current_page: 1, data: posts, last_page: 1, per_page: posts.length, total: posts.length };
    return response;
  }
  if (path.startsWith('/v3/blogs/')) {
    const post = SHOWCASE_BLOGS.find((item) => item.slug === path.split('/').at(-1) || String(item.id) === path.split('/').at(-1));
    return post ? { post, related: SHOWCASE_BLOGS.filter((item) => item.id !== post.id) } satisfies BlogDetailResponse : { post: null, related: [] };
  }
  if (path === '/v3/blog-categories') {
    const categories: BlogCategory[] = [...new Set(SHOWCASE_BLOGS.map((post) => post.category))].map((category) => ({ category, count: SHOWCASE_BLOGS.filter((post) => post.category === category).length }));
    return categories;
  }
  if (path === '/user-profile-list') return readStored<UserMultiProfile[]>('profiles', [{ id: 1, name: DEMO_USER.name, avatar: DEMO_USER.avatar, is_child: false }]);
  if (path === '/save-userprofile') {
    const profiles = readStored<UserMultiProfile[]>('profiles', []);
    const profile: UserMultiProfile = { id: Date.now(), name: String(body.name || 'New profile'), avatar: String(body.avatar || ''), is_child: Boolean(body.is_child) };
    writeStored('profiles', [...profiles, profile]);
    return profile;
  }
  if (path === '/delete-userprofile') { writeStored('profiles', readStored<UserMultiProfile[]>('profiles', []).filter((profile) => String(profile.id) !== String(body.id))); return ok; }
  if (path === '/select-userprofile/' || path.startsWith('/select-userprofile/')) return { message: 'Showcase profile selected.' };
  if (path === '/account-setting') return { auto_play: true, subtitle: true, video_quality: 'auto' };
  if (path === '/notification-list') return { data: [] };
  if (path === '/v3/cast-details') return { id: params.get('id') || 'showcase-cast', name: 'নওশাদ রহমান', bio: 'A showcase cast profile.', profile_image: posters[5], filmography: SHOWCASE_MOVIES.slice(0, 2) };
  if (path.startsWith('/v3/pages/')) {
    const slug = path.split('/').at(-1) || '';
    return { title: slug.replaceAll('-', ' '), name: slug.replaceAll('-', ' '), description: 'This informational page is sample content for the NOOR showcase.', content: '<p>Sample informational content for showcase purposes.</p>' };
  }
  if (path === '/update-parental-lock' || path === '/change-password' || path === '/logout-all' || path.startsWith('/device-logout') || path === '/delete-account' || path.endsWith('/cancel') || path === '/delete-notification' || path === '/delete-review') return ok;
  return {};
}
