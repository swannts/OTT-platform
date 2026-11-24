<?php

return [
    // Provider credentials and URLs are deployment secrets; the admin API exposes presence only.
    'drm_provider' => env('MEDIA_DRM_PROVIDER', 'none'),
    'drm_license_url' => env('MEDIA_DRM_LICENSE_URL'),
    'drm_secret' => env('MEDIA_DRM_SECRET'),
];
