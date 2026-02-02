<?php


if (!isset($_SESSION['user_id'])) {
    header('Location: /clinic/login.php');
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
    <div class="sidebar-header">
        <h2>Doctor Panel</h2>
    </div>

    <nav class="sidebar-nav">

        <a href="/clinic/doctor/dashboard.php"
           class="nav-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
            <span>📊</span> Dashboard
        </a>

        <a href="/clinic/doctor/availability.php" 
         class="nav-item <?= $current_page === 'availability.php' ? 'active' : '' ?>">
      <span>📅</span> My Availability
</a>



        <a href="/clinic/doctor/manage-records.php"
           class="nav-item <?= $current_page === 'manage-records.php' ? 'active' : '' ?>">
            <span>📝</span> Medical Records
        </a>

        

        <a href="/clinic/doctor/view-appointment.php"
           class="nav-item <?= $current_page === 'view-appointment.php' ? 'active' : '' ?>">
            <span>📅</span> View Appointments
        </a>

  

        <a href="/clinic/logout.php" class="nav-item logout">
            <span>🚪</span> Logout
        </a>

    </nav>
</div>
