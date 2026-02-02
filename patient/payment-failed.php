<?php
session_start();

// If not logged in, redirect to login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Optional: Capture specific error messages from URL if provided by gateway
// eSewa might send ?q=fu
$error_msg = "Your transaction was cancelled or could not be processed.";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed</title>
    <style>
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: #f3f4f6; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
        }
        .card { 
            background: white; 
            padding: 40px; 
            border-radius: 16px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.1); 
            text-align: center; 
            max-width: 400px; 
            width: 100%; 
        }
        .icon { 
            font-size: 60px; 
            margin-bottom: 20px; 
            color: #ef4444; 
            line-height: 1;
        }
        h2 { 
            margin: 10px 0; 
            color: #1f2937; 
        }
        p { 
            color: #6b7280; 
            margin-bottom: 30px; 
            line-height: 1.5;
        }
        .btn { 
            background: #0c74a6; 
            color: white; 
            text-decoration: none; 
            padding: 12px 30px; 
            border-radius: 8px; 
            font-weight: 600; 
            display: inline-block; 
            transition: 0.2s; 
        }
        .btn:hover { 
            background: #095c85; 
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✕</div>
        <h2>Payment Failed</h2>
        <p><?= htmlspecialchars($error_msg) ?></p>
        <a href="pay-bills.php" class="btn">Try Again</a>
    </div>
</body>
</html>