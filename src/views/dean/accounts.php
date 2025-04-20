<?php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../services/SchedulingService.php';

use App\config\Database;

$currentUri = $_SERVER['REQUEST_URI'];
$collegeId = $_SESSION['user']['college_id'] ?? null;
$userId = $_SESSION['user']['user_id'] ?? null;
if (!$collegeId || !$userId) {
    die("Session not set");
}

$schedulingService = new SchedulingService();
$departmentFilter = $_GET['department_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_account'])) {
    $targetUserId = (int)($_POST['user_id'] ?? 0);
    try {
        $schedulingService->deactivateAccount($targetUserId, $userId);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Account deactivated successfully'];
        header('Location: /dean/accounts');
        exit;
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
    }
}

$query = "SELECT u.user_id, u.first_name, u.last_name, u.username, r.role_name, 
                 d.department_name
          FROM users u
          JOIN roles r ON u.role_id = r.role_id
          LEFT JOIN departments d ON u.department_id = d.department_id
          WHERE u.college_id = :college_id AND u.is_active = 1 
                AND u.role_id IN (5, 6)";
$params = [':college_id' => $collegeId];
if ($departmentFilter) {
    $query .= " AND u.department_id = :department_id";
    $params[':department_id'] = $departmentFilter;
}
$stmt = (new Database())->connect()->prepare($query);
$stmt->execute($params);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$departments = $schedulingService->getCollegeDepartments($collegeId);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts | PRMSU</title>
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
            transition: all 0.3s ease;
            background: linear-gradient(180deg, var(--prmsu-gray-dark) 0%, rgb(79, 78, 78) 100%);
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

        .search-bar:focus {
            border-color: var(--prmsu-gold);
            box-shadow: 0 0 0 3px rgba(239, 187, 15, 0.2);
        }

        .btn-danger {
            background-color: #dc2626;
            color: var(--prmsu-white);
            transition: background-color 0.2s ease;
        }

        .btn-danger:hover {
            background-color: #b91c1c;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php include __DIR__ . '/../partials/dean/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden md:ml-64">
        <?php include __DIR__ . '/../partials/dean/header.php'; ?>

        <main class="flex-1 overflow-y-auto p-6">
            <div class="max-w-7xl mx-auto">
                <!-- Flash Messages -->
                <?php if (isset($_SESSION['flash'])): ?>
                    <div class="mb-6 p-4 rounded-lg <?= $_SESSION['flash']['type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                        <?= htmlspecialchars($_SESSION['flash']['message']) ?>
                    </div>
                    <?php unset($_SESSION['flash']); ?>
                <?php endif; ?>

                <h1 class="text-2xl font-bold text-gray-900 mb-6">Manage Accounts</h1>

                <!-- Department Filter -->
                <div class="mb-6">
                    <form method="GET" action="/dean/accounts">
                        <label for="department_id" class="block text-sm font-medium text-gray-700 mb-1">Filter by Department</label>
                        <select name="department_id" id="department_id" class="w-48 rounded-md border-gray-300 shadow-sm search-bar focus:ring-gold focus:border-gold" onchange="this.form.submit()">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['department_id'] ?>" <?= $departmentFilter == $dept['department_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <!-- Accounts Table -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="table-container">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($accounts)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                            No active accounts
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($accounts as $account): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                <?= htmlspecialchars($account['first_name'] . ' ' . $account['last_name']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                <?= htmlspecialchars($account['username']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                <?= htmlspecialchars($account['role_name']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                <?= htmlspecialchars($account['department_name'] ?? 'N/A') ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <form method="POST" action="/dean/accounts" class="inline">
                                                    <input type="hidden" name="user_id" value="<?= $account['user_id'] ?>">
                                                    <input type="hidden" name="deactivate_account" value="1">
                                                    <button type="submit" class="btn-danger px-3 py-1 rounded-md text-sm">
                                                        <i class="fas fa-ban mr-1"></i> Deactivate
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>