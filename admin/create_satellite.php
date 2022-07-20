<?php
// Start the session
session_start();

// Include files
include("config.php");

// if not logged in redirect back to login page.
if($_SESSION["auth_status"] != true) {
  header('Location: '.$siteUrl.'/admin/index.php');
}

$satelliteName = htmlspecialchars($_POST['satellite_name']);
$satelliteHTMLName = htmlspecialchars($_POST['html_satellite_name']);
$satelliteWebsite = htmlspecialchars($_POST['website']);

if ($satelliteName == "" || $satelliteHTMLName == "") {
  echo "You must enter the Satellite Name & HTML Name;";
  echo "<p><a href=\"".$siteUrl."/admin/dashboard.php\">Return to Dashboard</a></p>";
  exit;
}

$conn = new mysqli($mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "INSERT INTO satellite_name (name, html_element_name, website)
VALUES ('$satelliteName', '$satelliteHTMLName', '$satelliteWebsite')";

if ($conn->query($sql) === TRUE) {
    echo "New record created successfully";
    echo "<p><a href=\"".$siteUrl."/admin/dashboard.php\"> Return to Dashboard</a></p>";
} else {
    echo "Error: " . $sql . "<br>" . $conn->error;
    echo "<p><a href=\"".$siteUrl."/admin/dashboard.php\"> Return to Dashboard</a></p>";
}

$conn->close();

?>
