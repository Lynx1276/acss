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

// Get faculty members and departments
$faculty = $schedulingService->getFacultyMembers($departmentId);
$departments = $schedulingService->getAllDepartments();
$pendingApprovalsData = $schedulingService->getPendingApprovals($departmentId);
$stats = [
    'pendingApprovals' => is_array($pendingApprovalsData) ? count($pendingApprovalsData) : (int)($pendingApprovalsData ?? 0)
];

// Handle POST for searching faculty
$searchResults = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_faculty'])) {
    $searchTerm = $_POST['search_term'] ?? '';
    $searchDepartmentId = $_POST['search_department_id'] ?? null;

    $query = "SELECT f.faculty_id, f.employee_id, f.first_name, f.last_name, f.email, f.academic_rank, 
                     f.employment_type, f.department_id, d.department_name
              FROM faculty f
              JOIN departments d ON f.department_id = d.department_id
              WHERE (f.first_name LIKE :search_term OR f.last_name LIKE :search_term OR f.employee_id LIKE :search_term)";
    if ($searchDepartmentId) {
        $query .= " AND f.department_id = :department_id";
    }
    $query .= " ORDER BY f.last_name, f.first_name";

    $stmt = $db->prepare($query);
    $params = [':search_term' => "%$searchTerm%"];
    if ($searchDepartmentId) {
        $params[':department_id'] = $searchDepartmentId;
    }
    $stmt->execute($params);
    $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle POST for adding faculty to department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_department'])) {
    $facultyId = $_POST['faculty_id'] ?? null;
    $newDepartmentId = $_POST['department_id'] ?? null;

    try {
        if ($facultyId && $newDepartmentId) {
            $query = "UPDATE faculty SET department_id = :department_id, updated_at = NOW() 
                      WHERE faculty_id = :faculty_id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':department_id' => $newDepartmentId,
                ':faculty_id' => $facultyId
            ]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Faculty added to department successfully'];
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Missing required fields for adding faculty to department'];
        }
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to add faculty to department: ' . $e->getMessage()];
    }
    header('Location: /chair/faculty');
    exit;
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
            transition: transform 0.3s ease;
            background: linear-gradient(180deg, var(--prmsu-gray-dark) 0%, rgb(79, 78, 78) 100%);
        }

        .nav-item {
            transition: background-color 0.2s ease;
            border-left: 3px solid transparent;
        }

        .nav-item:hover {
            background-color: rgba(244, 147, 12, 0.15);
        }

        .nav-item.active {
            background-color: rgba(212, 175, 55, 0.2);
            border-left: 3px solid var(--prmsu-gold);
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
                    <h1 class="text-3xl font-bold text-gray-900">Faculty Management</h1>
                    <button onclick="showModal('searchFacultyModal')" class="btn-primary text-white px-4 py-2 rounded-md flex items-center">
                        <i class="fas fa-search mr-2"></i> Search Faculty
                    </button>
                </div>

                <!-- Faculty List -->
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Academic Rank</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employment Type</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($faculty as $member): ?>
                                <tr class="table-row">
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($member['employee_id']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?= htmlspecialchars($member['first_name'] . ' ' . ($member['middle_name'] ? $member['middle_name'][0] . '. ' : '') . $member['last_name'] . ($member['suffix'] ? ', ' . $member['suffix'] : '')) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($member['email']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($member['academic_rank']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($member['employment_type']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-end">
                    <span class="text-gray-600">Total Faculty: <?= count($faculty) ?></span>
                </div>

                <!-- Search Faculty Modal -->
                <div id="searchFacultyModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center">
                    <div class="bg-white rounded-lg p-8 w-full max-w-3xl modal">
                        <h2 class="text-2xl font-semibold mb-6 text-gray-900">Search Faculty</h2>
                        <form method="POST" class="mb-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Search by Name or Employee ID</label>
                                    <input type="text" name="search_term" class="w-full rounded-md border-gray-300 shadow-sm search-bar focus:ring-gold focus:border-gold" placeholder="Enter name or employee ID">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                                    <select name="search_department_id" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-gold focus:border-gold">
                                        <option value="">All Departments</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?= $dept['department_id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="flex justify-end mt-4">
                                <button type="submit" name="search_faculty" class="btn-gold px-4 py-2 rounded-md flex items-center">
                                    <i class="fas fa-search mr-2"></i> Search
                                </button>
                            </div>
                        </form>

                        <!-- Search Results -->
                        <?php if (!empty($searchResults)): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee ID</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach ($searchResults as $result): ?>
                                            <tr class="table-row">
                                                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($result['employee_id']) ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($result['first_name'] . ' ' . $result['last_name']) ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($result['department_name']) ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php if ($result['department_id'] != $departmentId): ?>
                                                        <form method="POST" class="inline">
                                                            <input type="hidden" name="faculty_id" value="<?= $result['faculty_id'] ?>">
                                                            <input type="hidden" name="department_id" value="<?= $departmentId ?>">
                                                            <button type="submit" name="add_to_department" class="text-blue-600 hover:text-blue-800 flex items-center">
                                                                <i class="fas fa-plus mr-1"></i> Add to Department
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-gray-500">Already in Department</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_faculty'])): ?>
                            <p class="text-gray-600">No faculty found matching your criteria.</p>
                        <?php endif; ?>

                        <div class="flex justify-end mt-6 space-x-3">
                            <button type="button" onclick="hideModal('searchFacultyModal')" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Close</button>
                        </div>
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