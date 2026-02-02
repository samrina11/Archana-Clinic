<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth($conn);

if (!$auth->isLoggedIn() || $_SESSION['role'] !== 'patient') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// 1. Get Patient Details
$stmt = $conn->prepare("
    SELECT p.id, p.name, p.phone, u.email 
    FROM patients p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.user_id = ?
");

if (!$stmt) {
    die("SQL Error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    die("Patient record not found. Please contact administration.");
}

$patient_id = $patient['id'];
$success_message = '';
$error_message   = '';

// ==========================================
// HANDLE ESEWA PAYMENT INITIATION (V2 API)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'initiate_esewa') {
    $billing_id = (int)$_POST['billing_id'];
    $amount     = (float)$_POST['amount'];
    
    // Generate Transaction UUID
    $transaction_uuid = "ESEWA-" . $billing_id . "-" . time();

    $stmt = $conn->prepare("INSERT INTO payments (billing_id, gateway, transaction_uuid, amount, status) VALUES (?, 'esewa', ?, ?, 'pending')");
    $stmt->bind_param("isd", $billing_id, $transaction_uuid, $amount);
    
    if($stmt->execute()){
        // Sandbox Config for eSewa
        $product_code = "EPAYTEST"; 
        $secret_key   = "8gBm/:&EnhH.1/q"; 
        
        $total_amount = $amount;
        $message = "total_amount={$total_amount},transaction_uuid={$transaction_uuid},product_code={$product_code}";
        $signature = base64_encode(hash_hmac('sha256', $message, $secret_key, true));

        echo '
        <!DOCTYPE html>
        <html>
        <head><title>Redirecting to eSewa...</title></head>
        <body>
            <form id="esewaForm" action="https://rc-epay.esewa.com.np/api/epay/main/v2/form" method="POST">
                <input type="hidden" name="amount" value="'.$amount.'">
                <input type="hidden" name="tax_amount" value="0">
                <input type="hidden" name="total_amount" value="'.$total_amount.'">
                <input type="hidden" name="transaction_uuid" value="'.$transaction_uuid.'">
                <input type="hidden" name="product_code" value="'.$product_code.'">
                <input type="hidden" name="product_service_charge" value="0">
                <input type="hidden" name="product_delivery_charge" value="0">
                <input type="hidden" name="success_url" value="http://localhost/clinic/patient/payment-success.php">
                <input type="hidden" name="failure_url" value="http://localhost/clinic/patient/payment-failed.php">
                <input type="hidden" name="signed_field_names" value="total_amount,transaction_uuid,product_code">
                <input type="hidden" name="signature" value="'.$signature.'">
            </form>
            <script>document.getElementById("esewaForm").submit();</script>
        </body>
        </html>';
        exit;
    }
}

// ==========================================
// HANDLE KHALTI PAYMENT INITIATION (LIVE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'initiate_khalti') {
    $billing_id = (int)$_POST['billing_id'];
    $amount     = (float)$_POST['amount'];
    
    // Khalti accepts amount in Paisa as an INTEGER
    $amount_paisa = (int) round($amount * 100); 
    
    $transaction_uuid = uniqid("KHALTI-");

    $stmt = $conn->prepare("INSERT INTO payments (billing_id, gateway, transaction_uuid, amount, status) VALUES (?, 'khalti', ?, ?, 'pending')");
    $stmt->bind_param("isd", $billing_id, $transaction_uuid, $amount);
    
    if($stmt->execute()){
        $curl = curl_init();
        
        // Prepare robust payload with Fallbacks for empty name/email/phone
        $customer_name = !empty($patient['name']) ? $patient['name'] : "Valued Patient";
        $customer_email = !empty($patient['email']) ? $patient['email'] : "info@clinic.com";
        $customer_phone = !empty($patient['phone']) ? $patient['phone'] : "9800000000";

        $payload = json_encode([
            "return_url" => "http://localhost/clinic/patient/payment-success.php?gateway=khalti",
            "website_url" => "http://localhost/clinic/",
            "amount" => $amount_paisa,
            "purchase_order_id" => $transaction_uuid,
            "purchase_order_name" => "Medical Bill #" . $billing_id,
            "customer_info" => [
                "name" => $customer_name,
                "email" => $customer_email,
                "phone" => $customer_phone
            ]
        ]);

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://dev.khalti.com/api/v2/epayment/initiate/', // LIVE URL
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false, // Fix for Localhost
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Authorization: Key 741aa35dd65a414d95885380aa9f19fc', // YOUR LIVE KEY
                'Content-Type: application/json'
            ],
        ]);

        $response = curl_exec($curl);
        
        if ($response === false) {
             $error_message = 'Connection Error: ' . curl_error($curl);
        } else {
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $res_data = json_decode($response, true);

            if ($http_code == 200 && isset($res_data['pidx'])) {
                $pidx = $res_data['pidx'];
                
                $update = $conn->prepare("UPDATE payments SET gateway_ref_id = ? WHERE transaction_uuid = ?");
                $update->bind_param("ss", $pidx, $transaction_uuid);
                $update->execute();

                header("Location: " . $res_data['payment_url']);
                exit;
            } else {
                $detail = $res_data['detail'] ?? json_encode($res_data);
                $error_message = "Khalti Error ($http_code): " . $detail;
            }
        }
        curl_close($curl);
    } else {
        $error_message = "Database Error: Could not initiate payment.";
    }
}

