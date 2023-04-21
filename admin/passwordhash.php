<?php

include("../config.php");

$password = "MgGI3GNwJ4VfCPS1EEw7aQCSYJ7JuYKC";
$password_hashed = password_hash($password, PASSWORD_DEFAULT);


if (password_verify($password, $password_hashed)) {
    echo 'Password is valid!';
} else {
    echo 'Invalid password.';
}

$conn = new mysqli($mysqlHost, $mysqlUsername, $mysqlPassword, $mysqlDatabase);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "INSERT INTO users (username, password, email)
VALUES ('satops', '$password_hashed', 'pgoodhall+amsat@gmail.com')";

if ($conn->query($sql) === TRUE) {
    echo "New record created successfully";
} else {
    echo "Error: " . $sql . "<br>" . $conn->error;
}

$conn->close();

?>
