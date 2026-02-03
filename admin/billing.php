<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth($conn);

// Only admin access
if (!$auth->isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

$success = '';
$error = '';

// =====================
// HANDLE POST ACTIONS
// =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Mark Paid (Cash)
    if (isset($_POST['mark_paid_id'])) {
        $id = intval($_POST['mark_paid_id']);
        $stmt = $conn->prepare("UPDATE billing SET status='paid', payment_method='Cash' WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $success = "Invoice marked as paid (Cash).";
    }

    // Add / Update Billing
    else {
        $id = $_POST['id'] ?? '';
        $patient_id = $_POST['patient_id'];
        $appointment_id = $_POST['appointment_id'];
        $amount = $_POST['amount'];
        $status = $_POST['status'];
        $payment_method = $_POST['payment_method'];
        $due_date = $_POST['due_date'] ?: null;
        $notes = $_POST['notes'];

        if ($id) {
            $stmt = $conn->prepare("
                UPDATE billing 
                SET patient_id=?, appointment_id=?, amount=?, status=?, payment_method=?, due_date=?, notes=? 
                WHERE id=?
            ");
            $stmt->bind_param("iidssssi",
                $patient_id, $appointment_id, $amount,
                $status, $payment_method, $due_date, $notes, $id
            );
            $stmt->execute();
            $success = "Invoice updated successfully.";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO billing (patient_id, appointment_id, amount, status, payment_method, due_date, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iidssss",
                $patient_id, $appointment_id, $amount,
                $status, $payment_method, $due_date, $notes
            );
            $stmt->execute();
            $success = "New invoice created successfully.";
        }
    }
}

// =====================
// DELETE BILLING
// =====================
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM billing WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $success = "Invoice deleted successfully.";
}

// =====================
// PAGINATION & SEARCH
// =====================
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');

$where = '';
$params = [];
$types = '';

if ($search) {
    $where = "WHERE (b.id LIKE ? OR u_patient.name LIKE ? OR u_doctor.name LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like];
    $types = "sss";
}

// =====================
// COUNT QUERY (FIXED)
// =====================
$count_sql = "
    SELECT COUNT(*) total
    FROM billing b
    JOIN patients p ON b.patient_id = p.id
    JOIN users u_patient ON p.user_id = u_patient.id
    JOIN appointments a ON b.appointment_id = a.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u_doctor ON d.user_id = u_doctor.id
    $where
";

$stmt = $conn->prepare($count_sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total_rows = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// =====================
// MAIN QUERY (FIXED)
// =====================
$sql = "
    SELECT 
        b.*, 
        p.id AS patient_id,
        u_patient.name AS patient_name,
        u_doctor.name AS doctor_name,
        a.appointment_datetime
    FROM billing b
    JOIN patients p ON b.patient_id = p.id
    JOIN users u_patient ON p.user_id = u_patient.id
    JOIN appointments a ON b.appointment_id = a.id
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u_doctor ON d.user_id = u_doctor.id
    $where
    ORDER BY b.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$billing_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// =====================
// STATS
// =====================
$stats = $conn->query("
    SELECT 
        COUNT(*) total_bills,
        SUM(amount) total_amount,
        SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) paid_amount,
        SUM(CASE WHEN status='unpaid' THEN amount ELSE 0 END) unpaid_amount,
        SUM(CASE WHEN status='partial' THEN amount ELSE 0 END) partial_amount
    FROM billing
")->fetch_assoc();

// =====================
// DROPDOWNS
// =====================
$patients = $conn->query("
    SELECT p.id, u.name 
    FROM patients p 
    JOIN users u ON p.user_id = u.id
    ORDER BY u.name
");

$appointments = $conn->query("
    SELECT a.id, a.appointment_datetime, u.name doctor_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    ORDER BY a.appointment_datetime DESC
");
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Billing Management - Admin</title>
<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    :root {
        --primary: #2563eb;
        --primary-dark: #1d4ed8;
        --bg-body: #f8fafc;
        --bg-card: #ffffff;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border: #e2e8f0;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
    }

    body.dashboard-page {
        background-color: var(--bg-body);
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        color: var(--text-main);
    }

    .manage-container {
        max-width: 1400px;
        margin: 2rem auto;
        padding: 0 1.5rem;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }
    .page-header h1 { margin: 0; font-size: 1.75rem; font-weight: 700; color: var(--text-main); }
    .page-header p { margin: 0.25rem 0 0; color: var(--text-muted); }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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
        gap: 1rem;
    }
    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    .stat-content h3 { margin: 0; font-size: 0.875rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; }
    .stat-content .value { font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin-top: 0.25rem; display: block; }

    .icon-blue { background: #eff6ff; color: var(--primary); }
    .icon-green { background: #ecfdf5; color: var(--success); }
    .icon-red { background: #fef2f2; color: var(--danger); }
    .icon-yellow { background: #fffbeb; color: var(--warning); }

    /* Main Card */
    .card {
        background: var(--bg-card);
        border-radius: 12px;
        border: 1px solid var(--border);
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .card-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--border);
        background: #fdfdfd;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .card-header h2 { margin: 0; font-size: 1.1rem; font-weight: 600; color: var(--text-main); }

    .btn-primary {
        background: var(--primary);
        color: white;
        border: none;
        padding: 0.6rem 1.2rem;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        font-size: 0.9rem;
    }
    .btn-primary:hover { background: var(--primary-dark); }

    /* Filter/Search Bar */
    .filter-bar {
        display: flex;
        gap: 0.5rem;
    }
    .search-input {
        padding: 0.5rem 1rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.9rem;
        width: 250px;
    }
    .btn-search {
        background: white;
        border: 1px solid var(--border);
        color: var(--text-main);
        padding: 0.5rem 1rem;
        border-radius: 8px;
        cursor: pointer;
    }
    .btn-search:hover { background: #f8fafc; border-color: var(--primary); }

    /* Table */
    .table-responsive { overflow-x: auto; }
    .custom-table { width: 100%; border-collapse: collapse; text-align: left; }
    .custom-table th {
        background: #f8fafc;
        padding: 0.875rem 1.25rem;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        border-bottom: 1px solid var(--border);
    }
    .custom-table td {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--border);
        color: var(--text-main);
        font-size: 0.9rem;
    }
    .custom-table tr:last-child td { border-bottom: none; }
    .custom-table tr:hover { background: #fcfcfc; }

    /* Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: capitalize;
    }
    .status-paid { background: #ecfdf5; color: #047857; border: 1px solid #6ee7b7; }
    .status-unpaid { background: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5; }
    .status-partial { background: #fffbeb; color: #b45309; border: 1px solid #fcd34d; }

    /* Action Buttons */
    .action-group { display: flex; gap: 0.5rem; }
    .btn-action {
        width: 32px; height: 32px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 6px;
        border: 1px solid var(--border);
        background: white;
        color: var(--text-muted);
        transition: all 0.2s;
        cursor: pointer;
    }
    .btn-edit:hover { border-color: var(--primary); color: var(--primary); background: #eff6ff; }
    .btn-delete:hover { border-color: var(--danger); color: var(--danger); background: #fef2f2; }
    .btn-cash:hover { border-color: var(--success); color: var(--success); background: #ecfdf5; }

    /* Pagination */
    .pagination {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f8fafc;
    }
    .page-info { font-size: 0.85rem; color: var(--text-muted); }
    .page-links { display: flex; gap: 0.25rem; }
    .page-link {
        padding: 0.35rem 0.75rem;
        border: 1px solid var(--border);
        background: white;
        color: var(--text-main);
        text-decoration: none;
        border-radius: 6px;
        font-size: 0.85rem;
        transition: all 0.2s;
    }
    .page-link:hover { background: #f1f5f9; }
    .page-link.active { background: var(--primary); color: white; border-color: var(--primary); }
    .page-link.disabled { opacity: 0.5; pointer-events: none; }

    /* Modal */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(15, 23, 42, 0.5);
        backdrop-filter: blur(4px);
        z-index: 50;
        justify-content: center;
        align-items: center;
    }
    .modal-card {
        background: white;
        width: 100%;
        max-width: 550px;
        border-radius: 16px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        animation: slideUp 0.3s ease-out;
    }
    @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

    .modal-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex; justify-content: space-between; align-items: center;
        background: #f8fafc;
    }
    .modal-header h3 { margin: 0; font-size: 1.1rem; color: var(--text-main); }
    .close-modal { background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer; }
    
    .modal-body { padding: 1.5rem; }

    /* Forms in Modal */
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .form-group { margin-bottom: 1rem; }
    .col-span-2 { grid-column: span 2; }
    
    .form-group label { display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500; color: var(--text-main); }
    .form-control {
        width: 100%;
        padding: 0.65rem 1rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.95rem;
        transition: border-color 0.2s;
        box-sizing: border-box;
        background: #fff;
    }
    .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }

    .btn-submit {
        width: 100%;
        background: var(--primary);
        color: white;
        border: none;
        padding: 0.75rem;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }
    .btn-submit:hover { background: var(--primary-dark); }

    /* Alerts */
    .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; }
    .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

    @media (max-width: 768px) {
        .form-grid { grid-template-columns: 1fr; }
        .col-span-2 { grid-column: span 1; }
    }
</style>
</head>
<body class="dashboard-page">
<div class="dashboard-container">
    <?php include __DIR__ . '/../include/sidebar-admin.php'; ?>
    
    <main class="dashboard-content">
        <?php include __DIR__ . '/../include/anav.php'; ?>
        
        <div class="manage-container">
            <div class="page-header">
                <div>
                    <h1>Billing & Invoices</h1>
                    <p>Track payments and manage financial records.</p>
                </div>
                <button class="btn-primary" id="addBillingBtn">
                    <i class="fas fa-plus"></i> New Invoice
                </button>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon icon-blue"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div class="stat-content">
                        <h3>Total Bills</h3>
                        <span class="value"><?= $stats['total_bills'] ?? 0 ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-green"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-content">
                        <h3>Paid</h3>
                        <span class="value">Rs. <?= number_format($stats['paid_amount'] ?? 0, 2) ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-red"><i class="fas fa-clock"></i></div>
                    <div class="stat-content">
                        <h3>Unpaid</h3>
                        <span class="value">Rs. <?= number_format($stats['unpaid_amount'] ?? 0, 2) ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-yellow"><i class="fas fa-adjust"></i></div>
                    <div class="stat-content">
                        <h3>Partial</h3>
                        <span class="value">Rs. <?= number_format($stats['partial_amount'] ?? 0, 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- Billing Table -->
            <div class="card">
                <div class="card-header">
                    <h2>Recent Transactions</h2>
                    <form method="GET" class="filter-bar">
                        <input type="text" name="search" class="search-input" placeholder="Search ID, Patient, Doctor..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn-search"><i class="fas fa-search"></i></button>
                        <?php if(!empty($search)): ?>
                            <a href="billing.php" class="btn-search" style="text-decoration:none;"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Invoice ID</th>
                                <th>Patient</th>
                                <th>Appointment</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Method</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($billing_records) > 0): foreach($billing_records as $bill): ?>
                            <tr>
                                <td style="font-family:monospace; font-weight:600;">#<?= str_pad($bill['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                <td><?= htmlspecialchars($bill['patient_name']) ?></td>
                                <td>
                                    <div style="font-size:0.9rem; font-weight:500;"><?= htmlspecialchars($bill['doctor_name']) ?></div>
                                    <div style="font-size:0.8rem; color:var(--text-muted);"><?= date('M d, Y', strtotime($bill['appointment_datetime'])) ?></div>
                                </td>
                                <td style="font-weight:600;">Rs. <?= number_format($bill['amount'], 2) ?></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($bill['status']) ?>">
                                        <?= ucfirst($bill['status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($bill['payment_method'] ?: '-') ?></td>
                                <td>
                                    <div class="action-group">
                                        <?php if($bill['status'] !== 'paid'): ?>
                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="mark_paid_id" value="<?= $bill['id'] ?>">
                                            <button type="submit" class="btn-action btn-cash" title="Mark Paid (Cash)" onclick="return confirm('Confirm cash payment received for Invoice #<?= $bill['id'] ?>?')">
                                                <i class="fas fa-money-bill-wave"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>

                                        <button class="btn-action btn-edit" 
                                            data-id="<?= $bill['id'] ?>"
                                            data-patient_id="<?= $bill['patient_id'] ?>"
                                            data-appointment_id="<?= $bill['appointment_id'] ?>"
                                            data-amount="<?= $bill['amount'] ?>"
                                            data-status="<?= $bill['status'] ?>"
                                            data-payment_method="<?= $bill['payment_method'] ?>"
                                            data-due_date="<?= $bill['due_date'] ?>"
                                            data-notes="<?= htmlspecialchars($bill['notes']) ?>"
                                            title="Edit Bill">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <a href="?delete_id=<?= $bill['id'] ?>" class="btn-action btn-delete" 
                                           onclick="return confirm('Are you sure you want to delete this billing record?')"
                                           title="Delete Bill">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">No billing records found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <div class="page-info">
                        Showing page <?= $page ?> of <?= $total_pages ?> (Total <?= $total_rows ?> records)
                    </div>
                    <div class="page-links">
                        <a href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search) ?>" class="page-link <?= $page <= 1 ? 'disabled' : '' ?>">Prev</a>
                        
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        
                        <a href="?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search) ?>" class="page-link <?= $page >= $total_pages ? 'disabled' : '' ?>">Next</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Add/Edit Billing Modal -->
<div class="modal-overlay" id="billingModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="modalTitle">Add New Invoice</h3>
            <button class="close-modal" id="closeModal">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="billingForm">
                <input type="hidden" name="id" id="billing-id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Patient</label>
                        <select name="patient_id" id="patient_id" class="form-control" required>
                            <option value="">Select Patient</option>
                            <?php while($p = $patients->fetch_assoc()): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Appointment Context</label>
                        <select name="appointment_id" id="appointment_id" class="form-control" required>
                            <option value="">Select Appointment</option>
                            <?php while($a = $appointments->fetch_assoc()): ?>
                                <option value="<?= $a['id'] ?>"><?= date('M d', strtotime($a['appointment_datetime'])) ?> - <?= htmlspecialchars($a['doctor_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Amount (Rs.)</label>
                        <input type="number" step="0.01" name="amount" id="amount" class="form-control" required placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="unpaid">Unpaid</option>
                            <option value="paid">Paid</option>
                            <option value="partial">Partial</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Payment Method</label>
                        <input type="text" name="payment_method" id="payment_method" class="form-control" placeholder="e.g. Cash, eSewa">
                    </div>

                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="date" name="due_date" id="due_date" class="form-control">
                    </div>

                    <div class="form-group col-span-2">
                        <label>Admin Notes</label>
                        <textarea name="notes" id="notes" class="form-control" style="min-height:80px;" placeholder="Optional details..."></textarea>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Save Invoice</button>
            </form>
        </div>
    </div>
</div>

<script>
const modal = document.getElementById('billingModal');
const addBtn = document.getElementById('addBillingBtn');
const closeModal = document.getElementById('closeModal');
const modalTitle = document.getElementById('modalTitle');

// Open Modal for New Entry
addBtn.onclick = () => {
    modal.style.display = 'flex';
    modalTitle.textContent = "Add New Invoice";
    document.getElementById('billingForm').reset();
    document.getElementById('billing-id').value = '';
}

// Close Modal
closeModal.onclick = () => modal.style.display = 'none';
window.onclick = e => { if(e.target == modal) modal.style.display = 'none'; }

// Open Modal for Edit
document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.onclick = () => {
        modal.style.display = 'flex';
        modalTitle.textContent = "Edit Invoice";
        document.getElementById('billing-id').value = btn.dataset.id;
        document.getElementById('patient_id').value = btn.dataset.patient_id;
        document.getElementById('appointment_id').value = btn.dataset.appointment_id;
        document.getElementById('amount').value = btn.dataset.amount;
        document.getElementById('status').value = btn.dataset.status;
        document.getElementById('payment_method').value = btn.dataset.payment_method;
        document.getElementById('due_date').value = btn.dataset.due_date;
        document.getElementById('notes').value = btn.dataset.notes;
    }
});
</script>
</body>
</html>