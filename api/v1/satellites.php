<?php
require("../../config.php");

header('Content-Type: application/json');

$db = mysqli_connect($mysqlHost, $mysqlUsername, $mysqlPassword);
if (mysqli_connect_errno()) {
    exit('Could not connect: ' . mysqli_connect_error() . "\n");
}
mysqli_select_db($db, $mysqlDatabase);

$result = mysqli_query(
    $db,
    "SELECT id, name, html_element_name, website FROM satellite_name ORDER BY name ASC"
);

$return_arr = [];
while ($row = mysqli_fetch_assoc($result)) {
    $return_arr[] = [
        'id'                => (int) $row['id'],
        'name'              => $row['name'],
        'html_element_name' => $row['html_element_name'],
        'website'           => $row['website'],
    ];
}

echo json_encode($return_arr);
