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
      <li>Reports arrive a few at a time and the status grid summarizes them in 2-hour windows, so polling more often than every <strong>5 minutes</strong> gains you nothing. Please don't.</li>
      <li>Fetch what you need in one request. Use <code>reports.php</code> without <code>name</code>, or <code>summary.php</code>, rather than one request per satellite.</li>
      <li>If you keep your own copy of the reports, use <code>since</code> to fetch only reports newer than your last poll. Clients that re-fetch a rolling window instead (for example, the last 24 hours on every poll, so old reports drop out) don't need <code>since</code>. That's the same number of requests.</li>
      <li>Apps with many users should poll from one server and serve their users from that copy, rather than having every user's device call this API directly. Responses carry <code>Cache-Control: no-store</code>, so browsers and HTTP caches won't store them. Keeping a copy of the data on your own server and serving your users from it is expected and fine.</li>
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
      <li>Set <code>observation_time</code> to the time of the observation, not the time it was submitted, with an explicit offset (send UTC with <code>Z</code>). This matters most for apps that queue reports offline. The server records when it received the report separately, as <code>submission_time</code>.</li>
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
      Show it wherever your users would expect to find credits. On a website, near the data or in the page footer is fine.
      In a mobile or desktop app, a credits, about, or acknowledgments screen is fine.
    </p>
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
      There is no uptime guarantee; check <code>/health.php</code> rather than assuming. It reports
      <code>"status": "degraded"</code> (still HTTP 200) when the API is up but has no reports, or the newest report is
      more than a day old. Treat that as "reachable, but the data may not be current".
    </p>

    <h3 id="versioning">Versioning</h3>
    <p>
      The API version is <code>MAJOR.MINOR</code>, currently <code><?php echo htmlspecialchars(API_VERSION); ?></code>.
      It is sent as the <code>X-AMSAT-API-Version</code> header on every response, and as <code>meta.api_version</code>
      in every JSON envelope response (the legacy <code>satellites.php</code> and <code>sat_info.php</code> arrays have
      only the header).
    </p>
    <ul>
      <li><strong>MAJOR</strong> always matches the URL: <code>/api/v1</code> is 1.x. Removing or renaming a field means a new URL version.</li>
      <li><strong>MINOR</strong> goes up for additive changes: new fields, endpoints or parameters, and deprecations.</li>
      <li>Bug fixes that don't change the response shape don't change the version.</li>
    </ul>
    <p>Every version change is noted in the CHANGELOG.</p>

    <h3 id="deprecated">Deprecated fields</h3>
    <p>
      Deprecated fields keep working for the life of <code>/api/v1</code>; they would be removed only in a future
      <code>/api/v2</code>. They are marked <code>deprecated</code> in the OpenAPI document. Please move to the new names.
    </p>
    <table>
      <tr><th>Deprecated</th><th>Use instead</th><th>Where</th><th>Since</th></tr>
      <tr><td><code>reported_at</code></td><td><code>observation_time</code></td><td><code>POST /reports.php</code> request</td><td>1.3</td></tr>
      <tr><td><code>year</code>, <code>month</code>, <code>day</code>, <code>hour</code>, <code>minute</code></td><td><code>observation_time</code></td><td><code>POST /reports.php</code> request</td><td>1.3</td></tr>
      <tr><td><code>reported_time</code></td><td><code>observation_time</code></td><td><code>/reports.php</code> responses</td><td>1.3</td></tr>
      <tr><td><code>latest_reported_time</code></td><td><code>latest_observation_time</code></td><td><code>/catalog.php</code>, <code>/summary.php</code></td><td>1.3</td></tr>
    </table>
    <p>
      <code>observation_time</code> is when the satellite was heard. <code>submission_time</code> (new in 1.3, in
      <code>/reports.php</code> responses) is when the server received the report; it is <code>null</code> for reports
      stored before November 2026. The legacy <code>sat_info.php</code> array keeps <code>reported_time</code>
      unchanged.
    </p>

    <h3 id="norad">NORAD catalog numbers</h3>
    <p>
      <code>norad_cat_id</code> is the satellite's NORAD catalog number, also called the satellite catalog number: the
      <code>NORAD_CAT_ID</code> field of CCSDS Orbit Mean-Elements Message (OMM) / GP data, an integer of up to nine
      digits. Use it to look up orbital elements, for example on CelesTrak or Space-Track.
    </p>
    <p>
      It comes only from the AMSAT GP feed, synced daily (see <code>/gpdata-sync.php</code> below), and is the same on
      every mode of one spacecraft. It is <code>null</code> until the satellite appears in the feed with an ID; newly
      launched satellites can take a few days. If an ID looks wrong, please
      <a href="https://github.com/AMSAT-NA/satellite-status/issues">open an issue</a>; it is fixed at the source, not in
      this API.
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
      <p>
        Lists satellites with links and optional report statistics. Use <code>name</code> for exact lookup.
        Each entry has <code>norad_cat_id</code>, the satellite's <a href="#norad">NORAD catalog number</a>
        (<code>null</code> if not known yet). <code>/reports.php</code> includes it on every report too.
      </p>
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
        <tr><td><code>hours</code></td><td>integer</td><td>Rolling UTC window. Defaults to <?php echo API_DEFAULT_REPORT_HOURS; ?>. Range <?php echo API_MIN_REPORT_HOURS; ?>-<?php echo API_MAX_REPORT_HOURS; ?>. Out-of-range values are clamped to the nearest bound, not rejected.</td></tr>
        <tr><td><code>since</code></td><td>date-time</td><td>ISO 8601 lower bound on <code>observation_time</code> (when heard, not when submitted). Overrides <code>hours</code>.</td></tr>
        <tr><td><code>limit</code></td><td>integer</td><td>Maximum records per page, newest first. Defaults to <?php echo API_DEFAULT_LIMIT; ?>. Range 1-<?php echo API_MAX_LIMIT; ?>. Out-of-range values are clamped to the nearest bound, not rejected.</td></tr>
        <tr><td><code>include_superseded</code></td><td>boolean</td><td>Also return superseded reports (see below). Default <code>false</code>.</td></tr>
        <tr><td><code>cursor</code></td><td>string</td><td>Opaque cursor for the next page, from the previous response. See <a href="#paging">Paging</a>.</td></tr>
        <tr><td><code>callsign</code></td><td>string</td><td>Exact callsign filter.</td></tr>
        <tr><td><code>grid_square</code></td><td>string</td><td>Maidenhead locator filter.</td></tr>
        <tr><td><code>status</code></td><td>string</td><td><code>Heard</code>, <code>Telemetry Only</code>, <code>Not Heard</code>, or <code>Crew Active</code>.</td></tr>
      </table>
      <p class="note">
        A non-integer <code>hours</code> or <code>limit</code> returns <code>400 invalid_parameter</code>.
        <code>meta</code> shows the <code>hours</code> and <code>limit</code> actually applied, after clamping.
      </p>
      <pre><code>curl "<?php echo htmlspecialchars($baseUrl); ?>/reports.php?name=AO-91&amp;hours=24&amp;limit=25"</code></pre>
      <h4 id="superseded">Superseded reports</h4>
      <p>
        Only one report per callsign per satellite per 15-minute UTC period counts: the latest. When the same callsign
        reports again in that period, the new report's POST response has <code>correction: true</code>, and the earlier
        report is <em>superseded</em>. Superseded reports are kept but left out of <code>reports.php</code> and
        <code>summary.php</code>. With <code>include_superseded=true</code>, <code>reports.php</code> returns them too.
        Every report has <code>superseded_by</code>: <code>null</code> for a report that counts, or the
        <code>id</code> of the report that replaced it.
      </p>
      <h4 id="paging">Paging</h4>
      <p>
        When more reports match than <code>limit</code>, the response has <code>links.next</code> (and
        <code>meta.next_cursor</code>). Request <code>links.next</code> unchanged until it is <code>null</code>:
        each page is a complete JSON response. <code>links.next</code> repeats your filters, with <code>since</code>
        fixed to the first page's window. The pages are a consistent snapshot as of the first page: reports
        submitted while you page are left out, so nothing is skipped or repeated. Fetch them on your next poll.
        A malformed cursor returns <code>400 invalid_cursor</code>.
      </p>
      <pre><code>{
  "data": [ ... 500 reports ... ],
  "meta": { "count": 500, "limit": 500, "next_cursor": "MToxMjM6..." },
  "links": { "next": "<?php echo htmlspecialchars($baseUrl); ?>/reports.php?since=...&amp;limit=500&amp;cursor=MToxMjM6..." }
}</code></pre>
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
    "observation_time": "2026-05-23T08:30:00Z"
  }'</code></pre>
      <p class="note">
        Every submission is stored; nothing is deleted. A resubmission from the same callsign for the same
        satellite and 15-minute UTC period (:00-:14, :15-:29, :30-:44, :45-:59) corrects the earlier report, as in
        API 1.0: <code>GET /reports.php</code> and <code>/summary.php</code> return and count only the latest one,
        and the response's <code>correction</code> field is <code>true</code>.
      </p>
      <p class="note">
        <code>observation_time</code> must be an ISO 8601 date and time with an explicit UTC offset
        (<code>Z</code>, <code>+HH:MM</code>, <code>+HHMM</code> or <code>+HH</code>, or the <code>-</code> forms);
        a timestamp without one is rejected with <code>422 invalid_observation_time</code>, since the time zone
        can't be guessed.
        Older clients may also send <code>satellite</code> instead of <code>name</code>,
        <code>status</code> instead of <code>report</code>, and the observation time as the
        <a href="#deprecated">deprecated</a> <code>reported_at</code>, or as separate UTC fields
        <code>year</code>, <code>month</code>, <code>day</code>, <code>hour</code> and <code>minute</code>.
        These are used only when the preferred field is absent. If <code>observation_time</code> and
        <code>reported_at</code> are both sent and differ, the request fails with
        <code>422 conflicting_observation_time</code>. If <code>minute</code> is
        omitted it defaults to <code>30</code>, so always send it. Report values are matched case-insensitively, and
        aliases such as <code>active</code>, <code>telemetry</code> and <code>no signal</code> are accepted; the
        OpenAPI document lists them all. New clients should use the fields in the example above.
      </p>
    </div>

    <div class="endpoint">
      <h3><span class="method">GET</span><code>/summary.php</code></h3>
      <p>Returns report counts grouped by satellite and report value for a rolling UTC window (<code>hours</code>, default 24, range <?php echo API_MIN_REPORT_HOURS; ?>-<?php echo API_MAX_REPORT_HOURS; ?>, clamped like <code>/reports.php</code>). As in API 1.0, a resubmission from the same callsign for the same satellite and 15-minute UTC period corrects the earlier report: only the latest one is counted. Corrected reports are still stored, but are not counted here or returned by <code>/reports.php</code>.</p>
      <pre><code>curl "<?php echo htmlspecialchars($baseUrl); ?>/summary.php?hours=24"</code></pre>
    </div>

    <div class="endpoint">
      <h3><span class="method">GET</span><code>/statuses.php</code></h3>
      <p>Lists the canonical report values accepted by <code>POST /reports.php</code>.</p>
      <pre><code>curl "<?php echo htmlspecialchars($baseUrl); ?>/statuses.php"</code></pre>
    </div>

    <div class="endpoint">
      <h3><span class="method">GET</span><code>/health.php</code></h3>
      <p>
        Checks API availability, database connectivity, and data freshness. <code>status</code> is <code>ok</code>,
        or <code>degraded</code> with a <code>reason</code> (<code>no_reports</code> or <code>stale_data</code>) when
        the newest report is older than <code>data_freshness.stale_after_seconds</code> (one day). A database outage
        returns HTTP <code>503</code> instead.
      </p>
      <pre><code>curl "<?php echo htmlspecialchars($baseUrl); ?>/health.php"</code></pre>
    </div>

    <div class="endpoint">
      <h3><span class="method post">POST</span><code>/gpdata-sync.php</code> <small>(admin only)</small></h3>
      <p>
        <strong>Not for API consumers:</strong> it requires an operator token (<code>Authorization: Bearer &lt;token&gt;</code>,
        configured on the server as <code>GPDATA_SYNC_TOKEN</code>). It's documented here so the behaviour behind
        <code>norad_cat_id</code> is public.
      </p>
      <p>
        Updates every catalog satellite's <code>norad_cat_id</code> from the AMSAT GP (general perturbations) feed,
        the only source of these IDs. The same sync runs automatically once a day; this endpoint runs it on demand, for
        example during a launch, and is what the admin dashboard's "Sync now" button calls. No request body.
      </p>
      <ul>
        <li>The feed is validated first. If it can't be read, isn't valid JSON, has malformed records or has too few
          records, the run is <code>aborted</code> and nothing is written.</li>
        <li>Catalog satellites are matched to the feed by name, ignoring the mode suffix (<code>AO-123_[FM]</code> is
          <code>AO-123</code>), dashes, leading zeros (<code>AO-07</code> = <code>AO-7</code>) and case. Either side of a
          bracketed alternate name matches (<code>UmKA 1 (RS40S)</code> matches <code>RS40S</code>).</li>
        <li>A matched spacecraft gets its ID on all of its modes (both QO-100 rows, all ISS rows).</li>
        <li>A spacecraft not in the feed, or in the feed without an ID yet, keeps its current ID. A spacecraft whose
          name matches several feed records with different IDs is skipped, never guessed. These are listed in the
          response.</li>
        <li>Only one run happens at a time; a second request gets <code>409 sync_in_progress</code>.</li>
      </ul>
      <p>
        Responses: <code>200</code> with the run (<code>data.status</code> <code>ok</code> or <code>aborted</code>, plus
        what changed and what didn't match); <code>401 unauthorized</code>; <code>409 sync_in_progress</code>;
        <code>500 sync_failed</code>; <code>503 sync_not_configured</code> when no token is set on the server.
      </p>
      <pre><code>curl -X POST -H "Authorization: Bearer $GPDATA_SYNC_TOKEN" "<?php echo htmlspecialchars($baseUrl); ?>/gpdata-sync.php"</code></pre>
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
    <p>
      <code>code</code> is stable and meant for programs; <code>message</code> is for people and may change. Some errors
      add a <code>details</code> object. The OpenAPI document lists every code, and which endpoint and status return it.
      The legacy <code>satellites.php</code> and <code>sat_info.php</code> endpoints return plain arrays when they succeed,
      but use this same envelope for errors (for example <code>405</code> or <code>503</code>). Any endpoint can return
      <code>405 method_not_allowed</code>, <code>500</code> and <code>503</code> errors in addition to the ones listed
      for it.
    </p>
  </section>

  <section>
    <h2>Compatibility</h2>
    <p>
      <code>/api/v1/satellites.php</code> and <code>/api/v1/sat_info.php</code> remain plain JSON-array endpoints
      for existing clients and tests. New clients should use <code>/catalog.php</code> and <code>/reports.php</code>.
    </p>
  </section>

  <section>
    <h2>Acknowledgments</h2>
    <p>
      See <a href="https://github.com/AMSAT-NA/satellite-status/blob/main/ACKNOWLEDGMENTS.md">ACKNOWLEDGMENTS.md</a> for the contributors who helped
      design, implement, document, and test this public API.
    </p>
  </section>
</main>
</body>
</html>
