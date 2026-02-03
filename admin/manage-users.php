<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$auth = new Auth($conn);
$error = '';
$success = '';

// Only admins can access
if (!$auth->isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

function validateMobileNumber($number)
{
    $number = trim($number);
    return preg_match("/^(98|97)[0-9]{8}$/", $number);
}

function validateName($name)
{
    $name = trim($name);

    if (empty($name))
        return "Name is required.";
    if (!preg_match("/^[A-Za-z]+( [A-Za-z]+)*$/", $name))
        return "Name must contain only letters and spaces.";
    if (strlen($name) < 10 || strlen($name) > 30)
        return "Name must be between 10 and 30 characters.";

    return true;
}

// Handle Add or Update User
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $phone = $_POST['phone'] ?? '';

    if (validateName($name) !== true) {
        $error = validateName($name);
    }elseif (!validateMobileNumber($phone)) {
        $error = "Invalid  mobile number.";
    }
     elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/", $password)) {
        $error = "Password must contain uppercase, lowercase, number, and special character and be at least 8 characters.";
    }
    elseif (!preg_match("/^[a-zA-Z0-9._%+-]+@gmail\.com$/", $email)) {
        $error = "Invalid email .";
    } 
    elseif (empty($name) || empty($email) || empty($role)) {
        $error = 'Name, Email, and Role are required';
    } else {
        // Check duplicate email
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?" . ($id ? " AND id != ?" : ""));
        if ($id) {
            $stmt->bind_param("si", $email, $id);
        } else {
            $stmt->bind_param("s", $email);
        }
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = 'Email already exists!';
        } else {
            if ($id) {
                // Update user
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $query = "UPDATE users SET name=?, email=?, password=?, role=?, phone=? WHERE id=?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("sssssi", $name, $email, $hashed_password, $role, $phone, $id);
                } else {
                    $query = "UPDATE users SET name=?, email=?, role=?, phone=? WHERE id=?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ssssi", $name, $email, $role, $phone, $id);
                }
                if ($stmt->execute()) {
                    $success = 'User updated successfully!';
                } else {
                    $error = 'Database error: ' . $stmt->error;
                }
            } else {
                // Insert new user
                if (empty($password)) {
                    $error = 'Password is required for new users!';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $query = "INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("sssss", $name, $email, $phone, $hashed_password, $role);
                    if ($stmt->execute()) {
                        $new_user_id = $stmt->insert_id;
                        $success = ucfirst($role) . " created successfully!";

                        // Insert into role-specific tables
                        if ($role === 'doctor') {
                            $stmt_doc = $conn->prepare("INSERT INTO doctors (user_id, specialization, qualification, experience_years, consultation_fee) VALUES (?, 'General', 'MBBS', 0, 500)");
                            $stmt_doc->bind_param("i", $new_user_id);
                            $stmt_doc->execute();
                        } elseif ($role === 'patient') {
                            $stmt_patient = $conn->prepare("INSERT INTO patients (user_id, name) VALUES (?, ?)");
                            $stmt_patient->bind_param("is", $new_user_id, $name);
                            $stmt_patient->execute();
                        }
                    } else {
                        $error = "Database error: " . $stmt->error;
                    }
                }
            }
        }
    }
}

// Handle Delete User
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    if ($delete_id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account.";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            $success = 'User deleted successfully!';
        } else {
            $error = 'Error deleting user!';
        }
    }
}

// --- Pagination & Search Logic ---
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role_filter']) ? trim($_GET['role_filter']) : '';

$where_clauses = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_term = "%" . $search . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

