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

// Fetch current appointment details
if ($appointment_id > 0) {
    $stmt = $conn->prepare("
        SELECT a.id, a.doctor_id, a.appointment_datetime, a.appointment_time, a.notes, a.status,
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
        $error = 'Cannot reschedule a cancelled appointment.';
    } else {
        $apptDateTime = new DateTime($appointment['appointment_datetime'] . ' ' . $appointment['appointment_time']);
        $now = new DateTime();
        if ($apptDateTime < $now) {
            $error = 'Cannot reschedule past appointments.';
        }
    }
}

// POST: Reschedule appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $new_date      = trim($_POST['new_date'] ?? '');
        $new_time      = trim($_POST['new_time'] ?? '');
        $reason        = trim($_POST['reschedule_reason'] ?? '');

        $new_datetime = "$new_date $new_time";

        if (!$new_date || !$new_time) {
            $error = "Please select new date and time.";
        } elseif (strlen($reason) < 10) {
            $error = "Reason for rescheduling must be at least 10 characters.";
        } elseif (strtotime($new_datetime) <= strtotime('+24 hours')) {
            $error = "Rescheduled appointment must be at least 24 hours in the future.";
        } elseif ($new_date === $appointment['appointment_datetime'] && $new_time === $appointment['appointment_time']) {
            $error = "New date/time is the same as current. No change needed.";
        } else {
            $doctor_id = $appointment['doctor_id'];
            $dow = date('N', strtotime($new_date));

            // Check new date availability
            $unavail = $conn->prepare("
                SELECT 1 FROM doctor_unavailable_dates 
                WHERE doctor_id = ? AND unavailable_date = ? AND whole_day = 1
                LIMIT 1
            ");
            $unavail->bind_param("is", $doctor_id, $new_date);
            $unavail->execute();
            if ($unavail->get_result()->num_rows > 0) {
                $error = "Doctor is not available on the new selected date.";
            } else {
                $sched = $conn->prepare("
                    SELECT start_time, end_time, slot_duration_min
                    FROM doctor_schedules
                    WHERE doctor_id = ? AND day_of_week = ? AND is_active = 1
                    LIMIT 1
                ");
                $sched->bind_param("ii", $doctor_id, $dow);
                $sched->execute();
                $schedule = $sched->get_result()->fetch_assoc();

                if (!$schedule) {
                    $old = $conn->prepare("
                        SELECT available_time_start AS start_time,
                               available_time_end   AS end_time,
                               15                   AS slot_duration_min
                        FROM doctors WHERE id = ?
                    ");
                    $old->bind_param("i", $doctor_id);
                    $old->execute();
                    $schedule = $old->get_result()->fetch_assoc();
                }

                if (!$schedule) {
                    $error = "No schedule found for doctor on new date.";
                } else {
                    $start_ts = strtotime("$new_date {$schedule['start_time']}");
                    $end_ts   = strtotime("$new_date {$schedule['end_time']}");
                    $slot_min = (int)$schedule['slot_duration_min'];
                    $selected_ts = strtotime($new_datetime);

                    $is_valid = false;
                    $current = $start_ts;
                    while ($current < $end_ts) {
                        if ($current == $selected_ts) {
                            $is_valid = true;
                            break;
                        }
                        $current += $slot_min * 60;
                    }

                    if (!$is_valid) {
                        $error = "Selected new time is not a valid slot.";
                    } else {
                        // Check if new slot is free
                        $booked = $conn->prepare("
                            SELECT 1 FROM appointments 
                            WHERE doctor_id = ? 
                              AND appointment_datetime = ?
                              
                              AND status NOT IN ('cancelled','rejected')
                              AND id != ?
                            LIMIT 1
                        ");
                        $booked->bind_param("issi", $doctor_id, $new_date, $new_time, $appointment_id);
                        $booked->execute();

                        if ($booked->get_result()->num_rows > 0) {
                            $error = "New time slot is already booked.";
                        } else {
                            // Update appointment
                            $stmt = $conn->prepare("
                                UPDATE appointments 
                                SET appointment_datetime = ?, 
                                    appointment_time = ?, 
                                    notes = CONCAT(notes, '\nReschedule reason: ', ?),
                                    status = 'rescheduled_pending',
                                    updated_at = NOW()
                                WHERE id = ? AND patient_id = ?
                            ");
                            $updated_reason = $reason;
                            $stmt->bind_param("ssssi", $new_date, $new_time, $updated_reason, $appointment_id, $patient['id']);

                            if ($stmt->execute()) {
                                $success = "Appointment rescheduled successfully!";
                            } else {
                                $error = "Failed to reschedule. Please try again.";
                            }
                        }
                    }
                }
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
    <title>Reschedule Appointment - Archana Clinic</title>
    <link rel="stylesheet" href="/clinic/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-page">

<div class="dashboard-container">
    <?php include __DIR__ . '/../include/sidebar-patient.php'; ?>

    <main class="dashboard-content">
        <?php include __DIR__ . '/../include/nav.php'; ?>

        <h1><i class="fas fa-calendar-alt"></i> Reschedule Appointment</h1>

        <?php if ($success): ?>
            <div class="alert alert-success" style="padding: 25px; font-size: 1.2rem; text-align: center;">
                <i class="fas fa-check-circle fa-3x" style="color:#28a745; margin-bottom: 15px;"></i><br>
                <?= htmlspecialchars($success) ?>
            </div>
            <div style="text-align: center; margin-top: 25px;">
                <a href="view-appointments.php" class="btn btn-primary">
                    <i class="fas fa-calendar-check"></i> Back to My Appointments
                </a>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="padding: 20px; font-size: 1.1rem;">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($appointment && !$success && !$error): ?>
            <div class="card" style="margin-bottom: 30px; padding: 25px; background: white; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
                <h3>Current Appointment</h3>
                <p><strong>Doctor:</strong> Dr. <?= htmlspecialchars($appointment['doctor_name']) ?></p>
                <p><strong>Date & Time:</strong> <?= date('l, d F Y - h:i A', strtotime($appointment['appointment_datetime'] . ' ' . $appointment['appointment_time'])) ?></p>
                <p><strong>Reason:</strong> <?= htmlspecialchars($appointment['notes'] ?: 'Not specified') ?></p>
                <p><strong>Status:</strong> <?= ucfirst($appointment['status']) ?></p>
            </div>

            <form method="POST" id="rescheduleForm" class="booking-form">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="form-group">
                    <label for="new_date">Select New Date</label>
                    <input type="date" name="new_date" id="new_date" 
                           min="<?= date('Y-m-d', strtotime('+1 day')) ?>" 
                           max="<?= date('Y-m-d', strtotime('+90 days')) ?>" required>
                </div>

                <button type="submit" name="show_new_times" class="btn-secondary">
                    <i class="fas fa-clock"></i> Show New Available Times
                </button>

                <?php
                // Show new time slots if date selected
                $new_available_times = [];
                $new_selected_date = trim($_POST['new_date'] ?? '');
                if ($new_selected_date && !$success) {
                    $dow = date('N', strtotime($new_selected_date));
                    $doctor_id = $appointment['doctor_id'];

                    $unavail = $conn->prepare("
                        SELECT 1 FROM doctor_unavailable_dates 
                        WHERE doctor_id = ? AND unavailable_date = ? AND whole_day = 1
                        LIMIT 1
                    ");
                    $unavail->bind_param("is", $doctor_id, $new_selected_date);
                    $unavail->execute();

                    if ($unavail->get_result()->num_rows === 0) {
                        $sched = $conn->prepare("
                            SELECT start_time, end_time, slot_duration_min
                            FROM doctor_schedules
                            WHERE doctor_id = ? AND day_of_week = ? AND is_active = 1
                            LIMIT 1
                        ");
                        $sched->bind_param("ii", $doctor_id, $dow);
                        $sched->execute();
                        $schedule = $sched->get_result()->fetch_assoc();

                        if (!$schedule) {
                            $old = $conn->prepare("
                                SELECT available_time_start AS start_time,
                                       available_time_end   AS end_time,
                                       15                   AS slot_duration_min
                                FROM doctors WHERE id = ?
                            ");
                            $old->bind_param("i", $doctor_id);
                            $old->execute();
                            $schedule = $old->get_result()->fetch_assoc();
                        }

                        if ($schedule) {
                            $start_ts = strtotime("$new_selected_date {$schedule['start_time']}");
                            $end_ts   = strtotime("$new_selected_date {$schedule['end_time']}");
                            $slot_min = (int)$schedule['slot_duration_min'];

                            $current = $start_ts;
                            while ($current < $end_ts) {
                                $time_str = date('H:i:s', $current);

                                $booked = $conn->prepare("
                                    SELECT 1 FROM appointments 
                                    WHERE doctor_id = ? 
                                      AND appointment_dattime = ?
                                      AND appointment_time = ?
                                      AND status NOT IN ('cancelled','rejected')
                                      AND id != ?
                                    LIMIT 1
                                ");
                                $booked->bind_param("issi", $doctor_id, $new_selected_date, $time_str, $appointment_id);
                                $booked->execute();

                                if ($booked->get_result()->num_rows === 0) {
                                    $display = date('h:i A', $current);
                                    $new_available_times[] = ['time' => $time_str, 'display' => $display];
                                }

                                $current += $slot_min * 60;
                            }
                        }
                    }
                }
                ?>

                <?php if (!empty($new_available_times)): ?>
                <div class="form-group time-slots-section" style="margin-top: 30px;">
                    <label>Available Time Slots on <?= date('D, d M Y', strtotime($new_selected_date)) ?></label>
                    <div class="time-slots-grid">
                        <?php foreach ($new_available_times as $slot): ?>
                            <label class="time-slot-option">
                                <input type="radio" name="new_time" value="<?= $slot['time'] ?>" required>
                                <?= $slot['display'] ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="reschedule_reason">Reason for Rescheduling <small>(min 10 characters)</small></label>
                    <textarea name="reschedule_reason" id="reschedule_reason" rows="4" required minlength="10" 
                              placeholder="Please explain why you need to reschedule..."></textarea>
                    <small id="reasonCounter">0/500 characters</small>
                </div>

                <button type="submit" name="reschedule_confirm" class="btn-primary" id="submitBtn">
                    <i class="fas fa-calendar-check"></i> Confirm Reschedule
                </button>
                <?php elseif ($new_selected_date && !$success): ?>
                <div class="info-message" style="margin-top: 25px; padding: 15px; background: #fff3cd; border: 1px solid #ffeeba; border-radius: 8px;">
                    <i class="fas fa-info-circle"></i> No available slots found for the new date.
                </div>
                <?php endif; ?>
            </form>

            <div style="margin-top: 30px; text-align: center;">
                <a href="view-appointments.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Appointments
                </a>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
// Client-side date validation for new date
const newDateInput = document.getElementById('new_date');
if (newDateInput) {
    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(today.getDate() + 1);
    const maxDate = new Date(today);
    maxDate.setDate(today.getDate() + 90);

    newDateInput.min = tomorrow.toISOString().split('T')[0];
    newDateInput.max = maxDate.toISOString().split('T')[0];

    newDateInput.addEventListener('change', function() {
        const selected = new Date(this.value);
        if (selected < tomorrow) {
            alert('New date must be at least tomorrow.');
            this.value = '';
        }
    });
}

// Reason character counter
const reasonTextarea = document.getElementById('reschedule_reason');
const reasonCounter = document.getElementById('reasonCounter');
if (reasonTextarea && reasonCounter) {
    reasonTextarea.addEventListener('input', function() {
        const len = this.value.length;
        reasonCounter.textContent = `${len}/500 characters`;
        reasonCounter.style.color = len < 10 ? '#dc3545' : len > 450 ? '#ffc107' : '#28a745';
    });
}

// Prevent double-submit
const submitBtn = document.getElementById('submitBtn');
if (submitBtn) {
    document.getElementById('rescheduleForm').addEventListener('submit', function(e) {
        if (submitBtn.disabled) {
            e.preventDefault();
            return;
        }
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    });
}
</script>

</body>
</html>