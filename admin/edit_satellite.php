<?php
// Start the session
session_start();

// Include files
include("../config.php");

// Block unauthenticated access. The previous version called header() without
// exit;, which let script execution fall through to the SELECT (and rendered
// the edit form for any anonymous visitor).
if (empty($_SESSION["auth_status"])) {
    header('Location: '.$siteUrl.'/admin/index.php');
    exit;
}

$satelliteID = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($satelliteID === false || $satelliteID === null) {
    echo "Error";
    exit;
}

$conn = new mysqli($mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$stmt = $conn->prepare("SELECT id, name, html_element_name, website FROM satellite_name WHERE id = ?");
$stmt->bind_param("i", $satelliteID);
$stmt->execute();
$result = $stmt->get_result();
$row    = $result->fetch_assoc();

if ($row === null) {
    echo "0 results";
    $stmt->close();
    $conn->close();
    exit;
}
?>
  <!DOCTYPE html>
  <html>
    <head>
      <meta charset="utf-8">
      <title>Amsat Status Dashboard - Update Satellite</title>
      <link rel="stylesheet" href="<?php echo htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8'); ?>/admin/assets/global.css" media="screen">
    </head>
    <body>

      <h1>Edit Satellite - <?php echo htmlspecialchars($row["name"], ENT_QUOTES, 'UTF-8'); ?></h1>

      <form action="<?php echo htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8'); ?>/admin/update_satellite.php" method="post">
        <table>
          <tr>
            <td style="vertical-align:top">Satellite Name</td>
            <td>
              <input type="text" name="satellite_name" value="<?php echo htmlspecialchars($row["name"], ENT_QUOTES, 'UTF-8'); ?>">
              <p>E.g AO-7 Mode B</p>
            </td>
          </tr>

          <tr>
            <td style="vertical-align:top">Satellite Name (HTML Name)</td>
            <td>
              <input type="text" name="html_satellite_name" value="<?php echo htmlspecialchars($row["html_element_name"], ENT_QUOTES, 'UTF-8'); ?>">
              <p>E.g [B]_AO-7</p>
            </td>
          </tr>

          <tr>
            <td style="vertical-align:top">Website</td>
            <td>
              <input type="text" name="website" value="<?php echo htmlspecialchars($row["website"] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
              <p>Including http://</p>
            </td>
          </tr>

          <tr>
            <td></td>
            <td>
              <input type="hidden" name="sat_id" value="<?php echo (int)$row["id"]; ?>">
              <input type="submit" name="submit" value="Update Satellite Information"></td>
          </tr>
        </table>
      </form>

    </body>
  </html>
<?php
$stmt->close();
$conn->close();
?>
