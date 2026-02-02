<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth($conn);

// Only admins can access
if (!$auth->isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$success = '';
$error = '';

// ------------------------------------------------
// 1. HANDLE FORM SUBMISSIONS (Add / Edit / Delete)
// ------------------------------------------------

// Handle Delete
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM medical_records WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $success = "Medical record deleted successfully.";
    } else {
        $error = "Failed to delete record.";
    }
}

// Handle Add / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $patient_id = $_POST['patient_id'] ?? '';
    $doctor_id = $_POST['doctor_id'] ?? '';
    $visit_date = $_POST['visit_date'] ?? '';
    $diagnosis = $_POST['diagnosis'] ?? '';
    $treatment = $_POST['treatment'] ?? '';
    $prescription = $_POST['prescription'] ?? '';
    $notes = $_POST['notes'] ?? '';

    if (empty($patient_id) || empty($doctor_id) || empty($visit_date)) {
        $error = "Patient, Doctor, and Visit Date are required.";
    } else {
        if ($id) {
            // Update
            $stmt = $conn->prepare("UPDATE medical_records SET patient_id=?, doctor_id=?, visit_date=?, diagnosis=?, treatment=?, prescription=?, notes=? WHERE id=?");
            $stmt->bind_param("iisssssi", $patient_id, $doctor_id, $visit_date, $diagnosis, $treatment, $prescription, $notes, $id);
            if ($stmt->execute()) $success = "Medical record updated successfully.";
            else $error = "Update failed: " . $conn->error;
        } else {
            // Insert
            $stmt = $conn->prepare("INSERT INTO medical_records (patient_id, doctor_id, visit_date, diagnosis, treatment, prescription, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssss", $patient_id, $doctor_id, $visit_date, $diagnosis, $treatment, $prescription, $notes);
            if ($stmt->execute()) $success = "Medical record created successfully.";
            else $error = "Creation failed: " . $conn->error;
        }
    }
}

// ------------------------------------------------
// 2. FETCH DATA FOR DROPDOWNS
// ------------------------------------------------
$patients = $conn->query("SELECT p.id, u.name FROM patients p JOIN users u ON p.user_id = u.id ORDER BY u.name");
$doctors = $conn->query("SELECT d.id, u.name, d.specialization FROM doctors d JOIN users u ON d.user_id = u.id ORDER BY u.name");

