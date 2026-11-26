<?php

$trustedGeoCidrs = array_values(array_filter(array_map(
    static fn (string $cidr): string => trim($cidr),
    explode(',', (string) env('PLAYBACK_GEO_TRUSTED_PROXY_CIDRS', ''))
)));

return [
    'trusted_geo' => [
        'country_header' => env('PLAYBACK_GEO_COUNTRY_HEADER', 'CF-IPCountry'),
        'trusted_proxy_cidrs' => $trustedGeoCidrs,
    ],
];
