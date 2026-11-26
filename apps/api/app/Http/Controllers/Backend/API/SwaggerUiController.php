<?php

namespace App\Http\Controllers\Backend\API;

use Symfony\Component\HttpFoundation\Response;

class SwaggerUiController
{
    /**
     * Serve the pinned Swagger UI distribution assets from Composer's vendor tree.
     */
    public function asset(string $asset): Response
    {
        $contentTypes = [
            'swagger-ui.css' => 'text/css; charset=UTF-8',
            'swagger-ui-bundle.js' => 'text/javascript; charset=UTF-8',
            'swagger-ui-standalone-preset.js' => 'text/javascript; charset=UTF-8',
            'favicon-32x32.png' => 'image/png',
        ];

        abort_unless(array_key_exists($asset, $contentTypes), 404);

        $path = base_path('vendor/swagger-api/swagger-ui/dist/'.$asset);
        abort_unless(is_file($path), 404);

        return response(file_get_contents($path), 200, [
            'Cache-Control' => 'public, max-age=86400',
            'Content-Type' => $contentTypes[$asset],
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
