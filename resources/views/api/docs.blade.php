<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $serviceName ?? 'API v1' }} — مستندات</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui.css">
    <style>
        html, body { margin: 0; padding: 0; background: #f8f9fa; }
        .api-topbar {
            background: #0d6efd;
            color: #fff;
            padding: 14px 22px;
            font-family: Vazirmatn, Tahoma, sans-serif;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            direction: rtl;
        }
        .api-topbar h1 { font-size: 18px; margin: 0; font-weight: 700; }
        .api-topbar .links { display: flex; gap: 14px; font-size: 13px; }
        .api-topbar a { color: #dbeafe; text-decoration: none; }
        .api-topbar a:hover { color: #fff; text-decoration: underline; }
        #swagger-ui { direction: ltr; text-align: left; max-width: 1280px; margin: 0 auto; }
        .swagger-ui .info .title { font-family: Vazirmatn, Tahoma, sans-serif; }
    </style>
</head>
<body>
    <div class="api-topbar">
        <h1>{{ $serviceName ?? 'سرویس مرکزی تولید اسناد — API v1' }}</h1>
        <div class="links">
            <a href="{{ $specUrl }}">OpenAPI YAML</a>
            <a href="{{ url('/api/v1/health') }}">Health</a>
        </div>
    </div>

    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-bundle.js" crossorigin></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-standalone-preset.js" crossorigin></script>
    <script>
        window.addEventListener('load', function () {
            window.ui = SwaggerUIBundle({
                url: @json($specUrl),
                dom_id: '#swagger-ui',
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                layout: 'BaseLayout',
                deepLinking: true,
                docExpansion: 'list',
                defaultModelsExpandDepth: 1,
                filter: true,
                persistAuthorization: true,
                tryItOutEnabled: true,
                displayRequestDuration: true
            });
        });
    </script>
</body>
</html>
