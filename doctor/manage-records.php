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

/* =========================
   GET DOCTOR ID (SAFE)
========================= */
$stmt = $conn->prepare("SELECT id FROM doctors WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

if (!$doctor) {
    die("Doctor profile not found. Please contact admin.");
}

$doctor_id = (int)$doctor['id'];

/* =========================
   HANDLE RECORD CREATION
========================= */
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_record') {

    $patient_id   = (int)$_POST['patient_id'];
    $visit_date   = $_POST['visit_date'];
    $diagnosis    = trim($_POST['diagnosis']);
    $treatment    = trim($_POST['treatment']);
    $prescription = trim($_POST['prescription']);
    $notes        = trim($_POST['notes'] ?? '');

    if (!$patient_id || !$visit_date || !$diagnosis || !$treatment || !$prescription) {
        $error_message = "All required fields must be filled.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO medical_records 
            (patient_id, doctor_id, visit_date, diagnosis, treatment, prescription, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "iisssss",
            $patient_id,
            $doctor_id,
            $visit_date,
            $diagnosis,
            $treatment,
            $prescription,
            $notes
        );

        if ($stmt->execute()) {
            $success_message = "Medical record created successfully!";
        } else {
            $error_message = "Failed to create medical record.";
        }
    }
}

/* =========================
   FETCH PATIENTS
========================= */
$stmt = $conn->prepare("
    SELECT DISTINCT p.id, u.name, u.email
    FROM patients p
    JOIN users u ON p.user_id = u.id
    JOIN appointments a ON a.patient_id = p.id
    WHERE a.doctor_id = ?
    ORDER BY u.name
");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* =========================
   FETCH RECENT RECORDS
========================= */
$stmt = $conn->prepare("
    SELECT mr.visit_date, mr.diagnosis, mr.treatment, u.name AS patient_name, u.email
    FROM medical_records mr
    JOIN patients p ON mr.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE mr.doctor_id = ?
    ORDER BY mr.visit_date DESC, mr.created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$recent_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Medical Records - Doctor</title>
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

    .page-header { margin-bottom: 2rem; }
    .page-header h1 { font-size: 1.75rem; font-weight: 700; color: var(--text-main); margin: 0; }
    .page-header p { color: var(--text-muted); margin: 0.25rem 0 0; }

    /* Layout */
    .records-grid {
        display: grid;
        grid-template-columns: 1fr 1.5fr; /* Form takes less width than table */
        gap: 2rem;
        align-items: start;
    }

    /* Cards */
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
        display: flex; justify-content: space-between; align-items: center;
    }
    .card-header h2 { margin: 0; font-size: 1.1rem; font-weight: 600; color: var(--text-main); }
    .card-body { padding: 1.5rem; }

    /* Forms */
    .form-group { margin-bottom: 1.25rem; }
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
    
    textarea.form-control { resize: vertical; min-height: 100px; }

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
        display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    }
    .btn-submit:hover { background: var(--primary-dark); }

    /* Table */
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
        vertical-align: top;
    }
    .custom-table tr:last-child td { border-bottom: none; }
    .custom-table tr:hover { background: #fcfcfc; }

    .diagnosis-tag {
        display: inline-block;
        background: #eff6ff; color: var(--primary);
        padding: 2px 8px; border-radius: 4px;
        font-size: 0.85rem; font-weight: 500;
        margin-bottom: 4px;
    }
    .treatment-text { color: var(--text-muted); font-size: 0.85rem; line-height: 1.4; }

    /* Alerts */
    .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; }
    .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

    @media (max-width: 1024px) {
        .records-grid { grid-template-columns: 1fr; }
    }
</style>
</head>

<body class="dashboard-page">
<div class="dashboard-container">

    <?php include __DIR__ . '/../include/sidebar-doctor.php'; ?>

    <main class="dashboard-content">
        <?php include __DIR__ . '/../include/dnav.php'; ?>

        <div class="manage-container">
            <div class="page-header">
                <h1>Medical Records</h1>
                <p>Create and view patient medical history.</p>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <div class="records-grid">

                <!-- LEFT: CREATE RECORD FORM -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-file-medical"></i> New Record</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="create_record">

                            <div class="form-group">
                                <label>Select Patient</label>
                                <select name="patient_id" class="form-control" required>
                                    <option value="">-- Choose Patient --</option>
                                    <?php foreach ($patients as $p): ?>
                                        <option value="<?= $p['id'] ?>">
                                            <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Visit Date</label>
                                <input type="date" name="visit_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Diagnosis</label>
                                <input type="text" name="diagnosis" class="form-control" placeholder="e.g. Acute Bronchitis" required>
                            </div>

                            <div class="form-group">
                                <label>Treatment Plan</label>
                                <textarea name="treatment" class="form-control" rows="3" placeholder="e.g. Rest, fluids, antibiotics..." required></textarea>
                            </div>

                            <div class="form-group">
                                <label>Prescription</label>
                                <textarea name="prescription" class="form-control" rows="3" placeholder="Medication details..." required></textarea>
                            </div>

                            <div class="form-group">
                                <label>Private Notes</label>
                                <textarea name="notes" class="form-control" rows="2" placeholder="Internal observations..."></textarea>
                            </div>

                            <button type="submit" class="btn-submit">
                                <i class="fas fa-save"></i> Save Record
                            </button>
                        </form>
                    </div>
                </div>

                <!-- RIGHT: RECENT RECORDS TABLE -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-history"></i> Recent History</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th style="width: 120px;">Date</th>
                                    <th>Patient</th>
                                    <th>Clinical Summary</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_records) > 0): ?>
                                    <?php foreach ($recent_records as $r): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight:600; font-size:0.9rem;"><?= date('M d, Y', strtotime($r['visit_date'])) ?></div>
                                            <div style="color:var(--text-muted); font-size:0.8rem;"><?= date('D', strtotime($r['visit_date'])) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight:500;"><?= htmlspecialchars($r['patient_name']) ?></div>
                                            <div style="color:var(--text-muted); font-size:0.8rem;"><?= htmlspecialchars($r['email']) ?></div>
                                        </td>
                                        <td>
                                            <div class="diagnosis-tag"><?= htmlspecialchars($r['diagnosis']) ?></div>
                                            <div class="treatment-text">
                                                <?= htmlspecialchars(substr($r['treatment'], 0, 60)) . (strlen($r['treatment']) > 60 ? '...' : '') ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" style="text-align:center; padding: 3rem; color: var(--text-muted);">
                                            <i class="fas fa-folder-open" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                                            <p>No medical records found.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div>
</body>
</html>