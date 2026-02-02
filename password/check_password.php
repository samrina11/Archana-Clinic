<?php
require_once './config/database.php';

function showPasswords($conn, $table, $limit = 5) {
    echo "<h2>Checking $table table</h2>";
    $stmt = $conn->prepare("SELECT id, email, password FROM $table LIMIT ?");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        echo "ID: " . htmlspecialchars($row['id']) . " | ";
        echo "Email: " . htmlspecialchars($row['email']) . " | ";
        echo "Password: " . htmlspecialchars($row['password']) . "<br>";
    }
}

// Show first few entries from both tables
showPasswords($conn, 'users');
showPasswords($conn, 'patients');
?>