if (!empty($role_filter) && in_array($role_filter, ['admin', 'doctor', 'patient'])) {
    $where_clauses[] = "role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// 1. Get Total Count
$count_query = "SELECT COUNT(*) as total FROM users $where_sql";
$stmt_count = $conn->prepare($count_query);
if (!empty($types)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_rows = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// 2. Fetch Users
$query = "SELECT * FROM users $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$users = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users - Admin</title>
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
        --warning: #f59e0b;
    }

    body.dashboard-page {
        background-color: var(--bg-body);
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        color: var(--text-main);
    }

    .manage-container {
        max-width: 1400px;
        margin: 2rem auto;
        padding: 0 1.5rem;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }
    .page-header h1 { margin: 0; font-size: 1.75rem; font-weight: 700; color: var(--text-main); }
    .page-header p { margin: 0.25rem 0 0; color: var(--text-muted); }

    /* Layout Grid */
    .admin-grid {
        display: grid;
        grid-template-columns: 350px 1fr;
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
        display: flex;
        flex-direction: column;
    }
    .card-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--border);
        background: #fdfdfd;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .card-header h2 { margin: 0; font-size: 1.1rem; font-weight: 600; color: var(--text-main); }
    .card-body { padding: 1.5rem; }

    /* Forms */
    .form-group { margin-bottom: 1.25rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500; color: var(--text-main); }
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
    .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }

    .btn-submit {
        width: 100%;
        background: var(--primary);
        color: white;
        border: none;
        padding: 0.75rem;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
        display: flex;
        justify-content: center;
        gap: 0.5rem;
    }
    .btn-submit:hover { background: var(--primary-dark); }

    /* Filter Bar */
    .filter-bar {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        flex-grow: 1;
        justify-content: flex-end;
    }
    .filter-input {
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.875rem;
        outline: none;
        max-width: 200px;
    }
    .filter-select {
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.875rem;
        outline: none;
        background: white;
    }
    .btn-filter {
        background: var(--primary);
        color: white;
        border: none;
        padding: 0.5rem 0.75rem;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.875rem;
    }

    /* Table */
    .table-responsive { overflow-x: auto; width: 100%; }
    .users-table { width: 100%; border-collapse: collapse; }
    .users-table th {
        background: #f8fafc;
        padding: 0.875rem 1.25rem;
        text-align: left;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        border-bottom: 1px solid var(--border);
    }
    .users-table td {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--border);
        color: var(--text-main);
        font-size: 0.9rem;
    }
    .users-table tr:last-child td { border-bottom: none; }
    .users-table tr:hover { background: #fcfcfc; }

    /* Badges */
    .role-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: capitalize;
    }
    .role-admin { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
    .role-doctor { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .role-patient { background: #f3f4f6; color: #1f2937; border: 1px solid #e5e7eb; }

    /* Action Buttons */
    .action-group { display: flex; gap: 0.5rem; }
    .btn-action {
        width: 32px; height: 32px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 6px;
        border: 1px solid var(--border);
        background: white;
        color: var(--text-muted);
        transition: all 0.2s;
        cursor: pointer;
    }
    .btn-edit:hover { border-color: var(--primary); color: var(--primary); background: #eff6ff; }
    .btn-delete:hover { border-color: var(--danger); color: var(--danger); background: #fef2f2; }

    /* Pagination */
    .pagination {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f8fafc;
    }
    .page-info { font-size: 0.85rem; color: var(--text-muted); }
    .page-links { display: flex; gap: 0.25rem; }
    .page-link {
        padding: 0.35rem 0.75rem;
        border: 1px solid var(--border);
        background: white;
        color: var(--text-main);
        text-decoration: none;
        border-radius: 6px;
        font-size: 0.85rem;
        transition: all 0.2s;
    }
    .page-link:hover { background: #f1f5f9; }
    .page-link.active { background: var(--primary); color: white; border-color: var(--primary); }
    .page-link.disabled { opacity: 0.5; pointer-events: none; }

    /* Modal */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(15, 23, 42, 0.5);
        backdrop-filter: blur(4px);
        z-index: 50;
        justify-content: center;
        align-items: center;
    }
    .modal-card {
        background: white;
        width: 100%;
        max-width: 500px;
        border-radius: 16px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        animation: slideUp 0.3s ease-out;
    }
    @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

    .modal-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex; justify-content: space-between; align-items: center;
        background: #f8fafc;
    }
    .modal-header h3 { margin: 0; font-size: 1.1rem; color: var(--text-main); }
    .close-modal { background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer; }
    
    /* Alerts */
    .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; }
    .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

    @media (max-width: 1024px) {
        .admin-grid { grid-template-columns: 1fr; }
        .users-table th, .users-table td { white-space: nowrap; }
        .filter-bar { width: 100%; justify-content: flex-start; margin-top: 1rem; }
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
                <div>
                    <h1>Manage Users</h1>
                    <p>Create, update, and manage system access.</p>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="admin-grid">
                
                <!-- Add User Card -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-user-plus"></i> Add New User</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="id" value="">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. John Doe" required  minlength="10"
                                maxlength="30" pattern="[A-Za-z]+( [A-Za-z]+)*"
                                        title="Only letters and spaces allowed (10–30 characters)">
                            </div>
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="email" class="form-control" placeholder="user@example.com" required pattern="[a-zA-Z0-9._%+-]+@gmail\.com" title="Invalid email format.">
                            </div>
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="tel" name="phone" class="form-control" placeholder="98XXXXXXXX" required pattern="(98|97)[0-9]{8}"
                                 title="Phone number must be  exactly 10 digits.">
                            </div>
                            <div class="form-group">
                                <label>Password</label>
                                <input type="password" name="password" class="form-control" placeholder="••••••••" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}"
                                 title="At least 8 chars, uppercase, lowercase, number, special character " required>
                            </div>
                            <div class="form-group">
                                <label>System Role</label>
                                <select name="role" class="form-control" required>
                                    <option value="" disabled selected>Select Role</option>
                                    <option value="patient">Patient</option>
                                    <option value="doctor">Doctor</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <button type="submit" class="btn-submit">
                                Create User <i class="fas fa-arrow-right"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Users List Card -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-users"></i> Registered Users</h2>
                        <form method="GET" class="filter-bar">
                            <input type="text" name="search" class="filter-input" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                            <select name="role_filter" class="filter-select">
                                <option value="">All Roles</option>
                                <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="doctor" <?= $role_filter === 'doctor' ? 'selected' : '' ?>>Doctor</option>
                                <option value="patient" <?= $role_filter === 'patient' ? 'selected' : '' ?>>Patient</option>
                            </select>
                            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i></button>
                            <?php if(!empty($search) || !empty($role_filter)): ?>
                                <a href="manage-users.php" class="btn-filter" style="background:#64748b; text-decoration:none;"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="table-responsive">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>User Details</th>
                                    <th>Role</th>
                                    <th>Phone</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($users->num_rows > 0): ?>
                                    <?php while ($user = $users->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600;"><?= htmlspecialchars($user['name']); ?></div>
                                            <div style="color: var(--text-muted); font-size: 0.85rem;"><?= htmlspecialchars($user['email']); ?></div>
                                        </td>
                                        <td>
                                            <span class="role-badge role-<?= htmlspecialchars($user['role']); ?>">
                                                <?= ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($user['phone'] ?? '-'); ?></td>
                                        <td>
                                            <div class="action-group">
                                                <button class="btn-action btn-edit" 
                                                    data-id="<?= $user['id']; ?>" 
                                                    data-name="<?= htmlspecialchars($user['name']); ?>"
                                                    data-email="<?= htmlspecialchars($user['email']); ?>"
                                                    data-role="<?= $user['role']; ?>"
                                                    data-phone="<?= htmlspecialchars($user['phone']); ?>"
                                                    title="Edit User">
                                                    <i class="fas fa-pen"></i>
                                                </button>
                                                
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <a href="?delete_id=<?= $user['id']; ?>" class="btn-action btn-delete"
                                                onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');"
                                                title="Delete User">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" style="text-align:center; padding: 2rem; color:var(--text-muted);">No users found matching your criteria.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <div class="page-info">
                            Showing page <?= $page ?> of <?= $total_pages ?> (Total <?= $total_rows ?> users)
                        </div>
                        <div class="page-links">
                            <a href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search) ?>&role_filter=<?= urlencode($role_filter) ?>" class="page-link <?= $page <= 1 ? 'disabled' : '' ?>">Prev</a>
                            
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&role_filter=<?= urlencode($role_filter) ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            
                            <a href="?page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search) ?>&role_filter=<?= urlencode($role_filter) ?>" class="page-link <?= $page >= $total_pages ? 'disabled' : '' ?>">Next</a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Edit User Details</h3>
            <button class="close-modal" id="closeModal">&times;</button>
        </div>
        <div class="card-body">
            <form method="POST" id="editForm">
                <input type="hidden" name="id" id="edit-id">
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" id="edit-name" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" id="edit-email" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" id="edit-phone" name="phone" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Role</label>
                    <select id="edit-role" name="role" class="form-control" required>
                        <option value="patient">Patient</option>
                        <option value="doctor">Doctor</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>New Password <span style="font-weight:400; color:var(--text-muted);">(Leave blank to keep current)</span></label>
                    <input type="password" id="edit-password" name="password" class="form-control" placeholder="••••••••">
                </div>
                
                <button type="submit" class="btn-submit">Update User</button>
            </form>
        </div>
    </div>
</div>

<script>
const editButtons = document.querySelectorAll('.btn-edit');
const modal = document.getElementById('editModal');
const closeModal = document.getElementById('closeModal');

editButtons.forEach(btn => {
    btn.addEventListener('click', () => {
        modal.style.display = 'flex';
        document.getElementById('edit-id').value = btn.dataset.id;
        document.getElementById('edit-name').value = btn.dataset.name;
        document.getElementById('edit-email').value = btn.dataset.email;
        document.getElementById('edit-phone').value = btn.dataset.phone;
        document.getElementById('edit-role').value = btn.dataset.role;
        document.getElementById('edit-password').value = ''; // Clear password field
    });
});

closeModal.addEventListener('click', () => modal.style.display = 'none');
window.onclick = function(event) {
    if (event.target == modal) modal.style.display = 'none';
}
</script>
</body>
</html>