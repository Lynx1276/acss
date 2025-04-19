<?php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../services/SchedulingService.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';

use App\config\Database;

$currentUri = $_SERVER['REQUEST_URI'];
AuthMiddleware::handle('chair');

$departmentId = $_SESSION['user']['department_id'] ?? null;
if (!$departmentId) {
    die("Department ID not found in session");
}

$schedulingService = new SchedulingService();
$db = (new Database())->connect();

// Get faculty members and resources
$faculty = $schedulingService->getFacultyMembers($departmentId);
$courses = $schedulingService->getDepartmentCourses($departmentId);
$semesters = $schedulingService->getAllSemesters();
$classrooms = $schedulingService->getAvailableClassrooms();

// Define $stats for sidebar
$pendingApprovalsData = $schedulingService->getPendingApprovals($departmentId);
$stats = [
    'pendingApprovals' => is_array($pendingApprovalsData) ? count($pendingApprovalsData) : (int)($pendingApprovalsData ?? 0)
];

// Handle POST for adding faculty
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_faculty'])) {
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $position = $_POST['position'] ?? '';
    $specializations = $_POST['specializations'] ?? '';

    $query = "INSERT INTO faculty (first_name, last_name, position, department_id) 
              VALUES (:first_name, :last_name, :position, :department_id)";
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':position' => $position,
        ':department_id' => $departmentId
    ]);
    $facultyId = $db->lastInsertId();

    if (!empty($specializations)) {
        $specializationsArray = explode(',', $specializations);
        $specQuery = "INSERT INTO specializations (faculty_id, subject_name) VALUES (:faculty_id, :subject_name)";
        $specStmt = $db->prepare($specQuery);
        foreach ($specializationsArray as $spec) {
            $specStmt->execute([':faculty_id' => $facultyId, ':subject_name' => trim($spec)]);
        }
    }

    header('Location: /chair/faculty');
    exit;
}

// Handle POST for adding faculty load
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_load'])) {
    $facultyId = $_POST['faculty_id'] ?? null;
    $courseId = $_POST['course_id'] ?? null;
    $semesterId = $_POST['semester_id'] ?? null;
    $roomId = $_POST['room_id'] ?? null;
    $timeSlots = $_POST['time_slots'] ?? [];

    if ($facultyId && $courseId && $semesterId) {
        $schedulingService->addFacultyLoad($facultyId, $courseId, $semesterId, $roomId, $timeSlots);
        header('Location: /chair/faculty');
        exit;
    } else {
        $error = "Missing required fields for adding load.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Management | PRMSU</title>
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
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Faculty Management</h1>
                    <button onclick="document.getElementById('addFacultyModal').classList.remove('hidden')"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md flex items-center">
                        <i class="fas fa-plus mr-2"></i> Add Faculty
                    </button>
                </div>

                <!-- Faculty List -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Specializations</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Load</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($faculty as $member): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($member['position']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($member['specializations'] ?? 'None') ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= $member['current_load'] ?> units</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="/chair/faculty_edit?id=<?= $member['faculty_id'] ?>"
                                            class="text-blue-600 hover:text-blue-800 mr-2"><i class="fas fa-edit"></i> Edit</a>
                                        <button onclick="openLoadModal(<?= $member['faculty_id'] ?>)"
                                            class="text-green-600 hover:text-green-800"><i class="fas fa-plus"></i> Add Load</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Add Faculty Modal -->
                <div id="addFacultyModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center">
                    <div class="bg-white rounded-lg p-6 w-full max-w-md">
                        <h2 class="text-xl font-semibold mb-4">Add New Faculty</h2>
                        <form method="POST">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">First Name</label>
                                <input type="text" name="first_name" required class="w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Last Name</label>
                                <input type="text" name="last_name" required class="w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Position</label>
                                <input type="text" name="position" required class="w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Specializations (comma-separated)</label>
                                <input type="text" name="specializations" class="w-full rounded-md border-gray-300 shadow-sm" placeholder="e.g., Math, Physics">
                            </div>
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="document.getElementById('addFacultyModal').classList.add('hidden')"
                                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Cancel</button>
                                <button type="submit" name="add_faculty" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                                    Add Faculty
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Add Load Modal -->
                <div id="addLoadModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center">
                    <div class="bg-white rounded-lg p-6 w-full max-w-lg">
                        <h2 class="text-xl font-semibold mb-4">Add Faculty Load</h2>
                        <form method="POST" id="loadForm">
                            <input type="hidden" name="faculty_id" id="facultyId">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Semester</label>
                                <select name="semester_id" required class="w-full rounded-md border-gray-300 shadow-sm">
                                    <?php foreach ($semesters as $semester): ?>
                                        <option value="<?= $semester['semester_id'] ?>" <?= $semester['is_current'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($semester['semester_name'] . ' ' . $semester['academic_year']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Course</label>
                                <select name="course_id" required class="w-full rounded-md border-gray-300 shadow-sm">
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?= $course['course_id'] ?>">
                                            <?= htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Room</label>
                                <select name="room_id" required class="w-full rounded-md border-gray-300 shadow-sm">
                                    <?php foreach ($classrooms as $room): ?>
                                        <option value="<?= $room['room_id'] ?>">
                                            <?= htmlspecialchars($room['room_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-4" id="timeSlots">
                                <label class="block text-sm font-medium text-gray-700">Time Slot</label>
                                <div class="time-slot flex space-x-2 mb-2">
                                    <select name="time_slots[0][day_of_week]" class="editable-field w-1/3">
                                        <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day): ?>
                                            <option value="<?= $day ?>"><?= $day ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="time" name="time_slots[0][start_time]" class="editable-field w-1/3" required>
                                    <input type="time" name="time_slots[0][end_time]" class="editable-field w-1/3" required>
                                </div>
                            </div>
                            <button type="button" onclick="addTimeSlot()" class="text-blue-600 hover:text-blue-800 mb-4">
                                <i class="fas fa-plus"></i> Add Another Time Slot
                            </button>
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="document.getElementById('addLoadModal').classList.add('hidden')"
                                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Cancel</button>
                                <button type="submit" name="add_load" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md">
                                    Add Load
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let timeSlotCount = 1;

        function openLoadModal(facultyId) {
            document.getElementById('facultyId').value = facultyId;
            document.getElementById('addLoadModal').classList.remove('hidden');
        }

        function addTimeSlot() {
            const container = document.getElementById('timeSlots');
            const newSlot = document.createElement('div');
            newSlot.className = 'time-slot flex space-x-2 mb-2';
            newSlot.innerHTML = `
                <select name="time_slots[${timeSlotCount}][day_of_week]" class="editable-field w-1/3">
                    <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day): ?>
                        <option value="<?= $day ?>"><?= $day ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="time" name="time_slots[${timeSlotCount}][start_time]" class="editable-field w-1/3" required>
                <input type="time" name="time_slots[${timeSlotCount}][end_time]" class="editable-field w-1/3" required>
            `;
            container.appendChild(newSlot);
            timeSlotCount++;
        }
    </script>
</body>

</html>