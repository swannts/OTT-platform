<?php

namespace App\Support\Staging;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class StagingCatalogGenerator
{
    private const GENRES = [
        ['Islamic History', 'ইসলামের ইতিহাস', 'Careful explorations of historical periods, communities, and institutions.'],
        ['Prophetic Biography', 'সীরাত ও জীবনী', 'Educational biographies presented without invented quotations or unsupported claims.'],
        ["Qur'an and Reflection", 'কুরআন ও চিন্তা', 'Reflective educational material that avoids presenting generated text as scripture.'],
        ['Education', 'শিক্ষা', 'Learning journeys about knowledge, teaching, and lifelong study.'],
        ['Family', 'পরিবার', 'Stories about care, trust, responsibility, and family life.'],
        ['Biography', 'জীবনী', 'Fictional biographical-style stories about scholars, makers, and community leaders.'],
        ['Culture and Heritage', 'সংস্কৃতি ও ঐতিহ্য', 'Fictional stories about language, craft, architecture, and shared heritage.'],
        ['Science and Civilization', 'বিজ্ঞান ও সভ্যতা', 'Educational stories about discovery, engineering, and intellectual history.'],
        ['Ethics and Character', 'নীতি ও চরিত্র', 'Stories about honesty, patience, generosity, and responsible choices.'],
        ["Children's Learning", 'শিশুদের শিক্ষা', 'Age-appropriate learning adventures for children and families.'],
        ['Spiritual Development', 'আত্মিক উন্নয়ন', 'Reflective stories about gratitude, purpose, and personal growth.'],
        ['Travel and Architecture', 'ভ্রমণ ও স্থাপত্য', 'Journeys through fictional places inspired by varied architectural traditions.'],
        ['Community', 'সম্প্রদায়', 'Stories about cooperation, local service, and belonging.'],
        ['Arts and Craft', 'শিল্প ও কারুশিল্প', 'Fictional accounts of makers, materials, and creative practice.'],
        ['Nature and Stewardship', 'প্রকৃতি ও দায়িত্ব', 'Family-friendly stories about nature and care for shared resources.'],
        ['Language and Literature', 'ভাষা ও সাহিত্য', 'Stories about translation, poetry, reading, and oral traditions.'],
        ['Discovery', 'আবিষ্কার', 'Educational mysteries built around observation, research, and teamwork.'],
        ['Leadership', 'নেতৃত্ব', 'Stories about service, accountability, and thoughtful leadership.'],
        ['Health and Wellbeing', 'স্বাস্থ্য ও সুস্থতা', 'General wellbeing stories that do not replace professional health advice.'],
        ['Migration and Belonging', 'অভিবাসন ও আপনত্ব', 'Fictional stories about journeys, welcome, and building a new community.'],
        ['Trade and Exchange', 'বাণিজ্য ও বিনিময়', 'Stories about honest trade, travel, and cultural exchange.'],
        ['Ocean and Navigation', 'সমুদ্র ও নৌচালনা', 'Family adventures about navigation, teamwork, and the sea.'],
        ['Memory and Place', 'স্মৃতি ও স্থান', 'Stories about how communities preserve memory and place.'],
        ['Conflict Resolution', 'দ্বন্দ্ব নিরসন', 'Stories about dialogue, fairness, and peaceful problem solving.'],
        ['Technology and Society', 'প্রযুক্তি ও সমাজ', 'Educational stories about technology, responsibility, and social change.'],
        ['Food and Hospitality', 'খাবার ও আতিথেয়তা', 'Stories about hospitality, shared meals, and regional traditions.'],
        ['Gardens and Agriculture', 'বাগান ও কৃষি', 'Family learning stories about cultivation, seasons, and stewardship.'],
        ['Libraries and Learning', 'গ্রন্থাগার ও শিক্ষা', 'Stories centered on libraries, archives, and collaborative research.'],
        ['Justice and Responsibility', 'ন্যায় ও দায়িত্ব', 'Fictional civic stories about fairness, evidence, and accountability.'],
        ['Friendship', 'বন্ধুত্ব', 'Stories about trust, empathy, and friendship across communities.'],
        ['Documentary', 'প্রামাণ্যচিত্র', 'Documentary-style educational titles; staging entries are fictional and unpublished.'],
        ['History for Families', 'পরিবারের জন্য ইতিহাস', 'Accessible historical learning designed for family viewing.'],
        ['Architecture and Cities', 'স্থাপত্য ও নগর', 'Stories about cities, buildings, and the people who shape them.'],
        ['Women in Scholarship', 'জ্ঞানচর্চায় নারী', 'Fictional educational stories about women teaching and preserving knowledge.'],
        ['Learning Through Travel', 'ভ্রমণের মাধ্যমে শিক্ষা', 'Journeys that connect geography, culture, and learning.'],
        ['Public Service', 'জনসেবা', 'Stories about community service and public responsibility.'],
        ['Critical Thinking', 'সমালোচনামূলক চিন্তা', 'Educational mysteries that reward careful questions and evidence.'],
        ['Resilience', 'ঘুরে দাঁড়ানো', 'Fictional stories about adapting to change with care and support.'],
        ['World Civilizations', 'বিশ্বসভ্যতা', 'Comparative educational stories about societies and exchange.'],
        ['Faith and Daily Life', 'বিশ্বাস ও দৈনন্দিন জীবন', 'Fictional reflections on values and everyday decisions.'],
    ];

