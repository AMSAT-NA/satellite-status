<?php
// Start the session
session_start();

// Include files
include("../config.php");

// Block unauthenticated access. The previous version called header() without
// exit;, which let script execution fall through to the DELETE below --
// meaning any anonymous GET could delete a satellite_name row.
if (empty($_SESSION["auth_status"])) {
    header('Location: '.$siteUrl.'/admin/index.php');
    exit;
}

$satelliteID = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($satelliteID === false || $satelliteID === null) {
    echo "Error.";
    echo "<p><a href=\"".$siteUrl."/admin/manage_satellites.php\">Return to Manage Satellite</a></p>";
    exit;
}

$conn = new mysqli($mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$stmt = $conn->prepare("DELETE FROM satellite_name WHERE id = ?");
$stmt->bind_param("i", $satelliteID);

if ($stmt->execute()) {
    echo "Satellite deleted successfully";
} else {
    error_log("delete_satellite: " . $stmt->error);
    echo "Error deleting record.";
}
echo "<p><a href=\"".$siteUrl."/admin/manage_satellites.php\">Return to Manage Satellite</a></p>";

$stmt->close();
$conn->close();
?>
