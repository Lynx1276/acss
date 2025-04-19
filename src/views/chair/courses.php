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

// Get courses
$courses = $schedulingService->getDepartmentCourses($departmentId);

// Define $stats for sidebar
$pendingApprovalsData = $schedulingService->getPendingApprovals($departmentId);
$stats = [
    'pendingApprovals' => is_array($pendingApprovalsData) ? count($pendingApprovalsData) : (int)($pendingApprovalsData ?? 0)
];

// Handle POST for adding a course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    $courseCode = $_POST['course_code'] ?? '';
    $courseName = $_POST['course_name'] ?? '';
    $units = (int)($_POST['units'] ?? 0);
    $lectureHours = (int)($_POST['lecture_hours'] ?? 0);
    $labHours = (int)($_POST['lab_hours'] ?? 0);
    $semester = $_POST['semester'] ?? '1st';
    $yearLevel = $_POST['year_level'] ?? null;
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $query = "INSERT INTO courses (course_code, course_name, department_id, units, lecture_hours, lab_hours, semester, year_level, is_active) 
              VALUES (:course_code, :course_name, :department_id, :units, :lecture_hours, :lab_hours, :semester, :year_level, :is_active)";
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':course_code' => $courseCode,
        ':course_name' => $courseName,
        ':department_id' => $departmentId,
        ':units' => $units,
        ':lecture_hours' => $lectureHours,
        ':lab_hours' => $labHours,
        ':semester' => $semester,
        ':year_level' => $yearLevel,
        ':is_active' => $isActive
    ]);

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Course added successfully'];
    header('Location: /chair/courses');
    exit;
}

