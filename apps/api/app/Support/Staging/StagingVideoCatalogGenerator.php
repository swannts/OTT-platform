<?php

namespace App\Support\Staging;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StagingVideoCatalogGenerator
{
    private const THEMES = [
        ['A Map of Shared Knowledge', 'জ্ঞান ভাগাভাগির মানচিত্র'],
        ['The Patient Observatory', 'ধৈর্যশীল মানমন্দির'],
        ['Letters from the Old City', 'পুরোনো শহর থেকে চিঠি'],
        ['A School beside the River', 'নদীর পাশে একটি বিদ্যালয়'],
        ['The Garden Keepers', 'বাগানের তত্ত্বাবধায়কেরা'],
        ['Crafting a Better Question', 'আরও ভালো প্রশ্ন গড়া'],
        ['The Traveling Library', 'ভ্রাম্যমাণ গ্রন্থাগার'],
        ['A Bridge of Stories', 'গল্পের সেতু'],
        ['The Makers’ Quarter', 'কারিগরদের পাড়া'],
        ['A Season of Discovery', 'আবিষ্কারের একটি ঋতু'],
        ['The Quiet Invention', 'নীরব উদ্ভাবন'],
        ['The Welcoming Courtyard', 'স্বাগত জানানো উঠোন'],
        ['The Careful Translator', 'মনোযোগী অনুবাদক'],
        ['The Night Sky Notebook', 'রাতের আকাশের খাতা'],
        ['A Table for Neighbors', 'প্রতিবেশীদের মিলন টেবিল'],
        ['The Archive of Everyday Life', 'দৈনন্দিন জীবনের আর্কাইভ'],
        ['A Journey through Architecture', 'স্থাপত্যের পথে যাত্রা'],
        ['The Honest Workshop', 'সৎ কারুশালা'],
        ['Seeds for the Next Generation', 'পরবর্তী প্রজন্মের জন্য বীজ'],
        ['The Listening Room', 'মন দিয়ে শোনার ঘর'],
    ];

    /** @return array{videos:int,draft_videos:int,stream_mappings:int} */
    public function generate(StagingGenerationContext $context): array
    {
        $target = $context->profile->counts['videos'];
        if ($target === 0) return ['videos' => 0, 'draft_videos' => 0, 'stream_mappings' => 0];

        $datasetPart = StagingIdentifiers::datasetPart($context->datasetId);
        $owned = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
            ->where('entity_type', 'video')->get()->keyBy('entity_key');
        $pending = [];
        $now = now('UTC')->toDateTimeString();

        for ($index = 0; $index < $target; $index++) {
            $key = sprintf('staging-%s-video-%05d', $datasetPart, $index + 1);
            $owner = $owned->get($key);
            if ($owner) {
                $video = DB::table('videos')->where('id', $owner->record_id)->first();
                if (! $video || $video->slug !== $key || (int) $video->status !== 0 || $video->video_url_input !== null
                    || DB::table('video_stream_content_mapping')->where('video_id', $video->id)->exists()) {
                    throw new RuntimeException("Owned staging video {$key} is missing, published, or linked to an unverified stream.");
                }
                continue;
            }
            if (DB::table('videos')->where('slug', $key)->exists()) {
                throw new RuntimeException("Refusing to adopt unowned standalone video {$key}.");
            }

            [$name, $nameBn] = self::THEMES[$index % count(self::THEMES)];
            $edition = intdiv($index, count(self::THEMES));
            if ($edition > 0) {
                $name .= ' — Collection '.($edition + 1);
                $nameBn .= ' — সংকলন '.($edition + 1);
            }
            $description = 'Fictional educational video draft for staging metadata, localization, and editorial workflows. No media asset or stream source has been attached.';
            $descriptionBn = 'মেটাডেটা, ভাষান্তর ও সম্পাদকীয় কার্যপ্রবাহের জন্য কাল্পনিক শিক্ষামূলক ভিডিও খসড়া। কোনো মিডিয়া অ্যাসেট বা সম্প্রচার উৎস যুক্ত করা হয়নি।';
            $pending[] = [
                'key' => $key, 'row' => [
                    'name' => $name, 'name_bn' => $nameBn, 'type' => 'video', 'content_rating' => 'family',
                    'access' => 'free', 'is_restricted' => 0, 'status' => 0,
                    'short_desc' => $description, 'short_description' => $description,
                    'description' => $description, 'description_bn' => $descriptionBn,
                    'video_upload_type' => null, 'video_url_input' => null,
                    'poster_url' => null, 'thumbnail_url' => null, 'poster_tv_url' => null,
                    'created_by' => null, 'updated_by' => null, 'deleted_by' => null,
                    'slug' => $key, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
                ],
            ];
            if (count($pending) >= $context->profile->batchSize) {
                $this->persistBatch($pending, $context);
                $pending = [];
            }
        }
        if ($pending !== []) $this->persistBatch($pending, $context);

        $counts = DB::table('staging_dataset_records as owners')
            ->join('videos', 'videos.id', '=', 'owners.record_id')
            ->where('owners.dataset_id', $context->datasetId)->where('owners.entity_type', 'video')
            ->selectRaw('count(*) as videos, sum(case when videos.status = 0 then 1 else 0 end) as drafts')->first();
        if ((int) $counts->videos !== $target || (int) $counts->drafts !== $target) {
            throw new RuntimeException("Staging video generation produced {$counts->videos} draft records; configured target is {$target}.");
        }
        return ['videos' => (int) $counts->videos, 'draft_videos' => (int) $counts->drafts, 'stream_mappings' => 0];
    }

    /** @param list<array{key:string,row:array<string,mixed>}> $pending */
    private function persistBatch(array $pending, StagingGenerationContext $context): void
    {
        DB::transaction(function () use ($pending, $context): void {
            DB::table('videos')->insert(array_column($pending, 'row'));
            $keys = array_column($pending, 'key');
            $records = DB::table('videos')->whereIn('slug', $keys)->get(['id', 'slug'])->keyBy('slug');
            $owners = [];
            foreach ($keys as $key) {
                $record = $records->get($key);
                if (! $record) throw new RuntimeException("Could not retrieve inserted staging video {$key}.");
                $owners[] = [
                    'dataset_id' => $context->datasetId, 'entity_type' => 'video',
                    'entity_key' => $key, 'record_id' => (string) $record->id,
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
            DB::table('staging_dataset_records')->insert($owners);
        });
    }
}
