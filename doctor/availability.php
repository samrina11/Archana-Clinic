<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!$auth->isLoggedIn() || $_SESSION['role'] !== 'doctor') {
    header('Location: /clinic/login.php');
    exit;
}

$user = $auth->getUser();

// Get doctor_id
$stmt = $conn->prepare("SELECT id FROM doctors WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$doctor_result = $stmt->get_result()->fetch_assoc();

if (!$doctor_result) {
    die("Doctor profile not found. Please contact admin.");
}
$doctor_id = (int)$doctor_result['id'];

$success = '';
$error   = '';

// ───────────────────────────────────────────────
// HANDLE WEEKLY SCHEDULE SAVE
// ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_weekly'])) {
    $days = $_POST['days'] ?? [];
    $slot_duration = (int)($_POST['slot_duration'] ?? 15);

    // Clear old schedule
    $conn->query("DELETE FROM doctor_schedules WHERE doctor_id = $doctor_id");

    $days_map = [
        'monday'    => 1, 'tuesday'   => 2, 'wednesday' => 3, 'thursday'  => 4,
        'friday'    => 5, 'saturday'  => 6, 'sunday'    => 7
    ];

    $stmt = $conn->prepare("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration_min, is_active) VALUES (?, ?, ?, ?, ?, 1)");

    foreach ($days as $day_key) {
        if (!isset($days_map[$day_key])) continue;

        $day_num = $days_map[$day_key];
        $start_key = $day_key . '_start';
        $end_key   = $day_key . '_end';

        $start = !empty($_POST[$start_key]) ? $_POST[$start_key] . ':00' : '09:00:00';
        $end   = !empty($_POST[$end_key])   ? $_POST[$end_key]   . ':00' : '17:00:00';

        if ($start >= $end) {
            $error = "Invalid time range for " . ucfirst($day_key) . ". Start time must be before end time.";
            continue; 
        }

        $stmt->bind_param("iissi", $doctor_id, $day_num, $start, $end, $slot_duration);
        $stmt->execute();
    }

    if (empty($error)) {
        $success = "Weekly schedule updated successfully.";
    }
}

// ───────────────────────────────────────────────
// HANDLE UNAVAILABLE DATE (ADD / DELETE)
// ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_unavailable'])) {
    $date       = trim($_POST['unavailable_date'] ?? '');
    $reason     = trim($_POST['reason'] ?? '');
    $whole_day  = isset($_POST['whole_day']) ? 1 : 0;
    $start_time = $whole_day ? NULL : ($_POST['block_start'] ?? NULL);
    $end_time   = $whole_day ? NULL : ($_POST['block_end'] ?? NULL);

    if (empty($date)) {
        $error = "Please select a date.";
    } elseif (strtotime($date) < strtotime(date('Y-m-d'))) {
        $error = "Cannot add past dates.";
    } else {
        $stmt = $conn->prepare("INSERT INTO doctor_unavailable_dates (doctor_id, unavailable_date, reason, whole_day, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ississ", $doctor_id, $date, $reason, $whole_day, $start_time, $end_time);

        if ($stmt->execute()) {
            $success = "Unavailable date added successfully.";
        } else {
            $error = "Failed to add date (it might already exist).";
        }
    }
}

// Handle Delete
if (isset($_GET['delete_unavail'])) {
    $del_id = (int)$_GET['delete_unavail'];
    $stmt = $conn->prepare("DELETE FROM doctor_unavailable_dates WHERE id = ? AND doctor_id = ?");
    $stmt->bind_param("ii", $del_id, $doctor_id);
    if($stmt->execute()) {
        $success = "Leave removed successfully.";
    }
}

// ───────────────────────────────────────────────
// FETCH DATA
// ───────────────────────────────────────────────
$current_schedule = [];
$slot_duration_global = 15;

$stmt = $conn->prepare("SELECT day_of_week, start_time, end_time, slot_duration_min FROM doctor_schedules WHERE doctor_id = ? ORDER BY day_of_week");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $current_schedule[$row['day_of_week']] = $row;
    if ($row['slot_duration_min'] > 0) $slot_duration_global = $row['slot_duration_min'];
}

