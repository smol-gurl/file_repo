<?php
$host = "localhost";
$user = "root";
$pass = ""; // XAMPP default has no password
$db = "file_repo";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
