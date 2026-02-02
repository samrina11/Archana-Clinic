<?php
$servername = "localhost";
$username   = "root";
$password   = "";   // root password RESET
$database   = "clinic";

$conn = new mysqli("localhost", "root", "", "clinic", 3306);


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8");
?>

