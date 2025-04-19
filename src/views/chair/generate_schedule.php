<?php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../services/SchedulingService.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';

use App\config\Database;

// Define current URI
$currentUri = $_SERVER['REQUEST_URI'];

AuthMiddleware::handle('chair');

$departmentId = $_SESSION['user']['department_id'] ?? null;
if (!$departmentId) {
    die("Department ID not found in session");
}

$schedulingService = new SchedulingService();
$db = (new Database())->connect();

error_log("Starting schedule generation process for department: $departmentId");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    error_log("Schedule generation form submitted");
    $selectedSemesterId = (int)($_POST['semester_id'] ?? $currentSemester['semester_id']);
    $maxSections = (int)($_POST['max_sections'] ?? 5);
    $yearLevel = $_POST['year_level'] ?? 'all';
    $constraints = $_POST['constraints'] ?? [];

    error_log("Generation parameters - Semester: $selectedSemesterId, Max Sections: $maxSections, Year Level: $yearLevel");
    error_log("Constraints: " . implode(', ', $constraints));

    $generatedSchedule = $schedulingService->generateSchedule($selectedSemesterId, $departmentId, $maxSections, $constraints, $yearLevel);

    // Log missing time slots
    foreach ($generatedSchedule as $index => $item) {
        foreach ($item['time_slots'] as $slotIndex => $slot) {
            if (empty($slot['start_time']) || empty($slot['end_time'])) {
                error_log("Missing time for course {$item['course_code']}, slot $slotIndex: " . json_encode($slot));
            }
        }
    }

    error_log("Generated " . count($generatedSchedule) . " schedule entries");
}

// Get semesters
$semestersQuery = "SELECT semester_id, semester_name, academic_year, is_current FROM semesters ORDER BY year_start DESC, semester_name";
$stmt = $db->prepare($semestersQuery);
$stmt->execute();
$semesters = $stmt->fetchAll(PDO::FETCH_ASSOC);
$currentSemester = $schedulingService->getCurrentSemester();

// Get resources
$courses = $schedulingService->getDepartmentCourses($departmentId);
$faculty = $schedulingService->getFacultyMembers($departmentId);
$classrooms = $schedulingService->getAvailableClassrooms();

// Define stats
$pendingApprovalsData = $schedulingService->getPendingApprovals($departmentId);
$stats = [
    'pendingApprovals' => is_array($pendingApprovalsData) ? count($pendingApprovalsData) : (int)($pendingApprovalsData ?? 0)
];

// Handle POST for generating
$generatedSchedule = [];
$selectedSemesterId = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $selectedSemesterId = (int)($_POST['semester_id'] ?? $currentSemester['semester_id']);
    $maxSections = (int)($_POST['max_sections'] ?? 5);
    $yearLevel = $_POST['year_level'] ?? 'all';
    $constraints = $_POST['constraints'] ?? [];

    $generatedSchedule = $schedulingService->generateSchedule($selectedSemesterId, $departmentId, $maxSections, $constraints, $yearLevel);
}

