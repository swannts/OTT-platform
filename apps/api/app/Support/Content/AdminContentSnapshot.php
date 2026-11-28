<?php

namespace App\Support\Content;

use Modules\Entertainment\Models\Entertainment;
use Modules\Video\Models\Video;

final class AdminContentSnapshot
{
    private const FIELDS = [
        'name', 'name_bn', 'description', 'description_bn', 'type', 'content_rating',
        'release_date', 'plan_id', 'movie_access', 'video_upload_type', 'status', 'media_asset_id',
    ];

    /** @return array<string,mixed> */
    public static function forEntertainment(Entertainment $item): array
    {
        $attributes = $item->getAttributes();
        $snapshot = [];
        foreach (self::FIELDS as $field) $snapshot[$field] = $attributes[$field] ?? null;
        foreach (['poster_url', 'thumbnail_url', 'video_url_input'] as $field) {
            $value = $attributes[$field] ?? null;
            $snapshot[$field.'_sha256'] = $value ? hash('sha256', (string) $value) : null;
        }
        ksort($snapshot);
        return $snapshot;
    }

    /** @return array<string,mixed> */
    public static function forVideo(Video $item): array
    {
        $fields = [
            'name', 'name_bn', 'description', 'description_bn', 'short_desc', 'type', 'access', 'plan_id',
            'price', 'purchase_type', 'access_duration', 'discount', 'available_for', 'duration', 'release_date', 'is_restricted', 'IMDb_rating',
            'content_rating', 'status', 'media_asset_id',
        ];
        $attributes = $item->getAttributes();
        $snapshot = [];
        foreach ($fields as $field) $snapshot[$field] = $attributes[$field] ?? null;
        foreach (['poster_url', 'poster_tv_url', 'thumbnail_url', 'video_url_input', 'trailer_url'] as $field) {
            $value = $attributes[$field] ?? null;
            $snapshot[$field.'_sha256'] = $value ? hash('sha256', (string) $value) : null;
        }
        ksort($snapshot);

        return $snapshot;
    }
}