$unavailable_dates = [];
$stmt = $conn->prepare("SELECT id, unavailable_date, reason, whole_day, start_time, end_time FROM doctor_unavailable_dates WHERE doctor_id = ? AND unavailable_date >= CURDATE() ORDER BY unavailable_date ASC");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$unavail_result = $stmt->get_result();
while ($row = $unavail_result->fetch_assoc()) {
    $unavailable_dates[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Availability - Archana Clinic</title>
    <link rel="stylesheet" href="/clinic/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
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

        .page-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .page-header { margin-bottom: 2rem; }
        .page-header h1 { font-size: 1.75rem; font-weight: 700; color: var(--text-main); margin: 0; }
        .page-header p { color: var(--text-muted); margin: 0.25rem 0 0; }

        /* Layout Grid */
        .availability-grid {
            display: grid;
            grid-template-columns: 3fr 2fr;
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
            margin-bottom: 2rem;
        }
        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            background: #fdfdfd;
            display: flex; justify-content: space-between; align-items: center;
        }
        .card-header h2 { margin: 0; font-size: 1.1rem; font-weight: 600; color: var(--text-main); }
        .card-body { padding: 1.5rem; }

        /* Schedule Rows */
        .day-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border);
        }
        .day-row:last-child { border-bottom: none; }
        
        .day-label {
            display: flex;
            align-items: center;
            gap: 1rem;
            width: 140px;
            font-weight: 500;
        }

        .time-inputs {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            opacity: 0.5;
            pointer-events: none;
            transition: opacity 0.2s;
        }
        .day-active .time-inputs { opacity: 1; pointer-events: auto; }

        /* Toggle Switch */
        .switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background-color: #cbd5e1; transition: .4s; border-radius: 34px;
        }
        .slider:before {
            position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px;
            background-color: white; transition: .4s; border-radius: 50%;
        }
        input:checked + .slider { background-color: var(--primary); }
        input:checked + .slider:before { transform: translateX(20px); }

        /* Inputs */
        .form-control {
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.9rem;
            color: var(--text-main);
        }
        .form-control:focus { outline: none; border-color: var(--primary); }

        .btn-primary {
            background: var(--primary); color: white; border: none; padding: 0.75rem 1.5rem;
            border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%; transition: 0.2s;
        }
        .btn-primary:hover { background: #1d4ed8; }

        /* Unavailable List */
        .unavail-item {
            display: flex; justify-content: space-between; align-items: flex-start;
            padding: 0.75rem; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 0.5rem;
            background: #fff;
        }
        .unavail-date { font-weight: 600; display: block; margin-bottom: 2px; }
        .unavail-meta { font-size: 0.85rem; color: var(--text-muted); }
        .btn-del { color: var(--text-muted); cursor: pointer; padding: 4px; }
        .btn-del:hover { color: var(--danger); }

        /* Preview Badges */
        .status-pill {
            display: inline-block; padding: 2px 8px; border-radius: 4px;
            font-size: 0.75rem; font-weight: 600;
        }
        .pill-avail { background: #ecfdf5; color: #047857; }
        .pill-unavail { background: #fef2f2; color: #b91c1c; }

        .preview-row {
            display: flex; justify-content: space-between; padding: 0.5rem 0;
            border-bottom: 1px dashed var(--border); font-size: 0.9rem;
        }

        /* Alerts */
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .alert-success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

        @media (max-width: 1024px) {
            .availability-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="dashboard-page">
<div class="dashboard-container">
    <?php include __DIR__ . '/../include/sidebar-doctor.php'; ?>

    <main class="dashboard-content">
        <?php include __DIR__ . '/../include/dnav.php'; ?>

        <div class="page-container">
            <div class="page-header">
                <h1>Schedule Management</h1>
                <p>Set your weekly working hours and mark unavailability.</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="availability-grid">
                
                <!-- Left: Weekly Schedule -->
                <div class="card">
                    <div class="card-header">
                        <h2>Weekly Recurring Schedule</h2>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="save_weekly" value="1">
                            
                            <div style="margin-bottom: 1.5rem;">
                                <label style="font-weight: 500; font-size: 0.9rem;">Appointment Slot Duration</label>
                                <select name="slot_duration" class="form-control" style="margin-left: 10px;">
                                    <?php foreach([10, 15, 20, 30, 45, 60] as $dur): ?>
                                        <option value="<?= $dur ?>" <?= $slot_duration_global == $dur ? 'selected' : '' ?>><?= $dur ?> Minutes</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="schedule-list">
                                <?php
                                $days = [
                                    ['key' => 'monday',    'name' => 'Monday',    'num' => 1],
                                    ['key' => 'tuesday',   'name' => 'Tuesday',   'num' => 2],
                                    ['key' => 'wednesday', 'name' => 'Wednesday', 'num' => 3],
                                    ['key' => 'thursday',  'name' => 'Thursday',  'num' => 4],
                                    ['key' => 'friday',    'name' => 'Friday',    'num' => 5],
                                    ['key' => 'saturday',  'name' => 'Saturday',  'num' => 6],
                                    ['key' => 'sunday',    'name' => 'Sunday',    'num' => 7]
                                ];

                                foreach ($days as $d):
                                    $is_active = isset($current_schedule[$d['num']]);
                                    $start = $current_schedule[$d['num']]['start_time'] ?? '09:00';
                                    $end = $current_schedule[$d['num']]['end_time'] ?? '17:00';
                                ?>
                                <div class="day-row <?= $is_active ? 'day-active' : '' ?>" id="row-<?= $d['key'] ?>">
                                    <div class="day-label">
                                        <label class="switch">
                                            <input type="checkbox" name="days[]" value="<?= $d['key'] ?>" <?= $is_active ? 'checked' : '' ?> onchange="toggleDay('<?= $d['key'] ?>')">
                                            <span class="slider"></span>
                                        </label>
                                        <span><?= $d['name'] ?></span>
                                    </div>
                                    <div class="time-inputs">
                                        <input type="time" name="<?= $d['key'] ?>_start" class="form-control" value="<?= substr($start, 0, 5) ?>">
                                        <span style="color:var(--text-muted)">-</span>
                                        <input type="time" name="<?= $d['key'] ?>_end" class="form-control" value="<?= substr($end, 0, 5) ?>">
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div style="margin-top: 2rem;">
                                <button type="submit" class="btn-primary">Update Schedule</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right: Exceptions & Preview -->
                <div class="right-col">
                    
                    <!-- Add Leave -->
                    <div class="card">
                        <div class="card-header">
                            <h2>Add Time Off / Leave</h2>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="add_unavailable" value="1">
                                
                                <div style="margin-bottom: 1rem;">
                                    <label style="display:block; margin-bottom: 0.5rem; font-size: 0.9rem; font-weight: 500;">Select Date</label>
                                    <input type="date" name="unavailable_date" class="form-control" required min="<?= date('Y-m-d') ?>" style="width: 100%; box-sizing: border-box;">
                                </div>

                                <div style="margin-bottom: 1rem;">
                                    <label style="display:block; margin-bottom: 0.5rem; font-size: 0.9rem; font-weight: 500;">Reason</label>
                                    <input type="text" name="reason" class="form-control" placeholder="e.g. Conference, Personal" style="width: 100%; box-sizing: border-box;">
                                </div>

                                <div style="margin-bottom: 1rem;">
                                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem;">
                                        <input type="checkbox" name="whole_day" checked id="whole_day_check" onchange="togglePartial(this)"> 
                                        Whole Day Unavailable
                                    </label>
                                </div>

                                <div id="partial_time_inputs" style="display: none; margin-bottom: 1rem; background: #f1f5f9; padding: 1rem; border-radius: 8px;">
                                    <label style="display:block; margin-bottom: 0.5rem; font-size: 0.85rem;">Specific Time Block</label>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <input type="time" name="block_start" class="form-control" style="flex:1;">
                                        <input type="time" name="block_end" class="form-control" style="flex:1;">
                                    </div>
                                </div>

                                <button type="submit" class="btn-primary" style="background: var(--text-main);">Add Exception</button>
                            </form>
                        </div>
                    </div>

                    <!-- Upcoming Leaves -->
                    <?php if (!empty($unavailable_dates)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h2>Upcoming Leave</h2>
                        </div>
                        <div class="card-body">
                            <?php foreach ($unavailable_dates as $ud): ?>
                                <div class="unavail-item">
                                    <div>
                                        <span class="unavail-date"><?= date('D, d M Y', strtotime($ud['unavailable_date'])) ?></span>
                                        <span class="unavail-meta">
                                            <?= $ud['whole_day'] ? 'Full Day' : date('H:i', strtotime($ud['start_time'])) . ' - ' . date('H:i', strtotime($ud['end_time'])) ?> 
                                            • <?= htmlspecialchars($ud['reason']) ?>
                                        </span>
                                    </div>
                                    <a href="?delete_unavail=<?= $ud['id'] ?>" class="btn-del" title="Remove" onclick="return confirm('Remove this unavailable date?');">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- 14 Day Preview -->
                    <div class="card">
                        <div class="card-header">
                            <h2>2-Week Preview</h2>
                        </div>
                        <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                            <?php
                            $today = new DateTime();
                            for ($i = 1; $i <= 14; $i++) {
                                $check = clone $today;
                                $check->modify("+$i days");
                                $date_str = $check->format('Y-m-d');
                                $dow = $check->format('N');
                                
                                // Check specific unavailable
                                $u_stmt = $conn->prepare("SELECT reason, whole_day FROM doctor_unavailable_dates WHERE doctor_id=? AND unavailable_date=?");
                                $u_stmt->bind_param("is", $doctor_id, $date_str);
                                $u_stmt->execute();
                                $u_res = $u_stmt->get_result()->fetch_assoc();

                                echo '<div class="preview-row">';
                                echo '<span>' . $check->format('D, d M') . '</span>';
                                
                                if ($u_res) {
                                    echo '<span class="status-pill pill-unavail">Unavailable (' . ($u_res['whole_day'] ? 'All Day' : 'Partial') . ')</span>';
                                } elseif (isset($current_schedule[$dow])) {
                                    $s = $current_schedule[$dow];
                                    echo '<span class="status-pill pill-avail">' . substr($s['start_time'],0,5) . ' - ' . substr($s['end_time'],0,5) . '</span>';
                                } else {
                                    echo '<span style="color:#cbd5e1; font-size:0.8rem;">Off</span>';
                                }
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </main>
</div>

<script>
    function toggleDay(key) {
        const row = document.getElementById('row-' + key);
        row.classList.toggle('day-active');
    }

    function togglePartial(checkbox) {
        const div = document.getElementById('partial_time_inputs');
        div.style.display = checkbox.checked ? 'none' : 'block';
    }
</script>
</body>
</html>