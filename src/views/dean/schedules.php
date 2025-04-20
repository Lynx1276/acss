<?php
                                // views/dean/schedules.php
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
$currentSemester = $deanService->getCurrentSemester();
$departments = $deanService->getCollegeDepartments($collegeId);
$schedules = $deanService->getClassSchedules($collegeId, $departmentFilter);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Schedules | PRMSU</title>
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--prmsu-gray-light);
        }

        .sidebar {
            transition: all 0.3s ease;
            background-color: var(--prmsu-gray-dark);
        }

        .nav-item:hover {
            background-color: rgba(244, 147, 12, 0.15);
        }

        .nav-item.active {
            background-color: rgba(212, 175, 55, 0.2);
            border-left: 4px solid var(--prmsu-gold);
        }

        .search-bar:focus {
            border-color: var(--prmsu-gold);
            box-shadow: 0 0 0 3px rgba(239, 187, 15, 0.2);
        }

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
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
                    <div class="mb-6 p-4 rounded-lg <?= $_SESSION['flash']['type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                        <?= htmlspecialchars($_SESSION['flash']['message']) ?>
                    </div>
                    <?php unset($_SESSION['flash']); ?>
                <?php endif; ?>

                <h1 class="text-2xl font-bold text-gray-900 mb-6">
                    Class Schedules - <?= htmlspecialchars($currentSemester['semester_name'] . ' Semester ' . $currentSemester['academic_year']) ?>
                </h1>

                <!-- Department Filter -->
                <div class="mb-6">
                    <form method="GET" action="/dean/schedules">
                        <label for="department_id" class="block text-sm font-medium text-gray-700 mb-1">Filter by Department</label>
                        <select name="department_id" id="department_id" class="w-48 rounded-md border-gray-300 shadow-sm search-bar focus:ring-gold focus:border-gold" onchange="this.form.submit()">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['department_id'] ?>" <?= $departmentFilter == $dept['department_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <!-- Class Schedules Table -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="table-container">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
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
                                        <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                                            No class schedules for this semester
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($schedules as $schedule): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                <?= htmlspecialchars($schedule['course_code']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                <?= htmlspecialchars($schedule['section_name']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                <?= htmlspecialchars($schedule['faculty_first_name'] . ' ' . $schedule['faculty_last_name']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                <?= htmlspecialchars($schedule['room_name'] ?? 'N/A') ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                <?= htmlspecialchars($schedule['day_of_week']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                <?= htmlspecialchars(date('h:i A', strtotime($schedule['start_time'])) . ' - ' . date('h:i A', strtotime($schedule['end_time']))) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                <?= htmlspecialchars($schedule['schedule_type']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                                    <?= $schedule['status'] === 'Approved' ? 'bg-green-100 text-green-800' : ($schedule['status'] === 'Pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                                    <?= htmlspecialchars($schedule['status']) ?>
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
        </main>
    </div>
</body>

</html>