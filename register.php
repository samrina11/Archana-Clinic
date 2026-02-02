<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'config/auth.php';

$error = '';
$success = '';

// Check if logged-in admin
$show_admin_option = false;
if ($auth->isLoggedIn() && $_SESSION['role'] === 'admin') {
    $show_admin_option = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'patient';

    if (!$show_admin_option) {
        $role = 'patient';
    }

    if (empty($name) || empty($email) || empty($password)) {
        $error = "Please fill all required fields";
    } else {

        if ($role === 'patient') {

            $phone             = trim($_POST['phone'] ?? '');
            $dob               = $_POST['date_of_birth'] ?? '';
            $gender            = $_POST['gender'] ?? '';
            $address           = trim($_POST['address'] ?? '');
            $emergency_contact = trim($_POST['emergency_contact'] ?? '');
            $emergency_phone   = trim($_POST['emergency_phone'] ?? '');
            $medical_history   = trim($_POST['medical_history'] ?? '');

            if (
                empty($phone) || empty($dob) || empty($gender) ||
                empty($address) || empty($emergency_contact) || empty($emergency_phone)
            ) {
                $error = "Please fill all patient details";
            } else {
                if ($auth->registerPatient(
                    $name,
                    $email,
                    $password,
                    $phone,
                    $dob,
                    $gender,
                    $address,
                    $emergency_contact,
                    $emergency_phone,
                    $medical_history
                )) {
                    header("Location: login.php?registered=1");
                    exit;
                } else {
                    $error = "Registration failed or email already exists";
                }
            }

        } else {
            $user_id = $auth->register($name, $email, $password, $role);

            if ($user_id) {
                if ($role === 'admin') {
                    header("Location: admin/dashboard.php");
                } elseif ($role === 'doctor') {
                    header("Location: doctor/dashboard.php");
                } elseif ($role === 'receptionist') {
                    header("Location: receptionist/dashboard.php");
                }
                exit;
            } else {
                $error = "Email already exists";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register - Archana Clinic</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="styles.css">
</head>

<body class="login-page">
<div class="login-container">
<div class="login-box">

<h1 style="text-align:center;color:#0c74a6;">Archana Clinic</h1>

<?php if ($error): ?>
    <div class="error-message"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">

<?php if ($show_admin_option): ?>
<div class="form-group">
    <label>Role</label>
    <select name="role">
        <option value="patient">Patient</option>
        <option value="doctor">Doctor</option>
        <option value="receptionist">Receptionist</option>
        <option value="admin">Admin</option>
    </select>
</div>
<?php else: ?>
<input type="hidden" name="role" value="patient">
<?php endif; ?>

<div class="form-group">
    <label>Full Name</label>
    <input type="text" name="name" required>
</div>

<div class="form-group">
    <label>Email</label>
    <input type="email" name="email" required>
</div>

<div class="form-group">
    <label>Password</label>
    <input type="password" name="password" required>
</div>

<div class="form-group">
    <label>Phone</label>
    <input type="text" name="phone">
</div>

<div class="form-group">
    <label>Date of Birth</label>
    <input type="date" name="date_of_birth">
</div>

<div class="form-group">
    <label>Gender</label>
    <select name="gender">
        <option value="">Select</option>
        <option>male</option>
        <option>female</option>
        <option>other</option>
    </select>
</div>

<div class="form-group">
    <label>Address</label>
    <textarea name="address"></textarea>
</div>

<div class="form-group">
    <label>Emergency Contact</label>
    <input type="text" name="emergency_contact">
</div>

<div class="form-group">
    <label>Emergency Phone</label>
    <input type="text" name="emergency_phone">
</div>

<div class="form-group">
    <label>Medical History</label>
    <textarea name="medical_history"></textarea>
</div>

<button type="submit">Register</button>
</form>

<p style="text-align:center;margin-top:10px;">
    Already have an account? <a href="login.php">Login</a>
</p>

</div>
</div>
</body>
</html>
