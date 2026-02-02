<?php


if (!isset($_SESSION['user_id'])) {
    header('Location: /clinic/login.php');
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
    <div class="sidebar-header">
        <h2>Receptionist Panel</h2>
    </div>

    <nav class="sidebar-nav">

        <a href="/clinic/receptionist/receptionist-dashboard.php"
           class="nav-item <?= $current_page === 'receptionist-dashboard.php' ? 'active' : '' ?>">
            <span>📊</span> Dashboard
        </a>

        <a href="/clinic/receptionist/manage-appointments.php"
           class="nav-item <?= $current_page === 'manage-appointments.php' ? 'active' : '' ?>">
            <span>📅</span> Appointments
        </a>

        <a href="/clinic/receptionist/handle-billing.php"
           class="nav-item <?= $current_page === 'handle-billing.php' ? 'active' : '' ?>">
            <span>💰</span> Billing
        </a>

        <a href="/clinic/logout.php" class="nav-item logout">
            <span>🚪</span> Logout
        </a>

    </nav>
</div>