// ------------------------------------------------
// 3. PAGINATION, SEARCH & FILTER LOGIC
// ------------------------------------------------
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where_clauses = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(u_p.name LIKE ? OR u_d.name LIKE ? OR mr.diagnosis LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $types .= "sss";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Count Total
$count_query = "
    SELECT COUNT(*) as total 
    FROM medical_records mr
    JOIN patients p ON mr.patient_id = p.id
    JOIN users u_p ON p.user_id = u_p.id
    JOIN doctors d ON mr.doctor_id = d.id
    JOIN users u_d ON d.user_id = u_d.id
    $where_sql
";
$stmt = $conn->prepare($count_query);
if (!empty($types)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total_rows = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Fetch Data
$query = "
    SELECT mr.*, 
           u_p.name AS patient_name,
           u_d.name AS doctor_name
    FROM medical_records mr
    JOIN patients p ON mr.patient_id = p.id
    JOIN users u_p ON p.user_id = u_p.id
    JOIN doctors d ON mr.doctor_id = d.id
    JOIN users u_d ON d.user_id = u_d.id
    $where_sql
    ORDER BY mr.visit_date DESC, mr.created_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$records = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Medical Records - Admin</title>
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

    /* Filter Bar */
    .filter-bar {
        background: white;
        padding: 1rem;
        border-radius: 12px;
        border: 1px solid var(--border);
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
        align-items: center;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    .search-input {
        flex: 1;
        padding: 0.6rem 1rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        outline: none;
    }
    .search-input:focus { border-color: var(--primary); }
    
    .btn-primary {
        background: var(--primary);
        color: white;
        border: none;
        padding: 0.6rem 1.2rem;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        font-size: 0.9rem;
    }
    .btn-primary:hover { background: var(--primary-dark); }

    /* Table */
    .card { background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border); overflow: hidden; }
    .table-responsive { overflow-x: auto; }
    .custom-table { width: 100%; border-collapse: collapse; text-align: left; }
    
    .custom-table th {
        background: #f8fafc;
        padding: 0.875rem 1.25rem;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        color: var(--text-muted);
        border-bottom: 1px solid var(--border);
    }
    .custom-table td {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--border);
        color: var(--text-main);
        font-size: 0.9rem;
        vertical-align: middle;
    }
    .custom-table tr:last-child td { border-bottom: none; }
    .custom-table tr:hover { background: #fcfcfc; }

    /* Action Buttons */
    .action-group { display: flex; gap: 0.5rem; }
    .btn-action {
        width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
        border-radius: 6px; border: 1px solid var(--border); background: white; color: var(--text-muted);
        cursor: pointer; transition: all 0.2s;
    }
    .btn-edit:hover { border-color: var(--primary); color: var(--primary); background: #eff6ff; }
    .btn-delete:hover { border-color: var(--danger); color: var(--danger); background: #fef2f2; }

    /* Modal */
    .modal-overlay {
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(4px); z-index: 50;
        justify-content: center; align-items: center;
    }
    .modal-card {
        background: white; width: 100%; max-width: 700px; border-radius: 16px;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden; animation: slideUp 0.3s ease-out;
    }
    @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

    .modal-header {
        padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex;
        justify-content: space-between; align-items: center; background: #f8fafc;
    }
    .modal-header h3 { margin: 0; font-size: 1.1rem; color: var(--text-main); }
    .close-modal { background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer; }
    
    .modal-body { padding: 1.5rem; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .form-group { margin-bottom: 1rem; }
    .col-span-2 { grid-column: span 2; }
    .form-group label { display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500; }
    .form-control { width: 100%; padding: 0.65rem 1rem; border: 1px solid var(--border); border-radius: 8px; box-sizing: border-box; }
    .btn-submit { width: 100%; background: var(--primary); color: white; border: none; padding: 0.75rem; border-radius: 8px; font-weight: 600; cursor: pointer; }

    /* Pagination */
    .pagination { padding: 1rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
    .page-link { padding: 0.35rem 0.75rem; border: 1px solid var(--border); border-radius: 6px; text-decoration: none; color: var(--text-main); font-size: 0.85rem; }
    .page-link:hover { background: #f1f5f9; }
    .page-link.active { background: var(--primary); color: white; border-color: var(--primary); }

    /* Alerts */
    .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; }
    .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    
    /* Truncate text */
    .truncate { max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
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
                    <h1>Medical Records</h1>
                    <p>Manage patient history and diagnoses.</p>
                </div>
                <button class="btn-primary" style="padding: 0.6rem 1rem; width: auto;" id="addBtn">
                    <i class="fas fa-plus"></i> Add Record
                </button>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Filter -->
            <form method="GET" class="filter-bar">
                <i class="fas fa-search" style="color:var(--text-muted)"></i>
                <input type="text" name="search" class="search-input" placeholder="Search by patient, doctor, or diagnosis..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn-primary"style="padding: 0.6rem 1rem; width: auto;" >Search</button>
                <?php if(!empty($search)): ?>
                    <a href="medical-records.php" class="btn-action" style="text-decoration:none; padding: 0 10px;">Clear</a>
                <?php endif; ?>
            </form>

            <!-- Table -->
            <div class="card">
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Diagnosis</th>
                                <th>Treatment/Rx</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($records->num_rows > 0): ?>
                                <?php while($row = $records->fetch_assoc()): ?>
                                <tr>
                                    <td><?= date('M d, Y', strtotime($row['visit_date'])) ?></td>
                                    <td style="font-weight: 500;"><?= htmlspecialchars($row['patient_name']) ?></td>
                                    <td style="color:var(--text-muted);">Dr. <?= htmlspecialchars($row['doctor_name']) ?></td>
                                    <td>
                                        <div class="truncate" title="<?= htmlspecialchars($row['diagnosis']) ?>">
                                            <?= htmlspecialchars($row['diagnosis']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="truncate" title="<?= htmlspecialchars($row['treatment'] . ' - ' . $row['prescription']) ?>">
                                            <?= htmlspecialchars(substr($row['treatment'], 0, 20)) ?>...
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-group">
                                            <button class="btn-action btn-edit" 
                                                data-id="<?= $row['id'] ?>"
                                                data-patient="<?= $row['patient_id'] ?>"
                                                data-doctor="<?= $row['doctor_id'] ?>"
                                                data-date="<?= $row['visit_date'] ?>"
                                                data-diagnosis="<?= htmlspecialchars($row['diagnosis']) ?>"
                                                data-treatment="<?= htmlspecialchars($row['treatment']) ?>"
                                                data-prescription="<?= htmlspecialchars($row['prescription']) ?>"
                                                data-notes="<?= htmlspecialchars($row['notes']) ?>"
                                                title="Edit Record">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            <a href="?delete_id=<?= $row['id'] ?>" class="btn-action btn-delete" 
                                               onclick="return confirm('Are you sure you want to delete this medical record?')"
                                               title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align:center; padding:3rem; color:var(--text-muted);">No medical records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <div class="page-info">Showing page <?= $page ?> of <?= $total_pages ?></div>
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

<!-- Modal -->
<div class="modal-overlay" id="recordModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="modalTitle">Add Medical Record</h3>
            <button class="close-modal" id="closeModal">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="recordForm">
                <input type="hidden" name="id" id="record-id">
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
                        <label>Doctor</label>
                        <select name="doctor_id" id="doctor_id" class="form-control" required>
                            <option value="">Select Doctor</option>
                            <?php while($d = $doctors->fetch_assoc()): ?>
                                <option value="<?= $d['id'] ?>">Dr. <?= htmlspecialchars($d['name']) ?> (<?= $d['specialization'] ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Visit Date</label>
                        <input type="date" name="visit_date" id="visit_date" class="form-control" required>
                    </div>
                    <div class="form-group col-span-2">
                        <label>Diagnosis</label>
                        <input type="text" name="diagnosis" id="diagnosis" class="form-control" placeholder="Primary diagnosis...">
                    </div>
                    <div class="form-group col-span-2">
                        <label>Treatment Plan</label>
                        <textarea name="treatment" id="treatment" class="form-control" rows="2" placeholder="Recommended treatment..."></textarea>
                    </div>
                    <div class="form-group col-span-2">
                        <label>Prescription</label>
                        <textarea name="prescription" id="prescription" class="form-control" rows="2" placeholder="Medications prescribed..."></textarea>
                    </div>
                    <div class="form-group col-span-2">
                        <label>Internal Notes</label>
                        <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="Additional observations..."></textarea>
                    </div>
                </div>
                <button type="submit" class="btn-submit">Save Record</button>
            </form>
        </div>
    </div>
</div>

<script>
const modal = document.getElementById('recordModal');
const addBtn = document.getElementById('addBtn');
const closeModal = document.getElementById('closeModal');
const modalTitle = document.getElementById('modalTitle');

// Open New
addBtn.onclick = () => {
    modal.style.display = 'flex';
    modalTitle.textContent = 'Add Medical Record';
    document.getElementById('recordForm').reset();
    document.getElementById('record-id').value = '';
}

// Close
closeModal.onclick = () => modal.style.display = 'none';
window.onclick = e => { if(e.target == modal) modal.style.display = 'none'; }

// Open Edit
document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.onclick = () => {
        modal.style.display = 'flex';
        modalTitle.textContent = 'Edit Medical Record';
        document.getElementById('record-id').value = btn.dataset.id;
        document.getElementById('patient_id').value = btn.dataset.patient;
        document.getElementById('doctor_id').value = btn.dataset.doctor;
        document.getElementById('visit_date').value = btn.dataset.date;
        document.getElementById('diagnosis').value = btn.dataset.diagnosis;
        document.getElementById('treatment').value = btn.dataset.treatment;
        document.getElementById('prescription').value = btn.dataset.prescription;
        document.getElementById('notes').value = btn.dataset.notes;
    }
});
</script>
</body>
</html>