    private const TITLE_STARTS = [
        ['The Quiet Map of', 'নীরব মানচিত্রে'], ['A Lantern Across', 'আলোর প্রদীপে'],
        ['Letters from', 'থেকে আসা চিঠি'], ['The Garden Beyond', 'পেরিয়ে সবুজ বাগান'],
        ['A Bridge Through', 'জুড়ে থাকা সেতু'], ['The Archive of', 'এর স্মৃতির আর্কাইভ'],
        ['Footsteps beside', 'পথে পদচিহ্ন'], ['The Compass of', 'এর দিকনির্দেশ'],
        ['A Promise in', 'এ একটি অঙ্গীকার'], ['The Workshop at', 'এর কারুশালায়'],
        ['Voices of', 'এর কণ্ঠস্বর'], ['The Long Road to', 'এর পথে দীর্ঘ যাত্রা'],
        ['The Library under', 'এর নিচে গ্রন্থাগার'], ['A Window toward', 'এর দিকে জানালা'],
        ['The Clockmaker of', 'এর ঘড়িনির্মাতা'], ['Beyond the Courtyard of', 'এর আঙিনা পেরিয়ে'],
    ];

    private const TOPICS = [
        ['shared knowledge', 'জ্ঞান ভাগাভাগি'], ['a distant observatory', 'দূরের মানমন্দির'],
        ['the old city', 'প্রাচীন নগর'], ['an unfinished atlas', 'অসমাপ্ত মানচিত্র'],
        ['a traveling school', 'ভ্রাম্যমাণ বিদ্যালয়'], ['a hidden garden', 'গোপন বাগান'],
        ['the river archives', 'নদীর স্মৃতি'], ['a family workshop', 'পারিবারিক কারুশালা'],
        ['the paper makers', 'কাগজ নির্মাতারা'], ['a changing coastline', 'বদলে যাওয়া উপকূল'],
        ['the patient teacher', 'ধৈর্যশীল শিক্ষক'], ['the night sky', 'রাতের আকাশ'],
        ['a city of courtyards', 'উঠোনের নগর'], ['the last manuscript', 'শেষ পাণ্ডুলিপি'],
        ['the honest merchant', 'সৎ বণিক'], ['a bridge of stories', 'গল্পের সেতু'],
        ['the seed keepers', 'বীজের রক্ষকেরা'], ['a new neighborhood', 'নতুন পাড়া'],
        ['the quiet inventor', 'নীরব উদ্ভাবক'], ['the open library', 'সবার গ্রন্থাগার'],
        ['a lesson in patience', 'ধৈর্যের শিক্ষা'], ['the mountain road', 'পাহাড়ি পথ'],
        ['the traveling clinic', 'ভ্রাম্যমাণ চিকিৎসাকেন্দ্র'], ['the city gardeners', 'নগরের মালী'],
        ['a map of kindness', 'মমতার মানচিত্র'], ['the public fountain', 'সবার পানির ফোয়ারা'],
        ['the makers of light', 'আলোর কারিগরেরা'], ['the keeper of letters', 'চিঠির রক্ষক'],
        ['the shared table', 'সবার মিলন টেবিল'], ['a careful discovery', 'সতর্ক আবিষ্কার'],
        ['the old harbor', 'পুরোনো বন্দর'], ['the listening room', 'মন দিয়ে শোনার ঘর'],
        ['a difficult choice', 'কঠিন সিদ্ধান্ত'], ['the winter classroom', 'শীতের পাঠশালা'],
        ['the house of questions', 'প্রশ্নের বাড়ি'], ['the returning traveler', 'ফিরে আসা পথিক'],
        ['a story carried home', 'ঘরে ফেরা গল্প'], ['the keeper of gardens', 'বাগানের তত্ত্বাবধায়ক'],
        ['the city of bridges', 'সেতুর নগর'], ['the careful translator', 'মনোযোগী অনুবাদক'],
        ['the workshop of ideas', 'ভাবনার কারুশালা'], ['a promise kept', 'রক্ষা করা অঙ্গীকার'],
        ['the river of time', 'সময়ের নদী'], ['the shared horizon', 'সবার দিগন্ত'],
        ['a welcoming courtyard', 'স্বাগত জানানো উঠোন'], ['the patient navigator', 'ধৈর্যশীল নাবিক'],
        ['the borrowed book', 'ধার করা বই'], ['the village observatory', 'গ্রামের মানমন্দির'],
        ['a season of learning', 'শেখার ঋতু'], ['the makers’ quarter', 'কারিগরদের পাড়া'],
    ];

