<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Fetch existing patient data
$stmt = $conn->prepare("
    SELECT phone, date_of_birth, gender, address,
           emergency_phone, medical_history, blood_group, allergies
    FROM patients WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

$patient = $data ?? [
    'phone' => '',
    'date_of_birth' => '',
    'gender' => '',
    'address' => '',
    'emergency_phone' => '',
    'medical_history' => '',
    'blood_group' => '',
    'allergies' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $phone = trim($_POST['phone'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $emergency_phone = trim($_POST['emergency_phone'] ?? '');
    $medical_history = trim($_POST['medical_history'] ?? '');
    $blood_group = $_POST['blood_group'] ?? '';
    $allergies = trim($_POST['allergies'] ?? '');

    // Optional: Add phone validation
    if (!empty($phone) && !preg_match('/^[0-9+\-\s]{10}$/', $phone)) {
        $error = "Invalid phone number";
    }

    if (empty($error)) {
        // Use INSERT ... ON DUPLICATE KEY UPDATE to ensure only one row per user_id
        $stmt = $conn->prepare("
            INSERT INTO patients 
                (user_id, phone, date_of_birth, gender, address,
                 emergency_phone, medical_history, blood_group, allergies)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                phone = VALUES(phone),
                date_of_birth = VALUES(date_of_birth),
                gender = VALUES(gender),
                address = VALUES(address),
                emergency_phone = VALUES(emergency_phone),
                medical_history = VALUES(medical_history),
                blood_group = VALUES(blood_group),
                allergies = VALUES(allergies)
        ");

        $stmt->bind_param(
            "issssssss",
            $user_id, $phone, $date_of_birth, $gender,
            $address, $emergency_phone, $medical_history,
            $blood_group, $allergies
        );

        if ($stmt->execute()) {
            $_SESSION['success'] = "Profile updated successfully";
            header("Location: patient-dashboard.php");
            exit;
        } else {
            $error = "Failed to update profile";
        }
    }
}

// Calculate age
$age = '';
if (!empty($patient['date_of_birth'])) {
    $age = (new DateTime())->diff(new DateTime($patient['date_of_birth']))->y;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Profile</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background: #f4f6f9;
}
.profile-card {
    max-width: 820px;
    margin: 40px auto;
}
.profile-header {
    background: linear-gradient(135deg, #0d6efd, #0dcaf0);
    color: white;
    padding: 20px;
    border-radius: 10px 10px 0 0;
}
</style>
</head>

<body>

<div class="container profile-card">
    <div class="card shadow">
        <div class="profile-header">
            <h3 class="mb-0">👤 My Profile</h3>
            <small>Manage your personal & medical details</small>
        </div>

        <div class="card-body">

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" class="row g-3">

                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control"
                           value="<?= htmlspecialchars($patient['phone'] ?? '') ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Emergency Phone</label>
                    <input type="text" name="emergency_phone" class="form-control"
                           value="<?= htmlspecialchars($patient['emergency_phone'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control"
                           value="<?= htmlspecialchars($patient['date_of_birth'] ?? '') ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Age</label>
                    <input type="text" class="form-control" value="<?= $age ?>" disabled>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">Select</option>
                        <option value="male" <?= ($patient['gender'] ?? '')==='male'?'selected':'' ?>>Male</option>
                        <option value="female" <?= ($patient['gender'] ?? '')==='female'?'selected':'' ?>>Female</option>
                        <option value="other" <?= ($patient['gender'] ?? '')==='other'?'selected':'' ?>>Other</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($patient['address'] ?? '') ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Blood Group</label>
                    <select name="blood_group" class="form-select">
                        <option value="">Select</option>
                        <?php
                        foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg) {
                            $sel = (($patient['blood_group'] ?? '') === $bg) ? 'selected' : '';
                            echo "<option value='$bg' $sel>$bg</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Medical History</label>
                    <textarea name="medical_history" class="form-control" rows="3"><?= htmlspecialchars($patient['medical_history'] ?? '') ?></textarea>
                </div>

                <div class="col-12">
                    <label class="form-label">Allergies</label>
                    <textarea name="allergies" class="form-control" rows="2"><?= htmlspecialchars($patient['allergies'] ?? '') ?></textarea>
                </div>

                <div class="col-12 text-end">
                    <button class="btn btn-primary px-4">Save Profile</button>
                    <a href="patient-dashboard.php" class="btn btn-secondary ms-2">Back</a>
                </div>

            </form>
        </div>
    </div>
</div>

</body>
</html>