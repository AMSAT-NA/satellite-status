<?php

declare(strict_types=1);

require_once __DIR__ . '/v1/lib/bootstrap.php';

$baseUrl = api_base_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AMSAT Satellite Status API</title>
  <style>
    :root {
      color-scheme: light;
      --ink: #17202a;
      --muted: #52616f;
      --line: #d8dee6;
      --panel: #f7f9fb;
      --accent: #b3261e;
      --code: #102a43;
    }

    body {
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      color: var(--ink);
      background: #ffffff;
      line-height: 1.55;
    }

    main {
      width: min(1040px, calc(100% - 32px));
      margin: 0 auto;
      padding: 40px 0 64px;
    }

    header {
      border-bottom: 1px solid var(--line);
      margin-bottom: 32px;
      padding-bottom: 24px;
    }

    h1 {
      margin: 0 0 8px;
      font-size: clamp(2rem, 4vw, 3rem);
      letter-spacing: 0;
    }

    h2 {
      margin-top: 40px;
      padding-bottom: 8px;
      border-bottom: 1px solid var(--line);
    }

    h3 {
      margin-top: 28px;
    }

    a {
      color: var(--accent);
    }

    code,
    pre {
      color: var(--code);
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 6px;
    }

    code {
      padding: 2px 5px;
    }

    pre {
      padding: 14px 16px;
      overflow-x: auto;
    }

    pre code {
      padding: 0;
      border: 0;
      background: transparent;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin: 16px 0 24px;
    }

    th,
    td {
      border-bottom: 1px solid var(--line);
      padding: 10px;
      text-align: left;
      vertical-align: top;
    }

    th {
      background: var(--panel);
    }

    .lead {
      max-width: 760px;
      color: var(--muted);
      font-size: 1.08rem;
    }

    .endpoint {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 18px;
      margin: 18px 0;
      background: #fff;
    }

    .method {
      display: inline-block;
      min-width: 48px;
      margin-right: 8px;
      padding: 2px 8px;
      border-radius: 4px;
      background: #e7f0ff;
      color: #0b4ea2;
      font-weight: 700;
      text-align: center;
    }

    .method.post {
      background: #e7f7ee;
      color: #0b6b3a;
    }

    .note {
      padding: 14px 16px;
      border-left: 4px solid var(--accent);
      background: var(--panel);
    }
  </style>
