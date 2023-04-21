<?php
// Start the session
session_start();

// Include files
include("../config.php");

// if not logged in redirect back to login page.
if($_SESSION["auth_status"] != true) {
  header('Location: '.$siteUrl.'/admin/index.php');}

$username = htmlspecialchars($_POST['username']);
$password = htmlspecialchars($_POST['password']);

// Create connection
$conn = new mysqli($mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT username, password FROM users WHERE username = '$username'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // output data of each row
    while($row = $result->fetch_assoc()) {
        if (password_verify($password, $row["password"])) {
          $_SESSION["auth_status"] = true;
          $_SESSION["auth_username"] = $row["username"];

          header('Location: '.$siteUrl.'/admin/dashboard.php');
        } else {
            echo 'Invalid password.';
        }
    }
} else {
    echo "Login Failed, Username incorrect.";
}
$conn->close();

?>
