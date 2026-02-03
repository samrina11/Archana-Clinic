<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

/* =========================
   ACCESS CONTROL
========================= */
if (!$auth->isLoggedIn() || $_SESSION['role'] !== 'patient') {
    header('Location: /clinic/login.php');
    exit;
}

$user = $auth->getUser();
if (!$user) {
    session_destroy();
    header('Location: /clinic/login.php');
    exit;
}

/* =========================
   GET PATIENT ID
========================= */
$stmt = $conn->prepare("SELECT id FROM patients WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    die("Patient profile not found. Please contact admin.");
}

$patient_id = (int)$patient['id'];

/* =========================
   FETCH PATIENT MEDICAL RECORDS
========================= */
$stmt = $conn->prepare("
    SELECT mr.visit_date, mr.diagnosis, mr.treatment, mr.prescription, mr.notes,
           u.name AS doctor_name, d.specialization
    FROM medical_records mr
    JOIN doctors d ON mr.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE mr.patient_id = ?
    ORDER BY mr.visit_date DESC, mr.created_at DESC
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your Medical Records - Patient</title>
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
}

body.dashboard-page {
    background-color: var(--bg-body);
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    color: var(--text-main);
}

.manage-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1.5rem;
}

.page-header { margin-bottom: 2rem; }
.page-header h1 { font-size: 1.75rem; font-weight: 700; color: var(--text-main); margin: 0; }
.page-header p { color: var(--text-muted); margin: 0.25rem 0 0; }

/* Record cards */
.record-card {
    background: var(--bg-card);
    border-radius: 12px;
    border: 1px solid var(--border);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.record-card h3 { margin: 0 0 0.5rem 0; color: var(--primary); }
.record-card p { margin: 0.25rem 0; color: var(--text-muted); }
.record-body { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px; }
.record-body h4 { margin: 0 0 5px 0; color: var(--text-main); }
.record-body p { margin: 0; color: var(--text-muted); }

.record-prescription { margin-top: 15px;  color: var(--text-muted); }
.record-notes { margin-top: 15px; color: var(--text-muted); }

.no-records { text-align: center; padding: 40px; color: var(--text-muted); }

/* Responsive */
@media (max-width: 768px) {
    .record-body { grid-template-columns: 1fr; }
}
</style>
</head>

<body class="dashboard-page">
<div class="dashboard-container">

    <?php include __DIR__ . '/../include/sidebar-patient.php'; ?>

    <main class="dashboard-content">
        <?php include __DIR__ . '/../include/dnav.php'; ?>

        <div class="manage-container">
            <div class="page-header">
                <h1>📋 Your Medical Records</h1>
                <p>View your medical history and prescriptions.</p>
            </div>

            <?php if (count($records) > 0): ?>
                <?php foreach ($records as $r): ?>
                    <div class="record-card">
                        <h3>Visit on <?= date('M d, Y', strtotime($r['visit_date'])) ?></h3>
                        <p><strong>Doctor:</strong> <?= htmlspecialchars($r['doctor_name']) ?> (<?= htmlspecialchars($r['specialization']) ?>)</p>

                        <div class="record-body">
                            <div>
                                <h4>Diagnosis</h4>
                                <p><?= htmlspecialchars($r['diagnosis']) ?></p>
                            </div>
                            <div>
                                <h4>Treatment</h4>
                                <p><?= htmlspecialchars($r['treatment']) ?></p>
                            </div>
                        </div>

                        <div class="record-prescription">
                            <h4>Prescription</h4>
                            <p><?= htmlspecialchars($r['prescription']) ?></p>
                        </div>

                        <?php if (!empty($r['notes'])): ?>
                            <div class="record-notes">
                                <h4>Additional Notes</h4>
                                <p><?= htmlspecialchars($r['notes']) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-records">
                    <i class="fas fa-folder-open" style="font-size:2rem; margin-bottom:0.5rem; opacity:0.5;"></i>
                    <p>No medical records found. Records will appear after your visits.</p>
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>
</body>
</html>
