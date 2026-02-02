<?php
require_once './config/database.php';

$email = 'admin@archana.com';
$newPlain = 'newpass'; // choose a new password
$newHash = password_hash($newPlain, PASSWORD_DEFAULT);

// Try users table first
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
$stmt->bind_param("ss", $newHash, $email);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    // If not in users, try patients
    $stmt = $conn->prepare("UPDATE admin SET password = ? WHERE email = ?");
    $stmt->bind_param("ss", $newHash, $email);
    $stmt->execute();
}

echo "Password reset done. Try logging in with: $newPlain";