    private const ENGLISH_SUMMARIES = [
        'A fictional educational story follows a small team as they investigate %s, compare accounts, and learn how patient collaboration can change a community.',
        'When a new challenge reaches %s, neighbors share skills and evidence to find a careful, fair way forward.',
        'This family-oriented drama follows a teacher and young researchers exploring %s while learning to ask better questions.',
        'A journey through %s brings together people with different experiences, practical knowledge, and a common responsibility.',
        'In this fictional documentary-style story, a community records what it learns about %s for the next generation.',
    ];

    private const BENGALI_SUMMARIES = [
        'একটি কাল্পনিক শিক্ষামূলক গল্পে ছোট একটি দল %s নিয়ে অনুসন্ধান করে, নানা বর্ণনা মিলিয়ে দেখে এবং ধৈর্যশীল সহযোগিতার গুরুত্ব বোঝে।',
        '%s-কে ঘিরে নতুন চ্যালেঞ্জ দেখা দিলে প্রতিবেশীরা নিজেদের দক্ষতা ও তথ্য ভাগ করে ন্যায্য সমাধান খোঁজে।',
        'পরিবারের উপযোগী এই কাল্পনিক গল্পে একজন শিক্ষক ও তরুণ অনুসন্ধানীরা %s নিয়ে কাজ করতে গিয়ে ভালো প্রশ্ন করতে শেখে।',
        '%s-কে ঘিরে যাত্রায় ভিন্ন অভিজ্ঞতার মানুষ একসঙ্গে এসে জ্ঞান ও দায়িত্ব ভাগ করে নেয়।',
        'এই কাল্পনিক প্রামাণ্যধর্মী গল্পে একটি সম্প্রদায় ভবিষ্যৎ প্রজন্মের জন্য %s সম্পর্কে তাদের শেখা বিষয় নথিবদ্ধ করে।',
    ];

    public function generate(StagingGenerationContext $context): array
    {
        $datasetPart = StagingIdentifiers::datasetPart($context->datasetId);
        $genreIds = $this->seedGenres($context, $datasetPart);
        $movieIds = $this->seedContentType($context, $datasetPart, 'movie', $context->profile->counts['movies'], $genreIds);
        $seriesIds = $this->seedContentType($context, $datasetPart, 'tvshow', $context->profile->counts['series'], $genreIds);
        [$seasonCount, $episodeCount] = $this->seedSeriesHierarchy($context, $datasetPart, $seriesIds);

        return [
            'genres' => count($genreIds),
            'movies' => count($movieIds),
            'series' => count($seriesIds),
            'seasons' => $seasonCount,
            'episodes' => $episodeCount,
        ];
    }

