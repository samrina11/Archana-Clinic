<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure Auth/DB connection if not already present
if (!isset($auth) && isset($conn)) {
    require_once __DIR__ . '/../config/auth.php';
    $auth = new Auth($conn);
}

// Fail-safe if $user isn't set yet
if (!isset($user)) {
    $user = $auth->getUser();
}
?>
<style>
    /* Scoped styles for Top Navbar to ensure consistency across pages */
    .top-navbar {
        background: white;
        height: 64px;
        padding: 0 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid #e2e8f0;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        position: sticky;
        top: 0;
        z-index: 40;
    }

    .nav-brand {
        font-size: 1.125rem;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        text-decoration: none;
    }
    
    .nav-brand i {
        color: #2563eb; /* Primary color */
        font-size: 1.25rem;
    }

    .nav-right-section {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }

    .nav-user-profile {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        text-decoration: none;
        padding: 0.25rem 0.5rem;
        border-radius: 8px;
        transition: background-color 0.2s;
    }
    
    .nav-user-profile:hover {
        background-color: #f8fafc;
    }

    .nav-avatar-circle {
        width: 36px;
        height: 36px;
        background: #eff6ff; /* Light blue bg */
        color: #2563eb; /* Primary text */
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.9rem;
        border: 1px solid #dbeafe;
    }

    .nav-user-meta {
        display: flex;
        flex-direction: column;
        line-height: 1.2;
    }

    .nav-user-name {
        font-size: 0.875rem;
        font-weight: 600;
        color: #0f172a;
    }

    .nav-user-subtitle {
        font-size: 0.75rem;
        color: #64748b;
    }

    .nav-separator {
        width: 1px;
        height: 24px;
        background-color: #e2e8f0;
    }

    .nav-logout-btn {
        color: #64748b;
        font-size: 1rem;
        padding: 0.5rem;
        border-radius: 50%;
        transition: all 0.2s;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .nav-logout-btn:hover {
        background-color: #fef2f2;
        color: #ef4444;
    }

    @media (max-width: 640px) {
        .nav-brand span { display: none; } /* Hide text on mobile */
        .nav-user-meta { display: none; }
    }
</style>

<nav class="top-navbar">
    <!-- Brand / Logo -->
    <a href="#" class="nav-brand">
        <i class="fas fa-heartbeat"></i>
        <span>Archana Clinic</span>
    </a>

    <!-- Right Side Actions -->
    <div class="nav-right-section">
        <!-- User Profile Link -->
        <a href="profile.php" class="nav-user-profile" title="View Profile">
            <div class="nav-avatar-circle">
                <?= isset($user['name']) ? strtoupper(substr($user['name'], 0, 1)) : 'U' ?>
            </div>
            <div class="nav-user-meta">
                <span class="nav-user-name">
                    <?= isset($user['name']) ? htmlspecialchars(explode(' ', $user['name'])[0]) : 'Guest' ?>
                </span>
                <span class="nav-user-subtitle">My Profile</span>
            </div>
        </a>

        <div class="nav-separator"></div>

        <!-- Logout -->
        <a href="../logout.php" class="nav-logout-btn" title="Logout">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</nav>