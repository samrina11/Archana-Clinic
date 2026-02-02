<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

// --------------------
// Check if logged in as patient
// --------------------
if (!$auth->isLoggedIn() || $_SESSION['role'] !== 'patient') {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();
if (!$user) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// --------------------
// Get patient ID
// --------------------
$patient_query = "SELECT id FROM patients WHERE user_id = ?";
$stmt_patient = $conn->prepare($patient_query);
$stmt_patient->bind_param("i", $user['id']);
$stmt_patient->execute();
$patient_result = $stmt_patient->get_result();
$patient = $patient_result->fetch_assoc();

if (!$patient) {
    die("No patient record found. Please contact admin.");
}
$patient_id = $patient['id'];

// --------------------
// Fetch medical records
// --------------------
$records_query = "SELECT 
    mr.id,
    mr.visit_date,
    mr.diagnosis,
    mr.treatment,
    mr.prescription,
    mr.notes,
    u.name AS doctor_name,
    d.specialization,
    mr.created_at
FROM medical_records mr
JOIN doctors d ON mr.doctor_id = d.id
JOIN users u ON d.user_id = u.id
WHERE mr.patient_id = ?
ORDER BY mr.visit_date DESC";

$stmt_records = $conn->prepare($records_query);
$stmt_records->bind_param("i", $patient_id);
$stmt_records->execute();
$result = $stmt_records->get_result();

$records = [];
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records - Patient Dashboard</title>
    <link rel="stylesheet" href="../styles.css"> <!-- current folder -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-page">
    <div class="dashboard-container">
        <?php include __DIR__ . '/../include/sidebar-patient.php'; ?>

        <div class="dashboard-content">
            <div class="top-bar">
                <h1>📋 Medical Records</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($user['name']); ?></span>
                </div>
            </div>

            <div class="appointments-section">
                <h2>Your Medical History</h2>
                <?php if (!empty($records)): ?>
                    <?php foreach ($records as $record): ?>
                        <div class="record-card">
                            <div class="record-header">
                                <h3>Visit on <?php echo date('M d, Y', strtotime($record['visit_date'])); ?></h3>
                                <p><strong>Doctor:</strong> <?php echo htmlspecialchars($record['doctor_name']); ?> (<?php echo htmlspecialchars($record['specialization']); ?>)</p>
                            </div>

                            <div class="record-body">
                                <div>
                                    <h4>Diagnosis</h4>
                                    <p><?php echo htmlspecialchars($record['diagnosis']); ?></p>
                                </div>
                                <div>
                                    <h4>Treatment</h4>
                                    <p><?php echo htmlspecialchars($record['treatment']); ?></p>
                                </div>
                            </div>

                            <div class="record-prescription">
                                <h4>Prescription</h4>
                                <p><?php echo htmlspecialchars($record['prescription']); ?></p>
                            </div>

                            <?php if (!empty($record['notes'])): ?>
                                <div class="record-notes">
                                    <h4>Additional Notes</h4>
                                    <p><?php echo htmlspecialchars($record['notes']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-records">
                        <p>No medical records found. Records will appear after your visits.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
        .record-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .record-header h3 { color: #0c74a6; margin: 0 0 5px 0; }
        .record-header p { color: #666; margin: 0; }
        .record-body { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px; }
        .record-body h4 { margin: 0 0 10px 0; color: #333; }
        .record-body p { margin: 0; color: #666; }
        .record-prescription { margin-top: 15px; background: #f5f5f5; padding: 10px; border-radius: 5px; color: #666; }
        .record-notes { margin-top: 15px; color: #666; }
        .no-records { text-align: center; padding: 40px; color: #999; }
    </style>
</body>
</html>
