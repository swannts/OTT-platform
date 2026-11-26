<?php

namespace App\Support\Staging;

final class StagingIdentifiers
{
    public static function datasetPart(string $datasetId): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '-', $datasetId) ?? 'dataset');

        return substr($slug, 0, 48).'-'.substr(hash('sha256', $datasetId), 0, 8);
    }
}
