<?php
require_once './config/database.php';

// Fetch all patients
$result = $conn->query("SELECT id, password FROM patients");

while ($row = $result->fetch_assoc()) {

    $id = $row['id'];
    $plainPassword = $row['password'];

    // Skip already hashed passwords
    if (password_get_info($plainPassword)['algo'] !== 0) {
        continue;
    }

    // Hash password
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

    // Update database
    $stmt = $conn->prepare("UPDATE patients SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashedPassword, $id);
    $stmt->execute();
}

echo "✅ All patient passwords have been hashed successfully!";
