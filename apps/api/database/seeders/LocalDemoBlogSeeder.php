<?php

namespace Database\Seeders;

use App\Models\Blog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use LogicException;

/** Publishes clearly labeled sample editorial fixtures for local development. */
class LocalDemoBlogSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('LocalDemoBlogSeeder may only run in local or testing environments.');
        }

        $posts = [
            [
                'slug' => 'demo-stories-of-mercy',
                'title' => 'Demo: Stories of Mercy in Islamic Tradition',
                'title_bn' => 'ডেমো: ইসলামি ঐতিহ্যে দয়ার গল্প',
                'summary' => 'A sample editorial about how stories of compassion can help families talk about kindness, responsibility, and care for neighbors.',
                'summary_bn' => 'সহমর্মিতার গল্প কীভাবে পরিবারকে সদয়তা, দায়িত্ববোধ ও প্রতিবেশীর যত্ন নিয়ে কথা বলতে সাহায্য করে—তার একটি নমুনা লেখা।',
                'content' => <<<'MARKDOWN'
# Demo: Stories of Mercy in Islamic Tradition

This sample article demonstrates the NOOR journal layout. It introduces a family discussion about mercy as a daily practice: listening carefully, helping a neighbor, and treating people with dignity.

## A conversation to share

After watching a story together, invite each person to describe one moment of compassion they noticed. Ask what made the action meaningful and how the same value could be practiced at home or in the community.

This is illustrative local demo copy. Review and replace it with approved editorial material before publication.
MARKDOWN,
                'content_bn' => <<<'MARKDOWN'
# ডেমো: ইসলামি ঐতিহ্যে দয়ার গল্প

এটি NOOR জার্নালের বিন্যাস দেখানোর জন্য তৈরি নমুনা লেখা। এতে দয়াকে দৈনন্দিন চর্চা হিসেবে তুলে ধরা হয়েছে—মনোযোগ দিয়ে শোনা, প্রতিবেশীকে সাহায্য করা এবং সবার সঙ্গে মর্যাদাপূর্ণ আচরণ করা।

## একসঙ্গে আলোচনার বিষয়

পরিবারের সঙ্গে কোনো গল্প দেখার পর প্রত্যেকে দয়ার একটি মুহূর্তের কথা বলতে পারেন। কাজটি কেন অর্থবহ ছিল এবং একই মূল্যবোধ ঘরে বা সমাজে কীভাবে চর্চা করা যায়, তা নিয়ে আলোচনা করুন।

এটি স্থানীয় ডেমোর জন্য লেখা নমুনা কনটেন্ট। প্রকাশের আগে পর্যালোচনা করে অনুমোদিত লেখা দিয়ে প্রতিস্থাপন করুন।
MARKDOWN,
                'category' => 'Demo: Faith & Values',
                'category_bn' => 'ডেমো: বিশ্বাস ও মূল্যবোধ',
                'tags' => ['demo', 'family', 'values'],
                'tags_bn' => ['ডেমো', 'পরিবার', 'মূল্যবোধ'],
                'image_url' => 'https://images.unsplash.com/photo-1519817650390-64a93db51149?auto=format&fit=crop&w=1600&q=85',
                'author_name' => 'NOOR Demo Editorial',
                'reading_time' => 3,
                'is_featured' => true,
                'days_ago' => 1,
            ],
            [
                'slug' => 'demo-how-documentaries-tell-history',
                'title' => 'Demo: How Documentaries Bring History to Life',
                'title_bn' => 'ডেমো: প্রামাণ্যচিত্রে ইতিহাসের উপস্থাপন',
                'summary' => 'A sample behind-the-scenes guide to research, narration, archival material, and careful visual storytelling in historical documentaries.',
                'summary_bn' => 'ঐতিহাসিক প্রামাণ্যচিত্রে গবেষণা, বর্ণনা, আর্কাইভের উপকরণ ও যত্নশীল দৃশ্যকাহিনি তৈরির একটি নমুনা পরিচিতি।',
                'content' => <<<'MARKDOWN'
# Demo: How Documentaries Bring History to Life

Historical documentaries combine research and visual storytelling. A production team may consult books, museum collections, maps, and subject-matter reviewers before shaping a script.

## From research to the screen

Clear narration helps viewers follow a timeline, while maps and captions provide context. Re-creations should be identified as illustrations, and uncertainty should be explained rather than hidden.

This is illustrative local demo copy. Review and replace it with approved editorial material before publication.
MARKDOWN,
                'content_bn' => <<<'MARKDOWN'
# ডেমো: প্রামাণ্যচিত্রে ইতিহাসের উপস্থাপন

ঐতিহাসিক প্রামাণ্যচিত্রে গবেষণা ও দৃশ্যকাহিনি একসঙ্গে কাজ করে। চিত্রনাট্য তৈরির আগে নির্মাতারা বই, জাদুঘরের সংগ্রহ, মানচিত্র এবং বিষয়বিশেষজ্ঞদের পরামর্শ নিতে পারেন।

## গবেষণা থেকে পর্দায়

স্পষ্ট বর্ণনা দর্শককে সময়রেখা বুঝতে সাহায্য করে, আর মানচিত্র ও ক্যাপশন প্রয়োজনীয় প্রেক্ষাপট দেয়। পুনর্নির্মিত দৃশ্যকে চিত্রায়িত উদাহরণ হিসেবে চিহ্নিত করা উচিত এবং অনিশ্চয়তা থাকলে তা ব্যাখ্যা করা উচিত।

এটি স্থানীয় ডেমোর জন্য লেখা নমুনা কনটেন্ট। প্রকাশের আগে পর্যালোচনা করে অনুমোদিত লেখা দিয়ে প্রতিস্থাপন করুন।
MARKDOWN,
                'category' => 'Demo: Cinema & Craft',
                'category_bn' => 'ডেমো: চলচ্চিত্র নির্মাণ',
                'tags' => ['demo', 'documentary', 'storytelling'],
                'tags_bn' => ['ডেমো', 'প্রামাণ্যচিত্র', 'কাহিনি'],
                'image_url' => 'https://images.unsplash.com/photo-1478720568477-152d9b164e26?auto=format&fit=crop&w=1600&q=85',
                'author_name' => 'NOOR Demo Editorial',
                'reading_time' => 4,
                'is_featured' => false,
                'days_ago' => 3,
            ],
            [
                'slug' => 'demo-learning-from-the-house-of-wisdom',
                'title' => 'Demo: Learning and Translation in the House of Wisdom',
                'title_bn' => 'ডেমো: বায়তুল হিকমায় জ্ঞানচর্চা ও অনুবাদ',
                'summary' => 'A sample introduction to Baghdad’s scholarly networks and the exchange of ideas across languages and disciplines.',
                'summary_bn' => 'বাগদাদের জ্ঞানচর্চার পরিমণ্ডল এবং নানা ভাষা ও শাস্ত্রে ভাবের আদান-প্রদান নিয়ে একটি নমুনা পরিচিতি।',
                'content' => <<<'MARKDOWN'
# Demo: Learning and Translation in the House of Wisdom

Medieval Baghdad became an important center of scholarship. Translators and scholars worked with texts from several traditions, helping ideas move between languages and fields of study.

## Knowledge grows through exchange

The history of science is shaped by collaboration: preserving earlier work, testing ideas, and sharing findings. This short demo article offers a starting point for further reading with reliable historical sources.

This is illustrative local demo copy. Review and replace it with approved editorial material before publication.
MARKDOWN,
                'content_bn' => <<<'MARKDOWN'
# ডেমো: বায়তুল হিকমায় জ্ঞানচর্চা ও অনুবাদ

মধ্যযুগে বাগদাদ জ্ঞানচর্চার একটি গুরুত্বপূর্ণ কেন্দ্র হয়ে ওঠে। বিভিন্ন ঐতিহ্যের গ্রন্থ নিয়ে অনুবাদক ও পণ্ডিতেরা কাজ করতেন, ফলে নানা ভাষা ও জ্ঞানশাখার মধ্যে ভাবের আদান-প্রদান সম্ভব হয়।

## আদান-প্রদানে জ্ঞানের বিকাশ

বিজ্ঞানের ইতিহাস সহযোগিতার মাধ্যমে গড়ে ওঠে: আগের কাজ সংরক্ষণ, ধারণা যাচাই এবং ফলাফল ভাগ করে নেওয়া এর অংশ। নির্ভরযোগ্য ঐতিহাসিক সূত্র ধরে আরও পড়ার সূচনা হিসেবে এই সংক্ষিপ্ত ডেমো লেখা তৈরি করা হয়েছে।

এটি স্থানীয় ডেমোর জন্য লেখা নমুনা কনটেন্ট। প্রকাশের আগে পর্যালোচনা করে অনুমোদিত লেখা দিয়ে প্রতিস্থাপন করুন।
MARKDOWN,
                'category' => 'Demo: Islamic History',
                'category_bn' => 'ডেমো: ইসলামি ইতিহাস',
                'tags' => ['demo', 'history', 'learning'],
                'tags_bn' => ['ডেমো', 'ইতিহাস', 'জ্ঞানচর্চা'],
                'image_url' => 'https://images.unsplash.com/photo-1507842217343-583bb7270b66?auto=format&fit=crop&w=1600&q=85',
                'author_name' => 'NOOR Demo Editorial',
                'reading_time' => 4,
                'is_featured' => false,
                'days_ago' => 5,
            ],
            [
                'slug' => 'demo-family-viewing-conversations',
                'title' => 'Demo: Turning Family Viewing into a Conversation',
                'title_bn' => 'ডেমো: একসঙ্গে দেখার পর পারিবারিক আলোচনা',
                'summary' => 'Simple prompts parents can use to help children reflect on courage, honesty, patience, and making thoughtful choices.',
                'summary_bn' => 'সাহস, সততা, ধৈর্য ও ভেবেচিন্তে সিদ্ধান্ত নেওয়া নিয়ে শিশুদের সঙ্গে আলোচনার জন্য অভিভাবকদের কিছু সহজ প্রশ্ন।',
                'content' => <<<'MARKDOWN'
# Demo: Turning Family Viewing into a Conversation

Watching together can create a relaxed opportunity to talk. Instead of testing children on details, ask open questions about the characters’ choices and feelings.

## Questions for after the story

- What choice surprised you?
- Which character showed patience or courage?
- What would you do if you faced a similar situation?

Keep the conversation age-appropriate and let children share at their own pace. This is illustrative local demo copy; replace it with reviewed editorial material before publication.
MARKDOWN,
                'content_bn' => <<<'MARKDOWN'
# ডেমো: একসঙ্গে দেখার পর পারিবারিক আলোচনা

একসঙ্গে কিছু দেখলে স্বচ্ছন্দে কথা বলার সুযোগ তৈরি হয়। শিশুদের পরীক্ষা না নিয়ে চরিত্রগুলোর সিদ্ধান্ত ও অনুভূতি নিয়ে খোলামেলা প্রশ্ন করুন।

## গল্প শেষে আলোচনার প্রশ্ন

- কোন সিদ্ধান্তটি তোমাকে অবাক করেছে?
- কোন চরিত্র ধৈর্য বা সাহস দেখিয়েছে?
- তুমি এমন পরিস্থিতিতে পড়লে কী করতে?

আলোচনা শিশুর বয়সের উপযোগী রাখুন এবং তাকে নিজের গতিতে কথা বলতে দিন। এটি স্থানীয় ডেমোর নমুনা কনটেন্ট; প্রকাশের আগে পর্যালোচনা করে অনুমোদিত লেখা দিয়ে প্রতিস্থাপন করুন।
MARKDOWN,
                'category' => 'Demo: Family & Learning',
                'category_bn' => 'ডেমো: পরিবার ও শিক্ষা',
                'tags' => ['demo', 'family', 'children'],
                'tags_bn' => ['ডেমো', 'পরিবার', 'শিশু'],
                'image_url' => 'https://images.unsplash.com/photo-1503454537195-1dcabb73ffb9?auto=format&fit=crop&w=1600&q=85',
                'author_name' => 'NOOR Demo Editorial',
                'reading_time' => 3,
                'is_featured' => false,
                'days_ago' => 7,
            ],
            [
                'slug' => 'demo-preserving-manuscripts-digitally',
                'title' => 'Demo: Preserving Manuscripts for Future Generations',
                'title_bn' => 'ডেমো: ভবিষ্যৎ প্রজন্মের জন্য পাণ্ডুলিপি সংরক্ষণ',
                'summary' => 'A sample overview of digitization, cataloguing, and responsible access to historic manuscripts and community collections.',
                'summary_bn' => 'ঐতিহাসিক পাণ্ডুলিপি ও জনগোষ্ঠীর সংগ্রহের ডিজিটাইজেশন, তালিকাভুক্তি এবং দায়িত্বশীল ব্যবহারের একটি নমুনা পরিচিতি।',
                'content' => <<<'MARKDOWN'
# Demo: Preserving Manuscripts for Future Generations

Libraries and archives use careful handling, cataloguing, and high-resolution imaging to help preserve fragile documents. Digital copies can support research while reducing repeated handling of originals.

## Care beyond the scan

Preservation also involves describing an item accurately, recording its provenance where known, and respecting the communities connected to it. A digital image is a useful resource, but it does not replace the original object or its context.

This is illustrative local demo copy. Review and replace it with approved editorial material before publication.
MARKDOWN,
                'content_bn' => <<<'MARKDOWN'
# ডেমো: ভবিষ্যৎ প্রজন্মের জন্য পাণ্ডুলিপি সংরক্ষণ

ভঙ্গুর নথি সংরক্ষণে গ্রন্থাগার ও আর্কাইভগুলো সতর্ক ব্যবস্থাপনা, তালিকাভুক্তি এবং উচ্চ রেজোলিউশনের ছবি ব্যবহার করে। ডিজিটাল কপি মূল নথি বারবার না ধরেই গবেষণায় সহায়তা করতে পারে।

## স্ক্যানের পরেও সংরক্ষণের কাজ

কোনো নথির সঠিক বিবরণ লেখা, জানা থাকলে তার উৎসের ইতিহাস নথিভুক্ত করা এবং এর সঙ্গে যুক্ত জনগোষ্ঠীর অধিকার সম্মান করাও সংরক্ষণের অংশ। ডিজিটাল ছবি উপকারী হলেও এটি মূল বস্তু বা তার প্রেক্ষাপটের বিকল্প নয়।

এটি স্থানীয় ডেমোর জন্য লেখা নমুনা কনটেন্ট। প্রকাশের আগে পর্যালোচনা করে অনুমোদিত লেখা দিয়ে প্রতিস্থাপন করুন।
MARKDOWN,
                'category' => 'Demo: Heritage & Preservation',
                'category_bn' => 'ডেমো: ঐতিহ্য ও সংরক্ষণ',
                'tags' => ['demo', 'heritage', 'archives'],
                'tags_bn' => ['ডেমো', 'ঐতিহ্য', 'আর্কাইভ'],
                'image_url' => 'https://images.unsplash.com/photo-1544947950-fa07a98d237f?auto=format&fit=crop&w=1600&q=85',
                'author_name' => 'NOOR Demo Editorial',
                'reading_time' => 4,
                'is_featured' => false,
                'days_ago' => 9,
            ],
            [
                'slug' => 'demo-craft-of-audio-storytelling',
                'title' => 'Demo: The Craft of Audio in Screen Storytelling',
                'title_bn' => 'ডেমো: পর্দার গল্পে শব্দের ব্যবহার',
                'summary' => 'A sample look at dialogue, room tone, ambience, and sound mixing—and how each supports a clear viewing experience.',
                'summary_bn' => 'সংলাপ, পরিবেশের স্বর, আবহ ও সাউন্ড মিক্সিং কীভাবে দর্শকের অভিজ্ঞতাকে স্পষ্ট করে—তার একটি নমুনা পরিচিতি।',
                'content' => <<<'MARKDOWN'
# Demo: The Craft of Audio in Screen Storytelling

Sound helps audiences understand a scene. Dialogue needs to remain clear, ambience can establish a sense of place, and a careful mix helps each element sit comfortably together.

## Listening with intention

Editors review audio on different devices and at different volume levels. Accessible captions and balanced dialogue can make a story easier to follow for more viewers.

This is illustrative local demo copy. Review and replace it with approved editorial material before publication.
MARKDOWN,
                'content_bn' => <<<'MARKDOWN'
# ডেমো: পর্দার গল্পে শব্দের ব্যবহার

শব্দ দর্শককে একটি দৃশ্য বুঝতে সাহায্য করে। সংলাপ স্পষ্ট থাকা দরকার, পরিবেশের শব্দ স্থান ও আবহ তৈরি করতে পারে, আর যত্নশীল মিক্সিং প্রতিটি উপাদানকে ভারসাম্যে রাখে।

## মনোযোগ দিয়ে শোনা

সম্পাদকেরা বিভিন্ন যন্ত্রে ও ভলিউমে শব্দ পরীক্ষা করেন। সহজে বোঝা যায় এমন ক্যাপশন এবং ভারসাম্যপূর্ণ সংলাপ আরও বেশি দর্শকের জন্য গল্প অনুসরণ করা সহজ করে।

এটি স্থানীয় ডেমোর জন্য লেখা নমুনা কনটেন্ট। প্রকাশের আগে পর্যালোচনা করে অনুমোদিত লেখা দিয়ে প্রতিস্থাপন করুন।
MARKDOWN,
                'category' => 'Demo: Cinema & Craft',
                'category_bn' => 'ডেমো: চলচ্চিত্র নির্মাণ',
                'tags' => ['demo', 'audio', 'production'],
                'tags_bn' => ['ডেমো', 'শব্দ', 'প্রযোজনা'],
                'image_url' => 'https://images.unsplash.com/photo-1598488035139-bdbb2231ce04?auto=format&fit=crop&w=1600&q=85',
                'author_name' => 'NOOR Demo Editorial',
                'reading_time' => 3,
                'is_featured' => false,
                'days_ago' => 11,
            ],
        ];

        foreach ($posts as $post) {
            $blog = Blog::query()->firstOrNew(['slug' => $post['slug']]);
            $daysAgo = $post['days_ago'];
            unset($post['days_ago']);

            $blog->fill([
                ...$post,
                'status' => 1,
                'published_at' => Carbon::now()->subDays($daysAgo),
            ]);
            $blog->views_count ??= 0;
            $blog->save();
        }
    }
}
