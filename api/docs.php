<?php
declare(strict_types=1);

// Block access in production unless explicitly enabled
if (getenv('APP_ENV') === 'production' && getenv('SWAGGER_ENABLED') !== 'true') {
    http_response_code(403);
    exit('API docs disabled in production. Set SWAGGER_ENABLED=true to enable.');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ProWay Lab API — Documentation</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
  <style>
    body { margin: 0; background: #0a0a0f; }
    .swagger-ui .topbar { background: #111118; border-bottom: 1px solid #2a2a3a; }
    .swagger-ui .topbar .download-url-wrapper { display: none; }
    .swagger-ui .info .title { color: #e8e8f8; }
    .swagger-ui .opblock.opblock-post .opblock-summary { border-color: #6c63ff; }
    .swagger-ui .opblock.opblock-get .opblock-summary { border-color: #00d4aa; }
    .swagger-ui .btn.authorize { background: #6c63ff; border-color: #6c63ff; color: #fff; }
    .swagger-ui .btn.authorize svg { fill: #fff; }
    #custom-header {
      background: linear-gradient(135deg, #111118, #1a1a2e);
      border-bottom: 1px solid #2a2a3a;
      padding: 16px 32px;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    #custom-header h1 { color: #e8e8f8; font-family: system-ui, sans-serif; font-size: 1.2rem; margin: 0; }
    #custom-header span { color: #6c63ff; font-size: 0.8rem; background: rgba(108,99,255,0.15); padding: 2px 8px; border-radius: 20px; }
  </style>
</head>
<body>
  <div id="custom-header">
    <h1>ProWay Lab API</h1>
    <span>v1.0.0</span>
  </div>
  <div id="swagger-ui"></div>
  <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
  <script>
    SwaggerUIBundle({
      url: '/api/openapi.yaml',
      dom_id: '#swagger-ui',
      deepLinking: true,
      presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset],
      layout: 'BaseLayout',
      tryItOutEnabled: true,
      persistAuthorization: true,
      displayRequestDuration: true,
    });
  </script>
</body>
</html>
