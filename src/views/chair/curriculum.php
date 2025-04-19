<?php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../services/SchedulingService.php';
require_once __DIR__ . '/../../services/CurriculumService.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';

use App\config\Database;

$currentUri = $_SERVER['REQUEST_URI'];
AuthMiddleware::handle('chair');

$departmentId = $_SESSION['user']['department_id'] ?? null;
if (!$departmentId) {
    die("Department ID not found in session");
}

$schedulingService = new SchedulingService();
$curriculumService = new CurriculumService();
$db = (new Database())->connect();

$curricula = $schedulingService->getDepartmentCurricula($departmentId);
$departments = $schedulingService->getAllDepartments();
$searchResults = [];

// Handle course search
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_courses'])) {
    $searchTerm = $_POST['search_term'] ?? '';
    $searchDepartmentId = $_POST['search_department_id'] ?? null;

    $query = "SELECT c.course_id, c.course_code, c.course_name, c.units, c.semester, c.year_level, 
                     d.department_name
              FROM courses c
              JOIN departments d ON c.department_id = d.department_id
              WHERE (c.course_code LIKE :search_term OR c.course_name LIKE :search_term)";
    if ($searchDepartmentId) {
        $query .= " AND c.department_id = :department_id";
    }
    $query .= " ORDER BY c.course_code";

    $stmt = $db->prepare($query);
    $params = [':search_term' => "%$searchTerm%"];
    if ($searchDepartmentId) {
        $params[':department_id'] = $searchDepartmentId;
    }
    $stmt->execute($params);
    $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle create course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_course'])) {
    try {
        $courseData = [
            'course_code' => $_POST['course_code'] ?? throw new Exception("Course code required"),
            'course_name' => $_POST['course_name'] ?? throw new Exception("Course name required"),
            'units' => $_POST['units'] ?? throw new Exception("Units required"),
            'lecture_hours' => $_POST['lecture_hours'] ?? 0,
            'lab_hours' => $_POST['lab_hours'] ?? 0,
            'semester' => $_POST['semester'] ?? throw new Exception("Semester required"),
            'year_level' => $_POST['year_level'] ?? null,
            'department_id' => $departmentId,
            'program_id' => $_POST['program_id'] ?? null
        ];
        $curriculumService->createCourse($courseData);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Course created successfully'];
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to create course: ' . $e->getMessage()];
    }
    header('Location: /chair/curriculum');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Curriculum Management | PRMSU</title>
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

        .modal {
            transition: opacity 0.3s ease, transform 0.3s ease;
            transform: scale(0.95);
        }

        .modal.show {
            transform: scale(1);
            opacity: 1;
        }

        .table-row:hover {
            background-color: var(--prmsu-gold-light);
            transition: background-color 0.2s ease;
        }

        .search-bar {
            transition: all 0.2s ease;
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
    </style>
</head>

<body>
    <?php include __DIR__ . '/../partials/chair/sidebar.php'; ?>

    <div class="flex-1 flex flex-col md:ml-64">
        <?php include __DIR__ . '/../partials/chair/header.php'; ?>

        <main class="flex-1 p-6">
            <div class="max-w-7xl mx-auto">
                <!-- Flash Messages -->
                <?php if (isset($_SESSION['flash'])): ?>
                    <div class="mb-6 p-4 rounded-lg <?= $_SESSION['flash']['type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                        <?= htmlspecialchars($_SESSION['flash']['message']) ?>
                    </div>
                    <?php unset($_SESSION['flash']); ?>
                <?php endif; ?>

                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">Curriculum Management</h1>
                    <button onclick="showModal('createCurriculumModal')" class="btn-primary text-white px-4 py-2 rounded-md flex items-center">
                        <i class="fas fa-plus mr-2"></i> Create Curriculum
                    </button>
                </div>

                <!-- Curriculum List -->
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Effective Year</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Units</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($curricula as $curriculum): ?>
                                <tr class="table-row">
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($curriculum['curriculum_code']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($curriculum['curriculum_name']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($curriculum['effective_year']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($curriculum['total_units']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($curriculum['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-end">
                    <span class="text-gray-600">Total Curricula: <?= count($curricula) ?></span>
                </div>

                <!-- Create Curriculum Modal -->
                <div id="createCurriculumModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center">
                    <div class="bg-white rounded-lg p-8 w-full max-w-4xl modal">
                        <h2 class="text-2xl font-semibold mb-6 text-gray-900">Create New Curriculum</h2>
                        <form method="POST" action="/chair/curriculum" class="mb-6">
                            <input type="hidden" name="create_curriculum" value="1">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Curriculum Name</label>
                                    <input type="text" name="name" required class="w-full rounded-md border-gray-300 shadow-sm search-bar focus:ring-gold focus:border-gold" placeholder="e.g., BSIT Curriculum 2025">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Curriculum Code</label>
                                    <input type="text" name="code" required class="w-full rounded-md border-gray-300 shadow-sm search-bar focus:ring-gold focus:border-gold" placeholder="e.g., BSIT-2025">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Effective Year</label>
                                    <input type="number" name="effective_year" required class="w-full rounded-md border-gray-300 shadow-sm search-bar focus:ring-gold focus:border-gold" placeholder="e.g., 2025">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Program Name</label>
                                    <input type="text" name="program_name" required class="w-full rounded-md border-gray-300 shadow-sm search-bar focus:ring-gold focus:border-gold" placeholder="e.g., Bachelor of Science in Information Technology">
                                </div>
                            </div>

                            <!-- Course Search Section -->
                            <div class="mb-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Add Courses</h3>
                                <div class="flex space-x-4 mb-4">
                                    <div class="flex-1">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Search Courses</label>
                                        <input type="text" name="search_term" class="w-full rounded-md border-gray-300 shadow-sm search-bar focus:ring-gold focus:border-gold" placeholder="Enter course code or name">
                                    </div>
                                    <div class="flex-1">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                                        <select name="search_department_id" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-gold focus:border-gold">
                                            <option value="">All Departments</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?= $dept['department_id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="flex items-end">
                                        <button type="submit" name="search_courses" class="btn-gold px-4 py-2 rounded-md flex items-center">
                                            <i class="fas fa-search mr-2"></i> Search
                                        </button>
                                    </div>
                                </div>

                                <!-- Search Results -->
                                <?php if (!empty($searchResults)): ?>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Select</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Units</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year Level</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Semester</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject Type</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                <?php foreach ($searchResults as $course): ?>
                                                    <tr class="table-row">
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <input type="checkbox" name="courses[<?= $course['course_id'] ?>][selected]" value="1">
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($course['course_code']) ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($course['course_name']) ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($course['units']) ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($course['department_name']) ?></td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <select name="courses[<?= $course['course_id'] ?>][year_level]" class="rounded-md border-gray-300">
                                                                <option value="">Select</option>
                                                                <option value="1st Year" <?= $course['year_level'] === '1st Year' ? 'selected' : '' ?>>1st Year</option>
                                                                <option value="2nd Year" <?= $course['year_level'] === '2nd Year' ? 'selected' : '' ?>>2nd Year</option>
                                                                <option value="3rd Year" <?= $course['year_level'] === '3rd Year' ? 'selected' : '' ?>>3rd Year</option>
                                                                <option value="4th Year" <?= $course['year_level'] === '4th Year' ? 'selected' : '' ?>>4th Year</option>
                                                            </select>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <select name="courses[<?= $course['course_id'] ?>][semester]" class="rounded-md border-gray-300">
                                                                <option value="">Select</option>
                                                                <option value="1st" <?= $course['semester'] === '1st' ? 'selected' : '' ?>>1st</option>
                                                                <option value="2nd" <?= $course['semester'] === '2nd' ? 'selected' : '' ?>>2nd</option>
                                                                <option value="Summer" <?= $course['semester'] === 'Summer' ? 'selected' : '' ?>>Summer</option>
                                                            </select>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <select name="courses[<?= $course['course_id'] ?>][subject_type]" class="rounded-md border-gray-300">
                                                                <option value="Major">Major</option>
                                                                <option value="Minor">Minor</option>
                                                                <option value="General Education">General Education</option>
                                                                <option value="Elective">Elective</option>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_courses'])): ?>
                                    <p class="text-gray-600">No courses found matching your criteria.</p>
                                <?php endif; ?>
                            </div>

                            <!-- Create Course Button -->
                            <div class="mb-6">
                                <button type="button" onclick="showModal('createCourseModal'); hideModal('createCurriculumModal')" class="btn-primary text-white px-4 py-2 rounded-md flex items-center">
                                    <i class="fas fa-plus mr-2"></i> Create New Course
                                </button>
                            </div>

                            <div class="flex justify-end space-x-3">
                                <button type="submit" class="btn-gold px-4 py-2 rounded-md">Create Curriculum</button>
                                <button type="button" onclick="hideModal('createCurriculumModal')" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Create Course Modal -->
                <div id="createCourseModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center">
                    <div class="bg-white rounded-lg p-8 w-full max-w-2xl modal">
                        <h2 class="text-2xl font-semibold mb-6 text-gray-900">Create New Course</h2>
                        <form method="POST" action="/chair/curriculum">
                            <input type="hidden" name="create_course" value="1">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Course Code</label>
                                    <input type="text" name="course_code" required class="w-full rounded-md border-gray-300 shadow-sm search-bar focus:ring-gold focus:border-gold" placeholder="e.g., IT101">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Course Name</label>
                                    <input type="text" name="course_name" required class="w-full rounded-md border-gray-300 shadow-sm search-bar focus:ring-gold focus:border-gold" placeholder="e.g., Introduction to Programming">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Units</label>
                                    <input type="number" name="units" required min="1" class="w-full rounded-md border-gray-300 shadow-sm search-bar focus:ring-gold focus:border-gold" placeholder="e.g., 3">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Lecture Hours</label>
                                    <input type="number" name="lecture_hours" min="0" class="w-full rounded-md border-gray-300 shadow-sm search-bar focus:ring-gold focus:border-gold" placeholder="e.g., 2">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Lab Hours</label>
                                    <input type="number" name="lab_hours" min="0" class="w-full rounded-md border-gray-300 shadow-sm search-bar focus:ring-gold focus:border-gold" placeholder="e.g., 1">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Semester</label>
                                    <select name="semester" required class="w-full rounded-md border-gray-300 shadow-sm focus:ring-gold focus:border-gold">
                                        <option value="1st">1st</option>
                                        <option value="2nd">2nd</option>
                                        <option value="Summer">Summer</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Year Level</label>
                                    <select name="year_level" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-gold focus:border-gold">
                                        <option value="">Select</option>
                                        <option value="1st Year">1st Year</option>
                                        <option value="2nd Year">2nd Year</option>
                                        <option value="3rd Year">3rd Year</option>
                                        <option value="4th Year">4th Year</option>
                                    </select>
                                </div>
                            </div>
                            <div class="flex justify-end space-x-3">
                                <button type="submit" class="btn-gold px-4 py-2 rounded-md">Create Course</button>
                                <button type="button" onclick="hideModal('createCourseModal'); showModal('createCurriculumModal')" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function showModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('hidden');
            setTimeout(() => modal.querySelector('.modal').classList.add('show'), 10);
        }

        function hideModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.querySelector('.modal').classList.remove('show');
            setTimeout(() => modal.classList.add('hidden'), 300);
        }
    </script>
</body>

</html>