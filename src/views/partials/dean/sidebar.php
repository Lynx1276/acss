<?php
// src/views/partials/dean/sidebar.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../services/DeanService.php';

// Ensure $currentUri is available
if (!isset($currentUri)) {
    $currentUri = $_SERVER['REQUEST_URI'];
}

// Default pending count
$pendingCount = 0;
if (isset($_SESSION['user']['college_id'])) {
    try {
        // Fetch pending requests if not already set
        if (!isset($requests)) {
            $deanService = new DeanService();
            $requests = $deanService->getPendingFacultyRequests($_SESSION['user']['college_id']);
        }
        $pendingCount = !empty($requests) && isset($requests[0]['count']) ? (int)$requests[0]['count'] : 0;
    } catch (Exception $e) {
        error_log("sidebar.php: Failed to fetch pending requests: " . $e->getMessage());
        $pendingCount = 0;
    }
} else {
    error_log("sidebar.php: college_id not set in session: " . json_encode($_SESSION['user'] ?? 'null'));
}
?>

<!-- Sidebar -->
<div id="sidebar" class="sidebar w-64 text-white fixed h-full overflow-y-auto transition-all duration-300 transform -translate-x-full md:translate-x-0 z-10 bg-gray-800">
    <div class="sidebar-header p-4 flex items-center justify-between border-b border-gray-700">
        <div class="flex items-center space-x-2">
            <img src="/assets/prmsu-logo-white.png" alt="PRMSU Logo" class="h-8">
            <span class="text-xl font-bold text-yellow-500">Scheduling</span>
        </div>
        <button id="sidebar-toggle" class="md:hidden text-white">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="p-4 border-b border-gray-700">
        <div class="flex items-center space-x-3">
            <img class="h-10 w-10 rounded-full border-2 border-yellow-500"
                src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['user']['username'] ?? 'User') ?>&background=f0f0f0&color=808080"
                alt="Profile">
            <div>
                <p class="font-medium text-white"><?= htmlspecialchars($_SESSION['user']['username'] ?? 'User') ?></p>
                <p class="text-xs text-gray-300">Dean</p>
            </div>
        </div>
    </div>

    <nav class="p-2 space-y-1">
        <!-- Dashboard -->
        <a href="/dean/dashboard" class="nav-item flex items-center p-3 rounded-lg text-white mb-1 hover:bg-gray-700 <?= strpos($currentUri, '/dean/dashboard') !== false ? 'bg-gray-700 border-l-4 border-yellow-500' : '' ?>">
            <i class="fas fa-tachometer-alt mr-3 <?= strpos($currentUri, '/dean/dashboard') !== false ? 'text-yellow-500' : 'text-gray-400' ?>"></i>
            <span>Dashboard</span>
        </a>

        <!-- Schedules -->
        <a href="/dean/schedules" class="nav-item flex items-center p-3 rounded-lg text-white mb-1 hover:bg-gray-700 <?= strpos($currentUri, '/dean/schedules') !== false ? 'bg-gray-700 border-l-4 border-yellow-500' : '' ?>">
            <i class="fas fa-calendar mr-3 <?= strpos($currentUri, '/dean/schedules') !== false ? 'text-yellow-500' : 'text-gray-400' ?>"></i>
            <span>Schedules</span>
        </a>

        <!-- Faculty Requests -->
        <a href="/dean/faculty-requests" class="nav-item flex items-center p-3 rounded-lg text-white mb-1 hover:bg-gray-700 <?= strpos($currentUri, '/dean/faculty-requests') !== false ? 'bg-gray-700 border-l-4 border-yellow-500' : '' ?>">
            <i class="fas fa-user-plus mr-3 <?= strpos($currentUri, '/dean/faculty-requests') !== false ? 'text-yellow-500' : 'text-gray-400' ?>"></i>
            <span>Faculty Requests</span>
            <?php if ($pendingCount > 0): ?>
                <span class="ml-auto badge text-xs font-bold px-2 py-1 rounded-full bg-yellow-500 text-gray-900">
                    <?= $pendingCount ?>
                </span>
            <?php endif; ?>
        </a>

        <!-- Manage Accounts -->
        <a href="/dean/accounts" class="nav-item flex items-center p-3 rounded-lg text-white mb-1 hover:bg-gray-700 <?= strpos($currentUri, '/dean/accounts') !== false ? 'bg-gray-700 border-l-4 border-yellow-500' : '' ?>">
            <i class="fas fa-users mr-3 <?= strpos($currentUri, '/dean/accounts') !== false ? 'text-yellow-500' : 'text-gray-400' ?>"></i>
            <span>Manage Accounts</span>
        </a>

        <!-- Profile -->
        <a href="/dean/profile" class="nav-item flex items-center p-3 rounded-lg text-white mb-1 hover:bg-gray-700 <?= strpos($currentUri, '/dean/profile') !== false ? 'bg-gray-700 border-l-4 border-yellow-500' : '' ?>">
            <i class="fas fa-user mr-3 <?= strpos($currentUri, '/dean/profile') !== false ? 'text-yellow-500' : 'text-gray-400' ?>"></i>
            <span>Profile</span>
        </a>

        <!-- Settings -->
        <a href="/dean/settings" class="nav-item flex items-center p-3 rounded-lg text-white mb-1 hover:bg-gray-700 <?= strpos($currentUri, '/dean/settings') !== false ? 'bg-gray-700 border-l-4 border-yellow-500' : '' ?>">
            <i class="fas fa-cog mr-3 <?= strpos($currentUri, '/dean/settings') !== false ? 'text-gray-400' : 'text-gray-400' ?>"></i>
            <span>Settings</span>
        </a>
    </nav>

    <div class="p-4 border-t border-gray-700 absolute bottom-0 w-full">
        <a href="/logout" class="flex items-center text-white hover:text-yellow-500">
            <i class="fas fa-sign-out-alt mr-3 text-gray-400"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<!-- Sidebar Toggle Script -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.querySelectorAll('#sidebar-toggle');

        sidebarToggle.forEach(button => {
            button.addEventListener('click', function() {
                sidebar.classList.toggle('-translate-x-full');
                const isOpen = !sidebar.classList.contains('-translate-x-full');
                button.innerHTML = `<i class="fas ${isOpen ? 'fa-times' : 'fa-bars'}"></i>`;
            });
        });
    });
</script>

<style>
    :root {
        --prmsu-gold: rgb(239, 187, 15);
    }

    .nav-item:hover {
        background: #4b5563;
    }

    .nav-item.border-yellow-500 {
        background: #1f2937;
    }
</style>