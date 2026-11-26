<?php

namespace App\Support\Content;

use Modules\Entertainment\Models\Entertainment;

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
}
