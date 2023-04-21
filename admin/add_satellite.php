<?php
// Start the session
session_start();

include("../config.php");

// if not logged in redirect back to login page.
if($_SESSION["auth_status"] != true) {
  header('Location: '.$siteUrl.'/admin/index.php');
}
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <title>Amsat Status Dashboard - Add Satellite</title>
    <link rel="stylesheet" href="<?php echo $siteUrl; ?>/admin/assets/global.css" media="screen">
  </head>
  <body>

    <h1>Add Satellite</h1>

    <form action="<?php echo $siteUrl; ?>/admin/create_satellite.php" method="post">
      <table>
        <tr>
          <td style="vertical-align:top">Satellite Name</td>
          <td><input type="text" name="satellite_name" value="">
          <p>
            E.g AO-7 Mode B
          </p></td>
        </tr>

        <tr>
          <td style="vertical-align:top">Satellite Name (HTML Name)</td>
          <td><input type="text" name="html_satellite_name" value="">
          <p>
            E.g [B]_AO-7
          </p></td>
        </tr>

        <tr>
          <td style="vertical-align:top">Website</td>
          <td><input type="text" name="website" value="">
          <p>
            Including http://
          </p></td>
        </tr>

        <tr>
          <td></td>
          <td><input type="submit" name="submit" value="Add Satellite"></td>
        </tr>
      </table>
    </form>

  </body>
</html>
