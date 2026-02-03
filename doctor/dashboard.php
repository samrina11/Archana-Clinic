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
$page_title = 'Doctor Dashboard';

/* =========================
   FETCH DOCTOR PROFILE
========================= */
$stmt = $conn->prepare("
    SELECT d.*, u.name 
    FROM doctors d 
    JOIN users u ON d.user_id = u.id
    WHERE d.user_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

if (!$doctor) {
    die("Doctor profile not found. Please contact admin.");
}

$doctor_id = (int)$doctor['id'];

/* =========================
   STATS
========================= */
function getCount($conn, $table, $where = '') {
    $sql = "SELECT COUNT(*) AS count FROM $table";
    if ($where) $sql .= " WHERE $where";
    $res = $conn->query($sql);
    return $res ? (int)$res->fetch_assoc()['count'] : 0;
}

$total_appointments = getCount(
    $conn,
    'appointments',
    "doctor_id = $doctor_id"
);

$upcoming_appointments = getCount(
    $conn,
    'appointments',
    "doctor_id = $doctor_id AND appointment_datetime >= CURDATE() AND status != 'cancelled'"
);

// Count pending appointments specifically
$pending_appointments = getCount(
    $conn,
    'appointments',
    "doctor_id = $doctor_id AND status = 'pending'"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?> - Archana Clinic</title>
<link rel="stylesheet" href="/clinic/styles.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    :root {
        --primary: #2563eb;
        --primary-dark: #1d4ed8;
        --secondary: #64748b;
        --bg-body: #f8fafc;
        --bg-card: #ffffff;
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border: #e2e8f0;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
        --purple: #8b5cf6;
    }

    body.dashboard-page {
        background-color: var(--bg-body);
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        color: var(--text-main);
    }

    .dashboard-wrapper {
        max-width: 1400px;
        margin: 0 auto;
        padding: 2rem;
    }

    /* Header */
    .welcome-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2.5rem;
    }
    .welcome-text h1 {
        font-size: 1.75rem;
        font-weight: 700;
        margin: 0;
        color: var(--text-main);
    }
    .welcome-text p {
        color: var(--text-muted);
        margin: 0.25rem 0 0;
        font-size: 0.95rem;
    }
    .date-badge {
        background: white;
        padding: 0.5rem 1rem;
        border-radius: 50px;
        border: 1px solid var(--border);
        font-size: 0.9rem;
        font-weight: 500;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.5rem;
        margin-bottom: 3rem;
    }
    .stat-card {
        background: var(--bg-card);
        padding: 1.5rem;
        border-radius: 16px;
        border: 1px solid var(--border);
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    }
    
    .stat-icon {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    
    .icon-blue { background: #eff6ff; color: var(--primary); }
    .icon-purple { background: #f5f3ff; color: var(--purple); }
    .icon-green { background: #ecfdf5; color: var(--success); }
    .icon-orange { background: #fff7ed; color: var(--warning); }

    .stat-content h3 { margin: 0; font-size: 0.875rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.025em; }
    .stat-content .value { font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin-top: 0.25rem; display: block; }

    /* Quick Actions Section */
    .section-header { margin-bottom: 1.5rem; }
    .section-header h2 { font-size: 1.25rem; font-weight: 600; color: var(--text-main); margin: 0; }

    .actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
    }

    .action-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        text-decoration: none;
        transition: all 0.2s;
    }
    .action-card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.1);
        transform: translateY(-2px);
    }

    .action-icon-circle {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: #f1f5f9;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        transition: all 0.2s;
    }
    .action-card:hover .action-icon-circle {
        background: var(--primary);
        color: white;
    }

    .action-info h4 { margin: 0; font-size: 1rem; font-weight: 600; color: var(--text-main); }
    .action-info p { margin: 0.25rem 0 0; font-size: 0.875rem; color: var(--text-muted); }

    @media (max-width: 768px) {
        .welcome-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
        .stats-grid { grid-template-columns: 1fr; }
    }
</style>
</head>

<body class="dashboard-page">
<div class="dashboard-container">

    <!-- Sidebar -->
    <?php include __DIR__ . '/../include/sidebar-doctor.php'; ?>

    <!-- Main Content -->
    <main class="dashboard-content">

        <!-- Top Navigation -->
        <?php include __DIR__ . '/../include/dnav.php'; ?>

        <div class="dashboard-wrapper">
            
            <!-- Welcome Header -->
            <div class="welcome-header">
                <div class="welcome-text">
                    <h1>Doctor Dashboard</h1>
                    <p>Welcome <?= htmlspecialchars($user['name']) ?>. Here is your practice overview.</p>
                </div>
                <div class="date-badge">
                    <i class="far fa-calendar-alt"></i>
                    <span><?= date('l, F j, Y') ?></span>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon icon-blue"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-content">
                        <h3>Total Appointments</h3>
                        <span class="value"><?= $total_appointments ?></span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon icon-green"><i class="fas fa-clock"></i></div>
                    <div class="stat-content">
                        <h3>Upcoming</h3>
                        <span class="value"><?= $upcoming_appointments ?></span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon icon-orange"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-content">
                        <h3>Pending Request</h3>
                        <span class="value"><?= $pending_appointments ?></span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon icon-purple"><i class="fas fa-user-md"></i></div>
                    <div class="stat-content">
                        <h3>Specialization</h3>
                        <span class="value" style="font-size: 1.1rem;"><?= htmlspecialchars($doctor['specialization']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Grid -->
            <div class="section-header">
                <h2>Quick Actions</h2>
            </div>

            <div class="actions-grid">
                
                <a href="/clinic/doctor/view-appointment.php" class="action-card">
                    <div class="action-icon-circle"><i class="fas fa-calendar-alt"></i></div>
                    <div class="action-info">
                        <h4>View Appointments</h4>
                        <p>Check your schedule and patient visits.</p>
                    </div>
                </a>

                <a href="/clinic/doctor/manage-records.php" class="action-card">
                    <div class="action-icon-circle"><i class="fas fa-notes-medical"></i></div>
                    <div class="action-info">
                        <h4>Medical Records</h4>
                        <p>Access and update patient history.</p>
                    </div>
                </a>

                <!-- Placeholder for future features -->
                <a href="/clinic/doctor/profile.php" class="action-card">
                    <div class="action-icon-circle"><i class="fas fa-user-cog"></i></div>
                    <div class="action-info">
                        <h4>My Profile</h4>
                        <p>Update your details and availability.</p>
                    </div>
                </a>

            </div>

        </div>
    </main>
</div>
</body>
</html>