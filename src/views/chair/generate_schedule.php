<?php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../services/SchedulingService.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';

// Define current URI at the top
$currentUri = $_SERVER['REQUEST_URI'];

AuthMiddleware::handle('chair');

$departmentId = $_SESSION['user']['department_id'] ?? null;
if (!$departmentId) {
    die("Department ID not found in session");
}

$schedulingService = new SchedulingService();
$db = (new Database())->connect();

// At the top of generate_schedule.php, after the includes
error_log("Starting schedule generation process for department: $departmentId");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    error_log("Schedule generation form submitted");
    $selectedSemesterId = (int)($_POST['semester_id'] ?? $currentSemester['semester_id']);
    $maxSections = (int)($_POST['max_sections'] ?? 5);
    $algorithm = $_POST['algorithm'] ?? 'basic';
    $constraints = $_POST['constraints'] ?? [];

    error_log("Generation parameters - Semester: $selectedSemesterId, Max Sections: $maxSections, Algorithm: $algorithm");
    error_log("Constraints: " . implode(', ', $constraints));

    $generatedSchedule = $schedulingService->generateSchedule($selectedSemesterId, $departmentId, $maxSections, $constraints);

    error_log("Generated " . count($generatedSchedule) . " schedule entries");
}


// Get all semesters
$semestersQuery = "SELECT semester_id, semester_name, academic_year, is_current FROM semesters ORDER BY year_start DESC, semester_name";
$stmt = $db->prepare($semestersQuery);
$stmt->execute();
$semesters = $stmt->fetchAll(PDO::FETCH_ASSOC);
$currentSemester = $schedulingService->getCurrentSemester();

// Get resources
$courses = $schedulingService->getDepartmentCourses($departmentId);
$faculty = $schedulingService->getFacultyMembers($departmentId);
$classrooms = $schedulingService->getAvailableClassrooms();

// Define $stats for sidebar
$pendingApprovalsData = $schedulingService->getPendingApprovals($departmentId);
$stats = [
    'pendingApprovals' => is_array($pendingApprovalsData) ? count($pendingApprovalsData) : (int)($pendingApprovalsData ?? 0)
];

// Handle POST request for generating schedule
$generatedSchedule = [];
$selectedSemesterId = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $selectedSemesterId = (int)($_POST['semester_id'] ?? $currentSemester['semester_id']);
    $maxSections = (int)($_POST['max_sections'] ?? 5);
    $algorithm = $_POST['algorithm'] ?? 'basic';
    $constraints = $_POST['constraints'] ?? [];

    $generatedSchedule = $schedulingService->generateSchedule($selectedSemesterId, $departmentId, $maxSections, $constraints);
}

