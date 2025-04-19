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

// Handle POST for adding new faculty
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_faculty'])) {
    $employeeId = $_POST['employee_id'] ?? '';
    $firstName = $_POST['first_name'] ?? '';
    $middleName = $_POST['middle_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $suffix = $_POST['suffix'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $academicRank = $_POST['academic_rank'] ?? '';
    $employmentType = $_POST['employment_type'] ?? '';
    $classification = $_POST['classification'] ?? null;
    $maxHours = $_POST['max_hours'] ?? 18.00;
    $departmentId = $_POST['department_id'] ?? $departmentId;

    try {
        $query = "INSERT INTO faculty (employee_id, first_name, middle_name, last_name, suffix, email, phone, 
                                      academic_rank, employment_type, classification, max_hours, department_id, created_at, updated_at)
                  VALUES (:employee_id, :first_name, :middle_name, :last_name, :suffix, :email, :phone, 
                          :academic_rank, :employment_type, :classification, :max_hours, :department_id, NOW(), NOW())";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':first_name' => $firstName,
            ':middle_name' => $middleName,
            ':last_name' => $lastName,
            ':suffix' => $suffix,
            ':email' => $email,
            ':phone' => $phone,
            ':academic_rank' => $academicRank,
            ':employment_type' => $employmentType,
            ':classification' => $classification,
            ':max_hours' => $maxHours,
            ':department_id' => $departmentId
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Faculty added successfully'];
        header('Location: /chair/faculty');
        exit;
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to add faculty: ' . $e->getMessage()];
    }
}

// Handle POST for searching faculty
$searchResults = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_faculty'])) {
    $searchTerm = $_POST['search_term'] ?? '';
    $searchDepartmentId = $_POST['search_department_id'] ?? null;

    $query = "SELECT f.faculty_id, f.employee_id, f.first_name, f.last_name, f.email, f.academic_rank, 
                     f.employment_type, d.department_name
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

        .modal {
            transition: opacity 0.3s ease, transform 0.3s ease;
            transform: scale(0.95);
        }

        .modal.show {
            transform: scale(1);
            opacity: 1;
        }

        .table-row:hover {
            background-color: #f1f5f9;
            transition: background-color 0.2s ease;
        }

        .search-bar {
            transition: all 0.2s ease;
        }

        .search-bar:focus {
            border-color: var(--prmsu-blue);
            box-shadow: 0 0 0 3px rgba(0, 86, 179, 0.1);
        }

        .btn-primary {
            background-color: var(--prmsu-blue);
            transition: background-color 0.2s ease;
        }

        .btn-primary:hover {
            background-color: #003366;
        }
    </style>
</head>

<body class="bg-gray-100">
    <?php include __DIR__ . '/../partials/chair/sidebar.php'; ?>

    <div class="flex-1 flex flex-col md:ml-64">
        <?php include __DIR__ . '/../partials/chair/header.php'; ?>

        <main class="flex-1 p-6">
            <div class="max-w-7xl mx-auto">
                <!-- Flash Messages -->
                <?php if (isset($_SESSION['flash'])): ?>
                    <div class="mb-4 p-4 rounded-md <?= $_SESSION['flash']['type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                        <?= htmlspecialchars($_SESSION['flash']['message']) ?>
                    </div>
                    <?php unset($_SESSION['flash']); ?>
                <?php endif; ?>

                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">Faculty Management</h1>
                    <div class="space-x-3">
                        <button onclick="showModal('searchFacultyModal')" class="btn-primary text-white px-4 py-2 rounded-md flex items-center">
                            <i class="fas fa-search mr-2"></i> Search Faculty
                        </button>
                        <button onclick="showModal('addFacultyModal')" class="btn-primary text-white px-4 py-2 rounded-md flex items-center">
                            <i class="fas fa-plus mr-2"></i> Add Faculty
                        </button>
                    </div>
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
                    <div class="bg-white rounded-lg p-8 w-full max-w-2xl modal">
                        <h2 class="text-2xl font-semibold mb-6 text-gray-900">Search Faculty</h2>
                        <form method="POST" class="mb-6">
                            <div class="flex space-x-4">
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700">Search by Name or Employee ID</label>
                                    <input type="text" name="search_term" class="w-full rounded-md border-gray-300 shadow-sm search-bar focus:ring-blue-500 focus:border-blue-500" placeholder="Enter name or employee ID">
                                </div>
                                <div class="flex-1">
                                    <label class="block text-sm font-medium text-gray-700">Department</label>
                                    <select name="search_department_id" class="w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">All Departments</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?= $dept['department_id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="flex justify-end mt-4">
                                <button type="submit" name="search_faculty" class="btn-primary text-white px-4 py-2 rounded-md">Search</button>
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
                                                            <button type="submit" name="add_to_department" class="text-blue-600 hover:text-blue-800"><i class="fas fa-plus"></i> Add to Department</button>
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

                        <div class="flex justify-end mt-6">
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