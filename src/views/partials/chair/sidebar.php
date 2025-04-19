<?php
require_once __DIR__ . '/../../../middleware/AuthMiddleware.php';
AuthMiddleware::handle('chair');

// Ensure $currentUri and $stats are available from the including page
if (!isset($currentUri)) {
    $currentUri = $_SERVER['REQUEST_URI'];
}
if (!isset($stats)) {
    $stats = ['pendingApprovals' => 0]; // Fallback
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
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="p-4 border-b border-gray-700">
        <div class="flex items-center space-x-3">
            <img class="h-10 w-10 rounded-full border-2 border-yellow-500"
                src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['user']['username'] ?? 'User') ?>&background=f0f0f0&color=808080"
                alt="Profile">
            <div>
                <p class="font-medium text-white"><?= htmlspecialchars($_SESSION['user']['username'] ?? 'User') ?></p>
                <p class="text-xs text-gray-300">Program Chair</p>
            </div>
        </div>
    </div>

    <nav class="p-2 space-y-1">
        <!-- Dashboard -->
        <a href="/chair/dashboard" class="nav-item flex items-center p-3 rounded-lg text-white mb-1 hover:bg-gray-700 <?= strpos($currentUri, '/chair/dashboard') !== false ? 'bg-gray-700 border-l-4 border-yellow-500' : '' ?>">
            <i class="fas fa-tachometer-alt mr-3 <?= strpos($currentUri, '/chair/dashboard') !== false ? 'text-yellow-500' : 'text-gray-400' ?>"></i>
            <span>Dashboard</span>
        </a>

        <!-- Schedule Dropdown -->
        <div class="mb-1 group relative">
            <?php
            $scheduleActive = strpos($currentUri, '/chair/view_schedule') !== false ||
                strpos($currentUri, '/chair/generate_schedule') !== false ||
                strpos($currentUri, '/chair/schedule/conflicts') !== false;
            ?>
            <button class="nav-item flex items-center justify-between w-full p-3 rounded-lg text-white hover:bg-gray-700 transition-colors duration-200 <?= $scheduleActive ? 'bg-gray-700 border-l-4 border-yellow-500' : '' ?>">
                <div class="flex items-center">
                    <i class="fas fa-calendar mr-3 <?= $scheduleActive ? 'text-yellow-500' : 'text-gray-400' ?>"></i>
                    <span>Schedule</span>
                </div>
                <i class="fas fa-chevron-down text-xs transition-transform duration-200 group-hover:rotate-180"></i>
            </button>
            <div class="absolute left-0 mt-1 w-56 origin-top-left bg-white rounded-md shadow-lg z-10 opacity-0 invisible 
                        group-hover:opacity-100 group-hover:visible transition-all duration-200 transform 
                        group-hover:translate-y-0 -translate-y-2">
                <div class="py-1">
                    <a href="/chair/view_schedule" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-colors <?= strpos($currentUri, '/chair/view_schedule') !== false ? 'bg-gray-100 text-gray-900' : '' ?>">
                        <i class="fas fa-eye mr-2 text-yellow-600"></i> View Schedule
                    </a>
                    <a href="/chair/generate_schedule" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-colors <?= strpos($currentUri, '/chair/generate_schedule') !== false ? 'bg-gray-100 text-gray-900' : '' ?>">
                        <i class="fas fa-magic mr-2 text-yellow-600"></i> Generate Schedule
                    </a>
                    <a href="/chair/schedule/conflicts" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-colors <?= strpos($currentUri, '/chair/schedule/conflicts') !== false ? 'bg-gray-100 text-gray-900' : '' ?>">
                        <i class="fas fa-exclamation-triangle mr-2 text-yellow-600"></i> Resolve Conflicts
                    </a>
                </div>
            </div>
        </div>

        <!-- Curriculum -->
        <a href="/chair/curriculum" class="nav-item flex items-center p-3 rounded-lg text-white mb-1 hover:bg-gray-700 <?= strpos($currentUri, '/chair/curriculum') !== false ? 'bg-gray-700 border-l-4 border-yellow-500' : '' ?>">
            <i class="fas fa-graduation-cap mr-3 <?= strpos($currentUri, '/chair/curriculum') !== false ? 'text-yellow-500' : 'text-gray-400' ?>"></i>
            <span>Curriculum</span>
        </a>

        <!-- Faculty -->
        <a href="/chair/faculty" class="nav-item flex items-center p-3 rounded-lg text-white mb-1 hover:bg-gray-700 <?= strpos($currentUri, '/chair/faculty') !== false ? 'bg-gray-700 border-l-4 border-yellow-500' : '' ?>">
            <i class="fas fa-chalkboard-teacher mr-3 <?= strpos($currentUri, '/chair/faculty') !== false ? 'text-yellow-500' : 'text-gray-400' ?>"></i>
            <span>Faculty</span>
        </a>

        <!-- Courses -->
        <a href="/chair/courses" class="nav-item flex items-center p-3 rounded-lg text-white mb-1 hover:bg-gray-700 <?= strpos($currentUri, '/chair/courses') !== false ? 'bg-gray-700 border-l-4 border-yellow-500' : '' ?>">
            <i class="fas fa-book mr-3 <?= strpos($currentUri, '/chair/courses') !== false ? 'text-yellow-500' : 'text-gray-400' ?>"></i>
            <span>Courses</span>
        </a>

        <!-- Classrooms -->
        <a href="/chair/classroom" class="nav-item flex items-center p-3 rounded-lg text-white mb-1 hover:bg-gray-700 <?= strpos($currentUri, '/chair/classroom') !== false ? 'bg-gray-700 border-l-4 border-yellow-500' : '' ?>">
            <i class="fas fa-door-open mr-3 <?= strpos($currentUri, '/chair/classroom') !== false ? 'text-yellow-500' : 'text-gray-400' ?>"></i>
            <span>Classrooms</span>
        </a>

        <!-- Sections -->
        <a href="/chair/section" class="nav-item flex items-center p-3 rounded-lg text-white mb-1 hover:bg-gray-700 <?= strpos($currentUri, '/chair/section') !== false ? 'bg-gray-700 border-l-4 border-yellow-500' : '' ?>">
            <i class="fas fa-door-open mr-3 <?= strpos($currentUri, '/chair/section') !== false ? 'text-yellow-500' : 'text-gray-400' ?>"></i>
            <span>Sections</span>
        </a>

        <!-- Approvals -->
        <a href="/chair/approvals" class="nav-item flex items-center p-3 rounded-lg text-white mb-1 hover:bg-gray-700 <?= strpos($currentUri, '/chair/approvals') !== false ? 'bg-gray-700 border-l-4 border-yellow-500' : '' ?>">
            <i class="fas fa-check-circle mr-3 <?= strpos($currentUri, '/chair/approvals') !== false ? 'text-yellow-500' : 'text-gray-400' ?>"></i>
            <span>Approvals</span>
            <?php if ($stats['pendingApprovals'] > 0): ?>
                <span class="ml-auto badge text-xs font-bold px-2 py-1 rounded-full bg-yellow-500 text-gray-900">
                    <?= $stats['pendingApprovals'] ?>
                </span>
            <?php endif; ?>
        </a>

        <!-- Reports -->
        <a href="/chair/report" class="nav-item flex items-center p-3 rounded-lg text-white mb-1 hover:bg-gray-700 <?= strpos($currentUri, '/chair/reports') !== false ? 'bg-gray-700 border-l-4 border-yellow-500' : '' ?>">
            <i class="fas fa-file-alt mr-3 <?= strpos($currentUri, '/chair/reports') !== false ? 'text-yellow-500' : 'text-gray-400' ?>"></i>
            <span>Reports</span>
        </a>

        <!-- Profile (New) -->
        <a href="/chair/profile" class="nav-item flex items-center p-3 rounded-lg text-white mb-1 hover:bg-gray-700 <?= strpos($currentUri, '/chair/profile') !== false ? 'bg-gray-700 border-l-4 border-yellow-500' : '' ?>">
            <i class="fas fa-user mr-3 <?= strpos($currentUri, '/chair/profile') !== false ? 'text-yellow-500' : 'text-gray-400' ?>"></i>
            <span>Profile</span>
        </a>

        <!-- Settings (New) -->
        <a href="/chair/settings" class="nav-item flex items-center p-3 rounded-lg text-white mb-1 hover:bg-gray-700 <?= strpos($currentUri, '/chair/settings') !== false ? 'bg-gray-700 border-l-4 border-yellow-500' : '' ?>">
            <i class="fas fa-cog mr-3 <?= strpos($currentUri, '/chair/settings') !== false ? 'text-yellow-500' : 'text-gray-400' ?>"></i>
            <span>Settings</span>
        </a>
    </nav>

    <div class="p-4 border-t border-gray-700 absolute bottom-0 w-full">
        <a href="/chair/logout" class="flex items-center text-white hover:text-yellow-500">
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