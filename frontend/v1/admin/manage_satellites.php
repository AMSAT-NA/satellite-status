<?php
// Start the session
session_start();

// Include files
include("../config.php");

// Block unauthenticated access.
if (empty($_SESSION["auth_status"])) {
  header('Location: '.$siteUrl.'/admin/index.php');
  exit;
}
?>

<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <title>Amsat Status Dashboard - Manage Satellites</title>
    <link rel="stylesheet" href="<?php echo $siteUrl; ?>/admin/assets/global.css" media="screen">
    <script type="text/javascript">
    function confirm_alert(node) {
        return confirm("Please click on OK to continue deleting satellite.");
    }
    </script>
  </head>
  <body>
    <h1>Manage Satellites</h1>
    <table width="100%">
      <tr>
        <td>Satellite Name</td>
        <td>HTML Name</td>
        <td>Website</td>
        <td></td>
      </tr>
    <?php

      // Create connection
      $conn = new mysqli($mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase);
      // Check connection
      if ($conn->connect_error) {
          die("Connection failed: " . $conn->connect_error);
      }

      $sql = "SELECT * FROM satellite_name ORDER BY name ASC";
      $result = $conn->query($sql);

      if ($result->num_rows > 0) {
          // output data of each row
          while($row = $result->fetch_assoc()) {
      ?>
        <tr class="border_bottom">
          <td><?php echo htmlspecialchars($row["name"], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><?php echo htmlspecialchars($row["html_element_name"], ENT_QUOTES, 'UTF-8'); ?></td>
          <td><a href="<?php echo htmlspecialchars($row["website"] ?? '', ENT_QUOTES, 'UTF-8'); ?>" target="_blank"><?php echo htmlspecialchars($row["website"] ?? '', ENT_QUOTES, 'UTF-8'); ?></a></td>
          <td>
            <a href="<?php echo htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8'); ?>/admin/edit_satellite.php?id=<?php echo (int)$row["id"]; ?>">Edit</a>
            <a href="<?php echo htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8'); ?>/admin/delete_satellite.php?id=<?php echo (int)$row["id"]; ?>" onclick="return confirm_alert(this);">Delete</a>
          </td>
        </tr>


      <?php
          }
      } else {
          echo "0 results";
      }
      $conn->close();

    ?>
    </table>
  </body>
</html>