    /** @return list<string> */
    private function seedGenres(StagingGenerationContext $context, string $datasetPart): array
    {
        $target = $context->profile->counts['genres'];
        if ($target > count(self::GENRES)) {
            throw new RuntimeException('The curated staging genre vocabulary is smaller than the configured target.');
        }

        $ids = [];
        foreach (array_slice(self::GENRES, 0, $target) as $index => [$name, $nameBn, $description]) {
            $slug = 'stg-'.$datasetPart.'-genre-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $owner = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                ->where('entity_type', 'genre')->where('entity_key', $slug)->first();
            $existing = $owner?->record_id ? DB::table('genres')->where('id', $owner->record_id)->first() : null;
            if ($existing) {
                if ($existing->name !== $name || ($existing->name_bn ?? null) !== $nameBn) {
                    throw new RuntimeException("Refusing to adopt or alter existing genre fixture {$slug}.");
                }
                $ids[] = (string) $existing->id;
                continue;
            }
            if ($owner) {
                throw new RuntimeException("Genre ownership for {$slug} exists but its record is missing.");
            }

            $id = (string) Str::uuid7();
            DB::transaction(function () use ($context, $slug, $id, $name, $nameBn, $description): void {
                DB::table('genres')->insert([
                    'id' => $id,
                    'name' => $name,
                    'name_bn' => $nameBn,
                    'slug' => $slug,
                    'description' => $description,
                    'description_bn' => 'এই বিষয়শ্রেণিতে পরিবারের উপযোগী কাল্পনিক ও শিক্ষামূলক বিষয় রাখা হয়।',
                    'status' => 1,
                    'created_at' => $context->historyStart(),
                    'updated_at' => $context->historyStart(),
                ]);
                $this->register($context->datasetId, 'genre', $slug, $id);
            });
            $ids[] = $id;
        }

        return $ids;
    }

    /** @param list<string> $genreIds
     *  @return list<string>
     */
    private function seedContentType(StagingGenerationContext $context, string $datasetPart, string $type, int $target, array $genreIds): array
    {
        $ids = [];
        $kind = $type === 'movie' ? 'movie' : 'series';
        for ($offset = 0; $offset < $target; $offset += $context->profile->batchSize) {
            $end = min($target, $offset + $context->profile->batchSize);
            DB::transaction(function () use ($offset, $end, $target, $kind, $type, $datasetPart, $genreIds, $context, &$ids): void {
                $rows = [];
                $contentGenres = [];
                $owners = [];
                for ($index = $offset; $index < $end; $index++) {
                    $slug = sprintf('stg-%s-%s-%05d', $datasetPart, $kind, $index + 1);
                    $owner = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                        ->where('entity_type', 'entertainment')->where('entity_key', $slug)->first();
                    $existing = $owner?->record_id ? DB::table('entertainments')->where('id', $owner->record_id)->first() : null;
                    if ($existing) {
                        if ($existing->type !== $type || (int) $existing->status !== 0) {
                            throw new RuntimeException("Refusing to adopt or alter existing catalog fixture {$slug}.");
                        }
                        $ids[] = (string) $existing->id;
                        continue;
                    }
                    if ($owner) {
                        throw new RuntimeException("Catalog ownership for {$slug} exists but its content record is missing.");
                    }

                    $id = (string) Str::uuid7();
                    $theme = self::TOPICS[$index % count(self::TOPICS)];
                    $opening = self::TITLE_STARTS[intdiv($index, count(self::TOPICS)) % count(self::TITLE_STARTS)];
                    $name = $opening[0].' '.$theme[0];
                    $nameBn = $opening[1].' '.$theme[1];
                    $summaryIndex = $index % count(self::ENGLISH_SUMMARIES);
                    $description = sprintf(self::ENGLISH_SUMMARIES[$summaryIndex], $theme[0]);
                    $descriptionBn = sprintf(self::BENGALI_SUMMARIES[$summaryIndex], $theme[1]);
                    $releaseOffset = (int) floor($context->historyStart()->diffInSeconds($context->referenceDate) * ($index + 1) / max(1, $target));
                    $releaseDate = $context->historyStart()->addSeconds($releaseOffset)->toDateString();
                    $genreId = $genreIds[$index % count($genreIds)] ?? null;
                    if ($genreId === null) {
                        throw new RuntimeException('At least one staging genre is required for generated content.');
                    }
                    $rows[] = [
                        'id' => $id,
                        'name' => $name,
                        'name_bn' => $nameBn,
                        'description' => $description,
                        'description_bn' => $descriptionBn,
                        'short_description' => mb_substr($description, 0, 180),
                        'type' => $type,
                        'movie_access' => 'free',
                        'language' => $index % 3 === 0 ? 'bn' : 'en',
                        'content_rating' => json_encode(['rating' => 'G', 'reason' => 'Family educational content'], JSON_THROW_ON_ERROR),
                        'duration' => (string) ($type === 'movie' ? 70 + (($index * 7) % 76) : 30 + (($index * 5) % 21)),
                        'release_date' => $releaseDate,
                        'is_restricted' => 0,
                        'status' => 0,
                        'created_at' => $context->historyStart()->addSeconds(max(0, $releaseOffset - 86400 * 30)),
                        'updated_at' => $context->historyStart()->addSeconds(max(0, $releaseOffset - 86400 * 30)),
                    ];
                    $contentGenres[] = [
                        'id' => (string) Str::uuid7(),
                        'entertainment_id' => $id,
                        'genre_id' => $genreId,
                        'created_at' => $context->historyStart(),
                        'updated_at' => $context->historyStart(),
                    ];
                    $owners[] = ['entity_type' => 'entertainment', 'entity_key' => $slug, 'record_id' => $id];
                    $ids[] = $id;
                }

                if ($rows === []) {
                    return;
                }
                DB::table('entertainments')->insert($rows);
                DB::table('entertainment_gener_mapping')->insert($contentGenres);
                foreach ($contentGenres as $mapping) {
                    $owners[] = ['entity_type' => 'entertainment_genre_mapping', 'entity_key' => $mapping['id'], 'record_id' => $mapping['id']];
                }
                $this->registerMany($context->datasetId, $owners);
            });
        }

        return $ids;
    }

