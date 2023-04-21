<?php
// Start the session
session_start();

// Include files
include("../config.php");

// if not logged in redirect back to login page.
if($_SESSION["auth_status"] != true) {
  header('Location: '.$siteUrl.'/admin/index.php');
}

$satelliteID = htmlspecialchars($_GET['id']);

if($satelliteID != "") {

  // Create connection
  $conn = new mysqli($mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase);
  // Check connection
  if ($conn->connect_error) {
      die("Connection failed: " . $conn->connect_error);
  }

  $sql = "SELECT * FROM satellite_name WHERE id = $satelliteID";
  $result = $conn->query($sql);

  if ($result->num_rows > 0) {
      // output data of each row
      while($row = $result->fetch_assoc()) {
?>
  <!DOCTYPE html>
  <html>
    <head>
      <meta charset="utf-8">
      <title>Amsat Status Dashboard - Update Satellite</title>
      <link rel="stylesheet" href="<?php echo $siteUrl; ?>/admin/assets/global.css" media="screen">
    </head>
    <body>

      <h1>Edit Satellite - <?php echo $row["name"]; ?></h1>

      <form action="<?php echo $siteUrl; ?>/admin/update_satellite.php" method="post">
        <table>
          <tr>
            <td style="vertical-align:top">Satellite Name</td>
            <td>
              <input type="text" name="satellite_name" value="<?php echo $row["name"]; ?>">
              <p>E.g AO-7 Mode B</p>
            </td>
          </tr>

          <tr>
            <td style="vertical-align:top">Satellite Name (HTML Name)</td>
            <td>
              <input type="text" name="html_satellite_name" value="<?php echo $row["html_element_name"]; ?>">
              <p>E.g [B]_AO-7</p>
            </td>
          </tr>

          <tr>
            <td style="vertical-align:top">Website</td>
            <td>
              <input type="text" name="website" value="<?php echo $row["website"]; ?>">
              <p>Including http://</p>
            </td>
          </tr>

          <tr>
            <td></td>
            <td>
              <input type="hidden" name="sat_id" value="<?php echo $row["id"]; ?>">
              <input type="submit" name="submit" value="Update Satellite Information"></td>
          </tr>
        </table>
      </form>

    </body>
  </html>
  <?php
      }
  } else {
      echo "0 results";
  }
  $conn->close();

?>
<?php } else {
  echo "Error";
}
