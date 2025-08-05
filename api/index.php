<?php
// API Documentation Page
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>AMSAT Oscar Satellite Report API</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      line-height: 1.6;
      max-width: 800px;
      margin: 0 auto;
      padding: 20px;
    }
    code {
      background-color: #f4f4f4;
      padding: 2px 4px;
      border-radius: 4px;
    }
    pre {
      background-color: #f4f4f4;
      padding: 10px;
      border-left: 4px solid #e00000;
      overflow-x: auto;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin: 1em 0;
    }
    table, th, td {
      border: 1px solid #ddd;
    }
    th, td {
      padding: 10px;
      text-align: left;
    }
    h1, h2, h3 {
      color: #2c3e50;
    }
  </style>
</head>
<body>

  <h1>AMSAT Oscar Satellite Report API</h1>

  <h2>Endpoint</h2>
  <pre><code>https://amsat.org/status/api/v1/sat_info.php</code></pre>

  <p>This API returns satellite status reports in JSON format from the <strong><a href="https://www.amsat.org/status" target="_self">AMSAT Oscar Satellite Status Page</strong>.</p></a>

  <h2>HTTP Request</h2>
  <pre><code>GET https://amsat.org/status/api/v1/sat_info.php?name=&lt;SATELLITE_NAME&gt;&hours=&lt;HOURS&gt;</code></pre>

  <h2>Query Parameters</h2>
  <table>
    <tr>
      <th>Parameter</th>
      <th>Type</th>
      <th>Required</th>
      <th>Description</th>
    </tr>
    <tr>
      <td><code>name</code></td>
      <td>string</td>
      <td>Yes</td>
      <td>Exact name of the satellite as shown below</td>
    </tr>
    <tr>
      <td><code>hours</code></td>
      <td>integer</td>
      <td>No</td>
      <td>Number of hours of data to retrieve (defaults to 96 if omitted)</td>
    </tr>
  </table>

  <h2>Response Format</h2>
  <p>The API returns a JSON array of report entries. Each entry contains:</p>
  <ul>
    <li>name</li>
    <li>reported_time</li>
    <li>callsign</li>
    <li>report</li>
    <li>grid_square</li>
  </ul>

  <h2>Example Request</h2>
  <pre><code>https://amsat.org/status/api/v1/sat_info.php?name=AO-91&hours=24</code></pre>

  <h2>Valid Satellite Names</h2>
  <p>The following satellite names are valid for the <code>name</code> parameter:</p>

  <pre><code><?php
    $satellites = [
      "AISAT-1", "AO-123", "AO-16", "AO-27", "AO-73", "AO-7[A]", "AO-7[B]", "AO-85", "AO-91",
      "CAS-2T", "CAS-4A", "CAS-4B", "CatSat", "CUTE-1", "DSTAR1", "DUCHIFAT1", "DUCHIFAT3", "EO-79",
      "EO-80", "ESEO", "FloripaSat-1", "FO-118[H/u]", "FO-118[V/u+FM]", "FO-118[V/u]", "FO-29",
      "FO-99", "GO-32", "HA-1", "HO-107", "HO-113", "IO-117", "IO-26", "IO-86", "ISS-DATA", "ISS-DATV",
      "ISS-FM", "ISS-SSTV", "JO-97", "K2SAT", "LEDSAT", "LilacSat-2", "LO-19", "LO-87", "LO-90",
      "LO-93", "MO-122", "NO-44", "NO-45", "OUFTI-1", "PARUS-T2", "PicSat", "PO-101[APRS]", "PO-101[FM]",
      "QIKCOM-1", "QO-100_NB", "QO-100_WB", "QUAKESAT", "RIDU-Sat_1", "RS-15", "RS-25", "RS-44",
      "SO-124", "SO-125", "SO-33", "SO-50", "SONATE-2+APRS", "SONATE-2+SSTV", "Taurus-1", "TEVEL2-1",
      "TEVEL2-2", "TEVEL2-3", "TEVEL2-4", "TEVEL2-5", "TEVEL2-6", "TEVEL2-7", "TEVEL2-8", "TEVEL2-9",
      "TO-108", "UiTMSAT-1", "UKube-1", "UO-11[B]", "UO-11[S]", "UVSQ-SAT", "UWE-3", "VO-96", "XI-IV", "XI-V"
    ];
    echo implode(", ", $satellites);
  ?></code></pre>

  <h2>Notes</h2>
  <ul>
    <li>This is a <strong>read-only</strong> endpoint.</li>
    <li>All timestamps are in <strong>UTC</strong>.</li>
	<li>This API is not stable yet ... we are still working on the time, and it seems a query 
		for the list of available satellites is in order.   For the moment, all reports show 
		half past the hour that they were in.</li>
  </ul>

<footer>
  <div align=center>
    Contributors to this documentation: Dominic Hord (AD8AK)
  </div>
</footer>

</body>
</html>
