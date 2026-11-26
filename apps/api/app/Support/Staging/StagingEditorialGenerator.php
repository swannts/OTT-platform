<?php

namespace App\Support\Staging;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StagingEditorialGenerator
{
    /** @var list<array{en: string, bn: string, category: string, category_bn: string}> */
    private const TOPICS = [
        ['en' => 'making time for reflective reading', 'bn' => 'ভাবনামূলক পাঠের জন্য সময় রাখা', 'category' => 'Quran & Reflection', 'category_bn' => 'কুরআন ও ভাবনা'],
        ['en' => 'patient listening in family conversations', 'bn' => 'পারিবারিক কথোপকথনে মনোযোগ দিয়ে শোনা', 'category' => 'Family & Learning', 'category_bn' => 'পরিবার ও শিক্ষা'],
        ['en' => 'building a gentle study routine', 'bn' => 'সহজ একটি অধ্যয়ন-রুটিন গড়ে তোলা', 'category' => 'Learning', 'category_bn' => 'শিক্ষা'],
        ['en' => 'curiosity about architecture and place', 'bn' => 'স্থাপত্য ও স্থান নিয়ে কৌতূহল', 'category' => 'Culture & Heritage', 'category_bn' => 'সংস্কৃতি ও ঐতিহ্য'],
        ['en' => 'careful questions for young learners', 'bn' => 'শিশুদের শেখার জন্য ভাবনাপূর্ণ প্রশ্ন', 'category' => 'Family & Learning', 'category_bn' => 'পরিবার ও শিক্ষা'],
        ['en' => 'sharing stories across generations', 'bn' => 'প্রজন্মের মধ্যে গল্প ভাগ করে নেওয়া', 'category' => 'Culture & Heritage', 'category_bn' => 'সংস্কৃতি ও ঐতিহ্য'],
        ['en' => 'attention as a daily practice', 'bn' => 'প্রতিদিনের চর্চা হিসেবে মনোযোগ', 'category' => 'Faith & Daily Life', 'category_bn' => 'বিশ্বাস ও দৈনন্দিন জীবন'],
        ['en' => 'the craft of asking better questions', 'bn' => 'আরও ভালো প্রশ্ন করার কৌশল', 'category' => 'Learning', 'category_bn' => 'শিক্ষা'],
        ['en' => 'a family evening without screens', 'bn' => 'পর্দা ছাড়া পরিবারের একটি সন্ধ্যা', 'category' => 'Family & Learning', 'category_bn' => 'পরিবার ও শিক্ষা'],
        ['en' => 'noticing design in everyday places', 'bn' => 'দৈনন্দিন স্থানে নকশা খেয়াল করা', 'category' => 'Culture & Heritage', 'category_bn' => 'সংস্কৃতি ও ঐতিহ্য'],
        ['en' => 'keeping a personal learning journal', 'bn' => 'নিজের শেখার দিনলিপি রাখা', 'category' => 'Learning', 'category_bn' => 'শিক্ষা'],
        ['en' => 'care and responsibility in shared spaces', 'bn' => 'সবার ব্যবহারের জায়গায় যত্ন ও দায়িত্ব', 'category' => 'Faith & Daily Life', 'category_bn' => 'বিশ্বাস ও দৈনন্দিন জীবন'],
        ['en' => 'preparing children for thoughtful media use', 'bn' => 'শিশুদের সচেতনভাবে গণমাধ্যম ব্যবহারে সহায়তা', 'category' => 'Family & Learning', 'category_bn' => 'পরিবার ও শিক্ষা'],
        ['en' => 'learning through maps and journeys', 'bn' => 'মানচিত্র ও ভ্রমণের মাধ্যমে শেখা', 'category' => 'Culture & Heritage', 'category_bn' => 'সংস্কৃতি ও ঐতিহ্য'],
        ['en' => 'small acts of generosity at home', 'bn' => 'ঘরে উদারতার ছোট ছোট কাজ', 'category' => 'Faith & Daily Life', 'category_bn' => 'বিশ্বাস ও দৈনন্দিন জীবন'],
        ['en' => 'making room for quiet reflection', 'bn' => 'নীরব ভাবনার জন্য জায়গা রাখা', 'category' => 'Quran & Reflection', 'category_bn' => 'কুরআন ও ভাবনা'],
        ['en' => 'learning to compare sources carefully', 'bn' => 'যত্ন নিয়ে বিভিন্ন সূত্র তুলনা করা', 'category' => 'Learning', 'category_bn' => 'শিক্ষা'],
        ['en' => 'sharing responsibilities between siblings', 'bn' => 'ভাইবোনের মধ্যে দায়িত্ব ভাগ করা', 'category' => 'Family & Learning', 'category_bn' => 'পরিবার ও শিক্ষা'],
        ['en' => 'the value of patient craftsmanship', 'bn' => 'ধৈর্যশীল কারুশিল্পের মূল্য', 'category' => 'Culture & Heritage', 'category_bn' => 'সংস্কৃতি ও ঐতিহ্য'],
        ['en' => 'planning a balanced weekend', 'bn' => 'ভারসাম্যপূর্ণ ছুটির দিন পরিকল্পনা', 'category' => 'Faith & Daily Life', 'category_bn' => 'বিশ্বাস ও দৈনন্দিন জীবন'],
        ['en' => 'helping children name their feelings', 'bn' => 'শিশুদের অনুভূতির নাম বুঝতে সহায়তা', 'category' => 'Family & Learning', 'category_bn' => 'পরিবার ও শিক্ষা'],
        ['en' => 'learning from patterns in nature', 'bn' => 'প্রকৃতির নানা বিন্যাস থেকে শেখা', 'category' => 'Science & Discovery', 'category_bn' => 'বিজ্ঞান ও আবিষ্কার'],
        ['en' => 'making a welcoming reading corner', 'bn' => 'স্বাগতপূর্ণ একটি পড়ার কোণা তৈরি', 'category' => 'Family & Learning', 'category_bn' => 'পরিবার ও শিক্ষা'],
        ['en' => 'balancing ambition with rest', 'bn' => 'আকাঙ্ক্ষার সঙ্গে বিশ্রামের ভারসাম্য', 'category' => 'Faith & Daily Life', 'category_bn' => 'বিশ্বাস ও দৈনন্দিন জীবন'],
        ['en' => 'recognizing the work behind a film', 'bn' => 'একটি চলচ্চিত্রের পেছনের কাজকে চেনা', 'category' => 'Culture & Heritage', 'category_bn' => 'সংস্কৃতি ও ঐতিহ্য'],
        ['en' => 'creating shared family traditions', 'bn' => 'পরিবারের সবার জন্য রীতি তৈরি', 'category' => 'Family & Learning', 'category_bn' => 'পরিবার ও শিক্ষা'],
        ['en' => 'using stories to open a conversation', 'bn' => 'আলাপের শুরুতে গল্পের ব্যবহার', 'category' => 'Learning', 'category_bn' => 'শিক্ষা'],
        ['en' => 'finding wonder in the night sky', 'bn' => 'রাতের আকাশে বিস্ময় খুঁজে পাওয়া', 'category' => 'Science & Discovery', 'category_bn' => 'বিজ্ঞান ও আবিষ্কার'],
        ['en' => 'making learning accessible for everyone', 'bn' => 'সবার জন্য শেখাকে সহজলভ্য করা', 'category' => 'Learning', 'category_bn' => 'শিক্ষা'],
        ['en' => 'practicing gratitude through attention', 'bn' => 'মনোযোগের মাধ্যমে কৃতজ্ঞতার চর্চা', 'category' => 'Quran & Reflection', 'category_bn' => 'কুরআন ও ভাবনা'],
    ];

