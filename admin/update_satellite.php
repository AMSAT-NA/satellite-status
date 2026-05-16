<?php
// Start the session
session_start();

// Include files
include("../config.php");

// Block unauthenticated access. The previous version called header() without
// exit;, which let script execution fall through to the UPDATE below.
if (empty($_SESSION["auth_status"])) {
    header('Location: '.$siteUrl.'/admin/index.php');
    exit;
}

$satelliteID       = filter_input(INPUT_POST, 'sat_id', FILTER_VALIDATE_INT);
$satelliteName     = $_POST['satellite_name']      ?? '';
$satelliteHTMLName = $_POST['html_satellite_name'] ?? '';
$satelliteWebsite  = $_POST['website']             ?? '';

if ($satelliteID === false || $satelliteID === null) {
    echo "Error.";
    echo "<p><a href=\"".$siteUrl."/admin/manage_satellites.php\">Return to Manage Satellite</a></p>";
    exit;
}

$conn = new mysqli($mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$stmt = $conn->prepare("UPDATE satellite_name SET name = ?, html_element_name = ?, website = ? WHERE id = ?");
$stmt->bind_param("sssi", $satelliteName, $satelliteHTMLName, $satelliteWebsite, $satelliteID);

if ($stmt->execute()) {
    echo htmlspecialchars($satelliteName, ENT_QUOTES, 'UTF-8') . " updated successfully";
} else {
    error_log("update_satellite: " . $stmt->error);
    echo "Error updating " . htmlspecialchars($satelliteName, ENT_QUOTES, 'UTF-8') . ".";
}
echo "<p><a href=\"".$siteUrl."/admin/manage_satellites.php\">Return to Manage Satellite</a></p>";

$stmt->close();
$conn->close();
?>
