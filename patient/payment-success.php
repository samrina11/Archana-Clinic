<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// If not logged in, redirect to login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// 1. Auto-Detect Gateway
$gateway = $_GET['gateway'] ?? '';

// If gateway param is missing, detect based on response params
if (empty($gateway)) {
    if (isset($_GET['data'])) {
        $gateway = 'esewa';
    } elseif (isset($_GET['pidx'])) {
        $gateway = 'khalti';
    }
}

$status  = 'failed';
$msg     = 'Payment verification failed.';
$billing_id = 0;

// =====================================
// 2. KHALTI VERIFICATION
// =====================================
if ($gateway === 'khalti' && isset($_GET['pidx'])) {
    $pidx = $_GET['pidx'];
    
    // Call Khalti Lookup API
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://khalti.com/api/v2/epayment/lookup/', // LIVE URL
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['pidx' => $pidx]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Key 741aa35dd65a414d95885380aa9f19fc', // YOUR LIVE KEY
            'Content-Type: application/json'
        ],
    ]);
    
    $response = curl_exec($curl);
    curl_close($curl);
    $data = json_decode($response, true);
    
    if (isset($data['status']) && $data['status'] === 'Completed') {
        $status = 'success';
        $transaction_uuid = $data['purchase_order_id']; // This corresponds to our local DB UUID
        $amount = $data['total_amount'] / 100; // Convert Paisa to Rs
        
        // Find Billing ID from payments table
        $stmt = $conn->prepare("SELECT billing_id FROM payments WHERE transaction_uuid = ?");
        $stmt->bind_param("s", $transaction_uuid);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $billing_id = $res['billing_id'] ?? 0;

        // Update Payment Record
        $update = $conn->prepare("UPDATE payments SET status = 'completed', raw_response = ? WHERE transaction_uuid = ?");
        $update->bind_param("ss", $response, $transaction_uuid);
        $update->execute();
    } else {
        $msg = "Khalti Payment Failed or Pending.";
    }

// =====================================
// 3. ESEWA VERIFICATION (V2)
// =====================================
} elseif ($gateway === 'esewa' && isset($_GET['data'])) {
    // eSewa sends a base64 encoded JSON in 'data' query param
    $encoded_data = $_GET['data'];
    $json_data = base64_decode($encoded_data);
    $data = json_decode($json_data, true);

    if (isset($data['status']) && $data['status'] === 'COMPLETE') {
        
        $status = 'success';
        $transaction_uuid = $data['transaction_uuid'];
        $amount = $data['total_amount'];
        $amount = str_replace(",", "", $amount); // Remove commas if any

        // Find Billing ID
        $stmt = $conn->prepare("SELECT billing_id FROM payments WHERE transaction_uuid = ?");
        $stmt->bind_param("s", $transaction_uuid);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $billing_id = $res['billing_id'] ?? 0;

        // Update Payment Record
        $update = $conn->prepare("UPDATE payments SET status = 'completed', raw_response = ? WHERE transaction_uuid = ?");
        $update->bind_param("ss", $json_data, $transaction_uuid);
        $update->execute();

    } else {
        $msg = "eSewa Payment was declined or failed.";
    }
}

// =====================================
// 4. FINAL DATABASE UPDATE (Common)
// =====================================
if ($status === 'success' && $billing_id > 0) {
    // Update Main Billing Table
    $bill_upd = $conn->prepare("UPDATE billing SET status = 'paid', payment_method = ?, payment_date = NOW(), payment_status = 'completed' WHERE id = ?");
    $bill_upd->bind_param("si", $gateway, $billing_id);
    $bill_upd->execute();
    
    // Update Appointment Status
    $appt_upd = $conn->prepare("
        UPDATE appointments a 
        JOIN billing b ON b.appointment_id = a.id 
        SET a.status = 'confirmed' 
        WHERE b.id = ?
    ");
    $appt_upd->bind_param("i", $billing_id);
    $appt_upd->execute();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f3f4f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); text-align: center; max-width: 400px; width: 100%; }
        .icon { font-size: 60px; margin-bottom: 20px; }
        .success { color: #10b981; }
        .failed { color: #ef4444; }
        h2 { margin: 10px 0; color: #1f2937; }
        p { color: #6b7280; margin-bottom: 30px; }
        .btn { background: #0c74a6; color: white; text-decoration: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; display: inline-block; transition: 0.2s; }
        .btn:hover { background: #095c85; }
    </style>
</head>
<body>
    <div class="card">
        <?php if ($status === 'success'): ?>
            <div class="icon success">✓</div>
            <h2>Payment Successful!</h2>
            <p>Your payment has been verified and your appointment is confirmed.</p>
            <a href="manage-appointments.php" class="btn">View Appointments</a>
        <?php else: ?>
            <div class="icon failed">✕</div>
            <h2>Payment Failed</h2>
            <p><?= htmlspecialchars($msg) ?></p>
            <a href="pay-bills.php" class="btn">Try Again</a>
        <?php endif; ?>
    </div>
</body>
</html>