<?php
// chair/dashboard.php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../services/SchedulingService.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';

// Define current URI at the top
$currentUri = $_SERVER['REQUEST_URI'];

AuthMiddleware::handle('chair');

// Get department ID from session
$departmentId = $_SESSION['user']['department_id'] ?? null;
if (!$departmentId) {
    die("Department ID not found in session");
}

// Initialize services
$db = (new Database())->connect();
$schedulingService = new SchedulingService();

// Get current semester
$currentSemester = $schedulingService->getCurrentSemester();
if (!$currentSemester) {
    die("Current semester not found");
}

// Dashboard statistics
$pendingApprovalsData = $schedulingService->getPendingApprovals($departmentId);
$conflictsData = $schedulingService->getScheduleConflicts($departmentId, $currentSemester['semester_id']);

// Get department curricula
$curricula = $schedulingService->getDepartmentCurricula($departmentId) ?? [];

// Define full $stats array
$stats = [
    'facultyCount' => (int)($schedulingService->getFacultyCount($departmentId) ?? 0),
    'courseCount' => (int)($schedulingService->getActiveCourseCount($departmentId) ?? 0),
    'pendingApprovals' => is_array($pendingApprovalsData) ? count($pendingApprovalsData) : (int)($pendingApprovalsData ?? 0),
    'conflicts' => (int)($conflictsData['total_conflicts'] ?? 0),
    'curriculumCount' => count(array_filter($curricula, fn($c) => ($c['status'] ?? '') === 'Active')),
    'pendingApprovalsData' => is_array($pendingApprovalsData) ? $pendingApprovalsData : [],
    'conflictsData' => $conflictsData
];

// Get recent schedule changes
$recentChanges = $schedulingService->getRecentScheduleChanges($departmentId, 5) ?? [];

// Get faculty availability summary
$availabilitySummary = $schedulingService->getFacultyAvailabilitySummary($departmentId, $currentSemester['semester_id']) ?? [];

