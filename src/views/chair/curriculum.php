<?php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../services/SchedulingService.php';
require_once __DIR__ . '/../../middleware/AuthMiddleware.php';

AuthMiddleware::handle('chair');
$db = (new Database())->connect();
$schedulingService = new SchedulingService();

try {
    $departmentId = $_SESSION['user']['department_id'] ?? throw new Exception("Department ID not set");
    $curricula = $schedulingService->getDepartmentCurricula($departmentId);
    $courses = $schedulingService->getDepartmentCourses($departmentId);
} catch (Exception $e) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
    header('Location: /chair/dashboard');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Curriculum Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
    </style>
    <script>
        function addCourseRow() {
            const container = document.getElementById('course-rows');
            const row = document.createElement('div');
            row.className = 'course-row grid grid-cols-5 gap-2 mb-2';
            row.innerHTML = `
                <input type="text" name="courses[][code]" placeholder="Code" required class="rounded-md border-gray-300 shadow-sm">
                <input type="text" name="courses[][name]" placeholder="Name" required class="rounded-md border-gray-300 shadow-sm">
                <select name="courses[][year_level]" required class="rounded-md border-gray-300 shadow-sm">
                    <option value="1st Year">1st Year</option>
                    <option value="2nd Year">2nd Year</option>
                    <option value="3rd Year">3rd Year</option>
                    <option value="4th Year">4th Year</option>
                </select>
                <select name="courses[][semester]" required class="rounded-md border-gray-300 shadow-sm">
                    <option value="1st">1st</option>
                    <option value="2nd">2nd</option>
                    <option value="Summer">Summer</option>
                </select>
                <select name="courses[][subject_type]" required class="rounded-md border-gray-300 shadow-sm">
                    <option value="General Education">General Education</option>
                    <option value="Major">Major</option>
                    <option value="Elective">Elective</option>
                </select>
                <input type="number" name="courses[][units]" placeholder="Units" min="1" value="3" class="rounded-md border-gray-300 shadow-sm">
                <input type="number" name="courses[][lecture_hours]" placeholder="Lec Hours" min="0" value="3" class="rounded-md border-gray-300 shadow-sm">
                <input type="number" name="courses[][lab_hours]" placeholder="Lab Hours" min="0" value="0" class="rounded-md border-gray-300 shadow-sm">
                <button type="button" onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800"><i class="fas fa-trash"></i></button>
            `;
            container.appendChild(row);
        }

        function toggleInputMethod(method) {
            document.getElementById('file-upload').classList.add('hidden');
            document.getElementById('manual-entry').classList.add('hidden');
            document.getElementById(method).classList.remove('hidden');
        }
    </script>
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uploaded By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($curricula as $curriculum): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($curriculum['curriculum_name']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($curriculum['curriculum_code']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($curriculum['effective_year']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($curriculum['status']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($curriculum['file_path']): ?>
                                            <a href="/Uploads/curricula/<?= htmlspecialchars($curriculum['file_path']) ?>"
                                                class="text-blue-600 hover:text-blue-800" download>
                                                <?= htmlspecialchars($curriculum['file_name']) ?>
                                            </a>
                                        <?php else: ?>
                                            No file
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($curriculum['username']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <button onclick="document.getElementById('uploadFileModal<?= $curriculum['curriculum_id'] ?>').classList.remove('hidden')"
                                            class="text-green-600 hover:text-green-800"><i class="fas fa-upload"></i> Upload</button>
                                    </td>
                                </tr>
                                <!-- Upload File Modal -->
                                <div id="uploadFileModal<?= $curriculum['curriculum_id'] ?>" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center">
                                    <div class="bg-white rounded-lg p-6 w-full max-w-md">
                                        <h2 class="text-xl font-semibold mb-4">Upload Curriculum File</h2>
                                        <form method="POST" action="/chair/curriculum/upload" enctype="multipart/form-data">
                                            <input type="hidden" name="curriculum_id" value="<?= $curriculum['curriculum_id'] ?>">
                                            <div class="mb-4">
                                                <label class="block text-sm font-medium text-gray-700">File (DOC, PDF, Excel) *</label>
                                                <input type="file" name="curriculum_file" accept=".doc,.docx,.pdf,.xlsx" required
                                                    class="w-full rounded-md border-gray-300 shadow-sm">
                                            </div>
                                            <div class="flex justify-end space-x-3">
                                                <button type="button"
                                                    onclick="document.getElementById('uploadFileModal<?= $curriculum['curriculum_id'] ?>').classList.add('hidden')"
                                                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Cancel</button>
                                                <button type="submit" name="upload_file"
                                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">Upload</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Create Curriculum Modal -->
                <div id="createCurriculumModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center">
                    <div class="bg-white rounded-lg p-6 w-full max-w-2xl">
                        <h2 class="text-xl font-semibold mb-4">Create New Curriculum</h2>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Input Method</label>
                                <select onchange="toggleInputMethod(this.value)" class="w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="file-upload">Upload File</option>
                                    <option value="manual-entry">Manual Entry</option>
                                </select>
                            </div>

                            <!-- File Upload -->
                            <div id="file-upload" class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Upload File (DOC, PDF, Excel) *</label>
                                <input type="file" name="curriculum_file" accept=".doc,.docx,.pdf,.xlsx"
                                    class="w-full rounded-md border-gray-300 shadow-sm">
                            </div>

                            <!-- Manual Entry -->
                            <div id="manual-entry" class="hidden">
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700">Name *</label>
                                    <input type="text" name="name" placeholder="e.g., BS Information Technology Series 2022" required
                                        class="w-full rounded-md border-gray-300 shadow-sm">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700">Code *</label>
                                    <input type="text" name="code" placeholder="e.g., BSIT-2022" required
                                        class="w-full rounded-md border-gray-300 shadow-sm">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700">Effective Year *</label>
                                    <input type="number" name="effective_year" placeholder="2022" min="1900" max="2099" required
                                        class="w-full rounded-md border-gray-300 shadow-sm">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700">Total Units *</label>
                                    <input type="number" name="total_units" placeholder="120" required
                                        class="w-full rounded-md border-gray-300 shadow-sm">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700">Program Name *</label>
                                    <input type="text" name="program_name" placeholder="e.g., BS Information Technology" required
                                        class="w-full rounded-md border-gray-300 shadow-sm">
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700">Courses</label>
                                    <div id="course-rows" class="mb-2"></div>
                                    <button type="button" onclick="addCourseRow()"
                                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md flex items-center">
                                        <i class="fas fa-plus mr-2"></i> Add Course
                                    </button>
                                </div>
                            </div>

                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="document.getElementById('createCurriculumModal').classList.add('hidden')"
                                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Cancel</button>
                                <button type="submit" name="create_curriculum"
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">Create</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>