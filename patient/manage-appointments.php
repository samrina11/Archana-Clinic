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

// Get Patient ID
$stmt = $conn->prepare("SELECT id FROM patients WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    die("Patient record not found.");
}

$patient_id = $patient['id'];

// Handle Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    $cancel_id = (int)$_POST['cancel_id'];
    $c_stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND patient_id = ? AND status = 'pending'");
    $c_stmt->bind_param("ii", $cancel_id, $patient_id);
    $c_stmt->execute();
    header("Location: manage-appointments.php");
    exit;
}

// Pagination, Filtering & Search
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

$query_params = [$patient_id];
$types = "i";
$where_clause = "WHERE a.patient_id = ?";

// Status Filter
if ($status_filter !== 'all' && !empty($status_filter)) {
    $where_clause .= " AND a.status = ?";
    $query_params[] = $status_filter;
    $types .= "s";
}

// Search Filter
if (!empty($search_query)) {
    $where_clause .= " AND (u.name LIKE ? OR d.specialization LIKE ?)";
    $search_term = "%" . $search_query . "%";
    $query_params[] = $search_term;
    $query_params[] = $search_term;
    $types .= "ss";
}

// Count Total Records
$count_sql = "
    SELECT COUNT(*) as total 
    FROM appointments a 
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    $where_clause
";
$stmt = $conn->prepare($count_sql);
$stmt->bind_param($types, ...$query_params);
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Fetch Data
$sql = "
    SELECT a.*, d.specialization, u.name AS doctor_name,
           b.id AS billing_id, b.status AS billing_status, b.amount AS billing_amount
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    LEFT JOIN billing b ON b.appointment_id = a.id
    $where_clause
    ORDER BY a.appointment_datetime DESC
    LIMIT ? OFFSET ?
";

