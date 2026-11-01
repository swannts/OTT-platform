import { api } from '@/lib/api';
import {
  BlogPost,
  BlogCategory,
  BlogQueryParams,
  BlogListResponse,
  BlogDetailResponse,
} from '@/types';

const FALLBACK_BLOGS: BlogPost[] = [
  {
    id: 1,
    title: 'The Renaissance of Halal Cinema: Storytelling in the Modern Era',
    slug: 'renaissance-of-halal-cinema',
    summary:
      'How contemporary Muslim filmmakers are reshaping global entertainment with values-driven narratives and state-of-the-art cinematography.',
    content: `For decades, families seeking clean, spiritually uplifting screen entertainment faced a stark compromise. Mainstream global streaming services rarely catered to Islamic ethical frameworks, while traditional faith-based video suffered from constrained budgets and rudimentary production values.

Today, a profound creative revolution is underway. Muslim directors, screenwriters, and cinematographers around the world are redefining what Islamic entertainment looks and feels like.

### The Pillars of Values-Driven Cinema
1. **Sacred Reverence**: Respecting the honor of Prophets, Companions, and righteous scholars without commercial exploitation.
2. **Technical Mastery**: Utilizing 4K digital cinema cameras, cinematic anamorphic lenses, and immersive soundscapes that rival major international studios.
3. **Universality**: Crafting stories of human dignity, justice, family warmth, and ethical dilemmas that resonate across cultural borders.

In projects like *Prophetic Chronicles* and *The Golden Age of Cordoba*, production designers collaborate directly with historians and classical Islamic scholars. Every costume, architectural detail, and dialogue fragment is meticulously verified against primary historical chronicles.`,
    image_url:
      'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=1200&auto=format&fit=crop&q=85',
    category: 'Cinema & Culture',
    tags: ['Cinema', 'Culture', 'Halal Media', 'Storytelling'],
    author_name: 'Dr. Zayd Al-Husseini',
    author_avatar:
      'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=120&auto=format&fit=crop&q=80',
    reading_time: 6,
    views_count: 1240,
    is_featured: true,
    status: 1,
    published_at: '2026-10-30T10:00:00Z',
  },
  {
    id: 2,
    title: 'Behind the Scenes: Recreating 7th Century Arabia in 4K UHD',
    slug: 'behind-the-scenes-7th-century-arabia',
    summary:
      'An exclusive look into the production design, archaeological references, and visual effects that brought the seerah of the Messenger ﷺ to life.',
    content: `Recreating the harsh desert sands, stone alleys of ancient Mecca, and lush date-palm oases of Medina required unprecedented research and cinematic discipline.

### Archaeological Authenticity
The art department spent over fourteen months reviewing excavations from the Arabian Peninsula. Rather than relying on modernized sets, the production built full-scale stone and mud-brick structures using authentic historical construction methods.

- **Over 400 bespoke costumes** woven with natural unbleached linen and dyed with traditional desert indigo and saffron.
- **Historic calligraphy reproduction** of early Hijazi scripts for treaties and letters.
- **Acoustic recreation** of ancient desert winds recorded on location in Wadi Rum.`,
    image_url:
      'https://images.unsplash.com/photo-1509316975850-ff9c5deb0cd9?w=1200&auto=format&fit=crop&q=85',
    category: 'Behind the Scenes',
    tags: ['Seerah', 'History', 'Production', 'Behind the Scenes'],
    author_name: 'Maryam Qasim',
    author_avatar:
      'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=120&auto=format&fit=crop&q=80',
    reading_time: 5,
    views_count: 890,
    is_featured: true,
    status: 1,
    published_at: '2026-10-27T14:30:00Z',
  },
  {
    id: 3,
    title: 'The Golden Age of Islamic Science: When Baghdad Lit the World',
    slug: 'golden-age-of-islamic-science',
    summary:
      'Exploring how the House of Wisdom and Islamic astronomers revolutionized medicine, mathematics, and navigation during Europe’s Dark Ages.',
    content: `From the 8th to the 14th centuries, the Islamic world was the intellectual epicenter of humanity. Scholars of diverse backgrounds gathered in Baghdad's famous Bayt al-Hikmah (House of Wisdom) to translate, refine, and pioneer revolutionary sciences.

- **Al-Khwarizmi**: The father of algebra, whose name gave rise to the term "algorithm".
- **Ibn al-Haytham**: The father of modern optics, who established the scientific empirical method.
- **Ibn Sina (Avicenna)**: Author of The Canon of Medicine, which remained standard medical authority in Europe for over five centuries.
- **Mariam Al-Ijliya**: Master astrolabe maker in Northern Syria whose calculations guided travelers across the oceans.`,
    image_url:
      'https://images.unsplash.com/photo-1564769625905-50e93615e769?w=1200&auto=format&fit=crop&q=85',
    category: 'Islamic History',
    tags: ['History', 'Science', 'Golden Age', 'Baghdad'],
    author_name: 'Prof. Tariq Mansour',
    author_avatar:
      'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=120&auto=format&fit=crop&q=80',
    reading_time: 7,
    views_count: 1520,
    is_featured: false,
    status: 1,
    published_at: '2026-10-23T09:15:00Z',
  },
  {
    id: 4,
    title: 'Crafting Halal Soundtracks: The Art of Vocal Orchestration & Percussion',
    slug: 'crafting-halal-soundtracks',
    summary:
      'How world-class sound designers build emotionally gripping film scores using layered acapella harmonies, ambient foley, and daf rhythms.',
    content: `Music in cinema is often responsible for more than half of the viewer’s emotional immersion. For strict halal productions, composing a gripping soundtrack without conventional musical instruments presents a unique creative challenge.

By multitracking 60 distinct human vocal registers — from deep sub-bass undertones to ethereal soprano harmonies — we can evoke the grandeur of a 90-piece symphony orchestra entirely using the human voice.`,
    image_url:
      'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=1200&auto=format&fit=crop&q=85',
    category: 'Sound & Art',
    tags: ['Soundtrack', 'Acapella', 'Halal Audio', 'Cinema'],
    author_name: 'Farhan Noor',
    author_avatar:
      'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=120&auto=format&fit=crop&q=80',
    reading_time: 4,
    views_count: 640,
    is_featured: false,
    status: 1,
    published_at: '2026-10-20T16:00:00Z',
  },
  {
    id: 5,
    title: 'Nurturing Young Minds: Mindful Screen Time for the Muslim Family',
    slug: 'mindful-screen-time-muslim-family',
    summary:
      'Practical advice for parents on navigating digital media, instilling prophetic morals, and choosing enriching family content.',
    content: `In an age of hyper-stimulating algorithmic video feeds and unfiltered content, intentional parenting requires deliberate curation of media diets.

### Guiding Principles for Wholesome Screen Time
1. **Co-Viewing over Isolation**: Watch meaningful series together as a family to spark discussions on virtues such as patience (sabr), generosity (karam), and honesty (sidq).
2. **Quality over Quantity**: Prioritize narrative depth, character growth, and calm pacing over rapid-fire sensory overload.
3. **Connect Stories to Real-Life Deeds**: When watching a story about helping an orphan or planting trees, plan a weekend family activity that mirrors the lesson.`,
    image_url:
      'https://images.unsplash.com/photo-1516627145497-ae6968895b74?w=1200&auto=format&fit=crop&q=85',
    category: 'Family & Parenting',
    tags: ['Kids', 'Parenting', 'Family', 'Education'],
    author_name: 'Amina Siddiqui',
    author_avatar:
      'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=120&auto=format&fit=crop&q=80',
    reading_time: 5,
    views_count: 1100,
    is_featured: false,
    status: 1,
    published_at: '2026-10-16T11:00:00Z',
  },
  {
    id: 6,
    title: 'Preserving Heritage: 4K Digital Restoration of Ancient Islamic Manuscripts',
    slug: 'preserving-heritage-4k-digital-restoration',
    summary:
      'Inside the global archival project documenting historic Quranic codices, Ottoman firmans, and Andalusian architecture before they fade.',
    content: `Centuries of humidity, conflict, and neglect threaten thousands of irreplaceable Islamic manuscripts stored in libraries across Fez, Cairo, Istanbul, and Timbuktu.

Through our cultural partnership initiative, NOOR has dispatched digital preservation teams equipped with multispectral phase-one cameras to scan parchment down to microscopic fibers.`,
    image_url:
      'https://images.unsplash.com/photo-1461360370896-922624d12aa1?w=1200&auto=format&fit=crop&q=85',
    category: 'Heritage & Preservation',
    tags: ['Heritage', 'Manuscripts', 'Quran', 'Art'],
    author_name: 'Dr. Zayd Al-Husseini',
    author_avatar:
      'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=120&auto=format&fit=crop&q=80',
    reading_time: 6,
    views_count: 980,
    is_featured: false,
    status: 1,
    published_at: '2026-10-12T08:30:00Z',
  },
];

