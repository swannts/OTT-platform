<?php

namespace Database\Seeders;

use App\Models\Blog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class BlogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $posts = [
            [
                'title' => 'The Renaissance of Halal Cinema: Storytelling in the Modern Era',
                'slug' => 'renaissance-of-halal-cinema',
                'summary' => 'How contemporary Muslim filmmakers are reshaping global entertainment with values-driven narratives and state-of-the-art cinematography.',
                'content' => <<<MARKDOWN
# The Renaissance of Halal Cinema: Storytelling in the Modern Era

For decades, families seeking clean, spiritually uplifting screen entertainment faced a stark compromise. Mainstream global streaming services rarely catered to Islamic ethical frameworks, while traditional faith-based video suffered from constrained budgets and rudimentary production values.

Today, a profound creative revolution is underway. Muslim directors, screenwriters, and cinematographers around the world are redefining what Islamic entertainment looks and feels like.

## The Pillars of Values-Driven Cinema

1. **Sacred Reverence**: Respecting the honor of Prophets, Companions, and righteous scholars without commercial exploitation.
2. **Technical Mastery**: Utilizing 4K digital cinema cameras, cinematic anamorphic lenses, and immersive soundscapes that rival major international studios.
3. **Universality**: Crafting stories of human dignity, justice, family warmth, and ethical dilemmas that resonate across cultural borders.

> *"Art is not merely a mirror held up to reality, but a hammer with which to shape our collective moral imagination."*

## Archival Precision and Historical Diligence

In projects like *Prophetic Chronicles* and *The Golden Age of Cordoba*, production designers collaborate directly with historians and classical Islamic scholars. Every costume, architectural detail, and dialogue fragment is meticulously verified against primary historical chronicles.

As platforms like NOOR Islamic Cinema invest in original productions, the horizon for halal entertainment has never looked brighter. Families now have an inspiring space to gather, learn, and be captivated without compromise.
MARKDOWN,
                'image_url' => 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=1200&auto=format&fit=crop&q=85',
                'category' => 'Cinema & Culture',
                'tags' => ['Cinema', 'Culture', 'Halal Media', 'Storytelling'],
                'author_name' => 'Dr. Zayd Al-Husseini',
                'author_avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=120&auto=format&fit=crop&q=80',
                'reading_time' => 6,
                'views_count' => 1240,
                'is_featured' => true,
                'status' => 1,
                'published_at' => Carbon::now()->subDays(2),
            ],
            [
                'title' => 'Behind the Scenes: Recreating 7th Century Arabia in 4K UHD',
                'slug' => 'behind-the-scenes-7th-century-arabia',
                'summary' => 'An exclusive look into the production design, archaeological references, and visual effects that brought the seerah of the Messenger ﷺ to life.',
                'content' => <<<MARKDOWN
# Behind the Scenes: Recreating 7th Century Arabia in 4K UHD

Recreating the harsh desert sands, stone alleys of ancient Mecca, and lush date-palm oases of Medina required unprecedented research and cinematic discipline.

## Archaeological Authenticity

The art department spent over fourteen months reviewing excavations from the Arabian Peninsula. Rather than relying on modernized sets, the production built full-scale stone and mud-brick structures using authentic historical construction methods.

### Key Milestones in the Production:
- **Over 400 bespoke costumes** woven with natural unbleached linen and dyed with traditional desert indigo and saffron.
- **Historic calligraphy reproduction** of early Hijazi scripts for treaties and letters.
- **Acoustic recreation** of ancient desert winds recorded on location in Wadi Rum.

The result is an awe-inspiring docudrama that transports viewers back to the dawn of Islam, making the prophetic legacy palpably real for viewers of all generations.
MARKDOWN,
                'image_url' => 'https://images.unsplash.com/photo-1509316975850-ff9c5deb0cd9?w=1200&auto=format&fit=crop&q=85',
                'category' => 'Behind the Scenes',
                'tags' => ['Seerah', 'History', 'Production', 'Behind the Scenes'],
                'author_name' => 'Maryam Qasim',
                'author_avatar' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=120&auto=format&fit=crop&q=80',
                'reading_time' => 5,
                'views_count' => 890,
                'is_featured' => true,
                'status' => 1,
                'published_at' => Carbon::now()->subDays(5),
            ],
            [
                'title' => 'The Golden Age of Islamic Science: When Baghdad Lit the World',
                'slug' => 'golden-age-of-islamic-science',
                'summary' => 'Exploring how the House of Wisdom and Islamic astronomers revolutionized medicine, mathematics, and navigation during Europe’s Dark Ages.',
                'content' => <<<MARKDOWN
# The Golden Age of Islamic Science: When Baghdad Lit the World

From the 8th to the 14th centuries, the Islamic world was the intellectual epicenter of humanity. Scholars of diverse backgrounds gathered in Baghdad's famous *Bayt al-Hikmah* (House of Wisdom) to translate, refine, and pioneer revolutionary sciences.

## Groundbreaking Pioneers Featured in Our Docuseries

- **Al-Khwarizmi**: The father of algebra, whose name gave rise to the term "algorithm".
- **Ibn al-Haytham**: The father of modern optics, who established the scientific empirical method.
- **Ibn Sina (Avicenna)**: Author of *The Canon of Medicine*, which remained standard medical authority in Europe for over five centuries.
- **Mariam Al-Ijliya (Al-Asturlabiya)**: Master astrolabe maker in Northern Syria whose calculations guided travelers across the oceans.

Our upcoming docuseries illuminates this glorious chapter of history with CGI reconstructions of historic observatories and interactive astrolabe diagrams.
MARKDOWN,
                'image_url' => 'https://images.unsplash.com/photo-1564769625905-50e93615e769?w=1200&auto=format&fit=crop&q=85',
                'category' => 'Islamic History',
                'tags' => ['History', 'Science', 'Golden Age', 'Baghdad'],
                'author_name' => 'Prof. Tariq Mansour',
                'author_avatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=120&auto=format&fit=crop&q=80',
                'reading_time' => 7,
                'views_count' => 1520,
                'is_featured' => false,
                'status' => 1,
                'published_at' => Carbon::now()->subDays(9),
            ],
            [
                'title' => 'Crafting Halal Soundtracks: The Art of Vocal Orchestration & Percussion',
                'slug' => 'crafting-halal-soundtracks',
                'summary' => 'How world-class sound designers build emotionally gripping film scores using layered acapella harmonies, ambient foley, and daf rhythms.',
                'content' => <<<MARKDOWN
# Crafting Halal Soundtracks: The Art of Vocal Orchestration & Percussion

Music in cinema is often responsible for more than half of the viewer’s emotional immersion. For strict halal productions, composing a gripping soundtrack without conventional musical instruments presents a unique creative challenge.

## Innovations in Vocal Polyphony

Sound director Farhan Noor describes the technique:
> *"By multitracking 60 distinct human vocal registers — from deep sub-bass undertones to ethereal soprano harmonies — we can evoke the grandeur of a 90-piece symphony orchestra entirely using the human voice."*

Combined with organic foley like blowing sands, flowing streams, and rhythmic percussion (*daf*), the acoustic landscape of NOOR's productions achieves deep emotional power while honoring strict Islamic guidelines.
MARKDOWN,
                'image_url' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=1200&auto=format&fit=crop&q=85',
                'category' => 'Sound & Art',
                'tags' => ['Soundtrack', 'Acapella', 'Halal Audio', 'Cinema'],
                'author_name' => 'Farhan Noor',
                'author_avatar' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=120&auto=format&fit=crop&q=80',
                'reading_time' => 4,
                'views_count' => 640,
                'is_featured' => false,
                'status' => 1,
                'published_at' => Carbon::now()->subDays(12),
            ],
            [
                'title' => 'Nurturing Young Minds: Mindful Screen Time for the Muslim Family',
                'slug' => 'mindful-screen-time-muslim-family',
                'summary' => 'Practical advice for parents on navigating digital media, instilling prophetic morals, and choosing enriching family content.',
                'content' => <<<MARKDOWN
# Nurturing Young Minds: Mindful Screen Time for the Muslim Family

In an age of hyper-stimulating algorithmic video feeds and unfiltered content, intentional parenting requires deliberate curation of media diets.

## Guiding Principles for Wholesome Screen Time

1. **Co-Viewing over Isolation**: Watch meaningful series together as a family to spark discussions on virtues such as patience (*sabr*), generosity (*karam*), and honesty (*sidq*).
2. **Quality over Quantity**: Prioritize narrative depth, character growth, and calm pacing over rapid-fire sensory overload.
3. **Connect Stories to Real-Life Deeds**: When watching a story about helping an orphan or planting trees, plan a weekend family activity that mirrors the lesson.

NOOR Kids provides parents with parental locks, safe profiles, and values-aligned animated shorts designed specifically to cultivate love for Allah and His Messenger ﷺ.
MARKDOWN,
                'image_url' => 'https://images.unsplash.com/photo-1516627145497-ae6968895b74?w=1200&auto=format&fit=crop&q=85',
                'category' => 'Family & Parenting',
                'tags' => ['Kids', 'Parenting', 'Family', 'Education'],
                'author_name' => 'Amina Siddiqui',
                'author_avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=120&auto=format&fit=crop&q=80',
                'reading_time' => 5,
                'views_count' => 1100,
                'is_featured' => false,
                'status' => 1,
                'published_at' => Carbon::now()->subDays(16),
            ],
            [
                'title' => 'Preserving Heritage: 4K Digital Restoration of Ancient Islamic Manuscripts',
                'slug' => 'preserving-heritage-4k-digital-restoration',
                'summary' => 'Inside the global archival project documenting historic Quranic codices, Ottoman firmans, and Andalusian architecture before they fade.',
                'content' => <<<MARKDOWN
# Preserving Heritage: 4K Digital Restoration of Ancient Islamic Manuscripts

Centuries of humidity, conflict, and neglect threaten thousands of irreplaceable Islamic manuscripts stored in libraries across Fez, Cairo, Istanbul, and Timbuktu.

Through our cultural partnership initiative, NOOR has dispatched digital preservation teams equipped with multispectral phase-one cameras to scan parchment down to microscopic fibers.

Our documentary series *Sacred Inks* showcases:
- 8th-century parchment leaves from the Kufic Quran of Samarkand.
- Architectural drawings of the Great Mosque of Cordoba.
- Astrological manuscripts decorated with lapis lazuli and crushed gold leaf.

By bringing these treasures to life in 4K Ultra HD, we ensure that current and future generations remain intimately tethered to the grandeur of our collective heritage.
MARKDOWN,
                'image_url' => 'https://images.unsplash.com/photo-1461360370896-922624d12aa1?w=1200&auto=format&fit=crop&q=85',
                'category' => 'Heritage & Preservation',
                'tags' => ['Heritage', 'Manuscripts', 'Quran', 'Art'],
                'author_name' => 'Dr. Zayd Al-Husseini',
                'author_avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=120&auto=format&fit=crop&q=80',
                'reading_time' => 6,
                'views_count' => 980,
                'is_featured' => false,
                'status' => 1,
                'published_at' => Carbon::now()->subDays(20),
            ],
        ];

        foreach ($posts as $data) {
            Blog::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}

