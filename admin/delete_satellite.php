<?php
// Start the session
session_start();

// Include files
include("config.php");

// if not logged in redirect back to login page.
if($_SESSION["auth_status"] != true) {
  header('Location: '.$siteUrl.'/admin/index.php');
}

$satelliteID = htmlspecialchars($_GET['id']);

if($satelliteID != "") {
  $conn = new mysqli($mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase);
  // Check connection
  if ($conn->connect_error) {
      die("Connection failed: " . $conn->connect_error);
  }

  // sql to delete a record
  $sql = "DELETE FROM satellite_name WHERE id = $satelliteID";

  if ($conn->query($sql) === TRUE) {
      echo "Satellite deleted successfully";
      echo "<p><a href=\"".$siteUrl."/admin/manage_satellites.php\">Return to Manage Satellite</a></p>";
  } else {
      echo "Error deleting record: " . $conn->error;
      echo "<p><a href=\"".$siteUrl."/admin/manage_satellites.php\">Return to Manage Satellite</a></p>";
  }

  $conn->close();

} else {
  echo "Error.";
  echo "<p><a href=\"".$siteUrl."/admin/manage_satellites.php\">Return to Manage Satellite</a></p>";
}

?>
