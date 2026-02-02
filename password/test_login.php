<?php
session_start();
require_once './config/database.php';
require_once './config/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Try login using your Auth class
    if ($auth->login($email, $password)) {
        echo "✅ Login successful for $email<br>";
        echo "Role: " . $_SESSION['role'] . "<br>";
    } else {
        echo "❌ Login failed for $email<br>";

        // Debug: fetch the stored hash directly
        $stmt = $conn->prepare("SELECT password FROM users WHERE email = ? UNION SELECT password FROM patients WHERE email = ?");
        $stmt->bind_param("ss", $email, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $storedHash = $row['password'];
            echo "Stored hash: " . htmlspecialchars($storedHash) . "<br>";
            echo "Verify result: " . (password_verify($password, $storedHash) ? 'true' : 'false') . "<br>";
        } else {
            echo "No account found with that email.<br>";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test Login</title>
</head>
<body>
    <h1>Test Login</h1>
    <form method="POST">
        <label>Email:</label>
        <input type="email" name="email" required><br><br>
        <label>Password:</label>
        <input type="password" name="password" required><br><br>
        <button type="submit">Test Login</button>
    </form>
</body>
</html>