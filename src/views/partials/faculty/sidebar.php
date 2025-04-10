<?php
// views/partials/faculty/sidebar.php
?>

<aside class="fixed inset-y-0 left-0 w-64 bg-gradient-to-b from-gray-800 to-gray-900 text-white flex flex-col z-50 transition-all duration-300 ease-in-out transform hover:shadow-xl">
    <!-- Logo/Branding Section -->
    <div class="p-6 border-b border-gray-700 flex items-center justify-center">
        <div class="flex items-center space-x-3">
            <div class="h-10 w-10 rounded-full bg-gradient-to-r from-blue-600 to-blue-400 flex items-center justify-center shadow-md">
                <i class="fas fa-university text-white"></i>
            </div>
            <h2 class="text-xl font-bold tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-blue-300 to-blue-100">
                PRMSU Faculty
            </h2>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
        <a href="/faculty/dashboard" class="nav-item <?= strpos($currentUri, '/faculty/dashboard') === 0 ? 'active' : '' ?>">
            <div class="nav-icon">
                <i class="fas fa-tachometer-alt"></i>
            </div>
            <span class="nav-text">Dashboard</span>
            <div class="nav-indicator"></div>
        </a>

        <a href="/faculty/schedule" class="nav-item <?= strpos($currentUri, '/faculty/schedule') === 0 ? 'active' : '' ?>">
            <div class="nav-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <span class="nav-text">Schedule</span>
            <div class="nav-indicator"></div>
        </a>

        <a href="/faculty/requests" class="nav-item <?= strpos($currentUri, '/faculty/requests') === 0 ? 'active' : '' ?>">
            <div class="nav-icon">
                <i class="fas fa-file-signature"></i>
            </div>
            <span class="nav-text">Requests</span>
            <div class="nav-indicator"></div>
        </a>

        <a href="/faculty/profile" class="nav-item <?= strpos($currentUri, '/faculty/profile') === 0 ? 'active' : '' ?>">
            <div class="nav-icon">
                <i class="fas fa-user"></i>
            </div>
            <span class="nav-text">Profile</span>
            <div class="nav-indicator"></div>
        </a>
    </nav>

    <!-- Logout Section -->
    <div class="p-4 border-t border-gray-700">
        <a href="/faculty/logout" class="logout-item">
            <div class="nav-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <span class="nav-text">Logout</span>
        </a>
    </div>
</aside>

<style>
    .nav-item {
        display: flex;
        align-items: center;
        padding: 12px 16px;
        border-radius: 8px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .nav-item:hover {
        background: rgba(0, 86, 179, 0.2);
        transform: translateX(4px);
    }

    .nav-item.active {
        background: linear-gradient(90deg, rgba(0, 86, 179, 0.2) 0%, rgba(0, 86, 179, 0.1) 100%);
        box-shadow: inset 3px 0 0 var(--prmsu-blue);
    }

    .nav-item.active .nav-icon {
        color: var(--prmsu-blue);
    }

    .nav-item.active .nav-text {
        font-weight: 600;
        color: white;
    }

    .nav-icon {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
        color: #9CA3AF;
        transition: all 0.3s ease;
    }

    .nav-text {
        font-size: 15px;
        color: #E5E7EB;
        transition: all 0.3s ease;
    }

    .nav-indicator {
        position: absolute;
        right: 16px;
        width: 6px;
        height: 6px;
        background-color: var(--prmsu-blue);
        border-radius: 50%;
        opacity: 0;
        transition: all 0.3s ease;
    }

    .nav-item.active .nav-indicator {
        opacity: 1;
    }

    .logout-item {
        display: flex;
        align-items: center;
        padding: 12px 16px;
        border-radius: 8px;
        transition: all 0.3s ease;
        color: #9CA3AF;
    }

    .logout-item:hover {
        background: rgba(239, 68, 68, 0.1);
        color: #EF4444;
    }

    .logout-item:hover .nav-icon {
        color: #EF4444;
    }
</style>