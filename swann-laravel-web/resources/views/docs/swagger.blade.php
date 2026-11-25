<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>NOOR OTT API Documentation</title>
    <link rel="icon" type="image/png" href="/docs/swagger/assets/favicon-32x32.png">
    <link rel="stylesheet" href="/docs/swagger/assets/swagger-ui.css">
    <style>
        html { box-sizing: border-box; overflow-y: scroll; }
        *, *::before, *::after { box-sizing: inherit; }
        body { margin: 0; background: #fafafa; }
    </style>
</head>
<body>
<div id="swagger-ui"></div>
<script src="/docs/swagger/assets/swagger-ui-bundle.js"></script>
<script src="/docs/swagger/assets/swagger-ui-standalone-preset.js"></script>
<script>
    window.ui = SwaggerUIBundle({
        url: '/docs/api.json',
        dom_id: '#swagger-ui',
        deepLinking: true,
        displayRequestDuration: true,
        filter: true,
        persistAuthorization: false,
        presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
        layout: 'StandaloneLayout',
    });
</script>
</body>
</html>
