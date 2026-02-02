<?php
session_start();

$bill_id = $_GET['bill_id'] ?? null;
$amount  = $_GET['amount'] ?? null;

if (!$bill_id || !$amount) {
    die("Invalid payment request.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>eSewa Payment (Demo)</title>
    <style>
        body { font-family: Arial; background:#f4f6f8; }
        .box {
            width: 400px;
            margin: 100px auto;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            text-align: center;
        }
        button {
            width: 100%;
            padding: 12px;
            margin-top: 10px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
        }
        .success { background: #2ecc71; color: #fff; }
        .fail { background: #e74c3c; color: #fff; }
    </style>
</head>
<body>

<div class="box">
    <h2>eSewa Demo Payment</h2>
    <p><strong>Bill ID:</strong> <?= htmlspecialchars($bill_id) ?></p>
    <p><strong>Amount:</strong> NPR <?= htmlspecialchars($amount) ?></p>

    <form method="POST" action="esewa-success.php">
        <input type="hidden" name="bill_id" value="<?= $bill_id ?>">
        <input type="hidden" name="amount" value="<?= $amount ?>">
        <button class="success">Confirm Payment</button>
    </form>

    <form method="GET" action="esewa-failed.php">
        <button class="fail">Cancel Payment</button>
    </form>
</div>

</body>
</html>
