<?php

namespace App\Support\Staging;

final class StagingIdentifiers
{
    public static function stableUuid(string $datasetId, string $entityKey): string
    {
        $namespace = hex2bin('6ba7b8109dad11d180b400c04fd430c8');
        $hex = substr(sha1($namespace.$datasetId."\0".$entityKey), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }

    public static function datasetPart(string $datasetId): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '-', $datasetId) ?? 'dataset');

        return substr($slug, 0, 48).'-'.substr(hash('sha256', $datasetId), 0, 8);
    }
}
