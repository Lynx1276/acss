<?php
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

$semesterId = $currentSemester['semester_id'];

// Set page variables for header
$pageTitle = "Department Schedule";
$pageSubtitle = $currentSemester['semester_name'] . " Semester " . $currentSemester['academic_year'];
$showScheduleButton = true;
$showExportButton = true;

// Get department schedules for current semester
$schedules = $schedulingService->getDepartmentSchedule($departmentId, $semesterId);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Schedule | PRMSU Scheduling System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
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
    <!-- Include Sidebar -->
    <?php include __DIR__ . '/../partials/chair/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden md:ml-64">
        <!-- Include Header -->
        <?php include __DIR__ . '/../partials/chair/header.php'; ?>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Class Schedule</h2>
                <button onclick="exportToExcel()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md flex items-center">
                    <i class="fas fa-file-excel mr-2"></i> Export to Excel
                </button>
            </div>

            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table id="scheduleTable" class="min-w-full divide-y divide-gray-200">
                        <!-- Table content remains the same -->
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Monday</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tuesday</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Wednesday</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Thursday</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Friday</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Saturday</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php for ($hour = 7; $hour < 20; $hour++): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= str_pad($hour, 2, '0', STR_PAD_LEFT) ?>:00 - <?= str_pad($hour + 1, 2, '0', STR_PAD_LEFT) ?>:00
                                    </td>
                                    <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day): ?>
                                        <td class="px-6 py-4 schedule-cell" data-day="<?= $day ?>" data-time="<?= $hour ?>:00">
                                            <?php foreach ($schedules as $schedule): ?>
                                                <?php if ($schedule['day_of_week'] == $day && date('H', strtotime($schedule['start_time'])) == $hour): ?>
                                                    <div class="draggable bg-blue-100 border border-blue-200 rounded p-2 mb-1 cursor-move"
                                                        data-schedule-id="<?= $schedule['schedule_id'] ?>">
                                                        <div class="font-medium"><?= $schedule['course_code'] ?></div>
                                                        <div class="text-xs"><?= $schedule['faculty_name'] ?></div>
                                                        <div class="text-xs">Room: <?= $schedule['room_name'] ?></div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Drag and drop functionality
        interact('.draggable').draggable({
            inertia: true,
            modifiers: [
                interact.modifiers.restrictRect({
                    restriction: 'parent',
                    endOnly: true
                })
            ],
            autoScroll: true,
            listeners: {
                move: dragMoveListener
            }
        });

        function dragMoveListener(event) {
            const target = event.target;
            const x = (parseFloat(target.getAttribute('data-x')) || 0);
            const y = (parseFloat(target.getAttribute('data-y')) || 0);

            target.style.transform = `translate(${x + event.dx}px, ${y + event.dy}px)`;
            target.setAttribute('data-x', x + event.dx);
            target.setAttribute('data-y', y + event.dy);
        }

        // Drop target setup
        interact('.schedule-cell').dropzone({
            accept: '.draggable',
            overlap: 0.5,
            ondrop: function(event) {
                const draggableElement = event.relatedTarget;
                const dropzoneElement = event.target;

                // Update position in DOM
                dropzoneElement.appendChild(draggableElement);

                // Reset position
                draggableElement.style.transform = 'none';
                draggableElement.removeAttribute('data-x');
                draggableElement.removeAttribute('data-y');

                // Get schedule data
                const scheduleId = draggableElement.getAttribute('data-schedule-id');
                const newDay = dropzoneElement.getAttribute('data-day');
                const newTime = dropzoneElement.getAttribute('data-time');

                // Send update to server
                updateSchedule(scheduleId, newDay, newTime);
            }
        });

        function updateSchedule(scheduleId, day, time) {
            fetch('/api/schedule/update', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        schedule_id: scheduleId,
                        day_of_week: day,
                        start_time: time
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        alert('Error updating schedule');
                        // You might want to revert the UI change here
                    }
                });
        }

        // Export to Excel
        function exportToExcel() {
            const table = document.getElementById('scheduleTable');
            const workbook = XLSX.utils.table_to_book(table);
            XLSX.writeFile(workbook, 'PRMSU_Schedule.xlsx');
        }
    </script>
</body>

</html>