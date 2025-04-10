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

// Get classroom details
$roomId = $_GET['id'] ?? null;
if (!$roomId) {
    header('Location: /chair/classroom');
    exit;
}

$query = "SELECT * FROM classrooms WHERE room_id = :room_id AND department_id = :department_id";
$stmt = $db->prepare($query);
$stmt->execute([':room_id' => $roomId, ':department_id' => $departmentId]);
$classroom = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$classroom) {
    header('Location: /chair/classroom');
    exit;
}

// Define $stats for sidebar
$stats = ['pendingApprovals' => count($schedulingService->getPendingApprovals($departmentId))];

// Handle POST for editing classroom
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roomName = $_POST['room_name'] ?? $classroom['room_name'];
    $building = $_POST['building'] ?? $classroom['building'];
    $capacity = (int)($_POST['capacity'] ?? $classroom['capacity']);
    $isLab = isset($_POST['is_lab']) ? 1 : 0;
    $hasProjector = isset($_POST['has_projector']) ? 1 : 0;
    $hasSmartboard = isset($_POST['has_smartboard']) ? 1 : 0;
    $hasComputers = isset($_POST['has_computers']) ? 1 : 0;
    $shared = isset($_POST['shared']) ? 1 : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $updateQuery = "UPDATE classrooms SET 
                    room_name = :room_name, 
                    building = :building, 
                    capacity = :capacity, 
                    is_lab = :is_lab, 
                    has_projector = :has_projector, 
                    has_smartboard = :has_smartboard, 
                    has_computers = :has_computers, 
                    shared = :shared, 
                    is_active = :is_active 
                    WHERE room_id = :room_id AND department_id = :department_id";
    $stmt = $db->prepare($updateQuery);
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
        ':room_id' => $roomId,
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
    <title>Edit Classroom | PRMSU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --prmsu-blue: #0056b3;
            --prmsu-gold: #FFD700;
            --prmsu-light: #f8f9fa;
            --prmsu-dark: #343a40;
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php include __DIR__ . '/../partials/chair/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden md:ml-64">
        <?php include __DIR__ . '/../partials/chair/header.php'; ?>

        <main class="flex-1 overflow-y-auto p-6">
            <div class="max-w-7xl mx-auto">
                <h1 class="text-2xl font-bold text-gray-900 mb-6">Edit Classroom: <?= htmlspecialchars($classroom['room_name']) ?></h1>

                <div class="bg-white shadow rounded-lg overflow-hidden p-6">
                    <form method="POST">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Room Name</label>
                            <input type="text" name="room_name" value="<?= htmlspecialchars($classroom['room_name']) ?>"
                                required class="w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Building</label>
                            <input type="text" name="building" value="<?= htmlspecialchars($classroom['building']) ?>"
                                required class="w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Capacity</label>
                            <input type="number" name="capacity" min="1" max="65535" value="<?= htmlspecialchars($classroom['capacity']) ?>"
                                required class="w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div class="mb-4 grid grid-cols-2 gap-4">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="is_lab" <?= $classroom['is_lab'] ? 'checked' : '' ?>
                                    class="rounded border-gray-300 text-blue-600 shadow-sm">
                                <span class="ml-2 text-sm font-medium text-gray-700">Is Laboratory?</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="has_projector" <?= $classroom['has_projector'] ? 'checked' : '' ?>
                                    class="rounded border-gray-300 text-blue-600 shadow-sm">
                                <span class="ml-2 text-sm font-medium text-gray-700">Has Projector?</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="has_smartboard" <?= $classroom['has_smartboard'] ? 'checked' : '' ?>
                                    class="rounded border-gray-300 text-blue-600 shadow-sm">
                                <span class="ml-2 text-sm font-medium text-gray-700">Has Smartboard?</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="has_computers" <?= $classroom['has_computers'] ? 'checked' : '' ?>
                                    class="rounded border-gray-300 text-blue-600 shadow-sm">
                                <span class="ml-2 text-sm font-medium text-gray-700">Has Computers?</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="shared" <?= $classroom['shared'] ? 'checked' : '' ?>
                                    class="rounded border-gray-300 text-blue-600 shadow-sm">
                                <span class="ml-2 text-sm font-medium text-gray-700">Shared?</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="is_active" <?= $classroom['is_active'] ? 'checked' : '' ?>
                                    class="rounded border-gray-300 text-blue-600 shadow-sm">
                                <span class="ml-2 text-sm font-medium text-gray-700">Active?</span>
                            </label>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <a href="/chair/classroom" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Cancel</a>
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