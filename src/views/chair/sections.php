<?php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../services/CurriculumService.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';

use App\config\Database;

$currentUri = $_SERVER['REQUEST_URI'];
AuthMiddleware::handle('chair');

$departmentId = $_SESSION['user']['department_id'] ?? null;
if (!$departmentId) {
    die("Department ID not found in session");
}

$curriculumService = new CurriculumService();
$yearLevelFilter = $_GET['year_level'] ?? null;
$sections = $curriculumService->getDepartmentSections($departmentId, $yearLevelFilter);
$availableCourses = [];
$sectionId = $_GET['section_id'] ?? null;

if ($sectionId) {
    $availableCourses = $curriculumService->getAvailableCoursesForSection($sectionId);
}

// Handle assign courses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_courses'])) {
    try {
        $sectionId = $_POST['section_id'] ?? throw new Exception("Section ID required");
        $courses = $_POST['courses'] ?? [];
        $curriculumService->assignCoursesToSection($sectionId, $courses);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Courses assigned successfully'];
        header('Location: /chair/sections');
        exit;
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to assign courses: ' . $e->getMessage()];
        header('Location: /chair/sections');
        exit;
    }
}

// Handle create section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_section'])) {
    try {
        $sectionData = [
            'section_name' => $_POST['section_name'] ?? throw new Exception("Section name required"),
            'year_level' => $_POST['year_level'] ?? throw new Exception("Year level required"),
            'academic_year' => $_POST['academic_year'] ?? throw new Exception("Academic year required"),
            'max_students' => $_POST['max_students'] ?? 40,
            'department_id' => $departmentId
        ];
        $curriculumService->createSection($sectionData);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Section created successfully'];
        header('Location: /chair/sections');
        exit;
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to create section: ' . $e->getMessage()];
        header('Location: /chair/sections');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sections Management | PRMSU</title>
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
                    <h1 class="text-3xl font-bold text-gray-900">Sections Management</h1>
                    <button onclick="showModal('createSectionModal')" class="btn-primary text-white px-4 py-2 rounded-md flex items-center">
                        <i class="fas fa-plus mr-2"></i> Create Section
                    </button>
                </div>

                <!-- Year Level Filter -->
                <div class="mb-6">
                    <form method="GET" action="/chair/sections">
                        <label for="year_level" class="block text-sm font-medium text-gray-700 mb-1">Filter by Year Level</label>
                        <select name="year_level" id="year_level" class="w-48 rounded-md border-gray-300 shadow-sm focus:ring-gold focus:border-gold" onchange="this.form.submit()">
                            <option value="">All Year Levels</option>
                            <option value="1st Year" <?= $yearLevelFilter === '1st Year' ? 'selected' : '' ?>>1st Year</option>
                            <option value="2nd Year" <?= $yearLevelFilter === '2nd Year' ? 'selected' : '' ?>>2nd Year</option>
                            <option value="3rd Year" <?= $yearLevelFilter === '3rd Year' ? 'selected' : '' ?>>3rd Year</option>
                            <option value="4th Year" <?= $yearLevelFilter === '4th Year' ? 'selected' : '' ?>>4th Year</option>
                        </select>
                    </form>
                </div>

                <!-- Sections List -->
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Curriculum</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year Level</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Semester</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Academic Year</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Students</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($sections as $section): ?>
                                <tr class="table-row">
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($section['section_name']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($section['curriculum_name']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($section['year_level']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($section['semester']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($section['academic_year']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($section['current_students'] . '/' . $section['max_students']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="/chair/sections?section_id=<?= $section['section_id'] ?>" onclick="showModal('assignCoursesModal')" class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-plus-circle mr-1"></i> Assign Courses
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-end">
                    <span class="text-gray-600">Total Sections: <?= count($sections) ?></span>
                </div>

                <!-- Create Section Modal -->
                <div id="createSectionModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center">
                    <div class="bg-white rounded-lg p-8 w-full max-w-2xl modal">
                        <h2 class="text-2xl font-semibold mb-6 text-gray-900">Create New Section</h2>
                        <form method="POST" action="/chair/sections">
                            <input type="hidden" name="create_section" value="1">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Section Name</label>
                                    <input type="text" name="section_name" required class="w-full rounded-md border-gray-300 shadow-sm search-bar focus:ring-gold focus:border-gold" placeholder="e.g., BSIT-1A">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Year Level</label>
                                    <select name="year_level" required class="w-full rounded-md border-gray-300 shadow-sm focus:ring-gold focus:border-gold">
                                        <option value="">Select</option>
                                        <option value="1st Year">1st Year</option>
                                        <option value="2nd Year">2nd Year</option>
                                        <option value="3rd Year">3rd Year</option>
                                        <option value="4th Year">4th Year</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label>
                                    <input type="text" name="academic_year" required pattern="\d{4}-\d{4}" class="w-full rounded-md border-gray-300 shadow-sm search-bar focus:ring-gold focus:border-gold" placeholder="e.g., 2024-2025">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Max Students</label>
                                    <input type="number" name="max_students" required min="1" value="40" class="w-full rounded-md border-gray-300 shadow-sm search-bar focus:ring-gold focus:border-gold" placeholder="e.g., 40">
                                </div>
                            </div>
                            <div class="flex justify-end space-x-3">
                                <button type="submit" class="btn-gold px-4 py-2 rounded-md">Create Section</button>
                                <button type="button" onclick="hideModal('createSectionModal')" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Assign Courses Modal -->
                <?php if ($sectionId && !empty($availableCourses)): ?>
                    <div id="assignCoursesModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center">
                        <div class="bg-white rounded-lg p-8 w-full max-w-4xl modal show">
                            <h2 class="text-2xl font-semibold mb-6 text-gray-900">Assign Courses to <?= htmlspecialchars($sections[array_search($sectionId, array_column($sections, 'section_id'))]['section_name']) ?></h2>
                            <form method="POST" action="/chair/sections">
                                <input type="hidden" name="assign_courses" value="1">
                                <input type="hidden" name="section_id" value="<?= $sectionId ?>">
                                <div class="overflow-x-auto mb-6">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Select</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course Code</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course Name</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Units</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <?php foreach ($availableCourses as $course): ?>
                                                <tr class="table-row">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <input type="checkbox" name="courses[<?= $course['course_id'] ?>][selected]" value="1">
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($course['course_code']) ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($course['course_name']) ?></td>
                                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($course['units']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="flex justify-end space-x-3">
                                    <button type="submit" class="btn-gold px-4 py-2 rounded-md">Assign Courses</button>
                                    <button type="button" onclick="hideModal('assignCoursesModal')" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
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