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
$stats = ['pendingApprovals' => count($schedulingService->getPendingApprovals($departmentId))];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Faculty | PRMSU</title>
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
                <h1 class="text-2xl font-bold text-gray-900 mb-6">Edit Faculty: <?= htmlspecialchars($facultyMember['first_name'] . ' ' . $facultyMember['last_name']) ?></h1>

                <div class="bg-white shadow rounded-lg overflow-hidden p-6">
                    <form method="POST">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">First Name</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($facultyMember['first_name']) ?>"
                                required class="w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Last Name</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($facultyMember['last_name']) ?>"
                                required class="w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Position</label>
                            <input type="text" name="position" value="<?= htmlspecialchars($facultyMember['position']) ?>"
                                required class="w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Specializations (comma-separated)</label>
                            <input type="text" name="specializations" value="<?= htmlspecialchars($facultyMember['specializations']) ?>"
                                class="w-full rounded-md border-gray-300 shadow-sm" placeholder="e.g., Math, Physics">
                        </div>
                        <div class="flex justify-end space-x-3">
                            <a href="/chair/faculty_edit" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Cancel</a>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>

</html>