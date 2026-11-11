<?php
// Start the session
session_start();

include("../config.php");

// Block unauthenticated access.
if (empty($_SESSION["auth_status"])) {
  header('Location: '.$siteUrl.'/admin/index.php');
  exit;
}

// GP data sync (Issue #25): last recorded run, plus the result of a
// "Sync now" click (set by gpdata_sync.php, shown once).
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$syncFlash = $_SESSION['gpdata_sync_flash'] ?? null;
unset($_SESSION['gpdata_sync_flash']);

$lastRun = null;
$conn = new mysqli($mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase);
if (!$conn->connect_error) {
  $conn->query("SET time_zone = '+00:00'");
  $result = $conn->query(
    "SELECT trigger_source, status, DATE_FORMAT(started_at, '%Y-%m-%d %H:%i UTC') AS started, matched, changed"
    . " FROM gpdata_sync_run ORDER BY id DESC LIMIT 1"
  );
  $lastRun = $result ? $result->fetch_assoc() : null;
  $conn->close();
}

function h($value) {
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <title>Amsat Status Dashboard</title>
    <link rel="stylesheet" href="<?php echo $siteUrl; ?>/admin/assets/global.css" media="screen">
  </head>
  <body>

    <h1>Amsat Status Dashboard</h1>

    <h2>Options</h2>

    <ul>
      <li><a href="<?php echo $siteUrl; ?>/admin/add_satellite.php">Add Satellite</a></li>
      <li><a href="<?php echo $siteUrl; ?>/admin/manage_satellites.php">Manage Satellites</a></li>
    </ul>

    <h2>GP data sync</h2>
    <p>
      NORAD IDs come only from the AMSAT GP feed. The sync runs automatically once a day; use
      <strong>Sync now</strong> to pick up new IDs sooner, e.g. during a launch.
    </p>
    <p>
      Last run:
      <?php if ($lastRun === null) { ?>
        none yet.
      <?php } else { ?>
        <?php echo h($lastRun['started']); ?> (<?php echo h($lastRun['trigger_source']); ?>),
        <strong><?php echo h($lastRun['status']); ?></strong><?php
          if ($lastRun['status'] === 'ok') {
            echo ': ' . (int) $lastRun['matched'] . ' matched, ' . (int) $lastRun['changed'] . ' changed';
          } ?>.
      <?php } ?>
    </p>
    <form action="<?php echo h($siteUrl); ?>/admin/gpdata_sync.php" method="post">
      <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
      <button type="submit">Sync now</button>
    </form>

    <?php if ($syncFlash !== null) { ?>
      <div id="gpdata-sync-result">
        <?php if (isset($syncFlash['error'])) { ?>
          <p><strong>Sync failed:</strong> <?php echo h($syncFlash['error']); ?></p>
        <?php } else { $run = $syncFlash['run']; ?>
          <p>
            <strong>Sync <?php echo h($run['status']); ?></strong>
            (run <?php echo (int) $run['run_id']; ?>, feed records: <?php echo h($run['feed_records'] ?? 'n/a'); ?>)
            <?php if ($run['reason'] !== null) { echo '&mdash; ' . h($run['reason']); } ?>
          </p>
          <?php if ($run['status'] === 'ok') { ?>
            <p><?php echo (int) $run['matched']; ?> spacecraft matched.</p>
            <p>Changed (<?php echo count($run['changed']); ?>):
              <?php echo $run['changed'] === [] ? 'none' : h(implode(', ', array_map(
                static fn ($c) => $c['spacecraft'] . ' ' . ($c['from'] ?? 'none') . ' → ' . $c['to'],
                $run['changed']
              ))); ?></p>
            <p>Not in the feed (<?php echo count($run['not_in_feed']); ?>; add them to the feed upstream):
              <?php echo h(implode(', ', $run['not_in_feed'])); ?></p>
            <?php if ($run['in_feed_without_id'] !== []) { ?>
              <p>In the feed without an ID yet: <?php echo h(implode(', ', $run['in_feed_without_id'])); ?></p>
            <?php } ?>
            <?php if ($run['ambiguous'] !== []) { ?>
              <p>Ambiguous, skipped: <?php echo h(implode('; ', array_map(
                static fn ($a) => $a['spacecraft'] . ' (' . implode(', ', $a['feed']) . ')',
                $run['ambiguous']
              ))); ?></p>
            <?php } ?>
          <?php } ?>
        <?php } ?>
      </div>
    <?php } ?>

  </body>
</html>
