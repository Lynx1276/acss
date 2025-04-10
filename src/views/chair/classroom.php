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

// Get classrooms
$classrooms = $schedulingService->getAvailableClassrooms($departmentId);

// Define $stats for sidebar
$pendingApprovalsData = $schedulingService->getPendingApprovals($departmentId);
$stats = [
    'pendingApprovals' => is_array($pendingApprovalsData) ? count($pendingApprovalsData) : (int)($pendingApprovalsData ?? 0)
];

// Handle POST for adding a classroom
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_classroom'])) {
    $roomName = $_POST['room_name'] ?? '';
    $building = $_POST['building'] ?? '';
    $capacity = (int)($_POST['capacity'] ?? 0);
    $isLab = isset($_POST['is_lab']) ? 1 : 0;
    $hasProjector = isset($_POST['has_projector']) ? 1 : 0;
    $hasSmartboard = isset($_POST['has_smartboard']) ? 1 : 0;
    $hasComputers = isset($_POST['has_computers']) ? 1 : 0;
    $shared = isset($_POST['shared']) ? 1 : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $query = "INSERT INTO classrooms (room_name, building, capacity, is_lab, has_projector, has_smartboard, has_computers, shared, is_active, department_id) 
              VALUES (:room_name, :building, :capacity, :is_lab, :has_projector, :has_smartboard, :has_computers, :shared, :is_active, :department_id)";
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':room_name' => $roomName,
        ':building' => $building,
        ':capacity' => $capacity,
        ':is_lab' => $isLab,
        ':has_projector' => $hasProjector,
        ':has_smartboard' => $hasSmartboard,
        ':has_computers' => $hasComputers,
        ':shared' => $shared,
        ':is_active' => $isActive,
        ':department_id' => $departmentId
    ]);

    header('Location: /chair/classroom');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classroom Management | PRMSU</title>
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
                    <h1 class="text-2xl font-bold text-gray-900">Classroom Management</h1>
                    <button onclick="document.getElementById('addClassroomModal').classList.remove('hidden')"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md flex items-center">
                        <i class="fas fa-plus mr-2"></i> Add Classroom
                    </button>
                </div>

                <!-- Classrooms List -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Building</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capacity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Features</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shared</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($classrooms as $room): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($room['room_name']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($room['building']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($room['capacity']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= $room['is_lab'] ? 'Lab' : 'Lecture' ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?= $room['has_projector'] ? '<i class="fas fa-video mr-1"></i>' : '' ?>
                                        <?= $room['has_smartboard'] ? '<i class="fas fa-chalkboard mr-1"></i>' : '' ?>
                                        <?= $room['has_computers'] ? '<i class="fas fa-desktop mr-1"></i>' : '' ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= $room['shared'] ? 'Yes' : 'No' ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= $room['is_active'] ? 'Active' : 'Inactive' ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="/chair/edit_classroom?id=<?= $room['room_id'] ?>"
                                            class="text-blue-600 hover:text-blue-800"><i class="fas fa-edit"></i> Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Add Classroom Modal -->
                <div id="addClassroomModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center">
                    <div class="bg-white rounded-lg p-6 w-full max-w-md">
                        <h2 class="text-xl font-semibold mb-4">Add New Classroom</h2>
                        <form method="POST">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Room Name</label>
                                <input type="text" name="room_name" required class="w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Building</label>
                                <input type="text" name="building" required class="w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Capacity</label>
                                <input type="number" name="capacity" min="1" max="65535" required class="w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div class="mb-4 grid grid-cols-2 gap-4">
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="is_lab" class="rounded border-gray-300 text-blue-600 shadow-sm">
                                    <span class="ml-2 text-sm font-medium text-gray-700">Is Laboratory?</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="has_projector" class="rounded border-gray-300 text-blue-600 shadow-sm">
                                    <span class="ml-2 text-sm font-medium text-gray-700">Has Projector?</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="has_smartboard" class="rounded border-gray-300 text-blue-600 shadow-sm">
                                    <span class="ml-2 text-sm font-medium text-gray-700">Has Smartboard?</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="has_computers" class="rounded border-gray-300 text-blue-600 shadow-sm">
                                    <span class="ml-2 text-sm font-medium text-gray-700">Has Computers?</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="shared" class="rounded border-gray-300 text-blue-600 shadow-sm">
                                    <span class="ml-2 text-sm font-medium text-gray-700">Shared?</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="is_active" checked class="rounded border-gray-300 text-blue-600 shadow-sm">
                                    <span class="ml-2 text-sm font-medium text-gray-700">Active?</span>
                                </label>
                            </div>
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="document.getElementById('addClassroomModal').classList.add('hidden')"
                                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Cancel</button>
                                <button type="submit" name="add_classroom" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                                    Add Classroom
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