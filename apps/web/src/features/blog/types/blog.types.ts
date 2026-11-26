export interface BlogPost {
  id: number | string;
  title: string;
  slug: string;
  summary?: string;
  content: string;
  image_url?: string;
  category: string;
  tags?: string[];
  author_name: string;
  author_avatar?: string;
  reading_time: number;
  views_count: number;
  is_featured: boolean;
  status: number;
  published_at?: string;
  created_at?: string;
  updated_at?: string;
}

export interface BlogCategory {
  category: string;
  count: number;
}

export interface BlogQueryParams {
  page?: number;
  per_page?: number;
  category?: string;
  search?: string;
  featured?: boolean;
  tag?: string;
}

export interface BlogListResponse {
  current_page: number;
  data: BlogPost[];
  last_page: number;
  per_page: number;
  total: number;
}

export interface BlogDetailResponse {
  post: BlogPost;
  related: BlogPost[];
}

