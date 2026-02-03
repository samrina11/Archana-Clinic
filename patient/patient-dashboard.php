<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth($conn);

// Check login and role
if (!$auth->isLoggedIn() || $_SESSION['role'] !== 'patient') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// 1. Fetch Patient Info
$stmt = $conn->prepare("
    SELECT u.name, u.email, p.id AS patient_id, p.phone, p.gender, p.date_of_birth, 
           p.medical_history, p.allergies, p.emergency_contact, p.blood_group, p.address
    FROM users u
    LEFT JOIN patients p ON u.id = p.user_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    // Fallback if patient record is missing (should redirect to profile completion in real app)
    $patient = ['name' => 'Patient', 'email' => '', 'patient_id' => 0];
}

// 2. Fetch Appointments
$stmt = $conn->prepare("
    SELECT a.*, d.specialization, u.name AS doctor_name 
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_datetime DESC
    LIMIT 5
");
$stmt->bind_param("i", $patient['patient_id']);
$stmt->execute();
$appointments_res = $stmt->get_result();

// 3. Stats Calculation (Optional - requires separate queries or fetching all appts)
// For this demo, we'll fetch counts
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM appointments WHERE patient_id = ?");
$count_stmt->bind_param("i", $patient['patient_id']);
$count_stmt->execute();
$total_appts = $count_stmt->get_result()->fetch_assoc()['total'];

$upcoming_stmt = $conn->prepare("SELECT COUNT(*) as upcoming FROM appointments WHERE patient_id = ? AND appointment_datetime > NOW() AND status != 'cancelled'");
$upcoming_stmt->bind_param("i", $patient['patient_id']);
$upcoming_stmt->execute();
$upcoming_count = $upcoming_stmt->get_result()->fetch_assoc()['upcoming'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Archana Polyclinic</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --secondary: #64748b;
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
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
            margin-bottom: 2rem;
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
            margin-bottom: 2rem;
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
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .icon-blue { background: #eff6ff; color: var(--primary); }
        .icon-green { background: #ecfdf5; color: var(--success); }
        .icon-purple { background: #f3e8ff; color: #9333ea; }

        .stat-content h3 { margin: 0; font-size: 0.875rem; color: var(--text-muted); font-weight: 500; }
        .stat-content .value { font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin-top: 0.25rem; display: block; }

        /* Main Grid Layout */
        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        /* Sections */
        .dashboard-card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header h2 { margin: 0; font-size: 1.125rem; font-weight: 600; }
        .card-body { padding: 1.5rem; }

        /* Appointments Table */
        .table-responsive { overflow-x: auto; }
        .app-table { width: 100%; border-collapse: collapse; }
        .app-table th {
            text-align: left;
            padding: 0.75rem 1rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            background: #f8fafc;
            border-bottom: 1px solid var(--border);
        }
        .app-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            color: var(--text-main);
            font-size: 0.95rem;
        }
        .app-table tr:last-child td { border-bottom: none; }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        .status-confirmed { background: #ecfdf5; color: #047857; }
        .status-pending { background: #fffbeb; color: #b45309; }
        .status-cancelled { background: #fef2f2; color: #b91c1c; }
        .status-completed { background: #eff6ff; color: #1e40af; }

        /* Profile Details */
        .profile-list { list-style: none; padding: 0; margin: 0; }
        .profile-list li {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px dashed var(--border);
            font-size: 0.95rem;
        }
        .profile-list li:last-child { border-bottom: none; }
        .profile-label { color: var(--text-muted); }
        .profile-value { font-weight: 500; color: var(--text-main); }

        /* Quick Actions Grid */
        .actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .action-card {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem;
            text-align: center;
            text-decoration: none;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
        }
        .action-card:hover {
            background: white;
            border-color: var(--primary);
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.1);
            transform: translateY(-2px);
        }
        .action-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e7ff;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }
        .action-text {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .btn-link { color: var(--primary); text-decoration: none; font-size: 0.9rem; font-weight: 500; }
        .btn-link:hover { text-decoration: underline; }

        @media (max-width: 1024px) {
            .main-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="dashboard-page">
<div class="dashboard-container">

    <!-- Sidebar -->
    <?php include __DIR__ . '/../include/sidebar-patient.php'; ?>

    <!-- Main Content -->
    <main class="dashboard-content">
        <?php include __DIR__ . '/../include/nav.php'; ?>

        <div class="dashboard-wrapper">
            <!-- Welcome Header -->
            <div class="welcome-header">
                <div class="welcome-text">
                    <h1>Hello, <?= htmlspecialchars(explode(' ', $patient['name'])[0]) ?>! 👋</h1>
                    <p>Welcome back to your health dashboard.</p>
                </div>
                <div class="date-badge">
                    <i class="far fa-calendar-alt"></i> <?= date('l, F j, Y') ?>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon icon-blue"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-content">
                        <h3>Total Appointments</h3>
                        <span class="value"><?= $total_appts ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-green"><i class="fas fa-clock"></i></div>
                    <div class="stat-content">
                        <h3>Upcoming Visits</h3>
                        <span class="value"><?= $upcoming_count ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-purple"><i class="fas fa-file-medical-alt"></i></div>
                    <div class="stat-content">
                        <h3>Medical Records</h3>
                        <span class="value">View</span>
                    </div>
                </div>
            </div>

            <div class="main-grid">
                <!-- Left Column -->
                <div class="left-col">
                    <!-- Recent Appointments -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h2>Recent Appointments</h2>
                            <a href="manage-appointments.php" class="btn-link">View All</a>
                        </div>
                        <div class="table-responsive">
                            <table class="app-table">
                                <thead>
                                    <tr>
                                        <th>Doctor</th>
                                        <th>Date</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($appointments_res && $appointments_res->num_rows > 0): ?>
                                        <?php while ($row = $appointments_res->fetch_assoc()): 
                                            $statusClass = 'status-' . strtolower($row['status']);
                                        ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 500;"><?= htmlspecialchars($row['doctor_name']) ?></div>
                                                <div style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($row['specialization']) ?></div>
                                            </td>
                                            <td>
                                                <div><?= date('M d, Y', strtotime($row['appointment_datetime'])) ?></div>
                                                <div style="font-size: 0.8rem; color: var(--text-muted);"><?= date('h:i A', strtotime($row['appointment_datetime'])) ?></div>
                                            </td>
                                            <td><?= htmlspecialchars(substr($row['notes'], 0, 30)) . (strlen($row['notes']) > 30 ? '...' : '') ?></td>
                                            <td><span class="status-badge <?= $statusClass ?>"><?= ucfirst($row['status']) ?></span></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                                No appointment history found.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="right-col">
                    <!-- Quick Actions -->
                    <div class="dashboard-card">
                        <div class="card-header"><h2>Quick Actions</h2></div>
                        <div class="card-body">
                            <div class="actions-grid">
                                <a href="book-appointment.php" class="action-card">
                                    <div class="action-icon"><i class="fas fa-calendar-plus"></i></div>
                                    <span class="action-text">Book New</span>
                                </a>
                                <a href="pay-bills.php" class="action-card">
                                    <div class="action-icon"><i class="fas fa-credit-card"></i></div>
                                    <span class="action-text">Pay Bills</span>
                                </a>
                                <a href="medical-records.php" class="action-card">
                                    <div class="action-icon"><i class="fas fa-file-medical"></i></div>
                                    <span class="action-text">Records</span>
                                </a>
                                <a href="manage-appointments.php" class="action-card">
                                    <div class="action-icon"><i class="fas fa-history"></i></div>
                                    <span class="action-text">History</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Summary -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h2>My Profile</h2>
                            <a href="profile.php" class="btn-link">Edit</a>
                        </div>
                        <div class="card-body">
                            <ul class="profile-list">
                                <li>
                                    <span class="profile-label">Full Name</span>
                                    <span class="profile-value"><?= htmlspecialchars($patient['name']) ?></span>
                                </li>
                                <li>
                                    <span class="profile-label">Phone</span>
                                    <span class="profile-value"><?= htmlspecialchars($patient['phone']) ?></span>
                                </li>
                                <li>
                                    <span class="profile-label">Blood Group</span>
                                    <span class="profile-value"><?= htmlspecialchars($patient['blood_group'] ?? '-') ?></span>
                                </li>
                                <li>
                                    <span class="profile-label">Emergency</span>
                                    <span class="profile-value"><?= htmlspecialchars($patient['emergency_contact'] ?? '-') ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>