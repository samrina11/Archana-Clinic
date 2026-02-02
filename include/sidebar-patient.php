<?php
// Ensure this file is included within a page that has started session and auth check
// We get $current_page from the parent file usually, but define fallback here
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
    <div class="sidebar-header">
        <h2><i class="fas fa-heartbeat"></i> Patient Portal</h2>
    </div>

    <nav class="sidebar-nav">

        <a href="/clinic/patient/patient-dashboard.php"
           class="nav-item <?= $current_page === 'patient-dashboard.php' ? 'active' : '' ?>">
            <span>📊</span> Dashboard
        </a>

        <!-- <a href="/clinic/patient/book-appointment.php"
           class="nav-item <?= $current_page === 'book-appointment.php' ? 'active' : '' ?>">
            <span>📅</span> Book New
        </a> -->

        <!-- Consolidated Management Link -->
        <a href="/clinic/patient/manage-appointments.php"
           class="nav-item <?= $current_page === 'manage-appointments.php' ? 'active' : '' ?>">
            <span>📅</span> Appointments
        </a>

        <a href="/clinic/patient/medical-records.php"
           class="nav-item <?= $current_page === 'medical-records.php' ? 'active' : '' ?>">
            <span>🗂️</span> Medical Records
        </a>

        <a href="/clinic/patient/pay-bills.php"
           class="nav-item <?= $current_page === 'pay-bills.php' ? 'active' : '' ?>">
            <span>💳</span> Payments
        </a>

        <a href="/clinic/patient/profile.php"
           class="nav-item <?= $current_page === 'profile.php' ? 'active' : '' ?>">
            <span>👤</span> My Profile
        </a>

        <a href="/clinic/logout.php" class="nav-item logout">
            <span>🚪</span> Logout
        </a>

    </nav>
</div>