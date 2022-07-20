<?php
// Start the session
session_start();

// Include files
include("config.php");

// if not logged in redirect back to login page.
if($_SESSION["auth_status"] != true) {
  header('Location: '.$siteUrl.'/admin/index.php');
}

$satelliteID = htmlspecialchars($_POST['sat_id']);
$satelliteName = htmlspecialchars($_POST['satellite_name']);
$satelliteHTMLName = htmlspecialchars($_POST['html_satellite_name']);
$satelliteWebsite = htmlspecialchars($_POST['website']);

if($satelliteID != "") {
  $conn = new mysqli($mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase);
  // Check connection
  if ($conn->connect_error) {
      die("Connection failed: " . $conn->connect_error);
  }

  $sql = "UPDATE satellite_name SET name='$satelliteName', html_element_name='$satelliteHTMLName', website='$satelliteWebsite'  WHERE id = $satelliteID";

  if ($conn->query($sql) === TRUE) {
      echo $satelliteName." updated successfully";
      echo "<p><a href=\"".$siteUrl."/admin/manage_satellites.php\">Return to Manage Satellite</a></p>";
  } else {
      echo "Error updating: " .  $satelliteName. " " . $conn->error;
      echo "<p><a href=\"".$siteUrl."/admin/manage_satellites.php\">Return to Manage Satellite</a></p>";
  }

  $conn->close();
}

?>
