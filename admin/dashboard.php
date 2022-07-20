<?php
// Start the session
session_start();

include("config.php");

// if not logged in redirect back to login page.
if($_SESSION["auth_status"] != true) {
  header('Location: '.$siteUrl.'/admin/index.php');
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

  </body>
</html>