export async function getBlogsDal(params: BlogQueryParams = {}): Promise<BlogListResponse> {
  try {
    const res = await api.getBlogs(params);
    if (res && Array.isArray(res.data) && res.data.length > 0) {
      return res;
    }
  } catch (error) {
    console.error('[DAL] getBlogsDal error:', error);
  }

  // Filter fallback list in-memory if backend is unreachable
  let list = [...FALLBACK_BLOGS];
  if (params.category && params.category !== 'all') {
    list = list.filter((b) => b.category.toLowerCase() === params.category!.toLowerCase());
  }
  if (params.search) {
    const term = params.search.toLowerCase();
    list = list.filter(
      (b) =>
        b.title.toLowerCase().includes(term) ||
        (b.summary && b.summary.toLowerCase().includes(term)) ||
        b.category.toLowerCase().includes(term)
    );
  }
  if (params.featured) {
    list = list.filter((b) => b.is_featured);
  }

  return {
    current_page: params.page || 1,
    data: list,
    last_page: 1,
    per_page: params.per_page || 9,
    total: list.length,
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

  const post = FALLBACK_BLOGS.find(
    (b) => String(b.slug) === String(slugOrId) || String(b.id) === String(slugOrId)
  );

  if (!post) return null;

  const related = FALLBACK_BLOGS.filter((b) => b.id !== post.id).slice(0, 3);
  return { post, related };
}

export async function getBlogCategoriesDal(): Promise<BlogCategory[]> {
  try {
    const res = await api.getBlogCategories();
    if (Array.isArray(res) && res.length > 0) {
      return res;
    }
  } catch (error) {
    console.error('[DAL] getBlogCategoriesDal error:', error);
  }

  const counts: Record<string, number> = {};
  FALLBACK_BLOGS.forEach((b) => {
    counts[b.category] = (counts[b.category] || 0) + 1;
  });

  return Object.entries(counts).map(([category, count]) => ({ category, count }));
}
