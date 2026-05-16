<?php
// Start the session
session_start();

// Include files
include("../config.php");

// Block unauthenticated access. The previous version called header() without
// exit;, which let script execution fall through to the INSERT below.
if (empty($_SESSION["auth_status"])) {
    header('Location: '.$siteUrl.'/admin/index.php');
    exit;
}

$satelliteName     = $_POST['satellite_name']      ?? '';
$satelliteHTMLName = $_POST['html_satellite_name'] ?? '';
$satelliteWebsite  = $_POST['website']             ?? '';

if ($satelliteName === "" || $satelliteHTMLName === "") {
  echo "You must enter the Satellite Name & HTML Name;";
  echo "<p><a href=\"".$siteUrl."/admin/dashboard.php\">Return to Dashboard</a></p>";
  exit;
}

$conn = new mysqli($mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$stmt = $conn->prepare("INSERT INTO satellite_name (name, html_element_name, website) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $satelliteName, $satelliteHTMLName, $satelliteWebsite);

if ($stmt->execute()) {
    echo "New record created successfully";
} else {
    error_log("create_satellite: " . $stmt->error);
    echo "Error creating record.";
}
echo "<p><a href=\"".$siteUrl."/admin/dashboard.php\"> Return to Dashboard</a></p>";

$stmt->close();
$conn->close();
?>