// Handle POST request for saving edited schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_schedule'])) {
    $scheduleData = json_decode($_POST['schedule_data'], true);
    $selectedSemesterId = (int)($_POST['semester_id'] ?? $currentSemester['semester_id']);
    if ($schedulingService->saveGeneratedSchedule($scheduleData, $selectedSemesterId)) {
        header('Location: /chair/view_schedule?semester_id=' . $selectedSemesterId);
        exit;
    } else {
        $error = "Failed to save the edited schedule.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Schedule | PRMSU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        /* ... Existing styles unchanged ... */
        .editable-field {
            border: 1px solid #d1d5db;
            padding: 4px;
            border-radius: 4px;
            cursor: pointer;
        }

        .editable-field:hover {
            background-color: #f3f4f6;
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php include __DIR__ . '/../partials/chair/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden md:ml-64">
        <?php include __DIR__ . '/../partials/chair/header.php'; ?>

        <main class="flex-1 overflow-y-auto p-6">
            <div class="max-w-7xl mx-auto">
                <h1 class="text-2xl font-bold text-gray-900 mb-6">Generate New Schedule</h1>

                <?php if (!$hasOfferings): ?>
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                        <!-- Your existing warning message HTML -->
                        <form method="POST" action="/chair/create_offerings" class="inline">
                            <input type="hidden" name="semester_id" value="<?= $currentSemester['semester_id'] ?>">
                            <button type="submit" class="text-yellow-700 underline hover:text-yellow-600">
                                Click here to create default offerings
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Schedule Generation Form -->
                <div class="bg-white shadow rounded-lg overflow-hidden p-6 mb-6">
                    <form method="POST">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Semester</label>
                                <select name="semester_id" class="w-full rounded-md border-gray-300 shadow-sm">
                                    <?php foreach ($semesters as $semester): ?>
                                        <option value="<?= $semester['semester_id'] ?>"
                                            <?= $semester['is_current'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($semester['semester_name'] . ' ' . $semester['academic_year']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Max Sections</label>
                                <select name="max_sections" class="w-full rounded-md border-gray-300 shadow-sm">
                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                        <option value="<?= $i ?>" <?= $i === 5 ? 'selected' : '' ?>><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Algorithm</label>
                                <select name="algorithm" class="w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="basic">Basic Scheduling</option>
                                    <option value="advanced">Advanced (with constraints)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Schedule Constraints</label>
                            <div class="space-y-2">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="constraints[]" value="faculty_availability" checked
                                        class="rounded border-gray-300 text-blue-600 shadow-sm">
                                    <span class="ml-2">Respect faculty availability</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="constraints[]" value="room_capacity" checked
                                        class="rounded border-gray-300 text-blue-600 shadow-sm">
                                    <span class="ml-2">Respect room capacity</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="constraints[]" value="course_conflicts" checked
                                        class="rounded border-gray-300 text-blue-600 shadow-sm">
                                    <span class="ml-2">Avoid course conflicts</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3">
                            <a href="/chair/view_schedule" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">
                                Cancel
                            </a>
                            <button type="submit" name="generate" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md flex items-center">
                                <i class="fas fa-magic mr-2"></i> Generate Schedule
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Display Generated Schedule -->
                <?php if (!empty($generatedSchedule)): ?>
                    <div class="bg-white shadow rounded-lg overflow-hidden p-6">
                        <h2 class="text-lg font-semibold mb-4">Generated Schedule (Editable) -
                            <?= htmlspecialchars($semesters[array_search($selectedSemesterId, array_column($semesters, 'semester_id'))]['semester_name'] . ' ' . $semesters[array_search($selectedSemesterId, array_column($semesters, 'semester_id'))]['academic_year']) ?>
                        </h2>
                        <form method="POST" id="saveScheduleForm">
                            <input type="hidden" name="semester_id" value="<?= $selectedSemesterId ?>">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Faculty</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Day</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="scheduleTable">
                                    <?php foreach ($generatedSchedule as $index => $item): ?>
                                        <?php foreach ($item['time_slots'] as $slotIndex => $slot): ?>
                                            <tr data-index="<?= $index ?>" data-slot-index="<?= $slotIndex ?>">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?= htmlspecialchars($item['course_code'] . ' - ' . $item['course_name']) ?>
                                                    <input type="hidden" name="schedule[<?= $index ?>][course_id]" value="<?= $item['course_id'] ?>">
                                                    <input type="hidden" name="schedule[<?= $index ?>][offering_id]" value="<?= $item['offering_id'] ?>">
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="editable-field section-name"><?= htmlspecialchars($item['section_name']) ?></span>
                                                    <input type="hidden" name="schedule[<?= $index ?>][section_id]" value="<?= $item['section_id'] ?>">
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <select name="schedule[<?= $index ?>][faculty_id]" class="editable-field w-full">
                                                        <?php foreach ($faculty as $f): ?>
                                                            <option value="<?= $f['faculty_id'] ?>" <?= $f['faculty_id'] === $item['faculty_id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <select name="schedule[<?= $index ?>][room_id]" class="editable-field w-full">
                                                        <?php foreach ($classrooms as $r): ?>
                                                            <option value="<?= $r['room_id'] ?>" <?= $r['room_id'] === $item['room_id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($r['room_name']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <select name="schedule[<?= $index ?>][time_slots][<?= $slotIndex ?>][day_of_week]" class="editable-field">
                                                        <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day): ?>
                                                            <option value="<?= $day ?>" <?= $slot['day_of_week'] === $day ? 'selected' : '' ?>><?= $day ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <input type="time" name="schedule[<?= $index ?>][time_slots][<?= $slotIndex ?>][start_time]"
                                                        value="<?= $slot['start_time'] ?>" class="editable-field">
                                                    -
                                                    <input type="time" name="schedule[<?= $index ?>][time_slots][<?= $slotIndex ?>][end_time]"
                                                        value="<?= $slot['end_time'] ?>" class="editable-field">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div class="mt-6 flex justify-end space-x-3">
                                <button type="submit" name="save_schedule" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md flex items-center">
                                    <i class="fas fa-save mr-2"></i> Save Schedule
                                </button>
                            </div>
                            <input type="hidden" name="schedule_data" id="scheduleData">
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Available Resources -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-medium">Available Resources</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-6">
                        <div>
                            <h3 class="text-md font-medium mb-3 flex items-center">
                                <i class="fas fa-book mr-2 text-blue-500"></i> Courses
                            </h3>
                            <div class="space-y-2 max-h-60 overflow-y-auto">
                                <?php foreach ($courses as $course): ?>
                                    <div class="bg-gray-50 p-2 rounded-md">
                                        <div class="font-medium"><?= htmlspecialchars($course['course_code']) ?></div>
                                        <div class="text-sm"><?= htmlspecialchars($course['course_name']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-md font-medium mb-3 flex items-center">
                                <i class="fas fa-chalkboard-teacher mr-2 text-blue-500"></i> Faculty
                            </h3>
                            <div class="space-y-2 max-h-60 overflow-y-auto">
                                <?php foreach ($faculty as $member): ?>
                                    <div class="bg-gray-50 p-2 rounded-md">
                                        <div class="font-medium"><?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?></div>
                                        <div class="text-sm"><?= htmlspecialchars($member['position']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-md font-medium mb-3 flex items-center">
                                <i class="fas fa-door-open mr-2 text-blue-500"></i> Classrooms
                            </h3>
                            <div class="space-y-2 max-h-60 overflow-y-auto">
                                <?php foreach ($classrooms as $room): ?>
                                    <div class="bg-gray-50 p-2 rounded-md">
                                        <div class="font-medium"><?= htmlspecialchars($room['room_name']) ?></div>
                                        <div class="text-sm">Capacity: <?= htmlspecialchars($room['capacity']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.getElementById('saveScheduleForm')?.addEventListener('submit', function(e) {
            const form = this;
            const schedule = [];
            document.querySelectorAll('#scheduleTable tr').forEach(row => {
                const index = row.dataset.index;
                const slotIndex = row.dataset.slotIndex;
                if (!schedule[index]) {
                    schedule[index] = {
                        course_id: row.querySelector(`input[name="schedule[${index}][course_id]"]`).value,
                        offering_id: row.querySelector(`input[name="schedule[${index}][offering_id]"]`).value,
                        section_id: row.querySelector(`input[name="schedule[${index}][section_id]"]`).value,
                        faculty_id: row.querySelector(`select[name="schedule[${index}][faculty_id]"]`).value,
                        room_id: row.querySelector(`select[name="schedule[${index}][room_id]"]`).value,
                        time_slots: []
                    };
                }
                schedule[index].time_slots[slotIndex] = {
                    day_of_week: row.querySelector(`select[name="schedule[${index}][time_slots][${slotIndex}][day_of_week]"]`).value,
                    start_time: row.querySelector(`input[name="schedule[${index}][time_slots][${slotIndex}][start_time]"]`).value,
                    end_time: row.querySelector(`input[name="schedule[${index}][time_slots][${slotIndex}][end_time]"]`).value
                };
            });
            document.getElementById('scheduleData').value = JSON.stringify(schedule);
        });
    </script>
</body>

</html>