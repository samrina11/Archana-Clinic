<?php
require_once 'config/database.php';

$passwordHash = password_hash('admin123', PASSWORD_DEFAULT);

$sql = "INSERT IGNORE INTO users (id, name, email, password, role, phone) VALUES
(1, 'Admin User', 'admin@archana.com', '$passwordHash', 'admin', '9999999999')";

if ($conn->query($sql)) {
    echo "First admin inserted successfully!";
} else {
    echo "Error: " . $conn->error;
}
?>