// Handle POST for editing a course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_course'])) {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $courseCode = $_POST['course_code'] ?? '';
    $courseName = $_POST['course_name'] ?? '';
    $units = (int)($_POST['units'] ?? 0);
    $lectureHours = (int)($_POST['lecture_hours'] ?? 0);
    $labHours = (int)($_POST['lab_hours'] ?? 0);
    $semester = $_POST['semester'] ?? '1st';
    $yearLevel = $_POST['year_level'] ?? null;
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $query = "UPDATE courses SET 
                course_code = :course_code, 
                course_name = :course_name, 
                units = :units, 
                lecture_hours = :lecture_hours, 
                lab_hours = :lab_hours, 
                semester = :semester, 
                year_level = :year_level, 
                is_active = :is_active 
              WHERE course_id = :course_id AND department_id = :department_id";

    $stmt = $db->prepare($query);
    $stmt->execute([
        ':course_code' => $courseCode,
        ':course_name' => $courseName,
        ':units' => $units,
        ':lecture_hours' => $lectureHours,
        ':lab_hours' => $labHours,
        ':semester' => $semester,
        ':year_level' => $yearLevel,
        ':is_active' => $isActive,
        ':course_id' => $courseId,
        ':department_id' => $departmentId
    ]);

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Course updated successfully'];
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
        /* Updated PRMSU Color Palette - Gray, White, Gold */
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
            background: linear-gradient(180deg, var(--prmsu-gray-dark) 0%, rgb(79, 78, 78) 100%);
        }

        .sidebar-header {
            background-color: rgba(0, 0, 0, 0.2);
        }

        .nav-item {
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .nav-item:hover {
            background-color: rgba(244, 147, 12, 0.15);
        }

        .nav-item.active {
            background-color: rgba(212, 175, 55, 0.2);
            border-left: 3px solid var(--prmsu-gold);
        }

        .nav-item.active:hover {
            background-color: rgba(212, 175, 55, 0.25);
        }

        .badge {
            background-color: var(--prmsu-gold);
            color: var(--prmsu-gray-dark);
        }

        .status-active {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-inactive {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        table {
            border-collapse: separate;
            border-spacing: 0;
        }

        table th {
            position: sticky;
            top: 0;
            background-color: #f9fafb;
            z-index: 10;
        }

        .table-container {
            overflow-x: auto;
            max-height: calc(100vh - 240px);
            border-radius: 8px;
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

                <!-- Courses List - Table View -->
                <div class="bg-white rounded-lg shadow overflow-hidden mb-8">
                    <div class="table-container">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Course Code
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Course Name
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Units
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Hours (L/LB)
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Semester
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Year Level
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($courses as $course): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($course['course_code']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            <?= htmlspecialchars($course['course_name']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            <?= htmlspecialchars($course['units']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            <?= htmlspecialchars($course['lecture_hours'] . 'L/' . $course['lab_hours'] . 'LB') ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            <?= htmlspecialchars($course['semester']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            <?= htmlspecialchars($course['year_level'] ?? 'N/A') ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $course['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                                <?= $course['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button onclick="openEditModal(<?= htmlspecialchars(json_encode($course)) ?>)"
                                                class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-edit mr-1"></i> Edit
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Add Course Modal -->
                <div id="addCourseModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
                    <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold">Add New Course</h2>
                            <button onclick="document.getElementById('addCourseModal').classList.add('hidden')"
                                class="text-gray-500 hover:text-gray-700">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <form method="POST">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Course Code*</label>
                                    <input type="text" name="course_code" required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Course Name*</label>
                                    <input type="text" name="course_name" required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Units*</label>
                                        <input type="number" name="units" min="1" max="255" required
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Lecture Hours</label>
                                        <input type="number" name="lecture_hours" min="0" max="255" value="0"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Lab Hours</label>
                                        <input type="number" name="lab_hours" min="0" max="255" value="0"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Semester*</label>
                                        <select name="semester" required
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="1st">1st Semester</option>
                                            <option value="2nd">2nd Semester</option>
                                            <option value="Summer">Summer</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Year Level</label>
                                        <select name="year_level"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="">Select Year</option>
                                            <option value="1st Year">1st Year</option>
                                            <option value="2nd Year">2nd Year</option>
                                            <option value="3rd Year">3rd Year</option>
                                            <option value="4th Year">4th Year</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="flex items-center">
                                    <input type="checkbox" name="is_active" checked id="is_active"
                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <label for="is_active" class="ml-2 block text-sm text-gray-700">
                                        Active Course
                                    </label>
                                </div>

                                <div class="flex justify-end space-x-3 pt-4">
                                    <button type="button" onclick="document.getElementById('addCourseModal').classList.add('hidden')"
                                        class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Cancel
                                    </button>
                                    <button type="submit" name="add_course"
                                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Add Course
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Edit Course Modal -->
                <div id="editCourseModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
                    <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold">Edit Course</h2>
                            <button onclick="document.getElementById('editCourseModal').classList.add('hidden')"
                                class="text-gray-500 hover:text-gray-700">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="course_id" id="edit_course_id">

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Course Code*</label>
                                    <input type="text" name="course_code" id="edit_course_code" required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Course Name*</label>
                                    <input type="text" name="course_name" id="edit_course_name" required
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Units*</label>
                                        <input type="number" name="units" id="edit_units" min="1" max="255" required
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Lecture Hours</label>
                                        <input type="number" name="lecture_hours" id="edit_lecture_hours" min="0" max="255"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Lab Hours</label>
                                        <input type="number" name="lab_hours" id="edit_lab_hours" min="0" max="255"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Semester*</label>
                                        <select name="semester" id="edit_semester" required
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="1st">1st Semester</option>
                                            <option value="2nd">2nd Semester</option>
                                            <option value="Summer">Summer</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Year Level</label>
                                        <select name="year_level" id="edit_year_level"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="">Select Year</option>
                                            <option value="1st Year">1st Year</option>
                                            <option value="2nd Year">2nd Year</option>
                                            <option value="3rd Year">3rd Year</option>
                                            <option value="4th Year">4th Year</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="flex items-center">
                                    <input type="checkbox" name="is_active" id="edit_is_active"
                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <label for="edit_is_active" class="ml-2 block text-sm text-gray-700">
                                        Active Course
                                    </label>
                                </div>

                                <div class="flex justify-end space-x-3 pt-4">
                                    <button type="button" onclick="document.getElementById('editCourseModal').classList.add('hidden')"
                                        class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Cancel
                                    </button>
                                    <button type="submit" name="edit_course"
                                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Update Course
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Close modals when clicking outside
        document.getElementById('addCourseModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });

        document.getElementById('editCourseModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });

        // Function to open edit modal and populate form fields
        function openEditModal(course) {
            // Set values to form fields
            document.getElementById('edit_course_id').value = course.course_id;
            document.getElementById('edit_course_code').value = course.course_code;
            document.getElementById('edit_course_name').value = course.course_name;
            document.getElementById('edit_units').value = course.units;
            document.getElementById('edit_lecture_hours').value = course.lecture_hours;
            document.getElementById('edit_lab_hours').value = course.lab_hours;

            // Set select options
            document.getElementById('edit_semester').value = course.semester;

            const yearLevelSelect = document.getElementById('edit_year_level');
            yearLevelSelect.value = course.year_level || '';

            // Set checkbox
            document.getElementById('edit_is_active').checked = course.is_active === 1;

            // Show the modal
            document.getElementById('editCourseModal').classList.remove('hidden');
        }
    </script>
</body>

</html>