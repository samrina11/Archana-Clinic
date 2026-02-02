<?php
session_start();
require_once './config/database.php';
require_once './config/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        if ($auth->login($email, $password)) {
            $user = $auth->getUser();

            if ($user) {
                switch ($user['role']) {
                    case 'admin':
                        header('Location: ./admin/dashboard.php');
                        exit;
                    case 'doctor':
                        header('Location: ./doctor/dashboard.php');
                        exit;
                    case 'receptionist':
                        header('Location: ./receptionist/dashboard.php');
                        exit;
                    case 'patient':
                        header('Location: ./patient/dashboard.php');
                        exit;
                    default:
                        $error = 'Unknown user role';
                }
            } else {
                $error = 'Session error. Please login again.';
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
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="Enter your email">
            </div>

            <div class="form-group password-group">
                <label for="password">Password</label>
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



<?php
session_start();
require_once 'config/database.php';
require_once 'config/auth.php';

$error = '';
$success = '';

// Only admin can create admin
$show_admin_option = false;
if ($auth->isLoggedIn()) {
    $user = $auth->getUser();
    if ($user['role'] === 'admin') {
        $show_admin_option = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';

    // Admin restriction
    if ($role === 'admin' && !$show_admin_option) {
        $error = "Only admin can create another admin";
    }

    elseif (!$show_admin_option) {
    $role = 'patient';
}


    // Common validation
    elseif (empty($name) || empty($email) || empty($password) || empty($role)) {
        $error = "Please fill all required fields";
    }

    // Patient-specific validation
    elseif ($role === 'patient') {
        $username          = trim($_POST['username'] ?? '');
        $phone             = trim($_POST['phone'] ?? '');
        $dob               = $_POST['date_of_birth'] ?? '';
        $gender            = $_POST['gender'] ?? '';
        $address           = trim($_POST['address'] ?? '');
        $emergency_contact = trim($_POST['emergency_contact'] ?? '');
        $emergency_phone   = trim($_POST['emergency_phone'] ?? '');
        $medical_history   = trim($_POST['medical_history'] ?? '');
       
        
        if (
            empty($phone) || empty($dob) || empty($gender) ||
            empty($address) || empty($emergency_contact) ||
            empty($emergency_phone) || empty($username)
        ) {
            $error = "Please fill all patient details";
        } else {
            if ($auth->registerPatient( 
                $name, 
                $email,
                $username,
                $password,
                $phone,
                $dob,
                $gender,
                $address,
                $emergency_contact,
                $emergency_phone,
                $medical_history
                
            )) {
                $success = "Patient registered successfully! <a href='login.php'>Login</a>";
            } else {
                $error = "Registration failed or email already exists";
            }
        }
    }

    // Doctor / Receptionist / Admin
    else {
        if ($auth->register($name, $email, $password, $role)) {
            $success = "Registration successful! <a href='login.php'>Login</a>";
        } else {
            $error = "Email already exists";
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

<style>
body {
    font-family: Arial, sans-serif;
    background: #f4f6f8;
}

.login-container {
    max-width: 500px;
    margin: 40px auto;
}

.login-box {
    background: #fff;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.login-header h1 {
    text-align: center;
    color: #0c74a6;
}

.form-group, .form-g {
    margin-bottom: 15px;
}

label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
}

input, select, textarea {
    width: 100%;
    padding: 10px;
    border: 2px solid #ddd;
    border-radius: 6px;
}

input:focus, textarea:focus, select:focus {
    border-color: #0c74a6;
    outline: none;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

#patient-fields {
    display: inline-block;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 2px solid #eee;
}

button {
    width: 100%;
    padding: 12px;
    background: linear-gradient(135deg, #0c74a6, #5bbbe0);
    border: none;
    color: white;
    font-size: 16px;
    border-radius: 6px;
    cursor: pointer;
}

button:hover {
    opacity: 0.9;
}

.error-message {
    background: #ffd6d6;
    padding: 10px;
    color: #a10000;
    margin-bottom: 15px;
}

.success-message {
    background: #d6ffe0;
    padding: 10px;
    color: #006b2e;
    margin-bottom: 15px;
}

.login-footer {
    text-align: center;
    margin-top: 15px;
}
.password-wrapper { position: relative; }
 .password-wrapper input { width: 100%; padding-right: 40px; }
  .toggle-password { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); 
  cursor: pointer; 
  font-size: 18px; 
  color: #555; }
   .toggle-password:hover { color: #000; }
    .form-g { margin-bottom: 1rem; }

    /* Make the white box wider */
.login-box {
    max-width: 600px; /* increased from 500px */
    padding: 40px 50px; /* more space inside */
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    margin: 40px auto;
}

/* Reduce height of all inputs and textareas */
input[type="text"],
input[type="email"],
input[type="password"],
select,
textarea {
    height: 35px; /* smaller height */
    font-size: 14px;
    padding: 5px 10px; /* adjust inner spacing */
    border: 2px solid #ddd;
    border-radius: 6px;
    width: 100%;
    box-sizing: border-box; /* include padding in width */
}

/* Make textarea height smaller if needed */
textarea {
    height: 60px; /* adjust as needed */
    resize: vertical; /* allow vertical resize */
}

/* Adjust password wrapper height to match input */
.password-wrapper {
    position: relative;
    height: 35px; /* match input height */
}

.password-wrapper input {
    height: 100%;
    padding-right: 40px; /* for eye icon */
}

/* Optional: adjust toggle eye icon */
.toggle-password {
    right: 10px;
    font-size: 16px;
    top: 50%;
    transform: translateY(-50%);
}


    
</style>

<script>
function togglePatientFields() {
    const role = document.getElementById("role").value;
    document.getElementById("patient-fields").style.display =
        role === "patient" ? "block" : "none";
}

function togglePassword() { const passwordInput = document.getElementById("password"); 
const icon = document.querySelector(".toggle-password");
 if (passwordInput.type === "password") { passwordInput.type = "text";
  icon.textContent = "🙈"; } else { passwordInput.type = "password"; 
  icon.textContent = "👁"; } }


window.onload = function () {
    <?php if (!$show_admin_option): ?>
        document.getElementById("patient-fields").style.display = "block";
    <?php endif; ?>
};


</script>
</head>

<body>
<div class="login-container">
<div class="login-box">

<div class="login-header">
    <h1>Archana Clinic</h1>
</div>

<form method="POST">

<?php if ($error): ?>
    <div class="error-message"><?= $error ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="success-message"><?= $success ?></div>
<?php endif; ?>

<?php if ($show_admin_option): ?>
<div class="form-group">
    <label>Role</label>
    <select name="role" id="role" onchange="togglePatientFields()" required>
        <option value="">Select Role</option>
        <option value="admin">Admin</option>
        <option value="doctor">Doctor</option>
        <option value="receptionist">Receptionist</option>
        <option value="patient">Patient</option>
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
    <label for="password">Password</label> <div class="password-wrapper">
         <input type="password" id="password" name="password" required placeholder="Create password"> 
         <span class="toggle-password" onclick="togglePassword()">👁</span> </div>
   
</div>

<div class="form-group">
    <label>Role</label>
    <select name="role" id="role" onchange="togglePatientFields()" required>
        <option value="">Select Role</option>
        <?php if ($show_admin_option): ?>
            <option value="admin">Admin</option>
        <?php endif; ?>
        <option value="doctor">Doctor</option>
        <option value="receptionist">Receptionist</option>
        <option value="patient">Patient</option>
    </select>
</div>

<!-- PATIENT ONLY -->
<div id="patient-fields">
     <div class="form-g">
        <label>Username</label>
        <input type="text" name="username">
 </div>

    <div class="form-row">
        <div class="form-g">
            <label>Phone</label>
            <input type="text" name="phone">
        </div>
        <div class="form-g">
            <label>Date of Birth</label>
            <input type="date" name="date_of_birth">
        </div>
    </div>

    <div class="form-row">
        <div class="form-g">
            <label>Gender</label>
            <select name="gender">
                <option value="">Select</option>
                <option>male</option>
                <option>female</option>
                <option>other</option>
            </select>
        </div>
        <div class="form-g">
            <label>Address</label>
            <textarea name="address"></textarea>
        </div>
    </div>

    <div class="form-row">
        <div class="form-g">
            <label>Emergency Contact</label>
            <input type="text" name="emergency_contact">
        </div>
        <div class="form-g">
            <label>Emergency Phone</label>
            <input type="text" name="emergency_phone">
        </div>
    </div>

    <div class="form-g">
        <label>Medical History</label>
        <textarea name="medical_history"></textarea>
    </div>


</div>

<button type="submit">Register</button>

</form>

<div class="login-footer">
    Already have an account? <a href="login.php">Login</a>
</div>

</div>
</div>
</body>
</html>
