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

// Get department classrooms
$classrooms = $schedulingService->getAvailableClassrooms($departmentId);

// Search functionality
$searchResults = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_classrooms'])) {
    $building = $_POST['building'] ?? '';
    $minCapacity = (int)($_POST['min_capacity'] ?? 0);
    $roomType = $_POST['room_type'] ?? '';
    $availability = $_POST['availability'] ?? 'available';

    $query = "SELECT c.*, d.department_name, cl.college_name 
              FROM classrooms c
              JOIN departments d ON c.department_id = d.department_id
              JOIN colleges cl ON d.college_id = cl.college_id
              WHERE (c.shared = 1 OR c.department_id = :department_id)
              AND c.availability = :availability
              AND c.capacity >= :min_capacity";

    $params = [
        ':department_id' => $departmentId,
        ':availability' => $availability,
        ':min_capacity' => $minCapacity
    ];

    if (!empty($building)) {
        $query .= " AND c.building LIKE :building";
        $params[':building'] = "%$building%";
    }

    if (!empty($roomType)) {
        $query .= " AND c.room_type = :room_type";
        $params[':room_type'] = $roomType;
    }

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Define $stats for sidebar
$pendingApprovalsData = $schedulingService->getPendingApprovals($departmentId);
$stats = [
    'pendingApprovals' => is_array($pendingApprovalsData) ? count($pendingApprovalsData) : (int)($pendingApprovalsData ?? 0)
];
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

        .status-available {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-unavailable {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .status-maintenance {
            background-color: #fef3c7;
            color: #92400e;
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
                    <button onclick="document.getElementById('searchModal').classList.remove('hidden')"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md flex items-center">
                        <i class="fas fa-search mr-2"></i> Search Classrooms
                    </button>
                </div>

                <!-- Department Classrooms -->
                <div class="mb-8">
                    <h2 class="text-lg font-semibold mb-4">My Department Classrooms</h2>
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Building</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capacity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
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
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?= ucfirst(str_replace('_', ' ', $room['room_type'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap"><?= $room['shared'] ? 'Yes' : 'No' ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 rounded-full text-xs font-medium 
                                                <?php
                                                switch ($room['availability']) {
                                                    case 'available':
                                                        echo 'status-available';
                                                        break;
                                                    case 'unavailable':
                                                        echo 'status-unavailable';
                                                        break;
                                                    case 'under_maintenance':
                                                        echo 'status-maintenance';
                                                        break;
                                                }
                                                ?>">
                                                <?= ucfirst(str_replace('_', ' ', $room['availability'])) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <a href="/chair/edit_classroom?id=<?= $room['room_id'] ?>"
                                                class="text-blue-600 hover:text-blue-800"><i class="fas fa-edit"></i> Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Search Results -->
                <?php if (!empty($searchResults)): ?>
                    <div class="mt-8">
                        <h2 class="text-lg font-semibold mb-4">Available Classrooms from Other Colleges</h2>
                        <div class="bg-white shadow rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Building</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capacity</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">College</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($searchResults as $room): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($room['room_name']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($room['building']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($room['capacity']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?= ucfirst(str_replace('_', ' ', $room['room_type'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($room['department_name']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($room['college_name']) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 rounded-full text-xs font-medium status-available">
                                                    Available
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Search Modal -->
                <div id="searchModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center">
                    <div class="bg-white rounded-lg p-6 w-full max-w-md">
                        <h2 class="text-xl font-semibold mb-4">Search Available Classrooms</h2>
                        <form method="POST">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Building</label>
                                <input type="text" name="building" class="w-full rounded-md border-gray-300 shadow-sm" placeholder="Enter building name">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Minimum Capacity</label>
                                <input type="number" name="min_capacity" min="1" class="w-full rounded-md border-gray-300 shadow-sm" value="20">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Room Type</label>
                                <select name="room_type" class="w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Any Type</option>
                                    <option value="classroom">Classroom</option>
                                    <option value="laboratory">Laboratory</option>
                                    <option value="auditorium">Auditorium</option>
                                    <option value="seminar_room">Seminar Room</option>
                                </select>
                            </div>
                            <input type="hidden" name="availability" value="available">
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="document.getElementById('searchModal').classList.add('hidden')"
                                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Cancel</button>
                                <button type="submit" name="search_classrooms" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                                    Search
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