<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth($conn);

// Check authentication
if (!$auth->isLoggedIn() || $_SESSION['role'] !== 'patient') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';
$success = '';

// 1. Fetch Appointment Details
// Ensure it belongs to the user and is 'pending'
$stmt = $conn->prepare("
    SELECT a.*, d.specialization, u.name AS doctor_name, d.consultation_fee
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    JOIN patients p ON a.patient_id = p.id
    WHERE a.id = ? AND p.user_id = ? AND a.status = 'pending'
");
$stmt->bind_param("ii", $appointment_id, $user_id);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();

if (!$appointment) {
    // If not found or not pending, redirect
    header("Location: manage-appointments.php");
    exit;
}

// 2. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_date = trim($_POST['appointment_date'] ?? '');
    $new_reason = trim($_POST['reason'] ?? '');

    if (!$new_date || !$new_reason) {
        $error = 'All fields are required.';
    } elseif (strlen($new_reason) < 10) {
        $error = 'Reason must be at least 10 characters.';
    } else {
        $date_obj = DateTime::createFromFormat('Y-m-d\TH:i', $new_date);
        $min_time = strtotime('+1 day');

        if (!$date_obj || strtotime($new_date) <= $min_time) {
            $error = 'Appointment must be rescheduled at least 24 hours in advance.';
        } else {
            // Update
            $update_stmt = $conn->prepare("UPDATE appointments SET appointment_date = ?, notes = ? WHERE id = ?");
            $update_stmt->bind_param("ssi", $new_date, $new_reason, $appointment_id);
            
            if ($update_stmt->execute()) {
                // Also update billing due date if exists and unpaid
                $billing_date = date('Y-m-d', strtotime($new_date));
                $update_bill = $conn->prepare("UPDATE billing SET due_date = ? WHERE appointment_id = ? AND status = 'unpaid'");
                $update_bill->bind_param("si", $billing_date, $appointment_id);
                $update_bill->execute();

                $success = 'Appointment updated successfully.';
                // Refresh data
                $appointment['appointment_date'] = $new_date;
                $appointment['notes'] = $new_reason;
            } else {
                $error = 'Failed to update appointment.';
            }
        }
    }
}

// Date Constraints
$min_date = date('Y-m-d\TH:i', strtotime('+1 day'));
$max_date = date('Y-m-d\TH:i', strtotime('+3 months'));
$current_appt_date = date('Y-m-d\TH:i', strtotime($appointment['appointment_date']));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Appointment - Clinic</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
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

        .edit-container {
            max-width: 700px;
            margin: 3rem auto;
            padding: 0 1rem;
        }

        .main-card {
            background: var(--bg-card);
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .card-header {
            padding: 2rem;
            border-bottom: 1px solid var(--border);
            background: #f1f5f9;
        }
        .card-header h1 { margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--text-main); }
        .card-header p { margin: 0.5rem 0 0; color: var(--text-muted); font-size: 0.95rem; }

        .card-body { padding: 2rem; }

        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-main); font-size: 0.9rem; }
        .form-control {
            width: 100%; padding: 0.75rem 1rem;
            border: 1px solid var(--border); border-radius: 8px;
            font-size: 0.95rem; box-sizing: border-box;
            transition: all 0.2s;
        }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        
        .static-field {
            background: #f8fafc; border: 1px solid var(--border);
            padding: 0.75rem 1rem; border-radius: 8px;
            color: var(--text-muted); font-weight: 500;
        }

        .form-actions {
            display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem;
        }

        .btn {
            padding: 0.75rem 1.5rem; border-radius: 8px;
            font-weight: 600; cursor: pointer; text-decoration: none;
            display: inline-flex; align-items: center; gap: 0.5rem; border: none; font-size: 0.95rem;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: white; border: 1px solid var(--border); color: var(--text-muted); }
        .btn-secondary:hover { background: #f1f5f9; color: var(--text-main); }

        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; gap: 0.75rem; align-items: center; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
    </style>
</head>
<body class="dashboard-page">
<div class="dashboard-container">
    <?php include __DIR__ . '/../include/sidebar-patient.php'; ?>

    <main class="dashboard-content">
        <?php include __DIR__ . '/../include/nav.php'; ?>

        <div class="edit-container">
            <div class="main-card">
                <div class="card-header">
                    <h1>Edit Appointment</h1>
                    <p>Reschedule or update details for your pending visit.</p>
                </div>

                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <!-- Static Info -->
                        <div class="form-group">
                            <label>Doctor</label>
                            <div class="static-field">
                                Dr. <?= htmlspecialchars($appointment['doctor_name']) ?> 
                                (<?= htmlspecialchars($appointment['specialization']) ?>)
                            </div>
                        </div>

                        <!-- Date Selection -->
                        <div class="form-group">
                            <label for="appointment_date">New Date & Time</label>
                            <input type="datetime-local" 
                                   id="appointment_date" 
                                   name="appointment_date" 
                                   class="form-control"
                                   min="<?= $min_date ?>"
                                   max="<?= $max_date ?>"
                                   value="<?= $current_appt_date ?>"
                                   required>
                            <p style="margin: 0.25rem 0 0; font-size: 0.8rem; color: var(--text-muted);">
                                Must be at least 24 hours from now.
                            </p>
                        </div>

                        <!-- Reason -->
                        <div class="form-group">
                            <label for="reason">Reason for Visit</label>
                            <textarea id="reason" name="reason" class="form-control" rows="4" required minlength="10"><?= htmlspecialchars($appointment['notes']) ?></textarea>
                        </div>

                        <div class="form-actions">
                            <a href="manage-appointments.php" class="btn btn-secondary">Back</a>
                            <button type="submit" class="btn btn-primary">
                                Save Changes <i class="fas fa-save"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>