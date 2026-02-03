<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

/* =========================
   ACCESS CONTROL
========================= */
if (!$auth->isLoggedIn() || $_SESSION['role'] !== 'doctor') {
    header('Location: /clinic/login.php');
    exit;
}

$user = $auth->getUser();
$success_msg = '';
$error_msg = '';

/* =========================
   GET DOCTOR ID
========================= */
$stmt = $conn->prepare("SELECT id, consultation_fee FROM doctors WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$doctor_res = $stmt->get_result()->fetch_assoc();

if (!$doctor_res) die("Doctor profile not found.");
$doctor_id = (int)$doctor_res['id'];
$consultation_fee = $doctor_res['consultation_fee'] ?? 500;

/* =========================
   HANDLE ACTIONS (Update Status + Auto Bill)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appt_id = intval($_POST['appt_id']);
    $action  = $_POST['action']; // 'confirmed', 'completed', 'cancelled'
    $notes   = trim($_POST['notes'] ?? '');

    if ($appt_id && in_array($action, ['confirmed', 'completed', 'cancelled'])) {
        
        // 1. Update Appointment Status
        $update_sql = "UPDATE appointments SET status = ?, notes = ? WHERE id = ? AND doctor_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssii", $action, $notes, $appt_id, $doctor_id);
        
        if ($stmt->execute()) {
            $success_msg = "Appointment marked as " . ucfirst($action) . ".";

            // 2. AUTO-BILLING LOGIC (Only when Confirmed)
            if ($action === 'confirmed') {
                // Check if bill already exists to prevent duplicates
                $check_bill = $conn->query("SELECT id FROM billing WHERE appointment_id = $appt_id LIMIT 1");
                
                if ($check_bill->num_rows === 0) {
                    // Get Patient ID for this appointment
                    $get_pat = $conn->query("SELECT patient_id FROM appointments WHERE id = $appt_id");
                    $pat_data = $get_pat->fetch_assoc();
                    $pat_id = $pat_data['patient_id'];

                    // Insert Bill
                    $bill_sql = "INSERT INTO billing (appointment_id, patient_id, amount, status, due_date, created_at) 
                                 VALUES (?, ?, ?, 'unpaid', DATE_ADD(NOW(), INTERVAL 7 DAY), NOW())";
                    $bill_stmt = $conn->prepare($bill_sql);
                    $bill_stmt->bind_param("iid", $appt_id, $pat_id, $consultation_fee);
                    
                    if ($bill_stmt->execute()) {
                        $success_msg .= " Bill generated successfully.";
                    }
                }
            }

        } else {
            $error_msg = "Failed to update appointment.";
        }
    }
}

/* =========================
   FILTERS & PAGINATION
========================= */
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where_clause = "WHERE a.doctor_id = ?";
$params = [$doctor_id];
$types = "i";

if ($search) {
    $where_clause .= " AND (u.name LIKE ? OR u.phone LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
    $types .= "ss";
}

if ($status_filter !== 'all') {
    $where_clause .= " AND a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Get Total Count
$query_base = "
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    $where_clause
";

$count_stmt = $conn->prepare("SELECT COUNT(*) as total $query_base");
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Get Data
$query_sql = "
    SELECT 
        a.id, a.appointment_datetime,  a.status, a.notes,
        u.name AS patient_name, u.email AS patient_email, p.phone AS patient_phone
    $query_base
    ORDER BY a.appointment_datetime DESC, a.appointment_time ASC
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query_sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$appointments = $stmt->get_result();

// Stats for Today
$today = date('Y-m-d');
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN appointment_datetime = ? THEN 1 ELSE 0 END) as today_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count
    FROM appointments 
    WHERE doctor_id = ?
");
$stats_stmt->bind_param("si", $today, $doctor_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Appointments - Archana Clinic</title>
<link rel="stylesheet" href="/clinic/styles.css">
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
        --warning: #f59e0b;
        --danger: #ef4444;
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

    /* Stats Row */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .stat-card {
        background: var(--bg-card);
        padding: 1.25rem 1.5rem;
        border-radius: 12px;
        border: 1px solid var(--border);
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .stat-icon {
        width: 42px; height: 42px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center; font-size: 1.25rem;
    }
    .stat-info h3 { margin: 0; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; }
    .stat-info .value { font-size: 1.5rem; font-weight: 700; color: var(--text-main); display: block; margin-top: 0.1rem; }

    /* Filter Bar */
    .controls-bar {
        background: white;
        padding: 1rem;
        border-radius: 12px;
        border: 1px solid var(--border);
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
        align-items: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .search-input {
        flex: 1;
        padding: 0.65rem 1rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.9rem;
        outline: none;
        max-width: 300px;
    }
    .search-input:focus { border-color: var(--primary); }
    .filter-select {
        padding: 0.65rem 2rem 0.65rem 1rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: white;
        font-size: 0.9rem;
        cursor: pointer;
    }

    /* Table */
    .card { background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .table-responsive { overflow-x: auto; }
    .custom-table { width: 100%; border-collapse: collapse; text-align: left; }
    .custom-table th {
        background: #f8fafc; padding: 0.875rem 1.25rem;
        font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted);
        border-bottom: 1px solid var(--border);
    }
    .custom-table td { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); color: var(--text-main); font-size: 0.9rem; vertical-align: middle; }
    .custom-table tr:hover { background: #fcfcfc; }

    /* Badges */
    .status-badge {
        display: inline-flex; align-items: center; padding: 0.2rem 0.6rem;
        border-radius: 9999px; font-size: 0.75rem; font-weight: 600; text-transform: capitalize;
    }
    .status-pending { background: #fffbeb; color: #b45309; border: 1px solid #fcd34d; }
    .status-confirmed { background: #ecfdf5; color: #047857; border: 1px solid #6ee7b7; }
    .status-completed { background: #eff6ff; color: #1e40af; border: 1px solid #93c5fd; }
    .status-cancelled { background: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5; }

    /* Buttons */
    .action-group { display: flex; gap: 0.5rem; }
    .btn-icon {
        width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
        border-radius: 6px; border: 1px solid var(--border); background: white; color: var(--text-muted);
        cursor: pointer; transition: all 0.2s;
    }
    .btn-confirm:hover { border-color: var(--primary); color: var(--primary); background: #eff6ff; }
    .btn-complete:hover { border-color: var(--success); color: var(--success); background: #ecfdf5; }
    .btn-cancel:hover { border-color: var(--danger); color: var(--danger); background: #fef2f2; }

    /* Modal */
    .modal-overlay {
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(4px); z-index: 50;
        justify-content: center; align-items: center;
    }
    .modal-card {
        background: white; width: 100%; max-width: 500px; border-radius: 16px;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden; animation: slideUp 0.3s ease-out;
    }
    @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    
    .modal-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
    .modal-body { padding: 1.5rem; }
    
    .form-label { display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500; }
    .form-control { width: 100%; padding: 0.65rem; border: 1px solid var(--border); border-radius: 8px; box-sizing: border-box; font-family: inherit; }
    .btn-submit { width: 100%; background: var(--primary); color: white; border: none; padding: 0.75rem; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 1rem; }

    /* Alerts */
    .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; }
    .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

    /* Pagination */
    .pagination { padding: 1rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
    .page-link { padding: 0.35rem 0.75rem; border: 1px solid var(--border); border-radius: 6px; text-decoration: none; color: var(--text-main); font-size: 0.85rem; }
    .page-link:hover { background: #e2e8f0; }
    .page-link.disabled { opacity: 0.5; pointer-events: none; }
</style>
</head>

<body class="dashboard-page">
<div class="dashboard-container">

    <?php include __DIR__ . '/../include/sidebar-doctor.php'; ?>

    <main class="dashboard-content">
        <?php include __DIR__ . '/../include/dnav.php'; ?>

        <div class="manage-container">
            <div class="page-header">
                <div>
                    <h1>My Appointments</h1>
                    <p>Track schedule and manage patient visits.</p>
                </div>
            </div>

            <?php if ($success_msg): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error_msg) ?></div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#eff6ff; color:#2563eb;"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-info"><h3>Today</h3><span class="value"><?= $stats['today_count'] ?></span></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fff7ed; color:#f59e0b;"><i class="fas fa-clock"></i></div>
                    <div class="stat-info"><h3>Pending</h3><span class="value"><?= $stats['pending_count'] ?></span></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#f0fdf4; color:#16a34a;"><i class="fas fa-users"></i></div>
                    <div class="stat-info"><h3>Total</h3><span class="value"><?= $stats['total'] ?></span></div>
                </div>
            </div>

            <!-- Controls -->
            <form method="GET" class="controls-bar">
                <i class="fas fa-search" style="color:var(--text-muted)"></i>
                <input type="text" name="search" class="search-input" placeholder="Search by patient name or phone..." value="<?= htmlspecialchars($search) ?>">
                
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </form>

            <!-- Table -->
            <div class="card">
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Patient Details</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($appointments->num_rows > 0): ?>
                                <?php while ($row = $appointments->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:600;"><?= date('M d, Y', strtotime($row['appointment_datetime'])) ?></div>
                                        <div style="font-size:0.85rem; color:var(--text-muted);"><?= date('h:i A', strtotime($row['appointment_datetime'])) ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight:500;"><?= htmlspecialchars($row['patient_name']) ?></div>
                                        <div style="font-size:0.85rem; color:var(--text-muted);"><?= htmlspecialchars($row['patient_phone'] ?? 'No Phone') ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($row['notes'] ?: '-') ?></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($row['status']) ?>">
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-group">
                                            
                                            <!-- Action: Confirm (Triggers Auto-Billing) -->
                                            <?php if($row['status'] === 'pending'): ?>
                                                <button class="btn-icon btn-confirm" title="Confirm & Bill" 
                                                    onclick="openActionModal(<?= $row['id'] ?>, 'confirmed', '<?= addslashes($row['patient_name']) ?>')">
                                                    <i class="fas fa-thumbs-up"></i>
                                                </button>
                                            <?php endif; ?>

                                            <!-- Action: Complete -->
                                            <?php if(in_array($row['status'], ['confirmed'])): ?>
                                                <button class="btn-icon btn-complete" title="Mark Completed" 
                                                    onclick="openActionModal(<?= $row['id'] ?>, 'completed', '<?= addslashes($row['patient_name']) ?>')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Action: Cancel -->
                                            <?php if($row['status'] !== 'cancelled' && $row['status'] !== 'completed'): ?>
                                                <button class="btn-icon btn-cancel" title="Cancel Appointment" 
                                                    onclick="openActionModal(<?= $row['id'] ?>, 'cancelled', '<?= addslashes($row['patient_name']) ?>')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>

                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align:center; padding:3rem; color:var(--text-muted);">No appointments found matching your criteria.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <div style="font-size:0.85rem; color:var(--text-muted);">Page <?= $page ?> of <?= $total_pages ?></div>
                    <div style="display:flex; gap:0.5rem;">
                        <a href="?page=<?= max(1, $page-1) ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>" 
                           class="page-link <?= $page <= 1 ? 'disabled' : '' ?>">Prev</a>
                        <a href="?page=<?= min($total_pages, $page+1) ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>" 
                           class="page-link <?= $page >= $total_pages ? 'disabled' : '' ?>">Next</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Action Modal -->
<div class="modal-overlay" id="actionModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="modalTitle">Update Status</h3>
            <button class="close-modal" id="closeModal">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="appt_id" id="modal_appt_id">
                <input type="hidden" name="action" id="modal_action_val">
                
                <p id="modalDesc" style="margin-bottom:1rem; color:var(--text-muted);"></p>

                <div class="form-group">
                    <label class="form-label">Doctor's Notes (Optional)</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Add clinical notes or reason..."></textarea>
                </div>

                <button type="submit" class="btn-submit" id="modalBtn">Confirm</button>
            </form>
        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('actionModal');
    const closeModal = document.getElementById('closeModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalDesc = document.getElementById('modalDesc');
    const modalAction = document.getElementById('modal_action_val');
    const modalApptId = document.getElementById('modal_appt_id');
    const modalBtn = document.getElementById('modalBtn');

    function openActionModal(id, action, name) {
        modal.style.display = 'flex';
        modalApptId.value = id;
        modalAction.value = action;

        if (action === 'confirmed') {
            modalTitle.textContent = 'Confirm Appointment';
            modalDesc.textContent = `Confirm appointment with ${name}? This will generate a pending bill for the patient.`;
            modalBtn.textContent = 'Confirm & Bill';
            modalBtn.style.background = 'var(--primary)';
        } else if (action === 'completed') {
            modalTitle.textContent = 'Complete Appointment';
            modalDesc.textContent = `Mark appointment with ${name} as successfully completed?`;
            modalBtn.textContent = 'Mark Completed';
            modalBtn.style.background = 'var(--success)';
        } else {
            modalTitle.textContent = 'Cancel Appointment';
            modalDesc.textContent = `Are you sure you want to cancel the appointment with ${name}?`;
            modalBtn.textContent = 'Confirm Cancellation';
            modalBtn.style.background = 'var(--danger)';
        }
    }

    closeModal.onclick = () => modal.style.display = 'none';
    window.onclick = (e) => { if(e.target === modal) modal.style.display = 'none'; }
</script>
</body>
</html>