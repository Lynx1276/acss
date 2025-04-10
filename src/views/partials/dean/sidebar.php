<?php
// views/partials/dean/sidebar.php
?>

<aside class="fixed inset-y-0 left-0 w-64 bg-gray-800 text-white flex flex-col">
    <div class="p-4 border-b border-gray-700">
        <h2 class="text-xl font-bold">PRMSU Dean</h2>
    </div>
    <nav class="flex-1 p-4 space-y-1">
        <a href="/dean/dashboard" class="nav-item <?= strpos($currentUri, '/dean/dashboard') === 0 ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
        </a>
        <a href="/dean/schedule" class="nav-item <?= strpos($currentUri, '/dean/schedules') === 0 ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt mr-3"></i> Faculty Schedules
        </a>
        <a href="/dean/requests" class="nav-item <?= strpos($currentUri, '/dean/requests') === 0 ? 'active' : '' ?>">
            <i class="fas fa-file-signature mr-3"></i> Requests
        </a>
        <a href="/dean/faculty" class="nav-item <?= strpos($currentUri, '/dean/faculty') === 0 ? 'active' : '' ?>">
            <i class="fas fa-users mr-3"></i> Faculty Management
        </a>
        <a href="/logout" class="nav-item">
            <i class="fas fa-sign-out-alt mr-3"></i> Logout
        </a>
    </nav>
</aside>

<style>
    .nav-item {
        display: flex;
        align-items: center;
        padding: 10px 15px;
        border-radius: 5px;
        transition: background-color 0.2s;
    }

    .nav-item:hover {
        background-color: var(--prmsu-blue);
    }

    .nav-item.active {
        background-color: var(--prmsu-blue);
        font-weight: bold;
    }
</style>