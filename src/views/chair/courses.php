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

// Get courses and programs
$courses = $schedulingService->getDepartmentCourses($departmentId);
$programs = $schedulingService->getDepartmentPrograms($departmentId);

// Define $stats for sidebar
$pendingApprovalsData = $schedulingService->getPendingApprovals($departmentId);
$stats = [
    'pendingApprovals' => is_array($pendingApprovalsData) ? count($pendingApprovalsData) : (int)($pendingApprovalsData ?? 0)
];

// Handle POST for adding a course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    $courseCode = $_POST['course_code'] ?? '';
    $courseName = $_POST['course_name'] ?? '';
    $programId = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
    $units = (int)($_POST['units'] ?? 0);
    $lectureHours = (int)($_POST['lecture_hours'] ?? 0);
    $labHours = (int)($_POST['lab_hours'] ?? 0);
    $semester = $_POST['semester'] ?? '1st';
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $query = "INSERT INTO courses (course_code, course_name, department_id, program_id, units, lecture_hours, lab_hours, semester, is_active) 
              VALUES (:course_code, :course_name, :department_id, :program_id, :units, :lecture_hours, :lab_hours, :semester, :is_active)";
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':course_code' => $courseCode,
        ':course_name' => $courseName,
        ':department_id' => $departmentId,
        ':program_id' => $programId,
        ':units' => $units,
        ':lecture_hours' => $lectureHours,
        ':lab_hours' => $labHours,
        ':semester' => $semester,
        ':is_active' => $isActive
    ]);

    header('Location: /chair/courses');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Management | PRMSU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --prmsu-blue: #0056b3;
            --prmsu-gold: #FFD700;
            --prmsu-light: #f8f9fa;
            --prmsu-dark: #343a40;
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
                    <h1 class="text-2xl font-bold text-gray-900">Course Management</h1>
                    <button onclick="document.getElementById('addCourseModal').classList.remove('hidden')"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md flex items-center">
                        <i class="fas fa-plus mr-2"></i> Add Course
                    </button>
                </div>

                <!-- Courses List -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Program</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Units</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hours (Lec/Lab)</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Semester</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($course['course_code']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($course['course_name']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($course['program_name'] ?? 'N/A') ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($course['units']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($course['lecture_hours'] . '/' . $course['lab_hours']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($course['semester']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= $course['is_active'] ? 'Active' : 'Inactive' ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="/chair/deit_course?id=<?= $course['course_id'] ?>"
                                            class="text-blue-600 hover:text-blue-800"><i class="fas fa-edit"></i> Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Add Course Modal -->
                <div id="addCourseModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center">
                    <div class="bg-white rounded-lg p-6 w-full max-w-md">
                        <h2 class="text-xl font-semibold mb-4">Add New Course</h2>
                        <form method="POST">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Course Code</label>
                                <input type="text" name="course_code" required class="w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Course Name</label>
                                <input type="text" name="course_name" required class="w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Program</label>
                                <select name="program_id" class="w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">None</option>
                                    <?php foreach ($programs as $program): ?>
                                        <option value="<?= $program['program_id'] ?>">
                                            <?= htmlspecialchars($program['program_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Units</label>
                                <input type="number" name="units" min="1" max="255" required class="w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div class="mb-4 grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Lecture Hours</label>
                                    <input type="number" name="lecture_hours" min="0" max="255" value="0" class="w-full rounded-md border-gray-300 shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Lab Hours</label>
                                    <input type="number" name="lab_hours" min="0" max="255" value="0" class="w-full rounded-md border-gray-300 shadow-sm">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Semester</label>
                                <select name="semester" class="w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="1st">1st</option>
                                    <option value="2nd">2nd</option>
                                    <option value="Summer">Summer</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="is_active" checked class="rounded border-gray-300 text-blue-600 shadow-sm">
                                    <span class="ml-2 text-sm font-medium text-gray-700">Active?</span>
                                </label>
                            </div>
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="document.getElementById('addCourseModal').classList.add('hidden')"
                                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Cancel</button>
                                <button type="submit" name="add_course" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                                    Add Course
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>