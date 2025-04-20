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
$requests = $schedulingService->getPendingFacultyRequests($collegeId, $departmentFilter);
$departments = $schedulingService->getCollegeDepartments($collegeId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    try {
        if ($requestId && in_array($action, ['approve', 'reject'])) {
            $schedulingService->updateFacultyRequestStatus($requestId, $action === 'approve' ? 'approved' : 'rejected', $userId);
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Faculty request " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully"];
            header('Location: /dean/faculty-requests');
            exit;
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid request action'];
        }
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Requests | PRMSU</title>
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

                <h1 class="text-2xl font-bold text-gray-900 mb-6">Faculty Requests</h1>

                <!-- Department Filter -->
                <div class="mb-6">
                    <form method="GET" action="/dean/faculty-requests">
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

                <!-- Faculty Requests Table -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="table-container">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Academic Rank</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($requests)): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                            No pending faculty requests
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($requests as $request): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                <?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                <?= htmlspecialchars($request['username']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                <?= htmlspecialchars($request['email']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                <?= htmlspecialchars($request['department_name']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                <?= htmlspecialchars($request['academic_rank']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <form method="POST" action="/dean/faculty-requests" class="inline">
                                                    <input type="hidden" name="request_id" value="<?= $request['request_id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="text-green-600 hover:text-green-800 mr-2">
                                                        <i class="fas fa-check mr-1"></i> Approve
                                                    </button>
                                                </form>
                                                <form method="POST" action="/dean/faculty-requests" class="inline">
                                                    <input type="hidden" name="request_id" value="<?= $request['request_id'] ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="text-red-600 hover:text-red-800">
                                                        <i class="fas fa-times mr-1"></i> Reject
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