<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';
if (!$auth->isLoggedIn() || $auth->getUser()['role'] !== 'admin') {
    header("Location: ../login.php"); exit;
}
$error=''; $success='';
if ($_SERVER['REQUEST_METHOD']==='POST'){
    $name=trim($_POST['name']??''); $email=trim($_POST['email']??''); $password=$_POST['password']??''; $phone=trim($_POST['phone']??'');
    if(empty($name||$email||$password||$phone)){$error="Please fill all fields";}
    else{
        $hashedPassword=password_hash($password,PASSWORD_DEFAULT);
        if($auth->register($name,$email,$hashedPassword,'doctor')){
            header("Location: ../admin/dashboard.php"); exit;
        }else{$error="Email already exists";}
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register Doctor</title>
<style>
body{font-family:Arial,sans-serif;background:#f4f6f8;}
.login-container{max-width:500px;margin:40px auto;}
.login-box{background:#fff;padding:40px;border-radius:10px;box-shadow:0 10px 25px rgba(0,0,0,0.1);}
.form-group{margin-bottom:15px;} label{display:block;margin-bottom:6px;font-weight:600;}
input{width:100%;height:35px;padding:5px 10px;border:2px solid #ddd;border-radius:6px;box-sizing:border-box;}
button{width:100%;padding:12px;background:linear-gradient(135deg,#0c74a6,#5bbbe0);border:none;color:#fff;border-radius:6px;cursor:pointer;}
button:hover{opacity:0.9;}
.error-message{background:#ffd6d6;padding:10px;color:#a10000;margin-bottom:15px;}
</style>
</head>
<body>
<div class="login-container"><div class="login-box">
<h2>Register Doctor</h2>
<?php if($error):?><div class="error-message"><?= $error ?></div><?php endif;?>
<form method="POST">
<div class="form-group"><label>Name</label><input type="text" name="name" required></div>
<div class="form-group"><label>Email</label><input type="email" name="email" required></div>
<div class="form-group"><label>Password</label><input type="password" name="password" id="password" required placeholder="Create password"></div>
<div class="form-group"><label>Phone</label><input type="text" name="phone"></div>
<button type="submit">Register</button>
</form>
</div></div>
<script>
function togglePassword(){const p=document.getElementById('password');p.type=p.type==='password'?'text':'password';}
</script>
</body>
</html>
