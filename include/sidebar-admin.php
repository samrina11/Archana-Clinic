<?php


if (!isset($_SESSION['user_id'])) {
    header('Location: /clinic/login.php');
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
    <div class="sidebar-header">
        <h2>Admin Panel</h2>
    </div>

    <nav class="sidebar-nav">

        <a href="/clinic/admin/dashboard.php"
           class="nav-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
            <span>📊</span> Dashboard
        </a>

        <a href="/clinic/admin/manage-users.php"
           class="nav-item <?= $current_page === 'manage-users.php' ? 'active' : '' ?>">
            <span>👥</span> Manage Users
        </a>

        <a href="/clinic/admin/billing.php"
           class="nav-item <?= $current_page === 'billing.php' ? 'active' : '' ?>">
            <span>💰</span> Billing
        </a>

        <a href="/clinic/admin/view-appointment.php"
           class="nav-item <?= $current_page === 'view-appointment.php' ? 'active' : '' ?>">
            <span>🛏️</span> View Appointments
        </a>

        <a href="/clinic/admin/system-settings.php"
           class="nav-item <?= $current_page === 'system-settings.php' ? 'active' : '' ?>">
            <span>⚙️</span> System Settings
        </a>

        <a href="/clinic/logout.php" class="nav-item logout">
            <span>🚪</span> Logout
        </a>

    </nav>
</div>
