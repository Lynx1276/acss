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
<div id="sidebar" class="sidebar w-64 text-white fixed h-full overflow-y-auto transition-all duration-300 transform -translate-x-full md:translate-x-0 z-10">
    <div class="sidebar-header p-4 flex items-center justify-between border-b border-blue-700">
        <div class="flex items-center space-x-2">
            <img src="/assets/prmsu-logo-white.png" alt="PRMSU Logo" class="h-8">
            <span class="text-xl font-bold">Scheduling</span>
        </div>
        <button id="sidebar-toggle" class="md:hidden text-white">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="p-4 border-b border-blue-700">
        <div class="flex items-center space-x-3">
            <img class="h-10 w-10 rounded-full border-2 border-white"
                src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['user']['username'] ?? 'User') ?>&background=ffffff&color=0056b3"
                alt="Profile">
            <div>
                <p class="font-medium"><?= htmlspecialchars($_SESSION['user']['username'] ?? 'User') ?></p>
                <p class="text-xs text-blue-200">Program Chair</p>
            </div>
        </div>
    </div>

    <nav class="p-2 space-y-1">
        <!-- Dashboard -->
        <a href="/chair/dashboard" class="nav-item flex items-center p-3 rounded-lg text-white mb-1 <?= strpos($currentUri, '/chair/dashboard') !== false ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt mr-3"></i>
            <span>Dashboard</span>
        </a>

        <!-- Schedule Dropdown -->
        <div class="mb-1 group relative">
            <?php
            $scheduleActive = strpos($currentUri, '/chair/view_schedule') !== false ||
                strpos($currentUri, '/chair/generate_schedule') !== false ||
                strpos($currentUri, '/chair/schedule/conflicts') !== false;
            ?>
            <button class="nav-item flex items-center justify-between w-full p-3 rounded-lg text-white hover:bg-blue-700 transition-colors duration-200 <?= $scheduleActive ? 'active' : '' ?>">
                <div class="flex items-center">
                    <i class="fas fa-calendar mr-3"></i>
                    <span>Schedule</span>
                </div>
                <i class="fas fa-chevron-down text-xs transition-transform duration-200 group-hover:rotate-180"></i>
            </button>
            <div class="absolute left-0 mt-1 w-56 origin-top-left bg-white rounded-md shadow-lg z-10 opacity-0 invisible 
                        group-hover:opacity-100 group-hover:visible transition-all duration-200 transform 
                        group-hover:translate-y-0 -translate-y-2">
                <div class="py-1">
                    <a href="/chair/view_schedule" class="block px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors <?= strpos($currentUri, '/chair/view_schedule') !== false ? 'bg-blue-100 text-blue-700' : '' ?>">
                        <i class="fas fa-eye mr-2 text-blue-500"></i> View Schedule
                    </a>
                    <a href="/chair/generate_schedule" class="block px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors <?= strpos($currentUri, '/chair/generate_schedule') !== false ? 'bg-blue-100 text-blue-700' : '' ?>">
                        <i class="fas fa-magic mr-2 text-blue-500"></i> Generate Schedule
                    </a>
                    <a href="/chair/schedule/conflicts" class="block px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors <?= strpos($currentUri, '/chair/schedule/conflicts') !== false ? 'bg-blue-100 text-blue-700' : '' ?>">
                        <i class="fas fa-exclamation-triangle mr-2 text-blue-500"></i> Resolve Conflicts
                    </a>
                </div>
            </div>
        </div>

        <!-- Curriculum -->
        <a href="/chair/curriculum" class="nav-item flex items-center p-3 rounded-lg text-white mb-1 <?= strpos($currentUri, '/chair/curriculum') !== false ? 'active' : '' ?>">
            <i class="fas fa-graduation-cap mr-3"></i>
            <span>Curriculum</span>
        </a>

        <!-- Faculty -->
        <a href="/chair/faculty" class="nav-item flex items-center p-3 rounded-lg text-white mb-1 <?= strpos($currentUri, '/chair/faculty') !== false ? 'active' : '' ?>">
            <i class="fas fa-chalkboard-teacher mr-3"></i>
            <span>Faculty</span>
        </a>

        <!-- Courses -->
        <a href="/chair/courses" class="nav-item flex items-center p-3 rounded-lg text-white mb-1 <?= strpos($currentUri, '/chair/courses') !== false ? 'active' : '' ?>">
            <i class="fas fa-book mr-3"></i>
            <span>Courses</span>
        </a>

        <!-- Classrooms -->
        <a href="/chair/classroom" class="nav-item flex items-center p-3 rounded-lg text-white mb-1 <?= strpos($currentUri, '/chair/classroom') !== false ? 'active' : '' ?>">
            <i class="fas fa-door-open mr-3"></i>
            <span>Classrooms</span>
        </a>

        <!-- Approvals -->
        <a href="/chair/approvals" class="nav-item flex items-center p-3 rounded-lg text-white mb-1 <?= strpos($currentUri, '/chair/approvals') !== false ? 'active' : '' ?>">
            <i class="fas fa-check-circle mr-3"></i>
            <span>Approvals</span>
            <?php if ($stats['pendingApprovals'] > 0): ?>
                <span class="ml-auto badge text-xs font-bold px-2 py-1 rounded-full">
                    <?= $stats['pendingApprovals'] ?>
                </span>
            <?php endif; ?>
        </a>

        <!-- Reports -->
        <a href="/chair/report" class="nav-item flex items-center p-3 rounded-lg text-white mb-1 <?= strpos($currentUri, '/chair/reports') !== false ? 'active' : '' ?>">
            <i class="fas fa-file-alt mr-3"></i>
            <span>Reports</span>
        </a>
    </nav>

    <div class="p-4 border-t border-blue-700 absolute bottom-0 w-full">
        <a href="/chair/logout" class="flex items-center text-white hover:text-blue-200">
            <i class="fas fa-sign-out-alt mr-3"></i>
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