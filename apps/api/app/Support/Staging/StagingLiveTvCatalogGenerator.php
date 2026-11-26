<?php

namespace App\Support\Staging;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StagingLiveTvCatalogGenerator
{
    private const CATEGORIES = [
        ['Learning and Reflection', 'Careful educational programming for families and curious learners.'],
        ['History and Heritage', 'Fictional documentary-style programs about culture, places, and shared heritage.'],
        ['Family and Character', 'Family-oriented stories about empathy, responsibility, and community life.'],
        ['Science and Discovery', 'Educational programs about observation, invention, and the natural world.'],
        ['Arts and Reading', 'Programs about books, language, craft, and creative practice.'],
    ];

    private const CATEGORY_BN = [
        ['শিক্ষা ও ভাবনা', 'পরিবার ও অনুসন্ধিৎসু শিক্ষার্থীদের জন্য যত্নসহকারে তৈরি শিক্ষামূলক অনুষ্ঠান।'],
        ['ইতিহাস ও ঐতিহ্য', 'সংস্কৃতি, স্থান ও যৌথ ঐতিহ্য নিয়ে কাল্পনিক প্রামাণ্যধর্মী অনুষ্ঠান।'],
        ['পরিবার ও চরিত্র', 'সহমর্মিতা, দায়িত্ব ও সামাজিক জীবন নিয়ে পরিবার-উপযোগী গল্প।'],
        ['বিজ্ঞান ও আবিষ্কার', 'পর্যবেক্ষণ, উদ্ভাবন ও প্রকৃতি নিয়ে শিক্ষামূলক অনুষ্ঠান।'],
        ['শিল্প ও পাঠ', 'বই, ভাষা, কারুশিল্প ও সৃজনশীল চর্চা নিয়ে অনুষ্ঠান।'],
    ];

    private const CHANNEL_STARTS = [
        ['The Open Learning Room', 'উন্মুক্ত শেখার ঘর'],
        ['Letters from the Archive', 'আর্কাইভ থেকে চিঠি'],
        ['A Family Field Journal', 'পরিবারের অনুসন্ধানী খাতা'],
        ['The Patient Question', 'ধৈর্যের প্রশ্ন'],
        ['Across the Shared Table', 'সবার মিলিত আসরে'],
        ['The Makers’ Workshop', 'কারিগরদের কর্মশালা'],
        ['Maps for Curious Minds', 'অনুসন্ধিৎসু মনের মানচিত্র'],
        ['Small Steps in Discovery', 'আবিষ্কারের ছোট পদক্ষেপ'],
        ['Stories beside the River', 'নদীর পাশে গল্প'],
        ['The Neighborhood Library', 'পাড়ার গ্রন্থাগার'],
    ];

    private const CHANNEL_THEMES = [
        ['Shared Knowledge', 'জ্ঞান ভাগাভাগি'], ['Living Heritage', 'জীবন্ত ঐতিহ্য'],
        ['Questions and Evidence', 'প্রশ্ন ও প্রমাণ'], ['Gardens and Stewardship', 'বাগান ও যত্ন'],
        ['Craft and Materials', 'কারুশিল্প ও উপকরণ'], ['Books across Generations', 'প্রজন্ম পেরোনো বই'],
        ['Cities and Architecture', 'নগর ও স্থাপত্য'], ['The Night Sky', 'রাতের আকাশ'],
        ['Travel and Belonging', 'ভ্রমণ ও আপনত্ব'], ['Everyday Ethics', 'দৈনন্দিন নৈতিকতা'],
    ];

    /** @return array{categories:int,channels:int,draft_channels:int,stream_mappings:int} */
    public function generate(StagingGenerationContext $context): array
    {
        $target = $context->profile->counts['live_channels'];
        if ($target === 0) return ['categories' => 0, 'channels' => 0, 'draft_channels' => 0, 'stream_mappings' => 0];

        $datasetPart = StagingIdentifiers::datasetPart($context->datasetId);
        $categoryTarget = min(count(self::CATEGORIES), max(1, (int) ceil($target / 5)));
        $categories = $this->generateCategories($context, $datasetPart, $categoryTarget);
        $channelCount = $this->generateChannels($context, $datasetPart, $target, $categories);

        return ['categories' => count($categories), 'channels' => $channelCount, 'draft_channels' => $channelCount, 'stream_mappings' => 0];
    }