</head>
<body>
<main>
  <header>
    <h1>AMSAT Satellite Status API</h1>
    <p class="lead">
      Version <?php echo htmlspecialchars(API_VERSION); ?> exposes the satellite catalog, recent status reports,
      report submission, rollups, health checks, and an OpenAPI document for client generation.
      Responses are JSON, timestamps are UTC, and satellite API names match the status page names.
    </p>
  </header>

  <section>
    <h2>Base URLs</h2>
    <table>
      <tr><th>Purpose</th><th>URL</th></tr>
      <tr><td>API root</td><td><code><?php echo htmlspecialchars($baseUrl); ?></code></td></tr>
      <tr><td>Swagger UI</td><td><a href="../api/docs.php"><code><?php echo htmlspecialchars(rtrim((string) $siteUrl, '/')); ?>/api/docs.php</code></a></td></tr>
      <tr><td>OpenAPI JSON</td><td><a href="<?php echo htmlspecialchars($baseUrl); ?>/openapi.php"><code><?php echo htmlspecialchars($baseUrl); ?>/openapi.php</code></a></td></tr>
      <tr><td>Legacy reports</td><td><a href="<?php echo htmlspecialchars($baseUrl); ?>/sat_info.php?name=AO-91&amp;hours=24"><code><?php echo htmlspecialchars($baseUrl); ?>/sat_info.php?name=AO-91&amp;hours=24</code></a></td></tr>
      <tr><td>Acknowledgements</td><td><a href="../api/acknowledgements.php"><code><?php echo htmlspecialchars(rtrim((string) $siteUrl, '/')); ?>/api/acknowledgements.php</code></a></td></tr>
    </table>
  </section>

  <section>
    <h2>Endpoints</h2>

    <div class="endpoint">
      <h3><span class="method">GET</span><code>/catalog.php</code></h3>
      <p>Lists satellites with links and optional report statistics. Use <code>name</code> for exact lookup.</p>
      <table>
        <tr><th>Parameter</th><th>Type</th><th>Description</th></tr>
        <tr><td><code>name</code></td><td>string</td><td>Optional API name, for example <code>AO-91</code>.</td></tr>
        <tr><td><code>include_stats</code></td><td>boolean</td><td>When true, includes report count and latest report timestamp.</td></tr>
      </table>
      <pre><code>curl "<?php echo htmlspecialchars($baseUrl); ?>/catalog.php?include_stats=true"</code></pre>
    </div>

    <div class="endpoint">
      <h3><span class="method">GET</span><code>/reports.php</code></h3>
      <p>Searches recent satellite reports with bounded limits and normalized filters.</p>
      <table>
        <tr><th>Parameter</th><th>Type</th><th>Description</th></tr>
        <tr><td><code>name</code></td><td>string</td><td>Optional satellite API name.</td></tr>
        <tr><td><code>hours</code></td><td>integer</td><td>Rolling UTC window. Defaults to <?php echo API_DEFAULT_REPORT_HOURS; ?>. Maximum <?php echo API_MAX_REPORT_HOURS; ?>.</td></tr>
        <tr><td><code>since</code></td><td>date-time</td><td>ISO 8601 lower bound. Overrides <code>hours</code>.</td></tr>
        <tr><td><code>limit</code></td><td>integer</td><td>Maximum records. Defaults to <?php echo API_DEFAULT_LIMIT; ?>. Maximum <?php echo API_MAX_LIMIT; ?>.</td></tr>
        <tr><td><code>callsign</code></td><td>string</td><td>Exact callsign filter.</td></tr>
        <tr><td><code>grid_square</code></td><td>string</td><td>Maidenhead locator filter.</td></tr>
        <tr><td><code>status</code></td><td>string</td><td><code>Heard</code>, <code>Telemetry Only</code>, <code>Not Heard</code>, or <code>Crew Active</code>.</td></tr>
      </table>
      <pre><code>curl "<?php echo htmlspecialchars($baseUrl); ?>/reports.php?name=AO-91&amp;hours=24&amp;limit=25"</code></pre>
    </div>

    <div class="endpoint">
      <h3><span class="method post">POST</span><code>/reports.php</code></h3>
      <p>Submits a public satellite status report using JSON or form data.</p>
      <pre><code>curl -X POST "<?php echo htmlspecialchars($baseUrl); ?>/reports.php" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "AO-91",
    "report": "Heard",
    "callsign": "N0CALL",
    "grid_square": "EM48",
    "reported_at": "2026-05-23T08:30:00Z"
  }'</code></pre>
      <p class="note">
        Submitting another report for the same satellite, callsign, hour, and 15-minute period replaces the previous one,
        matching the legacy form behavior.
      </p>
    </div>

    <div class="endpoint">
      <h3><span class="method">GET</span><code>/summary.php</code></h3>
      <p>Returns report counts grouped by satellite and report value for a rolling UTC window.</p>
      <pre><code>curl "<?php echo htmlspecialchars($baseUrl); ?>/summary.php?hours=24"</code></pre>
    </div>

    <div class="endpoint">
      <h3><span class="method">GET</span><code>/statuses.php</code></h3>
      <p>Lists the canonical report values accepted by <code>POST /reports.php</code>.</p>
      <pre><code>curl "<?php echo htmlspecialchars($baseUrl); ?>/statuses.php"</code></pre>
    </div>

    <div class="endpoint">
      <h3><span class="method">GET</span><code>/health.php</code></h3>
      <p>Checks API availability and database connectivity.</p>
      <pre><code>curl "<?php echo htmlspecialchars($baseUrl); ?>/health.php"</code></pre>
    </div>
  </section>

  <section>
    <h2>Response Shape</h2>
    <p>New endpoints return top-level <code>data</code> and optional <code>meta</code> and <code>links</code>. Errors use a stable envelope:</p>
    <pre><code>{
  "error": {
    "code": "invalid_parameter",
    "message": "The hours parameter must be an integer.",
    "status": 400
  }
}</code></pre>
  </section>

  <section>
    <h2>Compatibility</h2>
    <p>
      <code>/api/v1/satellites.php</code> and <code>/api/v1/sat_info.php</code> remain plain JSON-array endpoints
      for existing clients and tests. New clients should use <code>/catalog.php</code> and <code>/reports.php</code>.
    </p>
  </section>

  <section>
    <h2>Acknowledgements</h2>
    <p>
      See the <a href="../api/acknowledgements.php">API acknowledgements</a> page for contributors who helped
      design, implement, document, and test this public API.
    </p>
  </section>
</main>
</body>
</html>