// Get classroom utilization
$classroomUtilization = $schedulingService->getClassroomUtilization($departmentId, $currentSemester['semester_id']) ?? [];
$classroomUtilizationData = [];
foreach ($classroomUtilization['classrooms'] ?? [] as $room) {
    $classroomUtilizationData[] = [
        'room_name' => $room['room_name'] ?? 'Unknown',
        'utilization' => isset($room['scheduled_hours']) ?
            min(1, (strtotime($room['scheduled_hours']) - strtotime('TODAY')) / 3600 / 40) : 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Program Chair Dashboard | PRMSU Scheduling System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* PRMSU Color Palette */
        :root {
            --prmsu-blue: #0056b3;
            --prmsu-gold: #FFD700;
            --prmsu-light: #f8f9fa;
            --prmsu-dark: #343a40;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            transition: all 0.3s ease;
            background: linear-gradient(180deg, var(--prmsu-blue) 0%, #003366 100%);
        }

        .sidebar-header {
            background-color: rgba(0, 0, 0, 0.1);
        }

        .nav-item {
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .nav-item:hover {
            background-color: #2b6cb0;
            /* Darker blue on hover */
        }

        .nav-item.active {
            background-color: rgba(255, 255, 255, 0.15);
            /* Slightly lighter for active */
            border-left: 3px solid var(--prmsu-gold);
        }

        .nav-item.active:hover {
            background-color: rgba(255, 255, 255, 0.25);
            /* Even lighter on active hover */
        }

        .badge {
            background-color: var(--prmsu-gold);
            color: var(--prmsu-dark);
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }

        .stat-card {
            border-top: 4px solid var(--prmsu-blue);
        }
    </style>
</head>

<body class="bg-gray-50 flex h-screen">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/../partials/chair/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden md:ml-64">
        <!-- Mobile header -->
        <header class="bg-white shadow md:hidden">
            <div class="px-4 py-3 flex items-center justify-between">
                <button id="sidebar-toggle" class="text-gray-500 focus:outline-none">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="flex items-center">
                    <img src="/assets/prmsu-logo.png" alt="PRMSU Logo" class="h-6 mr-2">
                    <h1 class="text-lg font-bold text-gray-900">Scheduling</h1>
                </div>
                <div class="w-8"></div>
            </div>
        </header>

        <!-- Desktop header -->
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Program Chair Dashboard</h1>
                    <p class="text-sm text-gray-500">Welcome back, <?= htmlspecialchars($_SESSION['user']['username'] ?? 'User') ?>!</p>
                </div>
                <div class="flex space-x-4">
                    <div class="relative">
                        <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center">
                            <i class="fas fa-plus mr-2"></i> New Schedule
                        </button>
                    </div>
                    <button class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-md text-sm font-medium flex items-center">
                        <i class="fas fa-download mr-2"></i> Export
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto p-6">
            <!-- Welcome Banner -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white rounded-xl p-6 mb-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold mb-2">PRMSU Scheduling System</h2>
                        <p class="opacity-90">Manage your department's schedules, curricula, and faculty assignments</p>
                    </div>
                    <div class="hidden md:block">
                        <img src="/assets/prmsu-icon-white.png" alt="PRMSU Icon" class="h-16 opacity-90">
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                <!-- Faculty Count -->
                <div class="card stat-card bg-white overflow-hidden">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-blue-100 rounded-full p-3">
                                <i class="fas fa-chalkboard-teacher text-blue-600 text-xl"></i>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Faculty Members</dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-2xl font-semibold text-gray-900"><?= $stats['facultyCount'] ?></div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm">
                            <a href="/chair/faculty" class="font-medium text-blue-600 hover:text-blue-500">View all →</a>
                        </div>
                    </div>
                </div>

                <!-- Course Count -->
                <div class="card stat-card bg-white overflow-hidden">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-100 rounded-full p-3">
                                <i class="fas fa-book text-green-600 text-xl"></i>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Active Courses</dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-2xl font-semibold text-gray-900"><?= $stats['courseCount'] ?></div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm">
                            <a href="/chair/courses" class="font-medium text-blue-600 hover:text-blue-500">Manage courses →</a>
                        </div>
                    </div>
                </div>

                <!-- Pending Approvals -->
                <div class="card stat-card bg-white overflow-hidden">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-yellow-100 rounded-full p-3">
                                <i class="fas fa-clock text-yellow-600 text-xl"></i>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Pending Approvals</dt>
                                    <dd class="flex items-baseline">
                                        <div class="text-2xl font-semibold text-gray-900"><?= $stats['pendingApprovals'] ?></div>
                                        <?php if ($stats['pendingApprovals'] > 0): ?>
                                            <span class="ml-2 text-sm font-medium text-yellow-600 animate-pulse">
                                                <i class="fas fa-exclamation-circle"></i> Needs attention
                                            </span>
                                        <?php endif; ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-5 py-3">
                        <div class="text-sm">
                            <a href="/chair/approvals" class="font-medium text-blue-600 hover:text-blue-500">Review now →</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Schedule Overview -->
                    <div class="card bg-white overflow-hidden">
                        <div class="px-6 py-5 border-b border-gray-200">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <i class="fas fa-calendar-alt text-blue-600 mr-2"></i>
                                Current Semester Schedule
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">
                                <?= htmlspecialchars($currentSemester['semester_name'] ?? '') ?> Semester <?= htmlspecialchars($currentSemester['academic_year'] ?? '') ?>
                            </p>
                        </div>
                        <div class="px-6 py-5">
                            <div class="h-64">
                                <canvas id="scheduleChart"></canvas>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-6 py-4">
                            <a href="/chair/schedule" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                                View full schedule →
                            </a>
                        </div>
                    </div>

                    <!-- Recent Changes -->
                    <div class="card bg-white overflow-hidden">
                        <div class="px-6 py-5 border-b border-gray-200">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <i class="fas fa-history text-blue-600 mr-2"></i>
                                Recent Schedule Changes
                            </h3>
                        </div>
                        <div class="px-6 py-5">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Change Type</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($recentChanges as $change): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-4 whitespace-nowrap">
                                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($change['course_code'] ?? '') ?></div>
                                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($change['faculty_name'] ?? '') ?></div>
                                                </td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars($change['change_type'] ?? 'Update') ?>
                                                </td>
                                                <td class="px-4 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                       <?= (($change['status'] ?? '') === 'Approved') ? 'bg-green-100 text-green-800' : ((($change['status'] ?? '') === 'Pending') ? 'bg-yellow-100 text-yellow-800' :
                                                            'bg-red-100 text-red-800') ?>">
                                                        <?= htmlspecialchars($change['status'] ?? '') ?>
                                                    </span>
                                                </td>
                                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= isset($change['created_at']) ? date('M j, Y', strtotime($change['created_at'])) : '' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                    <!-- Curriculum Overview -->
                    <div class="card bg-white overflow-hidden">
                        <div class="px-6 py-5 border-b border-gray-200">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <i class="fas fa-graduation-cap text-blue-600 mr-2"></i>
                                Active Curricula
                            </h3>
                        </div>
                        <div class="px-6 py-5">
                            <?php if (!empty($curricula)): ?>
                                <div class="space-y-4">
                                    <?php foreach (array_slice($curricula, 0, 3) as $curriculum): ?>
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 bg-blue-100 rounded-md p-2">
                                                <i class="fas fa-file-alt text-blue-600"></i>
                                            </div>
                                            <div class="ml-3 flex-1">
                                                <h4 class="font-medium text-gray-900"><?= htmlspecialchars($curriculum['curriculum_name'] ?? '') ?></h4>
                                                <p class="text-sm text-gray-500"><?= htmlspecialchars($curriculum['curriculum_code'] ?? '') ?></p>
                                                <div class="mt-1 flex items-center text-sm text-gray-500">
                                                    <span class="mr-2"><?= $curriculum['course_count'] ?? 0 ?> courses</span>
                                                    <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full">
                                                        <?= htmlspecialchars($curriculum['status'] ?? 'Active') ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-4">
                                    <a href="/chair/curriculum" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                                        View all curricula →
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-graduation-cap text-gray-300 text-4xl mb-2"></i>
                                    <p class="text-gray-500">No active curricula found</p>
                                    <a href="/chair/curriculum/new" class="mt-2 inline-block text-sm font-medium text-blue-600 hover:text-blue-500">
                                        Create new curriculum
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card bg-white overflow-hidden">
                        <div class="px-6 py-5 border-b border-gray-200">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 flex items-center">
                                <i class="fas fa-bolt text-blue-600 mr-2"></i>
                                Quick Actions
                            </h3>
                        </div>
                        <div class="p-4 grid grid-cols-2 gap-4">
                            <a href="/chair/schedule/generate" class="group p-4 border border-gray-200 rounded-lg hover:border-blue-300 hover:bg-blue-50 transition-colors">
                                <div class="flex flex-col items-center text-center">
                                    <div class="bg-blue-100 group-hover:bg-blue-200 rounded-full p-3 mb-2 transition-colors">
                                        <i class="fas fa-magic text-blue-600 text-xl"></i>
                                    </div>
                                    <span class="text-sm font-medium text-gray-700 group-hover:text-blue-700">Generate Schedule</span>
                                </div>
                            </a>
                            <a href="/chair/faculty/assign" class="group p-4 border border-gray-200 rounded-lg hover:border-green-300 hover:bg-green-50 transition-colors">
                                <div class="flex flex-col items-center text-center">
                                    <div class="bg-green-100 group-hover:bg-green-200 rounded-full p-3 mb-2 transition-colors">
                                        <i class="fas fa-user-plus text-green-600 text-xl"></i>
                                    </div>
                                    <span class="text-sm font-medium text-gray-700 group-hover:text-green-700">Assign Faculty</span>
                                </div>
                            </a>
                            <a href="/chair/approvals" class="group p-4 border border-gray-200 rounded-lg hover:border-yellow-300 hover:bg-yellow-50 transition-colors">
                                <div class="flex flex-col items-center text-center">
                                    <div class="bg-yellow-100 group-hover:bg-yellow-200 rounded-full p-3 mb-2 transition-colors">
                                        <i class="fas fa-check-circle text-yellow-600 text-xl"></i>
                                    </div>
                                    <span class="text-sm font-medium text-gray-700 group-hover:text-yellow-700">Review Approvals</span>
                                </div>
                            </a>
                            <a href="/chair/reports" class="group p-4 border border-gray-200 rounded-lg hover:border-purple-300 hover:bg-purple-50 transition-colors">
                                <div class="flex flex-col items-center text-center">
                                    <div class="bg-purple-100 group-hover:bg-purple-200 rounded-full p-3 mb-2 transition-colors">
                                        <i class="fas fa-file-alt text-purple-600 text-xl"></i>
                                    </div>
                                    <span class="text-sm font-medium text-gray-700 group-hover:text-purple-700">Generate Reports</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Schedule Chart
        const scheduleCtx = document.getElementById('scheduleChart').getContext('2d');
        const scheduleChart = new Chart(scheduleCtx, {
            type: 'bar',
            data: {
                labels: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                datasets: [{
                    label: 'Classes Scheduled',
                    data: [12, 19, 15, 17, 14, 5],
                    backgroundColor: 'rgba(0, 86, 179, 0.7)',
                    borderColor: 'rgba(0, 86, 179, 1)',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleFont: {
                            weight: 'bold'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>