// Handle POST for saving
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

        .nav-item {
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .nav-item:hover {
            background-color: #2b6cb0;
        }

        .nav-item.active {
            background-color: rgba(255, 255, 255, 0.15);
            border-left: 3px solid var(--prmsu-gold);
        }

        .badge {
            background-color: var(--prmsu-gold);
            color: var(--prmsu-dark);
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .editable-field {
            border: 1px solid #d1d5db;
            padding: 4px;
            border-radius: 4px;
            cursor: pointer;
        }

        .editable-field:hover {
            background-color: #f3f4f6;
        }

        .conflict-highlight {
            animation: pulse 2s infinite;
        }

        .faculty-conflict {
            background-color: rgba(255, 215, 0, 0.2);
            border-left: 3px solid #FFD700;
        }

        .room-conflict {
            background-color: rgba(220, 38, 38, 0.2);
            border-left: 3px solid #DC2626;
        }

        .missing-time {
            background-color: rgba(255, 165, 0, 0.2);
            border-left: 3px solid #FFA500;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.8;
            }

            100% {
                opacity: 1;
            }
        }

        .year-level-tabs {
            display: flex;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 1rem;
        }

        .year-level-tab {
            padding: 0.5rem 1rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }

        .year-level-tab.active {
            border-bottom-color: var(--prmsu-blue);
            color: var(--prmsu-blue);
            font-weight: 500;
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

                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 p-4 mb-6">
                        <p class="text-red-700"><?= htmlspecialchars($error) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Schedule Generation Form -->
                <div class="bg-white shadow rounded-lg p-6 mb-6">
                    <form method="POST">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Semester</label>
                                <select name="semester_id" class="w-full rounded-md border-gray-300 shadow-sm">
                                    <?php foreach ($semesters as $semester): ?>
                                        <option value="<?= $semester['semester_id'] ?>" <?= $semester['is_current'] ? 'selected' : '' ?>>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">Year Level</label>
                                <select name="year_level" class="w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="all">All Year Levels</option>
                                    <option value="1st Year">1st Year</option>
                                    <option value="2nd Year">2nd Year</option>
                                    <option value="3rd Year">3rd Year</option>
                                    <option value="4th Year">4th Year</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Schedule Constraints</label>
                            <div class="space-y-2">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="constraints[]" value="faculty_availability" checked class="rounded border-gray-300 text-blue-600 shadow-sm">
                                    <span class="ml-2">Respect faculty availability</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="constraints[]" value="room_capacity" checked class="rounded border-gray-300 text-blue-600 shadow-sm">
                                    <span class="ml-2">Respect room capacity</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="constraints[]" value="course_conflicts" checked class="rounded border-gray-300 text-blue-600 shadow-sm">
                                    <span class="ml-2">Avoid course conflicts</span>
                                </label>
                            </div>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <a href="/chair/view_schedule" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Cancel</a>
                            <button type="submit" name="generate" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md flex items-center">
                                <i class="fas fa-magic mr-2"></i> Generate Schedule
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Display Generated Schedule -->
                <?php if (!empty($generatedSchedule)): ?>
                    <div class="bg-white shadow rounded-lg p-6">
                        <h2 class="text-lg font-semibold mb-4">Generated Schedule (Editable) -
                            <?= htmlspecialchars($semesters[array_search($selectedSemesterId, array_column($semesters, 'semester_id'))]['semester_name'] . ' ' . $semesters[array_search($selectedSemesterId, array_column($semesters, 'semester_id'))]['academic_year']) ?>
                        </h2>
                        <div class="year-level-tabs">
                            <div class="year-level-tab active" data-year="all">All</div>
                            <div class="year-level-tab" data-year="1st Year">1st Year</div>
                            <div class="year-level-tab" data-year="2nd Year">2nd Year</div>
                            <div class="year-level-tab" data-year="3rd Year">3rd Year</div>
                            <div class="year-level-tab" data-year="4th Year">4th Year</div>
                        </div>
                        <form method="POST" id="saveScheduleForm" onsubmit="prepareScheduleData()">
                            <input type="hidden" name="save_schedule" value="1">
                            <input type="hidden" name="semester_id" value="<?= $selectedSemesterId ?>">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year Level</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Faculty</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Day</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="scheduleTable">
                                    <?php foreach ($generatedSchedule as $index => $item): ?>
                                        <?php foreach ($item['time_slots'] as $slotIndex => $slot): ?>
                                            <?php
                                            // Default times if missing
                                            $startTime = !empty($slot['start_time']) ? $slot['start_time'] : '08:00';
                                            $endTime = !empty($slot['end_time']) ? $slot['end_time'] : '09:00';
                                            $hasMissingTime = empty($slot['start_time']) || empty($slot['end_time']);
                                            ?>
                                            <tr data-index="<?= $index ?>" data-slot-index="<?= $slotIndex ?>"
                                                data-year-level="<?= htmlspecialchars($item['year_level'] ?? '1st Year') ?>"
                                                class="<?= $hasMissingTime ? 'missing-time' : '' ?>">
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
                                                    <select name="schedule[<?= $index ?>][year_level]" class="editable-field">
                                                        <option value="1st Year" <?= ($item['year_level'] ?? '1st Year') === '1st Year' ? 'selected' : '' ?>>1st Year</option>
                                                        <option value="2nd Year" <?= ($item['year_level'] ?? '1st Year') === '2nd Year' ? 'selected' : '' ?>>2nd Year</option>
                                                        <option value="3rd Year" <?= ($item['year_level'] ?? '1st Year') === '3rd Year' ? 'selected' : '' ?>>3rd Year</option>
                                                        <option value="4th Year" <?= ($item['year_level'] ?? '1st Year') === '4th Year' ? 'selected' : '' ?>>4th Year</option>
                                                    </select>
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
                                                        value="<?= htmlspecialchars($startTime) ?>" class="editable-field" required>
                                                    -
                                                    <input type="time" name="schedule[<?= $index ?>][time_slots][<?= $slotIndex ?>][end_time]"
                                                        value="<?= htmlspecialchars($endTime) ?>" class="editable-field" required>
                                                    <?php if ($hasMissingTime): ?>
                                                        <span class="text-orange-600 text-sm"><i class="fas fa-exclamation-circle"></i> Time missing</span>
                                                    <?php endif; ?>
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
        // Year level filtering
        document.querySelectorAll('.year-level-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.year-level-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                const yearLevel = tab.dataset.year;
                const rows = document.querySelectorAll('#scheduleTable tr');
                rows.forEach(row => {
                    const rowYear = row.dataset.yearLevel;
                    row.style.display = (yearLevel === 'all' || rowYear === yearLevel) ? '' : 'none';
                });
            });
        });

        function prepareScheduleData() {
            const scheduleData = [];
            document.querySelectorAll('#scheduleTable tr').forEach(row => {
                const index = row.dataset.index;
                const slotIndex = row.dataset.slotIndex;

                if (!scheduleData[index]) {
                    scheduleData[index] = {
                        course_id: row.querySelector(`input[name="schedule[${index}][course_id]"]`).value,
                        offering_id: row.querySelector(`input[name="schedule[${index}][offering_id]"]`).value,
                        section_id: row.querySelector(`input[name="schedule[${index}][section_id]"]`).value,
                        faculty_id: row.querySelector(`select[name="schedule[${index}][faculty_id]"]`).value,
                        room_id: row.querySelector(`select[name="schedule[${index}][room_id]"]`).value,
                        year_level: row.querySelector(`select[name="schedule[${index}][year_level]"]`).value,
                        time_slots: []
                    };
                }

                scheduleData[index].time_slots[slotIndex] = {
                    day_of_week: row.querySelector(`select[name="schedule[${index}][time_slots][${slotIndex}][day_of_week]"]`).value,
                    start_time: row.querySelector(`input[name="schedule[${index}][time_slots][${slotIndex}][start_time]"]`).value,
                    end_time: row.querySelector(`input[name="schedule[${index}][time_slots][${slotIndex}][end_time]"]`).value
                };
            });

            document.getElementById('scheduleData').value = JSON.stringify(scheduleData);
        }

        function checkForConflicts() {
            const schedule = [];
            const departmentId = <?= $departmentId ?>;

            document.querySelectorAll('#scheduleTable tr').forEach(row => {
                const index = row.dataset.index;
                const slotIndex = row.dataset.slotIndex;
                if (!schedule[index]) {
                    schedule[index] = {
                        course_id: row.querySelector(`input[name="schedule[${index}][course_id]"]`).value,
                        offering_id: row.querySelector(`input[name="schedule[${index}][offering_id]"]`).value,
                        section_id: row.querySelector(`input[name="schedule[${index}][section_id]"]`).value,
                        faculty_id: row.querySelector(`select[name="schedule[${index}][faculty_id]"]`).value,
                        faculty_name: row.querySelector(`select[name="schedule[${index}][faculty_id]"] option:checked`).textContent,
                        room_id: row.querySelector(`select[name="schedule[${index}][room_id]"]`).value,
                        room_name: row.querySelector(`select[name="schedule[${index}][room_id]"] option:checked`).textContent,
                        year_level: row.querySelector(`select[name="schedule[${index}][year_level]"]`).value,
                        time_slots: []
                    };
                }
                const startTime = row.querySelector(`input[name="schedule[${index}][time_slots][${slotIndex}][start_time]"]`).value;
                const endTime = row.querySelector(`input[name="schedule[${index}][time_slots][${slotIndex}][end_time]"]`).value;
                // Skip conflict check if times are missing
                if (!startTime || !endTime) return;

                schedule[index].time_slots[slotIndex] = {
                    day_of_week: row.querySelector(`select[name="schedule[${index}][time_slots][${slotIndex}][day_of_week]"]`).value,
                    start_time: startTime,
                    end_time: endTime
                };
            });

            fetch('/api/detect-conflicts', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        schedule: schedule,
                        semester_id: document.querySelector('select[name="semester_id"]').value,
                        department_id: departmentId
                    })
                })
                .then(response => response.json())
                .then(conflicts => {
                    displayConflicts(conflicts);
                })
                .catch(error => {
                    console.error('Error checking conflicts:', error);
                });
        }

        function displayConflicts(conflicts) {
            document.querySelectorAll('.conflict-highlight').forEach(el => {
                el.classList.remove('conflict-highlight', 'faculty-conflict', 'room-conflict');
            });

            const conflictList = document.getElementById('conflictList');
            if (conflictList) conflictList.innerHTML = '';

            const conflictAlert = document.getElementById('conflictAlert');
            if (!conflictAlert) return;

            if (conflicts.length === 0) {
                conflictAlert.className = 'bg-green-100 border-l-4 border-green-500 p-4 mb-6';
                conflictAlert.innerHTML = `
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-2"></i>
                        <p class="text-green-700">No scheduling conflicts detected</p>
                    </div>
                `;
                return;
            }

            conflictAlert.className = 'bg-red-100 border-l-4 border-red-500 p-4 mb-6';
            conflictAlert.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                    <p class="text-red-700 font-medium">${conflicts.length} conflict(s) detected</p>
                </div>
                <ul class="mt-2 list-disc list-inside" id="conflictList"></ul>
            `;

            conflicts.forEach(conflict => {
                const li = document.createElement('li');
                li.className = 'text-red-700 text-sm';
                li.textContent = conflict.message;
                conflictList.appendChild(li);

                document.querySelectorAll('#scheduleTable tr').forEach(row => {
                    const index = row.dataset.index;
                    const scheduleItem = schedule[index];
                    if (scheduleItem &&
                        ((scheduleItem.faculty_id == conflict.item.faculty_id && conflict.type === 'faculty') ||
                            (scheduleItem.room_id == conflict.item.room_id && conflict.type === 'room'))) {
                        row.classList.add('conflict-highlight', `${conflict.type}-conflict`);
                    }
                });
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('scheduleTable')?.addEventListener('change', (e) => {
                if (e.target.closest('select, input')) checkForConflicts();
            });
            if (document.querySelector('#scheduleTable tr')) checkForConflicts();
        });
    </script>
</body>

</html>