$query_params[] = $limit;
$query_params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$query_params);
$stmt->execute();
$appointments = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - Clinic</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --danger: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
            --esewa: #60bb46;
            --khalti: #5C2D91;
        }

        body.dashboard-page {
            background-color: var(--bg-body);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--text-main);
        }

        .manage-container {
            max-width: 1400px;
            margin: 1.5rem auto;
            padding: 0 1.5rem;
        }

        /* Header Section */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .page-title h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            color: var(--text-main);
        }
        .page-title p {
            color: var(--text-muted);
            margin: 0.25rem 0 0;
            font-size: 0.9rem;
        }

        /* Controls Bar */
        .controls-bar {
            background: white;
            padding: 1rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 250px;
        }
        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }
        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.2s;
            box-sizing: border-box;
            background-color: #f8fafc;
        }
        .search-input:focus { 
            border-color: var(--primary);
            background-color: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .filter-select {
            padding: 0.75rem 2.5rem 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: white;
            color: var(--text-main);
            font-size: 0.95rem;
            cursor: pointer;
            min-width: 160px;
            box-sizing: border-box;
        }
        .filter-select:focus {
            border-color: var(--primary);
            outline: none;
        }

        .btn-new {
            background-color: var(--primary);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.2s;
            white-space: nowrap;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.1);
        }
        .btn-new:hover { background-color: var(--primary-hover); }

        /* Table Design */
        .table-card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .custom-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        .custom-table th {
            background: #f8fafc;
            padding: 1rem 1.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
        }
        .custom-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            color: var(--text-main);
            font-size: 0.9rem;
            vertical-align: middle;
        }
        .custom-table tr:last-child td { border-bottom: none; }
        .custom-table tr:hover { background: #fcfcfc; }

        /* Doctor Profile */
        .doctor-cell { display: flex; align-items: center; gap: 0.75rem; }
        .doc-avatar {
            width: 32px; height: 32px;
            background: #e0e7ff; color: var(--primary);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem;
        }
        .doc-info { line-height: 1.2; }
        .doc-name { font-weight: 600; display: block; font-size: 0.9rem; }
        .doc-spec { font-size: 0.8rem; color: var(--text-muted); }

        /* Status Pills */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.15rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        .status-pending { background: #fffbeb; color: #b45309; border: 1px solid #fcd34d; }
        .status-confirmed { background: #ecfdf5; color: #047857; border: 1px solid #6ee7b7; }
        .status-completed { background: #eff6ff; color: #1e40af; border: 1px solid #93c5fd; }
        .status-cancelled { background: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5; }

        .bill-badge { font-weight: 600; font-size: 0.85rem; display: flex; align-items: center; gap: 4px; }
        .bill-paid { color: var(--success); }
        .bill-unpaid { color: var(--danger); }

        /* Action Buttons */
        .action-group { display: flex; gap: 0.5rem; align-items: center; justify-content: flex-end; }
        
        .btn-action {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s;
        }
        
        /* Pay Button */
        .btn-pay { 
            background: var(--text-main); 
            color: white; 
        }
        .btn-pay:hover { background: #000; transform: translateY(-1px); }

        /* View/Print Button */
        .btn-view { background: white; border-color: var(--border); color: var(--text-muted); }
        .btn-view:hover { border-color: var(--primary); color: var(--primary); }

        /* Edit Button */
        .btn-edit { background: white; border-color: var(--border); color: var(--text-main); }
        .btn-edit:hover { border-color: var(--primary); color: var(--primary); background-color: #eff6ff; }

        /* Cancel Button */
        .btn-cancel { background: white; border-color: var(--border); color: var(--danger); }
        .btn-cancel:hover { background: #fef2f2; border-color: #fecaca; }

        /* Empty State */
        .empty-state { text-align: center; padding: 3rem 1rem; color: var(--text-muted); }
        .empty-icon { font-size: 2.5rem; margin-bottom: 0.75rem; color: #cbd5e1; }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1.25rem;
            background: #f8fafc;
            border-top: 1px solid var(--border);
        }
        .page-info { font-size: 0.85rem; color: var(--text-muted); }
        .page-controls { display: flex; gap: 0.25rem; }
        .page-btn {
            background: white; border: 1px solid var(--border);
            padding: 0.3rem 0.7rem; border-radius: 6px;
            text-decoration: none; color: var(--text-main);
            font-size: 0.85rem;
        }
        .page-btn:hover { background: #f1f5f9; }
        .page-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
        .page-btn.disabled { opacity: 0.5; pointer-events: none; }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.4);
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

        @media (max-width: 1024px) {
            .table-card { overflow-x: auto; }
            .custom-table { min-width: 800px; }
        }
    </style>
</head>
<body class="dashboard-page">
<div class="dashboard-container">
    <?php include __DIR__ . '/../include/sidebar-patient.php'; ?>

    <main class="dashboard-content">
        <?php include __DIR__ . '/../include/nav.php'; ?>

        <div class="manage-container">
            <div class="page-header">
                <div class="page-title">
                    <h1>Appointments</h1>
                    <p>Manage bookings and payments.</p>
                </div>
                <a href="book-appointment.php" class="btn-new">
                    <i class="fas fa-plus"></i> New Appointment
                </a>
            </div>

            <!-- Controls: Search & Filter -->
            <form method="GET" class="controls-bar">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" class="search-input" placeholder="Search doctor or specialization..." value="<?= htmlspecialchars($search_query) ?>">
                </div>
                
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </form>

            <div class="table-card">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Doctor / Specialist</th>
                            <th>Date & Time</th>
                            <th>Payment Status</th>
                            <th>Appt. Status</th>
                            <th style="text-align: right; width: 240px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($appointments->num_rows > 0): ?>
                            <?php while ($row = $appointments->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="doctor-cell">
                                            <div class="doc-avatar"><i class="fas fa-user-md"></i></div>
                                            <div class="doc-info">
                                                <span class="doc-name">Dr. <?= htmlspecialchars($row['doctor_name']) ?></span>
                                                <span class="doc-spec"><?= htmlspecialchars($row['specialization']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500; font-size: 0.9rem;"><?= date('M d, Y', strtotime($row['appointment_datetime'])) ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);"><?= date('h:i A', strtotime($row['appointment_datetime'])) ?></div>
                                    </td>
                                    <td>
                                        <?php if ($row['billing_status'] == 'paid'): ?>
                                            <div class="bill-badge bill-paid">
                                                <i class="fas fa-check-circle"></i> Paid
                                            </div>
                                        <?php elseif ($row['billing_status'] == 'unpaid'): ?>
                                            <div class="bill-badge bill-unpaid">
                                                <i class="fas fa-clock"></i> Due: Rs. <?= number_format($row['billing_amount']) ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.85rem;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($row['status']) ?>">
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-group">
                                            <!-- Payment Actions -->
                                            <?php if ($row['billing_status'] == 'unpaid' && $row['status'] != 'cancelled'): ?>
                                                <button onclick="openPaymentModal(<?= $row['billing_id'] ?>, <?= $row['billing_amount'] ?>, '<?= addslashes($row['doctor_name']) ?>')" class="btn-action btn-pay" title="Pay Bill">
                                                    <i class="fas fa-credit-card"></i>
                                                </button>
                                            <?php endif; ?>

                                            <!-- View / Print Slip -->
                                            <a href="view-appointment.php?id=<?= $row['id'] ?>" class="btn-action btn-view" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>

                                            <!-- Edit Action -->
                                            <?php if ($row['status'] === 'pending'): ?>
                                                <a href="edit-appointment.php?id=<?= $row['id'] ?>" class="btn-action btn-edit" title="Edit Appointment">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>

                                            <!-- Cancel Action -->
                                            <?php if ($row['status'] === 'pending'): ?>
                                                <form method="POST" onsubmit="return confirm('Are you sure you want to cancel?');" style="margin:0;">
                                                    <input type="hidden" name="cancel_id" value="<?= $row['id'] ?>">
                                                    <button type="submit" class="btn-action btn-cancel" title="Cancel Appointment">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <div class="empty-icon"><i class="far fa-calendar-times"></i></div>
                                        <p>No appointments found matching your criteria.</p>
                                        <?php if(!empty($search_query)): ?>
                                            <a href="manage-appointments.php" style="color: var(--primary); font-size: 0.9rem;">Clear Search</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <div class="page-info">
                        Showing page <?= $page ?> of <?= $total_pages ?>
                    </div>
                    <div class="page-controls">
                        <a href="?page=<?= $page - 1 ?>&status=<?= $status_filter ?>&search=<?= urlencode($search_query) ?>" 
                           class="page-btn <?= ($page <= 1) ? 'disabled' : '' ?>">
                           Prev
                        </a>
                        
                        <?php 
                        $range = 2;
                        for($i = max(1, $page - $range); $i <= min($total_pages, $page + $range); $i++): 
                        ?>
                            <a href="?page=<?= $i ?>&status=<?= $status_filter ?>&search=<?= urlencode($search_query) ?>" 
                               class="page-btn <?= $i == $page ? 'active' : '' ?>">
                               <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <a href="?page=<?= $page + 1 ?>&status=<?= $status_filter ?>&search=<?= urlencode($search_query) ?>" 
                           class="page-btn <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                           Next
                        </a>
                    </div>
                </div>
                <?php endif; ?>
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
                <form action="pay-bills.php" method="post" id="form-esewa">
                    <input type="hidden" name="action" value="initiate_esewa">
                    <input id="esewa_billing_id" type="hidden" name="billing_id">
                    <input id="esewa_amount" type="hidden" name="amount">
                    <button type="submit" class="payment-btn btn-esewa">
                        <img src="https://esewa.com.np/common/images/esewa_logo.png" alt="eSewa" style="height: 20px; filter: brightness(0) invert(1);"> 
                        Pay with eSewa
                    </button>
                </form>

                <!-- Khalti Form -->
                <form action="pay-bills.php" method="post" id="form-khalti">
                    <input type="hidden" name="action" value="initiate_khalti">
                    <input id="khalti_billing_id" type="hidden" name="billing_id">
                    <input id="khalti_amount" type="hidden" name="amount">
                    <button type="submit" class="payment-btn btn-khalti">
                        <i class="fas fa-wallet"></i> Pay with Khalti
                    </button>
                </form>

                <!-- Cash Form -->
                <form action="pay-bills.php" method="post" id="form-cash">
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
    // Modal Logic
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
</script>
</body>
</html>