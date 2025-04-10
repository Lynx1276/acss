<?php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../services/SchedulingService.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';

$currentUri = $_SERVER['REQUEST_URI'];
AuthMiddleware::handle('chair');

$departmentId = $_SESSION['user']['department_id'] ?? null;
if (!$departmentId) {
    die("Department ID not found in session");
}

$schedulingService = new SchedulingService();
$db = (new Database())->connect();

// Get curricula
$curricula = $schedulingService->getDepartmentCurricula($departmentId);

// Define $stats for sidebar
$pendingApprovalsData = $schedulingService->getPendingApprovals($departmentId);
$stats = [
    'pendingApprovals' => is_array($pendingApprovalsData) ? count($pendingApprovalsData) : (int)($pendingApprovalsData ?? 0)
];

// Handle POST for creating curriculum
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_curriculum'])) {
    $data = [
        'name' => $_POST['name'] ?? '',
        'code' => $_POST['code'] ?? '',
        'description' => $_POST['description'] ?? '',
        'courses' => $_POST['courses'] ?? []
    ];
    $schedulingService->createCurriculum($departmentId, $data);
    header('Location: /chair/curriculum');
    exit;
}

// Get courses for selection
$courses = $schedulingService->getDepartmentCourses($departmentId);
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
        /* Reuse styles */
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
    </style>
</head>

<body class="bg-gray-50">
    <?php include __DIR__ . '/../partials/chair/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden md:ml-64">
        <?php include __DIR__ . '/../partials/chair/header.php'; ?>

        <main class="flex-1 overflow-y-auto p-6">
            <div class="max-w-7xl mx-auto">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Curriculum Management</h1>
                    <button onclick="document.getElementById('createCurriculumModal').classList.remove('hidden')"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md flex items-center">
                        <i class="fas fa-plus mr-2"></i> Create Curriculum
                    </button>
                </div>

                <!-- Curriculum List -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Courses</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($curricula as $curriculum): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($curriculum['curriculum_name']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($curriculum['curriculum_code']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= $curriculum['course_count'] ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($curriculum['status']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="/chair/curriculum/versions?id=<?= $curriculum['curriculum_id'] ?>"
                                            class="text-blue-600 hover:text-blue-800 mr-2"><i class="fas fa-eye"></i> Versions</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Create Curriculum Modal -->
                <div id="createCurriculumModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center">
                    <div class="bg-white rounded-lg p-6 w-full max-w-md">
                        <h2 class="text-xl font-semibold mb-4">Create New Curriculum</h2>
                        <form method="POST">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Name</label>
                                <input type="text" name="name" required class="w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Code</label>
                                <input type="text" name="code" required class="w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Description</label>
                                <textarea name="description" class="w-full rounded-md border-gray-300 shadow-sm"></textarea>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Courses</label>
                                <select name="courses[]" multiple class="w-full rounded-md border-gray-300 shadow-sm h-32">
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?= $course['course_id'] ?>">
                                            <?= htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="document.getElementById('createCurriculumModal').classList.add('hidden')"
                                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Cancel</button>
                                <button type="submit" name="create_curriculum" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                                    Create Curriculum
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