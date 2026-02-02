<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

/* =========================
   INIT AUTH
========================= */
$auth = new Auth($conn);

$error = '';

/* =========================
   HANDLE LOGIN
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please fill in all fields';
    } else {

        if ($auth->login($email, $password)) {

            // ✅ ROLE-BASED REDIRECT (CORRECT PATHS)
            switch ($_SESSION['role']) {

                case 'admin':
                    header('Location: /clinic/admin/dashboard.php');
                    exit;

                case 'doctor':
                    header('Location: /clinic/doctor/dashboard.php');
                    exit;

                case 'patient':
                    header('Location: /clinic/patient/patient-dashboard.php');
                    exit;

               
                default:
                    // Safety fallback
                    session_destroy();
                    $error = 'Unknown user role';
            }

        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Archana Clinic</title>

<link rel="stylesheet" href="styles.css">

<style>
.password-wrapper {
    position: relative;
}
.password-wrapper input {
    width: 100%;
    padding-right: 40px;
}
.toggle-password {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    font-size: 18px;
    color: #555;
}
.toggle-password:hover {
    color: #000;
}
.error-message {
    background-color: #fdd;
    color: #900;
    padding: 10px;
    margin-bottom: 15px;
    border-radius: 5px;
    text-align: center;
}
</style>

<script>
function togglePassword() {
    const passwordInput = document.getElementById("password");
    const icon = document.querySelector(".toggle-password");

    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        icon.textContent = "🙈";
    } else {
        passwordInput.type = "password";
        icon.textContent = "👁";
    }
}
</script>
</head>

<body class="login-page">
<div class="login-container">
    <div class="login-box">
        <div class="login-header">
            <h1>Archana Clinic</h1>
        </div>

        <form method="POST" class="login-form">

            <?php if ($error): ?>
                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required placeholder="Enter your email">
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required placeholder="Enter your password">
                    <span class="toggle-password" onclick="togglePassword()">👁</span>
                </div>
            </div>

            <button type="submit" class="btn-login">Login</button>
        </form>
    </div>
</div>
</body>
</html>
