<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth($conn);

// Check login and role
if (!$auth->isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();
$page_title = 'Admin Dashboard';

// Helper function for counts
function getCount($conn, $table, $where = '') {
    $sql = "SELECT COUNT(*) AS count FROM $table";
    if ($where) $sql .= " WHERE $where";
    $result = $conn->query($sql);
    if ($result) return $result->fetch_assoc()['count'];
    return 0;
}

$total_patients = getCount($conn, 'patients');
$total_doctors = getCount($conn, 'doctors');
$total_appointments = getCount($conn, 'appointments');
$pending_bills = getCount($conn, 'billing', "status='unpaid'");

// --- Fetch Data for Income Graph (Last 6 Months) ---
$income_months = [];
$income_totals = [];

// Get last 6 months dynamically
for ($i = 5; $i >= 0; $i--) {
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end   = date('Y-m-t', strtotime("-$i months"));
    $month_label = date('M', strtotime("-$i months"));
    
    $stmt = $conn->prepare("
        SELECT SUM(amount) as total 
        FROM billing 
        WHERE status = 'paid' 
        AND payment_date BETWEEN ? AND ?
    ");
    // Append time to ensure full day coverage
    $start_dt = $month_start . " 00:00:00";
    $end_dt = $month_end . " 23:59:59";
    
    $stmt->bind_param("ss", $start_dt, $end_dt);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    
    $income_months[] = $month_label;
    $income_totals[] = $res['total'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Archana Clinic</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
        .stat-content .value { font-size: 1.75rem; font-weight: 700; color: var(--text-main); margin-top: 0.25rem; display: block; }

        /* Chart Section */
        .chart-section {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .chart-header h2 { margin: 0; font-size: 1.25rem; font-weight: 600; color: var(--text-main); }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

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
    <?php include __DIR__ . '/../include/sidebar-admin.php'; ?>

    <!-- Main Content -->
    <main class="dashboard-content">
        
        <!-- Top Nav -->
        <?php include __DIR__ . '/../include/anav.php'; ?>

        <div class="dashboard-wrapper">
            
            <!-- Welcome Header -->
            <div class="welcome-header">
                <div class="welcome-text">
                    <h1>Admin Overview</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($user['name'] ?? 'Admin'); ?>. Here's what's happening today.</p>
                </div>
                <div class="date-badge">
                    <i class="far fa-calendar-alt"></i>
                    <span><?php echo date('l, F j, Y'); ?></span>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon icon-blue"><i class="fas fa-users"></i></div>
                    <div class="stat-content">
                        <h3>Total Patients</h3>
                        <span class="value"><?php echo $total_patients; ?></span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon icon-purple"><i class="fas fa-user-md"></i></div>
                    <div class="stat-content">
                        <h3>Active Doctors</h3>
                        <span class="value"><?php echo $total_doctors; ?></span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon icon-green"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-content">
                        <h3>Appointments</h3>
                        <span class="value"><?php echo $total_appointments; ?></span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon icon-orange"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div class="stat-content">
                        <h3>Pending Bills</h3>
                        <span class="value"><?php echo $pending_bills; ?></span>
                    </div>
                </div>
            </div>

            <!-- Income Graph -->
            <div class="chart-section">
                <div class="chart-header">
                    <h2>Revenue Overview</h2>
                    <select style="padding: 0.4rem; border-radius: 6px; border: 1px solid var(--border); font-size: 0.85rem; color: var(--text-muted);">
                        <option>Last 6 Months</option>
                    </select>
                </div>
                <div class="chart-container">
                    <canvas id="incomeChart"></canvas>
                </div>
            </div>

            <!-- Quick Actions Grid -->
            <div class="section-header">
                <h2>Quick Management</h2>
            </div>
            
            <div class="actions-grid">
                <!-- User Management -->
                <a href="manage-users.php" class="action-card">
                    <div class="action-icon-circle"><i class="fas fa-user-plus"></i></div>
                    <div class="action-info">
                        <h4>Manage Users</h4>
                        <p>Add, edit, or remove system users.</p>
                    </div>
                </a>

                <!-- Scheduling -->
                <a href="view-appointment.php" class="action-card">
                    <div class="action-icon-circle"><i class="fas fa-calendar-plus"></i></div>
                    <div class="action-info">
                        <h4>Appointments</h4>
                        <p>View bookings and doctor schedules.</p>
                    </div>
                </a>

                <!-- Billing -->
                <a href="billing.php" class="action-card">
                    <div class="action-icon-circle"><i class="fas fa-credit-card"></i></div>
                    <div class="action-info">
                        <h4>Billing System</h4>
                        <p>Create invoices and track payments.</p>
                    </div>
                </a>

                <!-- Medical Records -->
                <a href="medical-records.php" class="action-card">
                    <div class="action-icon-circle"><i class="fas fa-notes-medical"></i></div>
                    <div class="action-info">
                        <h4>Medical Records</h4>
                        <p>Access patient history and logs.</p>
                    </div>
                </a>
            </div>

        </div>
    </main>
</div>

<!-- Chart Script -->
<script>
    const ctx = document.getElementById('incomeChart').getContext('2d');
    
    // Gradient Background
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(37, 99, 235, 0.2)');
    gradient.addColorStop(1, 'rgba(37, 99, 235, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($income_months) ?>,
            datasets: [{
                label: 'Revenue (Rs)',
                data: <?= json_encode($income_totals) ?>,
                borderColor: '#2563eb',
                backgroundColor: gradient,
                borderWidth: 2,
                tension: 0.4, // Curve
                fill: true,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#2563eb',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    padding: 12,
                    titleFont: { size: 13 },
                    bodyFont: { size: 13 },
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return 'Rs. ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#f1f5f9',
                        borderDash: [5, 5]
                    },
                    ticks: {
                        color: '#64748b',
                        font: { family: 'Inter' }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#64748b',
                        font: { family: 'Inter' }
                    }
                }
            }
        }
    });
</script>
</body>
</html>