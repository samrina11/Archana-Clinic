<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// eSewa Merchant Code
define('ESEWA_MERCHANT_CODE', 'EPAYTEST123'); // replace with your sandbox/live merchant code

// Get POST variables from eSewa
$amt = $_POST['amt'] ?? 0;
$oid = $_POST['pid'] ?? 0;    // Your billing/invoice ID
$refId = $_POST['refId'] ?? ''; // eSewa reference ID

if (!$amt || !$oid || !$refId) {
    header("Location: billing.php?error=Invalid payment data!");
    exit;
}

// Verify payment with eSewa server
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://uat.esewa.com.np/epay/transrec", // sandbox URL
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => [
        'amt' => $amt,
        'scd' => ESEWA_MERCHANT_CODE,
        'rid' => $refId,
        'pid' => $oid,
    ],
]);

$response = curl_exec($curl);
curl_close($curl);

// Check if verification is successful
if (strpos($response, "Success") !== false) {
    // Update billing record as paid
    $stmt = $conn->prepare("UPDATE billing SET status='paid', payment_method='eSewa', payment_date=NOW() WHERE id=?");
    $stmt->bind_param("i", $oid);
    $stmt->execute();

    header("Location: billing.php?success=Payment successful!");
    exit;
} else {
    header("Location: billing.php?error=Payment verification failed!");
    exit;
}
