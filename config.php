<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "academic_tracking";

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
