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
   GET DOCTOR ID
========================= */
$stmt = $conn->prepare("SELECT id FROM doctors WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

if (!$doctor) {
    die("Doctor profile not found.");
}

$doctor_id = (int) $doctor['id'];

/* =========================
   UPDATE STATUS + AUTO BILL
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {

    $appointment_id = (int) $_POST['appointment_id'];
    $status = $_POST['status'];

    try {
        $conn->begin_transaction();

        /* 🔒 Update appointment */
        $update = $conn->prepare("
            UPDATE appointments 
            SET status = ? 
            WHERE id = ? AND doctor_id = ?
        ");
        $update->bind_param("sii", $status, $appointment_id, $doctor_id);
        $update->execute();

        if ($update->affected_rows === 0) {
            throw new Exception("Unauthorized update or invalid appointment.");
        }

        /* =========================
           AUTO CREATE BILL (ONLY ON CONFIRM)
        ========================= */
        if ($status === 'confirmed') {

            // prevent duplicate bills
            $check = $conn->prepare("
                SELECT id FROM billing WHERE appointment_id = ? LIMIT 1
            ");
            $check->bind_param("i", $appointment_id);
            $check->execute();
            $check->store_result();

            if ($check->num_rows === 0) {

                // fetch patient + consultation fee
                $info = $conn->prepare("
                    SELECT a.patient_id, d.consultation_fee
                    FROM appointments a
                    JOIN doctors d ON a.doctor_id = d.id
                    WHERE a.id = ?
                ");
                $info->bind_param("i", $appointment_id);
                $info->execute();
                $data = $info->get_result()->fetch_assoc();

                if ($data) {
                    $insert = $conn->prepare("
                        INSERT INTO billing
                        (appointment_id, patient_id, amount, status, due_date, created_at)
                        VALUES (?, ?, ?, 'unpaid', DATE_ADD(NOW(), INTERVAL 7 DAY), NOW())
                    ");
                    $insert->bind_param(
                        "iid",
                        $appointment_id,
                        $data['patient_id'],
                        $data['consultation_fee']
                    );
                    $insert->execute();
                }
            }
        }

        $conn->commit();
        header('Location: update-appointment.php?updated=1');
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        die("Error: " . $e->getMessage());
    }
}

/* =========================
   FETCH APPOINTMENTS
========================= */
$stmt = $conn->prepare("
    SELECT 
        a.id,
        a.appointment_datetime,
        a.appointment_time,
        a.status,
        p.age,
        p.blood_group,
        u.name AS patient_name,
        u.email
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE a.doctor_id = ?
    ORDER BY a.appointment_datetime DESC, a.appointment_time DESC
");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Appointments</title>
<link rel="stylesheet" href="/clinic/styles.css">
</head>

<body class="dashboard-page">
<div class="dashboard-container">

<?php include __DIR__ . '/../include/sidebar-doctor.php'; ?>

<div class="dashboard-content">
<?php include __DIR__ . '/../include/dnav.php'; ?>

<h1>📅 My Appointments</h1>

<?php if (isset($_GET['updated'])): ?>
    <div class="success-message">
        Appointment updated & bill generated.
    </div>
<?php endif; ?>

<table class="appointments-table">
<thead>
<tr>
    <th>ID</th>
    <th>Patient</th>
    <th>Date</th>
    <th>Status</th>
    <th>Update</th>
</tr>
</thead>
<tbody>
<?php foreach ($appointments as $apt): ?>
<tr>
    <td>#<?= $apt['id'] ?></td>
    <td><?= htmlspecialchars($apt['patient_name']) ?></td>
    <td>
        <?= date('M d, Y', strtotime($apt['appointment_datetime'])) ?>
        <?= date('h:i A', strtotime($apt['appointment_time'])) ?>
    </td>
    <td>
        <span class="badge badge-<?= $apt['status'] ?>">
            <?= ucfirst($apt['status']) ?>
        </span>
    </td>
    <td>
        <form method="POST">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
            <select name="status" onchange="this.form.submit()">
                <option disabled selected><?= ucfirst($apt['status']) ?></option>
                <option value="confirmed">Confirm</option>
                <option value="completed">Complete</option>
                <option value="cancelled">Cancel</option>
            </select>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

</div>
</div>
</body>
</html>
