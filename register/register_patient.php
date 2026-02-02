<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone    = trim($_POST['phone'] ?? '');
    $role     = 'patient';

    // ------------------------
    // Backend Validation (FINAL)
    // ------------------------
    if (empty($name) || empty($email) || empty($password) || empty($phone)) {
        $error = "Please fill all required fields.";
    } 
    elseif (!preg_match("/^[A-Za-z]+( [A-Za-z]+)* {10,50}$/", $name)) {
        $error = "Name must be 10–50 characters, letters and spaces only. ";
    } 
    elseif (!preg_match("/^[a-zA-Z0-9._%+-]+@[a-zA-Z.-]+\.[a-zA-Z]{2,}$/", $email)) {
        $error = "Invalid email address.";
    } 
    elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/", $password)) {
        $error = "Password must be at least 8 characters and include uppercase, lowercase, number, and special character.";
    } 
    elseif (!preg_match("/^\d{10}$/", $phone)) {
        $error = "Phone number must be exactly 10 digits.";
    } 
    else {

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "Email already registered.";
        } 
        else {

            if ($auth->registerPatient(
                $name,
                $email,
                $password,
                $phone,
                null, null, null, null, null
            )) {

                $auth->login($email, $password);
                header("Location: /clinic/patient/patient-dashboard.php");
                exit;

            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register Patient - Clinic</title>

<style>
body { font-family: Arial, sans-serif; background: #f4f6f8; margin:0; padding:0; }
.container { max-width: 500px; margin: 50px auto; }
.box { background: #fff; padding: 40px 30px; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
h1 { text-align: center; color: #0c74a6; margin-bottom: 25px; }
.form-group { margin-bottom: 15px; }
label { display: block; margin-bottom: 5px; font-weight: 600; }
input { width: 100%; padding: 8px 10px; border: 2px solid #ddd; border-radius: 6px; }
input:focus { border-color: #0c74a6; outline: none; }
button { width: 100%; padding: 12px; background: linear-gradient(135deg, #0c74a6, #5bbbe0); border: none; color: white; font-size: 16px; border-radius: 6px; cursor: pointer; margin-top: 10px; }
.error { background: #ffd6d6; padding: 10px; color: #a10000; margin-bottom: 15px; border-radius:5px; }
</style>
</head>

<body>
<div class="container">
    <div class="box">
        <h1>Register Patient</h1>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="registerForm">

            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="name"
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                       pattern="[A-Za-z ]{10,50}"
                       title="10–50 characters, letters and spaces only"
                       required>
            </div>

            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       required>
            </div>

            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password"
                       pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}"
                       title="At least 8 chars with uppercase, lowercase, number & special char"
                       required>
            </div>

            <div class="form-group">
                <label>Phone *</label>
                <input type="text" name="phone"
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                       pattern="\d{10}"
                       maxlength="10"
                       title="Exactly 10 digits"
                       required>
            </div>

            <button type="submit">Register as Patient</button>
        </form>

        <p style="margin-top:15px; text-align:center;">
            Already have an account? <a href="../login.php">Login here</a>
        </p>
    </div>
</div>

<!-- =========================
 Frontend Validation (JS)
========================= -->
<script>
document.getElementById("registerForm").addEventListener("submit", function (e) {

    const name = document.querySelector("input[name='name']").value.trim();
    const email = document.querySelector("input[name='email']").value.trim();
    const password = document.querySelector("input[name='password']").value;
    const phone = document.querySelector("input[name='phone']").value.trim();

    let error = "";

    if (!/^[A-Za-z ]{10,50}$/.test(name)) {
        error = "Name must be 10–50 characters and letters only.";
    }
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        error = "Invalid email address.";
    }
    else if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/.test(password)) {
        error = "Password must include uppercase, lowercase, number and special character.";
    }
    else if (!/^\d{10}$/.test(phone)) {
        error = "Phone number must be exactly 10 digits.";
    }

    if (error !== "") {
        e.preventDefault();
        alert(error);
    }
});
</script>

</body>
</html>
