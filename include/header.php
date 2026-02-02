<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';


$auth = new Auth($conn); // ✅ THIS WAS MISSING

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="styles.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <title>Archana clinic</title>
  
   
</head>
<body>

<header>

  <div class="nav-container">
    <div class="logo">
      <img src="./assets/logo.jpg" alt="Logo">
    </div>

    <nav>
      <ul class="nav-menu">
        <li><a href="index.php">Home</a></li>
        <li><a href="about.php">About</a></li>
        <li><a href="doctors.php">Doctors</a></li>
        <li><a href="contact.php">Contact</a></li>
      </ul>
    </nav>
    




<div class="nav-buttons">
<div class="navbar-nav ms-auto nav-buttons">
      <!-- Patient registration (everyone can see) -->
<a href="/clinic/register/register_patient.php" class="btn btn-small">Register as Patient</a>



<a href="login.php" class="btn btn-small">Login</a>
  </div>
</div>

</header>