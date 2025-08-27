<?php
require 'db.php';

$email = 'professor1@university.edu';
$plain = '123456';

$stmt = $conn->prepare("SELECT password FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($hash);
$stmt->fetch();
$stmt->close();

echo "<pre>";
echo "DB hash: " . htmlspecialchars($hash) . "\n";
echo "password_verify result: " . (password_verify($plain, $hash) ? "TRUE" : "FALSE");
echo "</pre>";