    /** @param list<string> $seriesIds
     *  @return array{int, int}
     */
    private function seedSeriesHierarchy(StagingGenerationContext $context, string $datasetPart, array $seriesIds): array
    {
        $seasonTarget = $context->profile->counts['seasons'];
        $episodeTarget = $context->profile->counts['episodes'];
        if ($seasonTarget > 0 && $seriesIds === []) {
            throw new RuntimeException('Season generation requires at least one generated series.');
        }

        $seasonIds = [];
        $seasonRows = [];
        for ($index = 0; $index < $seasonTarget; $index++) {
            $slug = sprintf('stg-%s-season-%06d', $datasetPart, $index + 1);
            $owner = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                ->where('entity_type', 'season')->where('entity_key', $slug)->first();
            $existing = $owner?->record_id ? DB::table('seasons')->where('id', $owner->record_id)->first() : null;
            if ($existing) {
                if ((int) $existing->status !== 0) {
                    throw new RuntimeException("Refusing to adopt or alter existing season fixture {$slug}.");
                }
                $seasonIds[] = (string) $existing->id;
                continue;
            }
            if ($owner) {
                throw new RuntimeException("Season ownership for {$slug} exists but its record is missing.");
            }

            $id = (string) Str::uuid7();
            $seriesIndex = $index % count($seriesIds);
            $seriesId = $seriesIds[$seriesIndex];
            $seasonIndex = 1 + intdiv($index, count($seriesIds));
            $seasonIds[] = $id;
            $seasonRows[] = [
                'id' => $id,
                'slug' => $slug,
                'entertainment_id' => $seriesId,
                'season_index' => (string) $seasonIndex,
                'name' => 'Season '.$seasonIndex,
                'access' => 'free',
                'status' => 0,
                'short_desc' => 'Unpublished staging season awaiting editorial review and media.',
                'description' => 'A fictional season outline for staging workflows. No media is attached or implied.',
                'created_at' => $context->historyStart(),
                'updated_at' => $context->historyStart(),
            ];
        }

        $seasonOwners = [];
        if ($seasonRows !== []) {
            foreach (array_chunk($seasonRows, $context->profile->batchSize) as $chunk) {
                DB::transaction(function () use ($chunk, $context, &$seasonOwners): void {
                    DB::table('seasons')->insert($chunk);
                    foreach ($chunk as $row) {
                        $seasonOwners[] = ['entity_type' => 'season', 'entity_key' => $row['slug'], 'record_id' => $row['id']];
                    }
                    $this->registerMany($context->datasetId, $seasonOwners);
                    $seasonOwners = [];
                });
            }
        }

        $episodeRows = [];
        $episodeOwners = [];
        $createdEpisodes = 0;
        $existingEpisodes = 0;
        for ($index = 0; $index < $episodeTarget; $index++) {
            $slug = sprintf('stg-%s-episode-%07d', $datasetPart, $index + 1);
            $owner = DB::table('staging_dataset_records')->where('dataset_id', $context->datasetId)
                ->where('entity_type', 'episode')->where('entity_key', $slug)->first();
            $existing = $owner?->record_id ? DB::table('episodes')->where('id', $owner->record_id)->first() : null;
            if ($existing) {
                if ((int) $existing->status !== 0) {
                    throw new RuntimeException("Refusing to adopt or alter existing episode fixture {$slug}.");
                }
                $existingEpisodes++;
                continue;
            }
            if ($owner) {
                throw new RuntimeException("Episode ownership for {$slug} exists but its record is missing.");
            }
            if ($seasonIds === []) {
                throw new RuntimeException('Episode generation requires at least one generated season.');
            }

            $seasonId = $seasonIds[$index % count($seasonIds)];
            $parentSeriesId = DB::table('seasons')->where('id', $seasonId)->value('entertainment_id');
            if (! $parentSeriesId) {
                throw new RuntimeException('Generated episode season has no valid parent series.');
            }
            $seasonIndex = (int) DB::table('seasons')->where('id', $seasonId)->value('season_index');
            $episodeNumber = 1 + intdiv($index, count($seasonIds));
            $theme = self::TOPICS[$index % count(self::TOPICS)];
            $id = (string) Str::uuid7();
            $episodeRows[] = [
                'id' => $id,
                'name' => 'Season '.$seasonIndex.' · '.$theme[0],
                'name_bn' => 'মৌসুম '.$seasonIndex.' · '.$theme[1],
                'entertainment_id' => $parentSeriesId,
                'season_id' => $seasonId,
                'episode_number' => (string) $episodeNumber,
                'access' => 'free',
                'duration' => (string) (18 + (($index * 11) % 29)),
                'release_date' => $context->historyStart()->addDays($index % max(1, $context->profile->historyMonths * 25))->toDateString(),
                'is_restricted' => 0,
                'short_desc' => 'Unpublished fictional educational episode; media is not attached.',
                'description' => sprintf(self::ENGLISH_SUMMARIES[$index % count(self::ENGLISH_SUMMARIES)], $theme[0]),
                'description_bn' => sprintf(self::BENGALI_SUMMARIES[$index % count(self::BENGALI_SUMMARIES)], $theme[1]),
                'status' => 0,
                'created_at' => $context->historyStart(),
                'updated_at' => $context->historyStart(),
            ];
            $episodeOwners[] = ['entity_type' => 'episode', 'entity_key' => $slug, 'record_id' => $id];
            if (count($episodeRows) >= $context->profile->batchSize) {
                DB::transaction(function () use (&$episodeRows, &$episodeOwners, $context, &$createdEpisodes): void {
                    DB::table('episodes')->insert($episodeRows);
                    $this->registerMany($context->datasetId, $episodeOwners);
                    $createdEpisodes += count($episodeRows);
                    $episodeRows = [];
                    $episodeOwners = [];
                });
            }
        }
        if ($episodeRows !== []) {
            DB::transaction(function () use (&$episodeRows, &$episodeOwners, $context, &$createdEpisodes): void {
                DB::table('episodes')->insert($episodeRows);
                $this->registerMany($context->datasetId, $episodeOwners);
                $createdEpisodes += count($episodeRows);
            });
        }

        return [count($seasonIds), $existingEpisodes + $createdEpisodes];
    }

    /** @param list<array{entity_type: string, entity_key: string}> $records */
    private function registerMany(string $datasetId, array $records): void
    {
        if ($records === []) {
            return;
        }
        $now = now();
        $rows = array_map(static fn (array $record): array => [
            'dataset_id' => $datasetId,
            'entity_type' => $record['entity_type'],
            'entity_key' => $record['entity_key'],
            'record_id' => $record['record_id'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $records);
        DB::table('staging_dataset_records')->insert($rows);
    }

    private function register(string $datasetId, string $entityType, string $entityKey, ?string $recordId = null): void
    {
        $this->registerMany($datasetId, [['entity_type' => $entityType, 'entity_key' => $entityKey, 'record_id' => $recordId]]);
    }
}
