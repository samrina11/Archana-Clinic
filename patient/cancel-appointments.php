<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

if (!$auth->isLoggedIn() || $_SESSION['role'] !== 'patient') {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = '';
$error = '';
$appointment = null;

// Fetch appointment
if ($appointment_id > 0) {
    $stmt = $conn->prepare("
        SELECT a.id, a.appointment_date, a.appointment_time, a.notes, a.status,
               du.name AS doctor_name
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users du ON d.user_id = du.id
        WHERE a.id = ? AND a.patient_id = ?
    ");
    $stmt->bind_param("ii", $appointment_id, $patient['id']);
    $stmt->execute();
    $appointment = $stmt->get_result()->fetch_assoc();

    if (!$appointment) {
        $error = 'Appointment not found or does not belong to you.';
    } elseif ($appointment['status'] === 'cancelled') {
        $error = 'This appointment is already cancelled.';
    } else {
        $apptDateTime = new DateTime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
        $now = new DateTime();
        if ($apptDateTime < $now) {
            $error = 'Cannot cancel past appointments.';
        }
    }
}

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $reason = trim($_POST['cancellation_reason'] ?? '');

        if (empty($reason)) {
            $error = 'Please provide a reason for cancellation.';
        } elseif (strlen($reason) < 10) {
            $error = 'Reason must be at least 10 characters.';
        } else {
            $stmt = $conn->prepare("
                UPDATE appointments 
                SET status = 'cancelled', 
                    cancellation_reason = ?, 
                    cancelled_at = NOW()
                WHERE id = ? AND patient_id = ?
            ");
            $stmt->bind_param("sii", $reason, $appointment_id, $patient['id']);

            if ($stmt->execute()) {
                $success = 'Appointment cancelled successfully.';
            } else {
                $error = 'Failed to cancel appointment. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Appointment - Archana Clinic</title>
    <link rel="stylesheet" href="/clinic/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-page">

<div class="dashboard-container">
    <?php include __DIR__ . '/../include/sidebar-patient.php'; ?>

    <main class="dashboard-content">
        <?php include __DIR__ . '/../include/nav.php'; ?>

        <h1><i class="fas fa-times-circle"></i> Cancel Appointment</h1>

        <?php if ($success): ?>
            <div class="alert alert-success" style="padding: 25px; font-size: 1.2rem; text-align: center; margin-bottom: 30px;">
                <i class="fas fa-check-circle fa-3x" style="color:#28a745; margin-bottom: 15px;"></i><br>
                <?= htmlspecialchars($success) ?>
            </div>
            <div style="text-align: center;">
                <a href="view-appointments.php" class="btn btn-primary">
                    <i class="fas fa-calendar-check"></i> Back to My Appointments
                </a>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="padding: 20px; font-size: 1.1rem;">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="view-appointments.php" class="btn btn-secondary">Back</a>
            </div>
        <?php endif; ?>

        <?php if ($appointment && !$success && !$error): ?>
            <div class="card" style="margin-bottom: 30px; padding: 25px; background: white; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
                <h3>Appointment Details</h3>
                <p><strong>Doctor:</strong> Dr. <?= htmlspecialchars($appointment['doctor_name']) ?></p>
                <p><strong>Date & Time:</strong> <?= date('l, d F Y - h:i A', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time'])) ?></p>
                <p><strong>Reason for Visit:</strong> <?= htmlspecialchars($appointment['notes'] ?: 'Not specified') ?></p>
                <p><strong>Current Status:</strong> <?= ucfirst($appointment['status']) ?></p>
            </div>

            <form method="POST" class="cancellation-form">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="form-group">
                    <label for="cancellation_reason">
                        Reason for Cancellation <span style="color:#dc3545;">*</span>
                    </label>
                    <textarea name="cancellation_reason" id="cancellation_reason" rows="4" required 
                              placeholder="Please tell us why you are cancelling (minimum 10 characters)..."></textarea>
                    <small id="reasonCounter" style="float:right; color:#666;">0/500 characters</small>
                </div>

                <div class="form-actions" style="margin-top: 30px; text-align: center;">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel this appointment? This action cannot be undone.');">
                        <i class="fas fa-times"></i> Confirm Cancellation
                    </button>
                    <a href="view-appointments.php" class="btn btn-secondary" style="margin-left: 20px;">
                        Keep Appointment
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </main>
</div>

<script>
// Character counter for reason
const reasonTextarea = document.getElementById('cancellation_reason');
const reasonCounter = document.getElementById('reasonCounter');
if (reasonTextarea && reasonCounter) {
    reasonTextarea.addEventListener('input', function() {
        const len = this.value.length;
        reasonCounter.textContent = `${len}/500 characters`;
        reasonCounter.style.color = len < 10 ? '#dc3545' : len > 450 ? '#ffc107' : '#28a745';
    });
}
</script>

</body>
</html>