<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>NOOR OTT API Documentation</title>
    <link rel="icon" type="image/png" href="{{ route('docs.swagger.assets', ['asset' => 'favicon-32x32.png']) }}">
    <link rel="stylesheet" href="{{ route('docs.swagger.assets', ['asset' => 'swagger-ui.css']) }}">
    <style>
        html { box-sizing: border-box; overflow-y: scroll; }
        *, *::before, *::after { box-sizing: inherit; }
        body { margin: 0; background: #fafafa; }
    </style>
</head>
<body>
<div id="swagger-ui"></div>
<script src="{{ route('docs.swagger.assets', ['asset' => 'swagger-ui-bundle.js']) }}"></script>
<script src="{{ route('docs.swagger.assets', ['asset' => 'swagger-ui-standalone-preset.js']) }}"></script>
<script>
    window.ui = SwaggerUIBundle({
        url: '{{ route('scramble.docs.document') }}',
        dom_id: '#swagger-ui',
        deepLinking: true,
        displayRequestDuration: true,
        filter: true,
        // Do not keep entered Sanctum credentials in browser storage.
        persistAuthorization: false,
        defaultModelsExpandDepth: 2,
        defaultModelExpandDepth: 3,
        showCommonExtensions: true,
        tryItOutEnabled: true,
        presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
        layout: 'StandaloneLayout',
    });
</script>
</body>
</html>
