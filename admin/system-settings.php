<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth($conn);

// Only admin access
if (!$auth->isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

$user = $auth->getUser();
$success = '';
$error = '';

// Fetch current system settings
$settings_query = "SELECT * FROM system_settings LIMIT 1";
$settings_result = $conn->query($settings_query);
$settings = $settings_result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clinic_name = trim($_POST['clinic_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $working_hours = trim($_POST['working_hours'] ?? '');
    $esewa_merchant_code = trim($_POST['esewa_merchant_code'] ?? '');

    if (empty($clinic_name) || empty($email) || empty($phone) || empty($address)) {
        $error = "All general information fields are required.";
    } else {
        if ($settings) {
            // Update existing settings
            $stmt = $conn->prepare("UPDATE system_settings SET clinic_name=?, email=?, phone=?, address=?, working_hours=?, esewa_merchant_code=? WHERE id=?");
            $stmt->bind_param("ssssssi", $clinic_name, $email, $phone, $address, $working_hours, $esewa_merchant_code, $settings['id']);
        } else {
            // Insert new settings
            $stmt = $conn->prepare("INSERT INTO system_settings (clinic_name, email, phone, address, working_hours, esewa_merchant_code) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $clinic_name, $email, $phone, $address, $working_hours, $esewa_merchant_code);
        }

        if ($stmt->execute()) {
            $success = "System configuration saved successfully.";
            // Refresh settings
            $settings_result = $conn->query($settings_query);
            $settings = $settings_result->fetch_assoc();
        } else {
            $error = "Database Error: " . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Admin</title>
    <link rel="stylesheet" href="../styles.css">
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
            --success: #10b981;
            --danger: #ef4444;
        }

        body.dashboard-page {
            background-color: var(--bg-body);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--text-main);
        }

        .manage-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }
        .page-header h1 { margin: 0; font-size: 1.75rem; font-weight: 700; color: var(--text-main); }
        .page-header p { margin: 0.25rem 0 0; color: var(--text-muted); }

        /* Card Styles */
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
        }
        .card-header h2 { margin: 0; font-size: 1.1rem; font-weight: 600; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem; }
        .card-body { padding: 2rem; }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        .full-width { grid-column: span 2; }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-main);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s;
            box-sizing: border-box;
            background-color: #fff;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-control.mono { font-family: monospace; letter-spacing: 0.05em; }

        textarea.form-control { resize: vertical; min-height: 100px; }

        .btn-submit {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-submit:hover { background: var(--primary-dark); }

        /* Alerts */
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
        }
    </style>
</head>
<body class="dashboard-page">
<div class="dashboard-container">
    <?php include __DIR__ . '/../include/sidebar-admin.php'; ?>
    
    <main class="dashboard-content">
        <?php include __DIR__ . '/../include/anav.php'; ?>
        
        <div class="manage-container">
            <div class="page-header">
                <h1>Configuration</h1>
                <p>Manage general clinic information and payment gateways.</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                
                <!-- General Settings Card -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-hospital-alt" style="color: var(--primary);"></i> General Information</h2>
                    </div>
                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="clinic_name">Clinic Name</label>
                                <input type="text" id="clinic_name" name="clinic_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['clinic_name'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="email">Official Email</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['email'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="phone">Contact Number</label>
                                <input type="tel" id="phone" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['phone'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group full-width">
                                <label for="address">Physical Address</label>
                                <textarea id="address" name="address" class="form-control" required><?php echo htmlspecialchars($settings['address'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group full-width">
                                <label for="working_hours">Operational Hours</label>
                                <input type="text" id="working_hours" name="working_hours" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['working_hours'] ?? ''); ?>" 
                                       placeholder="e.g. Sun - Fri: 9:00 AM - 5:00 PM">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Settings Card -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-wallet" style="color: var(--success);"></i> Payment Integration</h2>
                    </div>
                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="esewa_merchant_code">eSewa Merchant Code (Optional)</label>
                                <input type="text" id="esewa_merchant_code" name="esewa_merchant_code" class="form-control mono" 
                                       value="<?php echo htmlspecialchars($settings['esewa_merchant_code'] ?? ''); ?>" 
                                       placeholder="EPAYTEST (for sandbox)">
                                <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;">
                                    Leave blank if you wish to disable eSewa integration or use default testing credentials.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; margin-bottom: 3rem;">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>

            </form>
        </div>
    </main>
</div>
</body>
</html>