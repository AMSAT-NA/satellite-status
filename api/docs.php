<?php

declare(strict_types=1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AMSAT Satellite Status API Docs</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
  <style>
    body {
      margin: 0;
      background: #ffffff;
    }

    .topbar {
      display: none;
    }

    .api-docs-nav {
      box-sizing: border-box;
      width: 100%;
      padding: 12px 24px;
      border-bottom: 1px solid #d8dee6;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      font-size: 14px;
      line-height: 1.4;
    }

    .api-docs-nav a {
      color: #b3261e;
      margin-right: 16px;
    }
  </style>
</head>
<body>
  <nav class="api-docs-nav" aria-label="API documentation links">
    <a href="./index.php">API overview</a>
    <a href="./acknowledgements.php">Acknowledgements</a>
  </nav>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
  <script>
    window.addEventListener('load', function () {
      SwaggerUIBundle({
        url: './v1/openapi.php',
        dom_id: '#swagger-ui',
        deepLinking: true,
        displayOperationId: false,
        defaultModelsExpandDepth: 1,
        docExpansion: 'list',
        supportedSubmitMethods: ['get', 'post']
      });
    });
  </script>
</body>
</html>
