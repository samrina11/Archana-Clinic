<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$bill_id = $_POST['bill_id'] ?? null;

if (!$bill_id) {
    die("Invalid payment.");
}

// Update bill status
$stmt = $conn->prepare("UPDATE bills SET status = 'paid' WHERE id = ?");
$stmt->bind_param("i", $bill_id);
$stmt->execute();

// Redirect back
header("Location: ../patient/patient-dashboard.php?payment=success");
exit;
