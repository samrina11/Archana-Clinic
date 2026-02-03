<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$error = '';

function validateName($name)
{
    $name = trim($name);

    if (empty($name))
        return "Name is required.";
    if (!preg_match("/^[A-Za-z]+( [A-Za-z]+)*$/", $name))
        return "Name must contain only letters and spaces.";
    if (strlen($name) < 10 || strlen($name) > 30)
        return "Name must be between 10 and 30 characters.";

    return true;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone    = trim($_POST['phone'] ?? '');

    // ------------------------
    // Backend Validation
    // ------------------------
      if (validateName($name) !== true) {
        $error = validateName($name);
    }
    elseif (!preg_match("/^[a-zA-Z0-9._%+-]+@gmail\.com$/",$email )) {
        $error = "Invalid email address.";
    }
    elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/", $password)) {
        $error = "Password must be at least 8 characters and include uppercase, lowercase, number, and special character.";
    }
    elseif (!preg_match("/^\d{10}$/", $phone)) {
        $error = "Phone number must be exactly 10 digits.";
    }elseif (empty($name) || empty($email) || empty($password) || empty($phone)) {
        $error = "Please fill all required fields.";
    }
    else {

        // Check email exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "Email already registered.";
        } else {

            // Insert user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO users (name, email, password, phone, role)
                VALUES (?, ?, ?, ?, 'patient')
            ");

            if ($stmt) {
                $stmt->bind_param("ssss", $name, $email, $hashedPassword, $phone);

                if ($stmt->execute()) {
                    $_SESSION['user_id'] = $stmt->insert_id;
                    $_SESSION['email']   = $email;
                    $_SESSION['role']    = 'patient';

                    header("Location: /clinic/patient/patient-dashboard.php");
                    exit;
                } else {
                    $error = "Database insert failed: " . $stmt->error;
                }
            } else {
                $error = "Prepare failed: " . $conn->error;
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
<title>Register Patient</title>

<style>
body { font-family: Arial; background:#f4f6f8; }
.container { max-width:500px; margin:50px auto; }
.box { background:#fff; padding:30px; border-radius:10px; box-shadow:0 10px 25px rgba(0,0,0,.1); }
h1 { text-align:center; color:#0c74a6; }
label { font-weight:600; margin-top:10px; display:block; }
input { width:100%; padding:10px; margin-top:5px; border:2px solid #ddd; border-radius:6px; }
button { width:100%; padding:12px; margin-top:15px; background:#0c74a6; color:#fff; border:none; border-radius:6px; }
.error { background:#ffd6d6; padding:10px; margin-bottom:15px; color:#900; }
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

<label>Full Name *</label>
<input type="text" name="name" required  minlength="10"
                                maxlength="30" pattern="[A-Za-z]+( [A-Za-z]+)*"
                                        title="Only letters and spaces allowed (10–30 characters)">

<label>Email *</label>
<input type="email" name="email" required pattern="[a-zA-Z0-9._%+-]+@gmail\.com" title="Invalid email format">

<label>Password *</label>
<input type="password" name="password" required>

<label>Phone *</label>
<input type="text" name="phone" maxlength="10" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">

<button type="submit">Register</button>
</form>

<p style="text-align:center;margin-top:15px;">
Already have account? <a href="../login.php">Login</a>
</p>

</div>
</div>

<script>
document.getElementById("registerForm").addEventListener("submit", function(e) {

    const phone = document.querySelector("[name='phone']").value;

    if (!/^\d{10}$/.test(phone)) {
        e.preventDefault();
        alert("Phone must be exactly 10 digits");
    }
});
</script>

</body>
</html>
