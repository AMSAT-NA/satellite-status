<?php
date_default_timezone_set('UTC');
require("config.php");

header('Content-Type: application/json');

// name parameter is mandatory, if not set return an empty array
$sat_name = htmlspecialchars($_GET["name"]);
if(!isset($sat_name) || $sat_name == ""){
    echo json_encode([]);
    exit();   
}

define("MIN_HOURS", 1);
define("MAX_HOURS", 96);
define("DEFAULT_HOURS", "72");

// hours parameter is optional and defaults to DEFAULT_HOURS, but has a
// maximum allowable value of MAX_HOURS. Minimum value is MIN_HOURS 
if(isset($_GET["hours"])){
    $hours = htmlspecialchars($_GET["hours"]);
    if(is_numeric($hours)){
        $int_hours = intval($hours);
        $int_hours = ($int_hours > MAX_HOURS) ? MAX_HOURS : $int_hours;
        $int_hours = ($int_hours < MIN_HOURS) ? MIN_HOURS : $int_hours;
        $hours = strval($int_hours);
    } else {
        $hours = DEFAULT_HOURS;
    }
} else {
    $hours = DEFAULT_HOURS;
}

$min_date = date_sub(date_create(), date_interval_create_from_date_string($hours . ' hours'));
$min_date_str = date_format($min_date, 'Y-m-d H:i:s');
$db = mysqli_connect($mysqlHost, $mysqlUsername, $mysqlPassword);

if (mysqli_connect_errno()){
  exit('Could not connect: ' . mysqli_connect_error() . "\n");
}

mysqli_select_db($db, $mysqlDatabase);

$return_arr = array();

// The query is a bit hairy because date is stored separately from the hour of a report
// The result set shows all reports at the middle of the hour
$query = "SELECT name, CONCAT(day, 'T', LPAD(hour, 2, 0), ':30:00Z') AS reported_time, callsign, report, grid_square " .
    "FROM satellite " .
    "WHERE name = ? " .
    "AND CONCAT(day, 'T', LPAD(hour, 2, 0), ':30:00Z') > ? " .
    "ORDER BY day DESC, hour DESC " .
    "LIMIT 100";
$stmt = mysqli_prepare($db, $query);
mysqli_stmt_bind_param($stmt, "ss", $sat_name, $min_date_str);
if(mysqli_stmt_execute($stmt)){
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $row_array['name'] = $row['name'];
        $row_array['reported_time'] = $row['reported_time'];
        $row_array['callsign'] = $row['callsign'];
        $row_array['report'] = $row['report'];
        $row_array['grid_square'] = $row['grid_square'];

        array_push($return_arr,$row_array);
    }
}
mysqli_stmt_close($stmt);

echo json_encode($return_arr);
?>
