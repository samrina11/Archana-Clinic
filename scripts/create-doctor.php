<?php
exit('This script is disabled.');

require_once '../config/database.php';

// Doctor user info
$doctor_name = "Dr. Smith";
$doctor_email = "doctor@archana.com";
$doctor_password = password_hash("doctor123", PASSWORD_DEFAULT); // hashed password

// Insert doctor into users table
$doctor_query = "INSERT IGNORE INTO users (name, email, password, role) VALUES (?, ?, ?, 'doctor')";
$stmt = $conn->prepare($doctor_query);
$stmt->bind_param("sss", $doctor_name, $doctor_email, $doctor_password);
$stmt->execute();

// Get the inserted user's ID
$doctor_id = $stmt->insert_id;

// Optional: Add doctor details in doctors table
$specialization = "General Physician";
$qualification = "MBBS";
$experience = 5;
$available_slots = 10;

$doctor_details_query = "INSERT IGNORE INTO doctors (user_id, specialization, qualification, experience, available_slots) VALUES (?, ?, ?, ?, ?)";
$stmt2 = $conn->prepare($doctor_details_query);
$stmt2->bind_param("issii", $doctor_id, $specialization, $qualification, $experience, $available_slots);
$stmt2->execute();

echo "Doctor account created successfully!";
?>
