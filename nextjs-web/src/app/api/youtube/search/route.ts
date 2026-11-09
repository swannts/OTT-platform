import { NextRequest, NextResponse } from 'next/server';
import { SHOWCASE_MODE } from '@/lib/showcase';

const YOUTUBE_SEARCH_ENDPOINT = 'https://www.googleapis.com/youtube/v3/search';

type YouTubeSearchItem = {
  id?: { videoId?: string };
  snippet?: {
    title?: string;
    description?: string;
    channelTitle?: string;
    publishedAt?: string;
    thumbnails?: { high?: { url?: string }; medium?: { url?: string } };
  };
};

export async function GET(request: NextRequest) {
  const query = request.nextUrl.searchParams.get('q')?.trim();
  if (!query) {
    return NextResponse.json({ error: 'A search query is required.' }, { status: 400 });
  }

  const maxResults = Math.min(
    Math.max(Number(request.nextUrl.searchParams.get('maxResults') ?? 12) || 12, 1),
    50,
  );

  if (SHOWCASE_MODE) {
    return NextResponse.json({
      items: [
        { videoId: 'showcase-sample-1', title: `নমুনা ভিডিও: ${query}`, description: 'Showcase preview item. No external video search was performed.', channelTitle: 'NOOR Showcase', publishedAt: '2025-01-01T00:00:00Z', thumbnailUrl: null, url: 'https://www.youtube.com/watch?v=showcase-sample-1' },
        { videoId: 'showcase-sample-2', title: `প্রদর্শনী ক্লিপ: ${query}`, description: 'Sample result for the frontend showcase.', channelTitle: 'NOOR Showcase', publishedAt: '2025-01-01T00:00:00Z', thumbnailUrl: null, url: 'https://www.youtube.com/watch?v=showcase-sample-2' },
      ].slice(0, maxResults),
    });
  }

  const apiKey = process.env.YOUTUBE_API_KEY;
  if (!apiKey) {
    return NextResponse.json({ error: 'YouTube integration is not configured.' }, { status: 503 });
  }
  const params = new URLSearchParams({
    key: apiKey,
    part: 'snippet',
    q: query,
    type: 'video',
    maxResults: String(maxResults),
    regionCode: 'BD',
    videoEmbeddable: 'true',
    safeSearch: 'moderate',
  });

  try {
    const response = await fetch(`${YOUTUBE_SEARCH_ENDPOINT}?${params}`, {
      next: { revalidate: 300 },
    });
    const payload = (await response.json()) as { items?: YouTubeSearchItem[]; error?: { message?: string } };

    if (!response.ok) {
      return NextResponse.json(
        { error: payload.error?.message ?? 'YouTube search failed.' },
        { status: response.status >= 400 && response.status < 600 ? response.status : 502 },
      );
    }

    const items = (payload.items ?? [])
      .filter((item) => item.id?.videoId && item.snippet?.title)
      .map((item) => ({
        videoId: item.id!.videoId!,
        title: item.snippet!.title!,
        description: item.snippet?.description ?? '',
        channelTitle: item.snippet?.channelTitle ?? '',
        publishedAt: item.snippet?.publishedAt ?? null,
        thumbnailUrl:
          item.snippet?.thumbnails?.high?.url ?? item.snippet?.thumbnails?.medium?.url ?? null,
        url: `https://www.youtube.com/watch?v=${item.id!.videoId!}`,
      }));

    return NextResponse.json({ items });
  } catch {
    return NextResponse.json({ error: 'Unable to reach YouTube right now.' }, { status: 502 });
  }
}