// ==========================================
// HANDLE CASH PAYMENT
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_cash') {
    $billing_id = (int)$_POST['billing_id'];
    $amount     = (float)$_POST['amount'];

    $stmt = $conn->prepare("UPDATE billing SET status = 'pending', payment_method = 'cash', payment_date = NOW() WHERE id = ? AND patient_id = ?");
    $stmt->bind_param("ii", $billing_id, $patient_id);

    $uuid = uniqid("CASH-");
    $stmt2 = $conn->prepare("INSERT INTO payments (billing_id, gateway, transaction_uuid, amount, status) VALUES (?, 'cash', ?, ?, 'pending')");
    $stmt2->bind_param("isd", $billing_id, $uuid, $amount);

    if ($stmt->execute() && $stmt2->execute()) {
        $success_message = "Cash payment registered. Please pay at the clinic.";
    } else {
        $error_message = "Failed to register cash payment.";
    }
}

// ==========================================
// FETCH BILLS
// ==========================================
$stmt = $conn->prepare("
    SELECT b.id, b.amount, b.status, b.due_date, u.name AS doctor_name, b.created_at
    FROM billing b
    JOIN appointments a ON b.appointment_id = a.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE b.patient_id = ?
    ORDER BY b.created_at DESC
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$bills = $stmt->get_result();

// Calculate Stats
$total_unpaid = 0;
$count_unpaid = 0;
$total_paid = 0;

while($row = $bills->fetch_assoc()) {
    if ($row['status'] == 'unpaid') {
        $total_unpaid += $row['amount'];
        $count_unpaid++;
    } elseif ($row['status'] == 'paid') {
        $total_paid += $row['amount'];
    }
}
$bills->data_seek(0); // Reset pointer for display
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing & Payments - Clinic</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg-body: #f1f5f9;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --esewa: #60bb46;
            --khalti: #5C2D91;
        }

        body.dashboard-page {
            background-color: var(--bg-body);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--text-main);
        }

        .billing-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }
        .page-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            color: var(--text-main);
        }
        .page-header p {
            color: var(--text-muted);
            margin: 0.25rem 0 0 0;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-card);
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-info h3 { margin: 0; font-size: 0.875rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; }
        .stat-info .value { font-size: 1.75rem; font-weight: 700; color: var(--text-main); margin-top: 0.5rem; display: block; }
        .stat-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
        
        .stat-icon.blue { background: #eff6ff; color: var(--primary); }
        .stat-icon.red { background: #fef2f2; color: var(--danger); }
        .stat-icon.green { background: #ecfdf5; color: var(--success); }

        /* Controls */
        .table-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .search-wrapper { position: relative; max-width: 300px; width: 100%; }
        .search-wrapper i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .search-input { width: 100%; padding: 10px 12px 10px 36px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.95rem; outline: none; transition: border-color 0.2s; }
        .search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }

        /* Table */
        .table-container {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .custom-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .custom-table th {
            background-color: #f8fafc;
            padding: 1rem 1.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
        }

        .custom-table td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            color: var(--text-main);
            font-size: 0.95rem;
            vertical-align: middle;
        }

        .custom-table tr:last-child td { border-bottom: none; }
        .custom-table tr:hover { background-color: #f8fafc; }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: capitalize;
        }
        .status-unpaid { background-color: #fef2f2; color: #b91c1c; }
        .status-paid { background-color: #ecfdf5; color: #047857; }
        .status-pending { background-color: #fffbeb; color: #b45309; }

        /* Buttons */
        .btn-pay {
            background-color: var(--text-main);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-pay:hover { background-color: #000; transform: translateY(-1px); }
        
        .btn-view { color: var(--text-muted); background: none; border: 1px solid var(--border); padding: 0.4rem 0.8rem; border-radius: 6px; cursor: default; opacity: 0.6; font-size: 0.875rem; }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.4); /* Darker backdrop */
            backdrop-filter: blur(4px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 1rem;
        }

        .modal-content {
            background: white;
            width: 100%;
            max-width: 450px;
            border-radius: 16px;
            padding: 0;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            animation: slideUp 0.3s ease-out;
            overflow: hidden;
        }

        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; font-size: 1.125rem; font-weight: 600; color: var(--text-main); }
        .close-modal { font-size: 1.5rem; color: var(--text-muted); cursor: pointer; border: none; background: none; transition: color 0.2s; }
        .close-modal:hover { color: var(--danger); }

        .modal-body { padding: 1.5rem; }
        
        .invoice-summary { background: #f8fafc; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; border: 1px solid var(--border); }
        .summary-label { font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0.25rem; }
        .summary-amount { font-size: 1.5rem; font-weight: 700; color: var(--text-main); }
        
        .payment-methods-grid { display: grid; gap: 0.75rem; }
        .payment-btn { 
            width: 100%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 0.75rem; 
            padding: 0.875rem; 
            border: none; 
            border-radius: 8px; 
            font-weight: 600; 
            font-size: 1rem; 
            cursor: pointer; 
            transition: transform 0.1s, opacity 0.2s; 
            color: white; 
        }
        .payment-btn:hover { opacity: 0.9; transform: translateY(-1px); }
        .payment-btn:active { transform: scale(0.98); }
        
        .btn-esewa { background-color: var(--esewa); }
        .btn-khalti { background-color: var(--khalti); }
        .btn-cash { background-color: var(--text-main); }

        /* Mobile */
        @media (max-width: 768px) {
            .table-container { overflow-x: auto; }
            .custom-table th, .custom-table td { white-space: nowrap; }
        }
    </style>
</head>

<body class="dashboard-page">
<div class="dashboard-container">
    <?php include __DIR__ . '/../include/sidebar-patient.php'; ?>

    <main class="dashboard-content">
        <?php include __DIR__ . '/../include/nav.php'; ?>

        <div class="billing-container">
            <div class="page-header">
                <h2>Financial Overview</h2>
                <p>Manage your invoices and payments securely.</p>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success" style="padding: 1rem; background: #ecfdf5; color: #047857; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #a7f3d0; display:flex; align-items:center; gap: 0.5rem;">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger" style="padding: 1rem; background: #fef2f2; color: #b91c1c; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #fecaca; display:flex; align-items:center; gap: 0.5rem;">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Outstanding</h3>
                        <span class="value">Rs. <?= number_format($total_unpaid, 2) ?></span>
                    </div>
                    <div class="stat-icon red"><i class="fas fa-file-invoice-dollar"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Total Paid</h3>
                        <span class="value">Rs. <?= number_format($total_paid, 2) ?></span>
                    </div>
                    <div class="stat-icon green"><i class="fas fa-check-double"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Open Invoices</h3>
                        <span class="value"><?= $count_unpaid ?></span>
                    </div>
                    <div class="stat-icon blue"><i class="fas fa-bell"></i></div>
                </div>
            </div>

            <!-- Controls -->
            <div class="table-controls">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" class="search-input" placeholder="Search invoices, doctor..." onkeyup="filterTable()">
                </div>
                <div class="filters">
                    <select id="statusFilter" onchange="filterTable()" style="padding: 10px; border-radius: 8px; border: 1px solid var(--border); outline:none;">
                        <option value="all">All Status</option>
                        <option value="unpaid">Unpaid</option>
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="table-container">
                <table class="custom-table" id="billsTable">
                    <thead>
                        <tr>
                            <th>Invoice ID</th>
                            <th>Date</th>
                            <th>Doctor</th>
                            <th style="text-align: right;">Amount</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($bills->num_rows > 0):
                            while ($b = $bills->fetch_assoc()): 
                                switch ($b['status']) {
                                    case 'paid':
                                        $statusClass = 'status-paid';
                                        break;
                                    case 'unpaid':
                                        $statusClass = 'status-unpaid';
                                        break;
                                    default:
                                        $statusClass = 'status-pending';
                                }
                        ?>
                        <tr data-status="<?= strtolower($b['status']) ?>">
                            <td style="font-family: monospace; font-weight: 600; color: var(--text-muted);">#<?= str_pad($b['id'], 5, '0', STR_PAD_LEFT) ?></td>
                            <td>
                                <div style="font-weight: 500;"><?= date('M d, Y', strtotime($b['due_date'])) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= date('h:i A', strtotime($b['created_at'])) ?></div>
                            </td>
                            <td>
                                <div style="display:flex; align-items:center; gap: 8px;">
                                    <div style="width:32px; height:32px; background:#eff6ff; border-radius:50%; display:flex; align-items:center; justify-content:center; color:var(--primary); font-size:0.8rem;"><i class="fas fa-user-md"></i></div>
                                    <?= htmlspecialchars($b['doctor_name']) ?>
                                </div>
                            </td>
                            <td style="text-align: right; font-weight: 600;">Rs. <?= number_format($b['amount'], 2) ?></td>
                            <td><span class="status-badge <?= $statusClass ?>"><?= ucfirst($b['status']) ?></span></td>
                            <td style="text-align: right;">
                                <?php if ($b['status'] === 'unpaid'): ?>
                                    <button class="btn-pay" onclick="openPaymentModal(<?= $b['id'] ?>, <?= $b['amount'] ?>, '<?= addslashes($b['doctor_name']) ?>')">
                                        Pay Now <i class="fas fa-arrow-right" style="font-size: 0.7rem;"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn-view" disabled>
                                        Completed
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                            endwhile; 
                        else:
                        ?>
                            <tr><td colspan="6" style="text-align:center; padding: 3rem; color: var(--text-muted);">No invoices found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- PROFESSIONAL PAYMENT MODAL -->
<div class="modal-overlay" id="paymentModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Complete Payment</h3>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>

        <div class="modal-body">
            <div class="invoice-summary">
                <div>
                    <div class="summary-label">Invoice #<span id="modal_invoice_id">--</span></div>
                    <div style="font-size: 0.9rem; font-weight: 500; color: var(--text-main);" id="modal_doctor_name">Dr. --</div>
                </div>
                <div style="text-align: right;">
                    <div class="summary-label">Total Amount</div>
                    <div class="summary-amount">Rs. <span id="modal_amount_display">0.00</span></div>
                </div>
            </div>

            <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1rem; font-weight: 500;">Select Payment Method</p>

            <div class="payment-methods-grid">
                <!-- eSewa Form -->
                <form method="post" id="form-esewa">
                    <input type="hidden" name="action" value="initiate_esewa">
                    <input id="esewa_billing_id" type="hidden" name="billing_id">
                    <input id="esewa_amount" type="hidden" name="amount">
                    <button type="submit" class="payment-btn btn-esewa">
                        <img src="https://esewa.com.np/common/images/esewa_logo.png" alt="eSewa" style="height: 20px; filter: brightness(0) invert(1);"> 
                        Pay with eSewa
                    </button>
                </form>

                <!-- Khalti Form -->
                <form method="post" id="form-khalti">
                    <input type="hidden" name="action" value="initiate_khalti">
                    <input id="khalti_billing_id" type="hidden" name="billing_id">
                    <input id="khalti_amount" type="hidden" name="amount">
                    <button type="submit" class="payment-btn btn-khalti">
                        <i class="fas fa-wallet"></i> Pay with Khalti
                    </button>
                </form>

                <!-- Cash Form -->
                <form method="post" id="form-cash">
                    <input type="hidden" name="action" value="confirm_cash">
                    <input id="cash_billing_id" type="hidden" name="billing_id">
                    <input id="cash_amount" type="hidden" name="amount">
                    <button type="submit" class="payment-btn btn-cash">
                        <i class="fas fa-money-bill-wave"></i> Pay Cash at Counter
                    </button>
                </form>
            </div>
            
            <div style="text-align: center; margin-top: 1.5rem; font-size: 0.75rem; color: var(--text-muted);">
                <i class="fas fa-lock"></i> Payments are secure and encrypted.
            </div>
        </div>
    </div>
</div>

<script>
    // 1. Modal Logic
    const modal = document.getElementById('paymentModal');

    function openPaymentModal(id, amount, doctor) {
        // UI Updates
        document.getElementById('modal_invoice_id').textContent = id.toString().padStart(5, '0');
        document.getElementById('modal_amount_display').textContent = amount.toFixed(2);
        document.getElementById('modal_doctor_name').textContent = doctor;

        // Form Updates
        document.getElementById('esewa_billing_id').value = id;
        document.getElementById('esewa_amount').value = amount;
        
        document.getElementById('khalti_billing_id').value = id;
        document.getElementById('khalti_amount').value = amount;

        document.getElementById('cash_billing_id').value = id;
        document.getElementById('cash_amount').value = amount;

        // Show Modal
        modal.style.display = 'flex';
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    // Close on outside click
    window.onclick = function(event) {
        if (event.target == modal) {
            closeModal();
        }
    }

    // 2. Search & Filter Logic
    function filterTable() {
        const searchText = document.getElementById('searchInput').value.toLowerCase();
        const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
        const rows = document.querySelectorAll('#billsTable tbody tr');

        rows.forEach(row => {
            if(row.children.length < 2) return; 

            const textContent = row.innerText.toLowerCase();
            const status = row.getAttribute('data-status');
            
            const matchesSearch = textContent.includes(searchText);
            const matchesStatus = (statusFilter === 'all') || (status === statusFilter);

            row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
        });
    }

    // 3. Sort Logic (Date Default)
    function sortTable(criteria) {
        // Logic handled by SQL 'ORDER BY' initially, client side sort optional
    }
</script>

</body>
</html>