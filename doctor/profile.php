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
$user_id = $user['id'];
$success_msg = '';
$error_msg = '';

/* =========================
   HANDLE FORM SUBMISSION
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $specialization = trim($_POST['specialization']);
    $qualification = trim($_POST['qualification']);
    $experience = (int) $_POST['experience'];
    $fee = (float) $_POST['fee'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if (empty($name) || empty($email)) {
        $error_msg = "Name and Email are required.";
    } else {
        $conn->begin_transaction();
        try {
            // 1. Update User Info
            $user_sql = "UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?";
            $stmt = $conn->prepare($user_sql);
            $stmt->bind_param("sssi", $name, $email, $phone, $user_id);
            if (!$stmt->execute()) throw new Exception("Failed to update user info.");

            // 2. Update Password (if provided)
            if (!empty($new_pass)) {
                if (strlen($new_pass) < 6) {
                    throw new Exception("Password must be at least 6 characters.");
                }
                if ($new_pass !== $confirm_pass) {
                    throw new Exception("Passwords do not match.");
                }
                $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                $pass_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $pass_stmt->bind_param("si", $hashed, $user_id);
                if (!$pass_stmt->execute()) throw new Exception("Failed to update password.");
            }

            // 3. Update Doctor Info
            $doc_sql = "UPDATE doctors SET specialization = ?, qualification = ?, experience_years = ?, consultation_fee = ? WHERE user_id = ?";
            $stmt = $conn->prepare($doc_sql);
            $stmt->bind_param("ssidi", $specialization, $qualification, $experience, $fee, $user_id);
            if (!$stmt->execute()) throw new Exception("Failed to update professional details.");

            $conn->commit();
            $success_msg = "Profile updated successfully.";
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = $e->getMessage();
        }
    }
}

/* =========================
   FETCH CURRENT DATA
========================= */
$stmt = $conn->prepare("
    SELECT u.name, u.email, u.phone, d.specialization, d.qualification, d.experience_years, d.consultation_fee
    FROM users u
    JOIN doctors d ON u.id = d.user_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile - Archana Clinic</title>
<link rel="stylesheet" href="/clinic/styles.css">
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
        max-width: 1000px;
        margin: 2rem auto;
        padding: 0 1.5rem;
    }

    .page-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
    }
    .profile-avatar-large {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: #e0e7ff;
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        font-weight: 700;
        border: 4px solid #ffffff;
        box-shadow: 0 0 0 1px var(--border);
    }
    .header-content h1 { margin: 0; font-size: 1.5rem; font-weight: 700; color: var(--text-main); }
    .header-content p { margin: 0.25rem 0 0; color: var(--text-muted); }

    /* Layout */
    .profile-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 2rem;
    }

    /* Cards */
    .card {
        background: var(--bg-card);
        border-radius: 12px;
        border: 1px solid var(--border);
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .card-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--border);
        background: #fdfdfd;
        display: flex; align-items: center; gap: 0.5rem;
    }
    .card-header h2 { margin: 0; font-size: 1.1rem; font-weight: 600; color: var(--text-main); }
    .card-body { padding: 1.5rem; }

    /* Forms */
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
    }
    .col-span-2 { grid-column: span 2; }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-main);
    }
    .form-control {
        width: 100%;
        padding: 0.65rem 1rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.95rem;
        transition: border-color 0.2s;
        box-sizing: border-box;
        background: #fff;
    }
    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }
    .form-control:disabled {
        background: #f1f5f9;
        color: var(--text-muted);
    }

    .btn-save {
        background: var(--primary);
        color: white;
        border: none;
        padding: 0.75rem 2rem;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .btn-save:hover { background: var(--primary-dark); }

    /* Alerts */
    .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; }
    .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

    @media (min-width: 1024px) {
        .profile-grid { grid-template-columns: 2fr 1fr; }
    }
    @media (max-width: 768px) {
        .form-grid { grid-template-columns: 1fr; }
        .col-span-2 { grid-column: span 1; }
    }
</style>
</head>

<body class="dashboard-page">
<div class="dashboard-container">

    <?php include __DIR__ . '/../include/sidebar-doctor.php'; ?>

    <main class="dashboard-content">
        <?php include __DIR__ . '/../include/dnav.php'; ?>

        <div class="manage-container">
            
            <div class="page-header">
                <div class="profile-avatar-large">
                    <?= strtoupper(substr($profile['name'] ?? 'D', 0, 1)) ?>
                </div>
                <div class="header-content">
                    <h1>My Profile</h1>
                    <p>Manage your account settings and professional details.</p>
                </div>
            </div>

            <?php if ($success_msg): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_msg) ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_msg) ?></div>
            <?php endif; ?>

            <form method="POST" class="profile-grid">
                
                <!-- LEFT COLUMN: Main Info -->
                <div style="display: flex; flex-direction: column; gap: 2rem;">
                    
                    <!-- Personal Details -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-user-circle" style="color: var(--primary);"></i>
                            <h2>Personal Information</h2>
                        </div>
                        <div class="card-body">
                            <div class="form-grid">
                                <div class="form-group col-span-2">
                                    <label>Full Name</label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($profile['name']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Email Address</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($profile['email']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Phone Number</label>
                                    <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($profile['phone']) ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Professional Details -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-briefcase-medical" style="color: var(--primary);"></i>
                            <h2>Professional Details</h2>
                        </div>
                        <div class="card-body">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Specialization</label>
                                    <input type="text" name="specialization" class="form-control" value="<?= htmlspecialchars($profile['specialization']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Qualification</label>
                                    <input type="text" name="qualification" class="form-control" value="<?= htmlspecialchars($profile['qualification']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Experience (Years)</label>
                                    <input type="number" name="experience" class="form-control" value="<?= htmlspecialchars($profile['experience_years']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Consultation Fee (Rs.)</label>
                                    <input type="number" step="0.01" name="fee" class="form-control" value="<?= htmlspecialchars($profile['consultation_fee']) ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- RIGHT COLUMN: Security -->
                <div>
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-lock" style="color: var(--primary);"></i>
                            <h2>Security</h2>
                        </div>
                        <div class="card-body">
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                                Leave these fields blank if you do not want to change your password.
                            </p>
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" class="form-control" placeholder="••••••••">
                            </div>
                            <div class="form-group">
                                <label>Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="••••••••">
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 2rem; display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </main>
</div>
</body>
</html>