    /** @return array{blogs: int, published: int, drafts: int} */
    public function generate(StagingGenerationContext $context): array
    {
        $target = $context->profile->counts['blogs'];
        $datasetPart = StagingIdentifiers::datasetPart($context->datasetId);
        $keys = [];
        for ($i = 1; $i <= $target; $i++) {
            $keys[] = sprintf('stg-%s-blog-%05d', $datasetPart, $i);
        }
        $owned = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
            ->where('entity_type', 'blog')->whereIn('entity_key', $keys)->get(['entity_key', 'record_id'])->keyBy('entity_key');
        $existing = $owned->isNotEmpty()
            ? DB::table('blogs')->whereIn('id', $owned->pluck('record_id')->filter()->all())->whereNull('deleted_at')->get(['id', 'slug', 'status'])->keyBy('id')
            : collect();
        $rows = [];
        $owners = [];
        $published = 0;
        $drafts = 0;

        for ($index = 0; $index < $target; $index++) {
            $ordinal = $index + 1;
            $slug = $keys[$index];
            $record = $owned->get($slug);
            if ($record) {
                $blog = $record->record_id ? $existing->get($record->record_id) : null;
                if ($blog === null || $blog->slug !== $slug) {
                    throw new RuntimeException("Refusing to adopt or overwrite an unrelated or missing editorial fixture {$slug}.");
                }
                $blog->status === 1 ? $published++ : $drafts++;
                continue;
            }
            if (DB::table('blogs')->where('slug', $slug)->exists()) {
                throw new RuntimeException("Editorial slug {$slug} exists without staging ownership.");
            }

            $topic = self::TOPICS[$index % count(self::TOPICS)];
            $style = intdiv($index, count(self::TOPICS)) % 17;
            [$title, $titleBn] = $this->titles($topic['en'], $topic['bn'], $style);
            $publishedState = $ordinal % 10 !== 0;
            $publishedAt = $publishedState
                ? $context->historyStart()->addSeconds((int) floor($context->historyStart()->diffInSeconds($context->referenceDate) * $ordinal / max(1, $target)))
                : null;
            $summary = "A NOOR Editorial reflection on {$topic['en']}, with prompts for thoughtful discussion and practical learning.";
            $summaryBn = "{$topic['bn']} বিষয়ে নূর সম্পাদকীয়র একটি ভাবনা, সঙ্গে আলোচনা ও ব্যবহারিক শেখার কিছু প্রশ্ন।";
            $body = $this->body($topic['en']);
            $bodyBn = $this->bodyBangla($topic['bn']);
            $estimatedViews = $publishedAt === null ? 0 : (int) (40 + (($ordinal * 173) % 1800) + ($publishedAt->diffInDays($context->referenceDate) * 2));
            $rows[] = [
                'title' => $title,
                'title_bn' => $titleBn,
                'slug' => $slug,
                'summary' => $summary,
                'summary_bn' => $summaryBn,
                'content' => $body,
                'content_bn' => $bodyBn,
                'image_url' => null,
                'category' => $topic['category'],
                'category_bn' => $topic['category_bn'],
                'tags' => json_encode([$topic['category'], 'NOOR Editorial', 'Learning'], JSON_THROW_ON_ERROR),
                'tags_bn' => json_encode([$topic['category_bn'], 'নূর সম্পাদকীয়', 'শেখা'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'author_name' => 'NOOR Editorial',
                'reading_time' => max(2, (int) ceil((str_word_count(strip_tags($body)) + mb_strlen(strip_tags($bodyBn)) / 5) / 220)),
                'views_count' => $estimatedViews,
                'is_featured' => $ordinal % 17 === 1,
                'status' => $publishedState ? 1 : 0,
                'published_at' => $publishedAt,
                'created_at' => $publishedAt ?? $context->historyStart()->addDays($ordinal),
                'updated_at' => $publishedAt ?? $context->historyStart()->addDays($ordinal),
            ];
            $owners[] = ['dataset_id' => $context->datasetId, 'entity_type' => 'blog', 'entity_key' => $slug, 'record_id' => null];
            $publishedState ? $published++ : $drafts++;
        }

        foreach (array_chunk($rows, $context->profile->batchSize) as $chunkIndex => $chunk) {
            DB::transaction(function () use ($context, $chunk, $chunkIndex, $owners): void {
                DB::table('blogs')->insert($chunk);
                $offset = $chunkIndex * $context->profile->batchSize;
                $inserted = DB::table('blogs')->whereIn('slug', array_column($chunk, 'slug'))->get(['id', 'slug'])->keyBy('slug');
                $ownerRows = array_slice($owners, $offset, count($chunk));
                foreach ($ownerRows as &$owner) {
                    $owner['record_id'] = (string) $inserted[$owner['entity_key']]->id;
                    $owner['created_at'] = now();
                    $owner['updated_at'] = now();
                }
                unset($owner);
                DB::table('staging_dataset_records')->insert($ownerRows);
            });
        }

        $ownedCount = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)->where('entity_type', 'blog')->count();
        if ($ownedCount !== $target) {
            throw new RuntimeException("Staging editorial generation produced {$ownedCount} of {$target} configured articles.");
        }
        return ['blogs' => $ownedCount, 'published' => $published, 'drafts' => $drafts];
    }

    /** @return array{string, string} */
    private function titles(string $english, string $bengali, int $style): array
    {
        return match ($style) {
            0 => ['A thoughtful guide to '.$english, $bengali.' নিয়ে একটি ভাবনাপূর্ণ নির্দেশিকা'],
            1 => ['Questions that open a conversation about '.$english, $bengali.' নিয়ে আলাপ শুরুর কিছু প্রশ্ন'],
            2 => ['Bringing '.$english.' into everyday learning', 'দৈনন্দিন শেখায় '.$bengali.' যুক্ত করা'],
            3 => ['Making room for '.$english.' at home', 'ঘরে '.$bengali.' চর্চার জায়গা তৈরি'],
            4 => ['An editorial reflection on '.$english, $bengali.' বিষয়ে একটি সম্পাদকীয় ভাবনা'],
            5 => ['A family conversation about '.$english, $bengali.' নিয়ে পরিবারের আলাপ'],
            6 => ['A learner’s notebook for '.$english, $bengali.' নিয়ে শিক্ষার্থীর নোটবই'],
            7 => ['Small steps toward '.$english, $bengali.' চর্চায় ছোট ছোট পদক্ষেপ'],
            8 => ['How to explore '.$english, $bengali.' কীভাবে অন্বেষণ করা যায়'],
            9 => ['A weekend activity around '.$english, $bengali.' ঘিরে ছুটির দিনের কাজ'],
            10 => ['Ideas for discussing '.$english, $bengali.' নিয়ে আলোচনার ভাবনা'],
            11 => ['A closer look at '.$english, $bengali.' বিষয়ে আরেকটু গভীরভাবে দেখা'],
            12 => ['A gentle introduction to '.$english, $bengali.' বিষয়ে সহজ পরিচয়'],
            13 => ['Reflect, notice, and learn about '.$english, $bengali.' নিয়ে ভাবনা, পর্যবেক্ষণ ও শেখা'],
            14 => ['A shared learning activity: '.$english, 'একসঙ্গে শেখার কাজ: '.$bengali],
            15 => ['Practical prompts for '.$english, $bengali.' নিয়ে ব্যবহারিক কিছু প্রশ্ন'],
            default => ['Making a learning plan for '.$english, $bengali.' শেখার একটি পরিকল্পনা'],
        };
    }

    private function body(string $topic): string
    {
        return "<p>Learning about {$topic} can begin with a small, attentive question. This editorial offers a calm starting point rather than a definitive lesson, and invites readers to bring their own experience to the conversation.</p><p>Choose one example from daily life, describe what you notice, and listen to another perspective before drawing a conclusion. Families and educators can adapt the prompt to different ages, languages, and backgrounds.</p><p>Use the ideas here as discussion prompts. Check factual or religious questions against trusted primary sources and qualified teachers; this article does not present quotations, legal rulings, or historical claims.</p>";
    }

    private function bodyBangla(string $topic): string
    {
        return "<p>{$topic} নিয়ে শেখা শুরু হতে পারে ছোট একটি মনোযোগী প্রশ্ন দিয়ে। এই সম্পাদকীয়টি চূড়ান্ত পাঠ নয়; বরং পাঠকের নিজের অভিজ্ঞতা নিয়ে শান্তভাবে আলোচনার একটি সূচনা।</p><p>দৈনন্দিন জীবনের একটি উদাহরণ বেছে নিন, কী লক্ষ্য করছেন তা বর্ণনা করুন, তারপর সিদ্ধান্তে পৌঁছানোর আগে অন্যের দৃষ্টিভঙ্গি শুনুন। পরিবার ও শিক্ষকেরা বয়স, ভাষা এবং প্রেক্ষাপট অনুযায়ী প্রশ্নটি বদলে নিতে পারেন।</p><p>এখানকার বিষয়গুলো আলোচনার সূত্র হিসেবে ব্যবহার করুন। তথ্যভিত্তিক বা ধর্মীয় প্রশ্নের জন্য নির্ভরযোগ্য প্রাথমিক সূত্র ও যোগ্য শিক্ষকের পরামর্শ নিন; এই লেখায় কোনো উদ্ধৃতি, ধর্মীয় বিধান বা ঐতিহাসিক দাবি করা হয়নি।</p>";
    }
}
