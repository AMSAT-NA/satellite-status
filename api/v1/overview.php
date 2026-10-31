<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

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
      <tr><td>Swagger UI</td><td><a href="./docs.php"><code><?php echo htmlspecialchars($baseUrl); ?>/docs.php</code></a></td></tr>
      <tr><td>OpenAPI JSON</td><td><a href="<?php echo htmlspecialchars($baseUrl); ?>/openapi.php"><code><?php echo htmlspecialchars($baseUrl); ?>/openapi.php</code></a></td></tr>
      <tr><td>Legacy reports</td><td><a href="<?php echo htmlspecialchars($baseUrl); ?>/sat_info.php?name=AO-91&amp;hours=24"><code><?php echo htmlspecialchars($baseUrl); ?>/sat_info.php?name=AO-91&amp;hours=24</code></a></td></tr>
      <tr><td>Acknowledgements</td><td><a href="./acknowledgements.php"><code><?php echo htmlspecialchars($baseUrl); ?>/acknowledgements.php</code></a></td></tr>
      <tr><td>Usage guidelines</td><td><a href="#usage">Read before building on this API</a></td></tr>
    </table>
  </section>

  <section id="usage">
    <h2>Usage Guidelines</h2>
    <p>
      This API is run by volunteers for the amateur satellite community, and it is free to use.
      In return, please follow these guidelines. Clients that ignore them, especially unidentified
      ones, may be rate-limited or blocked.
    </p>

    <h3 id="identify">Identify your client</h3>
    <p>
      Send a <code>User-Agent</code> header that names your application, its version, and a way to reach you:
      a project URL, an email address, or both.
    </p>
    <pre><code>User-Agent: MySatTracker/1.2 (+https://github.com/example/mysattracker; you@example.com)</code></pre>
    <pre><code>curl -A "MySatTracker/1.2 (+https://github.com/example/mysattracker; you@example.com)" \
  "<?php echo htmlspecialchars($baseUrl); ?>/summary.php?hours=24"</code></pre>
    <p>
      Generic library defaults such as <code>python-requests/2.x</code> or <code>curl/8.x</code> tell us nothing.
      If something you run starts misbehaving, a descriptive User-Agent is how we find you before we block it.
      Code running in a web browser usually can't set <code>User-Agent</code>; browser clients are identified by
      their <code>Origin</code>, so serve them from a stable domain. Please don't invent custom identification headers:
      they will fail the browser CORS preflight.
    </p>

    <h3 id="polling">Poll gently</h3>
    <ul>
      <li>Reports are grouped into 15-minute periods, so polling more often than every <strong>5 minutes</strong> gains you nothing. Please don't.</li>
      <li>Fetch what you need in one request. Use <code>reports.php</code> without <code>name</code>, or <code>summary.php</code>, rather than one request per satellite.</li>
      <li>Use <code>since</code> to fetch only reports newer than your last poll.</li>
      <li>Apps with many users should poll from one server and serve their users from that copy, rather than having every user's device call this API directly.</li>
      <li>On errors (<code>5xx</code>, or <code>429</code> if rate limiting is introduced), back off rather than retrying immediately.</li>
    </ul>

    <h3 id="submitting">Submitting reports</h3>
    <p>
      Apps that let operators submit their own observations are welcome. That is what <code>POST /reports.php</code> is for.
      The rules below are about where a report comes from, not which software sends it.
    </p>
    <ul>
      <li>Each report must describe a real observation by the operator whose callsign it carries. If your app submits for its users, use the user's callsign, not the app's or the developer's.</li>
      <li>A report should be submitted because the operator chose to submit it, such as by tapping "submit" in your app. Don't have your app submit reports in the background without the operator's action.</li>
      <li>Set <code>reported_at</code> to the time of the observation, in UTC, not the time it was submitted. This matters most for apps that queue reports offline.</li>
      <li>Identify your app with a <code>User-Agent</code>, as described <a href="#identify">above</a>, so we can tell which app a report came through if something looks wrong.</li>
      <li>Talk to us first before submitting reports that no operator entered: imports from another network or dataset, reports generated automatically from decoders or telemetry, or bulk backfills of past observations. These may be fine, but we need to agree on how they're labeled and deduplicated.</li>
    </ul>

    <h3 id="data">Data and attribution</h3>
    <p>
      Report data returned by this API is available under the
      <a href="https://creativecommons.org/licenses/by/4.0/">Creative Commons Attribution 4.0 (CC BY 4.0)</a> license.
      Credit it as:
    </p>
    <pre><code>Satellite status data: AMSAT, https://status.amsat.org</code></pre>
    <p>
      Reports include callsigns and grid squares submitted by individual operators. Use the data to show and analyze
      satellite status. Don't use it to track, profile, or contact individual operators, and don't combine it with
      other data to locate them more precisely than the grid square they chose to share.
    </p>

    <h3 id="license">Source code</h3>
    <p>
      The API and status page source is at
      <a href="https://github.com/AMSAT-NA/satellite-status">github.com/AMSAT-NA/satellite-status</a> under the
      <a href="https://github.com/AMSAT-NA/satellite-status/blob/main/LICENSE">Apache License 2.0</a>.
      Contributions are welcome.
    </p>

    <h3 id="changes">Stability and changes</h3>
    <p>
      <code>/api/v1</code> is a living version. Changes, including occasional breaking changes, are announced in the
      <a href="https://github.com/AMSAT-NA/satellite-status/blob/main/CHANGELOG">CHANGELOG</a>.
      Watch the GitHub repository to hear about them. A new major version, when one exists, will get its own base URL,
      listed in the machine-readable index at <code>/api/</code>.
      There is no uptime guarantee; check <code>/health.php</code> rather than assuming.
    </p>

    <h3 id="support">Contact and support</h3>
    <ol>
      <li>Open an issue on <a href="https://github.com/AMSAT-NA/satellite-status/issues">GitHub</a>.</li>
      <li>Ask in the AMSAT Discord server, <code>#open-source-dev</code> channel.</li>
      <li>Email <a href="mailto:IT@amsat.org">IT@amsat.org</a>.</li>
    </ol>
    <p>If you're building something on this API, let us know. It helps us understand who depends on it before we change things.</p>
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
        Every submission is stored as its own report -- nothing is ever replaced or deleted, even for the same
        satellite, callsign, and time.
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
      See the <a href="./acknowledgements.php">API acknowledgements</a> page for contributors who helped
      design, implement, document, and test this public API.
    </p>
  </section>
</main>
</body>
</html>
