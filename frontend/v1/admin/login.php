<?php
// Start the session
session_start();

// Include files
include("../config.php");

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// Create connection
$conn = new mysqli($mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$stmt = $conn->prepare("SELECT username, password FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row !== null && password_verify($password, $row["password"])) {
    $_SESSION["auth_status"] = true;
    $_SESSION["auth_username"] = $row["username"];

    header('Location: '.$siteUrl.'/admin/dashboard.php');
    exit;
} else {
    echo 'Login failed. Username or password is incorrect.';
}

$stmt->close();
$conn->close();

?>
