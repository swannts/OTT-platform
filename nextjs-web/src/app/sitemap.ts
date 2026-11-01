import { MetadataRoute } from 'next';
import { api } from '@/lib/api';

export const revalidate = 3600; // Revalidate sitemap once every hour

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const baseUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://noorcinema.com';

  const staticRoutes: MetadataRoute.Sitemap = [
    {
      url: `${baseUrl}`,
      lastModified: new Date(),
      changeFrequency: 'daily',
      priority: 1.0,
    },
    {
      url: `${baseUrl}/movies`,
      lastModified: new Date(),
      changeFrequency: 'daily',
      priority: 0.9,
    },
    {
      url: `${baseUrl}/tv-shows`,
      lastModified: new Date(),
      changeFrequency: 'daily',
      priority: 0.9,
    },
    {
      url: `${baseUrl}/live-tv`,
      lastModified: new Date(),
      changeFrequency: 'hourly',
      priority: 0.9,
    },
    {
      url: `${baseUrl}/videos`,
      lastModified: new Date(),
      changeFrequency: 'weekly',
      priority: 0.8,
    },
    {
      url: `${baseUrl}/blog`,
      lastModified: new Date(),
      changeFrequency: 'daily',
      priority: 0.85,
    },
    {
      url: `${baseUrl}/pricing`,
      lastModified: new Date(),
      changeFrequency: 'monthly',
      priority: 0.7,
    },
    {
      url: `${baseUrl}/pages/privacy-policy`,
      lastModified: new Date(),
      changeFrequency: 'monthly',
      priority: 0.4,
    },
    {
      url: `${baseUrl}/pages/terms-of-service`,
      lastModified: new Date(),
      changeFrequency: 'monthly',
      priority: 0.4,
    },
  ];

  let movieRoutes: MetadataRoute.Sitemap = [];
  try {
    const moviesData = await api.getMovies({ page: 1 });
    const moviesList = Array.isArray(moviesData) ? moviesData : moviesData?.data || [];
    movieRoutes = (moviesList as Array<{ id: string | number }>).map((m) => ({
      url: `${baseUrl}/movies/${m.id}`,
      lastModified: new Date(),
      changeFrequency: 'weekly' as const,
      priority: 0.8,
    }));
  } catch (e) {
    console.error('Failed to generate sitemap movies:', e);
  }

  let tvRoutes: MetadataRoute.Sitemap = [];
  try {
    const tvData = await api.getTvShows({ page: 1 });
    const tvList = Array.isArray(tvData) ? tvData : tvData?.data || [];
    tvRoutes = (tvList as Array<{ id: string | number }>).map((t) => ({
      url: `${baseUrl}/tv-shows/${t.id}`,
      lastModified: new Date(),
      changeFrequency: 'weekly' as const,
      priority: 0.8,
    }));
  } catch (e) {
    console.error('Failed to generate sitemap tv shows:', e);
  }

  return [...staticRoutes, ...movieRoutes, ...tvRoutes];
}

