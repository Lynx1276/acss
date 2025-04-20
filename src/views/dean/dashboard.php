<?php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../services/DeanService.php';

use App\config\Database;

$currentUri = $_SERVER['REQUEST_URI'];
$collegeId = $_SESSION['user']['college_id'] ?? null;
if (!$collegeId) {
    die("College ID not found in session");
}

$deanService = new DeanService();
$departmentFilter = $_GET['department_id'] ?? null;
$pendingRequests = $deanService->getPendingFacultyRequests($collegeId, $departmentFilter);
$currentSemester = $deanService->getCurrentSemester();
$departments = $deanService->getCollegeDepartments($collegeId);
$metrics = $deanService->getCollegeMetrics($collegeId);
$schedules = $deanService->getClassSchedules($collegeId, $departmentFilter);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dean Dashboard | PRMSU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --prmsu-gray-dark: #333333;
            --prmsu-gray: #666666;
            --prmsu-gray-light: #f5f5f5;
            --prmsu-gold: rgb(239, 187, 15);
            --prmsu-gold-light: #F9F3E5;
            --prmsu-white: #ffffff;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f3f4f6;
        }

        .sidebar {
            transition: all 0.3s ease;
            background: linear-gradient(180deg, var(--prmsu-gray-dark) 0%, #2c2c2c 100%);
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.05);
        }

        .nav-item {
            transition: all 0.2s;
            border-left: 3px solid transparent;
            margin-bottom: 0.25rem;
        }

        .nav-item:hover {
            background-color: rgba(239, 187, 15, 0.15);
        }

        .nav-item.active {
            background-color: rgba(239, 187, 15, 0.2);
            border-left: 3px solid var(--prmsu-gold);
        }

        .badge {
            background-color: var(--prmsu-gold);
            color: var(--prmsu-gray-dark);
        }

        .metric-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border-radius: 0.75rem;
            overflow: hidden;
            border-top: 4px solid transparent;
        }

        .metric-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.08);
        }

        .metric-card-departments {
            border-top-color: #3b82f6;
        }

        .metric-card-sections {
            border-top-color: #10b981;
        }

        .metric-card-courses {
            border-top-color: #8b5cf6;
        }

        .metric-card-requests {
            border-top-color: #ef4444;
        }

        .metric-card-schedules {
            border-top-color: #f59e0b;
        }

        .search-bar:focus {
            border-color: var(--prmsu-gold);
            box-shadow: 0 0 0 3px rgba(239, 187, 15, 0.2);
        }

        .btn-primary {
            background-color: var(--prmsu-gray-dark);
            transition: background-color 0.2s ease;
        }

        .btn-primary:hover {
            background-color: #4f4e4e;
        }

        .btn-gold {
            background-color: var(--prmsu-gold);
            color: var(--prmsu-gray-dark);
            transition: background-color 0.2s ease;
        }

        .btn-gold:hover {
            background-color: #e6a70f;
        }

        .table-container {
            overflow-x: auto;
            max-height: calc(100vh - 240px);
            border-radius: 0.75rem;
        }

        .table-header {
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .flash-message {
            animation: fadeOut 5s forwards;
            animation-delay: 3s;
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
            }

            to {
                opacity: 0;
            }
        }

        .action-btn {
            padding: 0.35rem 0.75rem;
            border-radius: 0.375rem;
            font-weight: 500;
            font-size: 0.75rem;
            transition: all 0.2s;
        }

        .action-btn-approve {
            background-color: #dcfce7;
            color: #166534;
        }

        .action-btn-approve:hover {
            background-color: #bbf7d0;
        }

        .action-btn-reject {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .action-btn-reject:hover {
            background-color: #fecaca;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php include __DIR__ . '/../partials/dean/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden md:ml-64">
        <?php include __DIR__ . '/../partials/dean/header.php'; ?>

        <main class="flex-1 overflow-y-auto p-6">
            <div class="max-w-7xl mx-auto">
                <!-- Flash Messages -->
                <?php if (isset($_SESSION['flash'])): ?>
                    <div class="flash-message mb-6 p-4 rounded-lg shadow-sm <?= $_SESSION['flash']['type'] === 'success' ? 'bg-green-100 text-green-700 border-l-4 border-green-500' : 'bg-red-100 text-red-700 border-l-4 border-red-500' ?>">
                        <div class="flex items-center">
                            <div class="mr-3">
                                <?php if ($_SESSION['flash']['type'] === 'success'): ?>
                                    <i class="fas fa-check-circle text-green-500"></i>
                                <?php else: ?>
                                    <i class="fas fa-exclamation-circle text-red-500"></i>
                                <?php endif; ?>
                            </div>
                            <p><?= htmlspecialchars($_SESSION['flash']['message']) ?></p>
                        </div>
                    </div>
                    <?php unset($_SESSION['flash']); ?>
                <?php endif; ?>

                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">
                            <i class="fas fa-columns mr-2 text-gray-700"></i>Dean Dashboard
                        </h1>
                        <p class="text-sm text-gray-600 mt-1">
                            <?= htmlspecialchars($currentSemester['semester_name'] . ' Semester ' . $currentSemester['academic_year']) ?>
                        </p>
                    </div>

                    <!-- Department Filter -->
                    <div class="mt-4 md:mt-0">
                        <form method="GET" action="/dean/dashboard" class="flex items-center">
                            <label for="department_id" class="block text-sm font-medium text-gray-700 mr-3 whitespace-nowrap">Department:</label>
                            <select name="department_id" id="department_id" class="rounded-md border-gray-300 shadow-sm search-bar focus:ring-gold focus:border-gold text-sm py-2" onchange="this.form.submit()">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['department_id'] ?>" <?= $departmentFilter == $dept['department_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                </div>

                <!-- Metrics Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                    <div class="metric-card metric-card-departments bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 text-blue-500 mb-4">
                            <i class="fas fa-building"></i>
                        </div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">Departments</h3>
                        <p class="text-3xl font-bold text-gray-900"><?= $metrics['departments'] ?></p>
                    </div>
                    <div class="metric-card metric-card-sections bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-green-100 text-green-500 mb-4">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">Active Sections</h3>
                        <p class="text-3xl font-bold text-gray-900"><?= $metrics['sections'] ?></p>
                    </div>
                    <div class="metric-card metric-card-courses bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-purple-100 text-purple-500 mb-4">
                            <i class="fas fa-book"></i>
                        </div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">Active Courses</h3>
                        <p class="text-3xl font-bold text-gray-900"><?= $metrics['courses'] ?></p>
                    </div>
                    <div class="metric-card metric-card-requests bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-red-100 text-red-500 mb-4">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">Pending Requests</h3>
                        <p class="text-3xl font-bold text-gray-900"><?= $metrics['pending_faculty_requests'] ?></p>
                    </div>
                    <div class="metric-card metric-card-schedules bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-amber-100 text-amber-500 mb-4">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">Class Schedules</h3>
                        <p class="text-3xl font-bold text-gray-900"><?= $metrics['schedules'] ?></p>
                    </div>
                </div>

                <!-- Pending Faculty Requests Table -->
                <div class="mb-12">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-gray-800">
                            <i class="fas fa-user-check text-gray-600 mr-2"></i>Pending Faculty Requests
                        </h2>
                        <?php if (!empty($pendingRequests)): ?>
                            <span class="bg-red-100 text-red-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                <?= count($pendingRequests) ?> Pending
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="table-container">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50 table-header">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Academic Rank</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($pendingRequests)): ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">
                                                <div class="flex flex-col items-center justify-center">
                                                    <i class="fas fa-check-circle text-gray-400 text-4xl mb-3"></i>
                                                    <p>No pending faculty requests</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($pendingRequests as $request): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 text-sm text-gray-700">
                                                    <div class="font-medium"><?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?></div>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-600">
                                                    <?= htmlspecialchars($request['username']) ?>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-600">
                                                    <a href="mailto:<?= htmlspecialchars($request['email']) ?>" class="text-blue-600 hover:text-blue-800">
                                                        <?= htmlspecialchars($request['email']) ?>
                                                    </a>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-600">
                                                    <?= htmlspecialchars($request['department_name']) ?>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-600">
                                                    <?= htmlspecialchars($request['academic_rank']) ?>
                                                </td>
                                                <td class="px-6 py-4 text-right space-x-2">
                                                    <form method="POST" action="/dean/faculty-requests" class="inline">
                                                        <input type="hidden" name="request_id" value="<?= $request['request_id'] ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="action-btn action-btn-approve">
                                                            <i class="fas fa-check mr-1"></i> Approve
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="/dean/faculty-requests" class="inline">
                                                        <input type="hidden" name="request_id" value="<?= $request['request_id'] ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <button type="submit" class="action-btn action-btn-reject">
                                                            <i class="fas fa-times mr-1"></i> Reject
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Class Schedules Table -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-gray-800">
                            <i class="fas fa-calendar-alt text-gray-600 mr-2"></i>Class Schedules
                        </h2>
                        <?php if (!empty($schedules)): ?>
                            <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                <?= count($schedules) ?> Schedules
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="table-container">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50 table-header">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Faculty</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Day</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($schedules)): ?>
                                        <tr>
                                            <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500">
                                                <div class="flex flex-col items-center justify-center">
                                                    <i class="fas fa-calendar-times text-gray-400 text-4xl mb-3"></i>
                                                    <p>No class schedules for this semester</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($schedules as $schedule): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 text-sm font-medium text-gray-700">
                                                    <?= htmlspecialchars($schedule['course_code']) ?>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-600">
                                                    <?= htmlspecialchars($schedule['section_name']) ?>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-600">
                                                    <?= htmlspecialchars($schedule['faculty_first_name'] . ' ' . $schedule['faculty_last_name']) ?>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-600">
                                                    <?php if (!empty($schedule['room_name'])): ?>
                                                        <span class="inline-flex items-center">
                                                            <i class="fas fa-door-open text-gray-400 mr-1"></i>
                                                            <?= htmlspecialchars($schedule['room_name']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-gray-400">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-600">
                                                    <?= htmlspecialchars($schedule['day_of_week']) ?>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-600">
                                                    <span class="inline-flex items-center">
                                                        <i class="far fa-clock text-gray-400 mr-1"></i>
                                                        <?= htmlspecialchars(date('h:i A', strtotime($schedule['start_time'])) . ' - ' . date('h:i A', strtotime($schedule['end_time']))) ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-600">
                                                    <?= htmlspecialchars($schedule['schedule_type']) ?>
                                                </td>
                                                <td class="px-6 py-4 text-sm">
                                                    <?php
                                                    $statusClasses = [
                                                        'Approved' => 'bg-green-100 text-green-800',
                                                        'Pending' => 'bg-yellow-100 text-yellow-800',
                                                        'Rejected' => 'bg-red-100 text-red-800'
                                                    ];
                                                    $statusIcons = [
                                                        'Approved' => '<i class="fas fa-check-circle mr-1"></i>',
                                                        'Pending' => '<i class="fas fa-clock mr-1"></i>',
                                                        'Rejected' => '<i class="fas fa-times-circle mr-1"></i>'
                                                    ];
                                                    $statusClass = $statusClasses[$schedule['status']] ?? 'bg-gray-100 text-gray-800';
                                                    $statusIcon = $statusIcons[$schedule['status']] ?? '';
                                                    ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusClass ?>">
                                                        <?= $statusIcon ?><?= htmlspecialchars($schedule['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <footer class="bg-white py-4 border-t border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <p class="text-center text-xs text-gray-500">© <?= date('Y') ?> President Ramon Magsaysay State University. All rights reserved.</p>
            </div>
        </footer>
    </div>

    <script>
        // Auto-hide flash messages after 5 seconds
        document.addEventListener('DOMContentLoaded', () => {
            const flashMessages = document.querySelectorAll('.flash-message');
            if (flashMessages.length > 0) {
                setTimeout(() => {
                    flashMessages.forEach(message => {
                        message.style.display = 'none';
                    });
                }, 8000);
            }
        });
    </script>
</body>

</html>