    /** @return list<string> */
    private function generateCategories(StagingGenerationContext $context, string $datasetPart, int $target): array
    {
        $owned = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
            ->where('entity_type', 'live_tv_category')->get()->keyBy('entity_key');
        $ids = [];
        for ($index = 0; $index < $target; $index++) {
            $key = sprintf('staging-%s-category-%02d', $datasetPart, $index + 1);
            $owner = $owned->get($key);
            $row = DB::table('live_tv_category')->where('slug', $key)->first();
            if ($owner) {
                if (! $row || (string) $row->id !== (string) $owner->record_id || (int) $row->status !== 1) {
                    throw new RuntimeException("Owned staging Live TV category {$key} is missing or invalid.");
                }
                $ids[] = (string) $row->id;
                continue;
            }
            if ($row) throw new RuntimeException("Refusing to adopt unowned Live TV category {$key}.");

            $id = StagingIdentifiers::stableUuid($context->datasetId, 'live-tv-category:'.$key);
            $now = now('UTC')->toDateTimeString();
            DB::transaction(function () use ($context, $key, $id, $index, $now): void {
                DB::table('live_tv_category')->insert([
                    'id' => $id, 'name' => self::CATEGORIES[$index][0], 'slug' => $key,
                    'file_url' => null, 'description' => self::CATEGORIES[$index][1], 'status' => 1,
                    'created_by' => null, 'updated_by' => null, 'deleted_by' => null,
                    'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
                ]);
                DB::table('staging_dataset_records')->insert([
                    'dataset_id' => $context->datasetId, 'entity_type' => 'live_tv_category',
                    'entity_key' => $key, 'record_id' => $id, 'created_at' => $now, 'updated_at' => $now,
                ]);
            });
            $ids[] = $id;
        }
        return $ids;
    }

    /** @param list<string> $categories */
    private function generateChannels(StagingGenerationContext $context, string $datasetPart, int $target, array $categories): int
    {
        $owned = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
            ->where('entity_type', 'live_tv_channel')->get()->keyBy('entity_key');
        for ($index = 0; $index < $target; $index++) {
            $key = sprintf('staging-%s-channel-%04d', $datasetPart, $index + 1);
            $owner = $owned->get($key);
            $row = DB::table('live_tv_channel')->where('slug', $key)->first();
            if ($owner) {
                if (! $row || (string) $row->id !== (string) $owner->record_id || (int) $row->status !== 0
                    || DB::table('live_tv_stream_content_mapping')->where('tv_channel_id', $row->id)->whereNull('deleted_at')->exists()) {
                    throw new RuntimeException("Owned staging Live TV channel {$key} is missing, published, or has an unverified stream mapping.");
                }
                continue;
            }
            if ($row) throw new RuntimeException("Refusing to adopt unowned Live TV channel {$key}.");

            $id = StagingIdentifiers::stableUuid($context->datasetId, 'live-tv-channel:'.$key);
            [$start, $startBn] = self::CHANNEL_STARTS[$index % count(self::CHANNEL_STARTS)];
            [$theme, $themeBn] = self::CHANNEL_THEMES[intdiv($index, count(self::CHANNEL_STARTS)) % count(self::CHANNEL_THEMES)];
            $name = $start.' — '.$theme;
            $nameBn = $startBn.' — '.$themeBn;
            $now = now('UTC')->toDateTimeString();
            DB::transaction(function () use ($context, $key, $id, $index, $categories, $name, $nameBn, $now): void {
                DB::table('live_tv_channel')->insert([
                    'id' => $id, 'name' => $name, 'name_bn' => $nameBn,
                    'poster_url' => null, 'thumb_url' => null, 'poster_tv_url' => null,
                    'category_id' => $categories[$index % count($categories)], 'access' => 'free', 'plan_id' => null,
                    'description' => 'Fictional staging channel draft for metadata, categorization, and review workflows. No stream source has been configured.',
                    'description_bn' => 'মেটাডেটা, বিভাগ ও পর্যালোচনা কার্যপ্রবাহের জন্য কাল্পনিক স্টেজিং চ্যানেল খসড়া। কোনো সম্প্রচার উৎস নির্ধারণ করা হয়নি।',
                    'status' => 0, 'created_by' => null, 'updated_by' => null, 'deleted_by' => null,
                    'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
                    'slug' => $key,
                ]);
                DB::table('staging_dataset_records')->insert([
                    'dataset_id' => $context->datasetId, 'entity_type' => 'live_tv_channel',
                    'entity_key' => $key, 'record_id' => $id, 'created_at' => $now, 'updated_at' => $now,
                ]);
            });
        }
        return (int) DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
            ->where('entity_type', 'live_tv_channel')->count();
    }
}
