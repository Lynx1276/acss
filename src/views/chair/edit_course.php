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

// Get course details
$courseId = $_GET['id'] ?? null;
if (!$courseId) {
    header('Location: /chair/courses');
    exit;
}

$query = "SELECT * FROM courses WHERE course_id = :course_id AND department_id = :department_id";
$stmt = $db->prepare($query);
$stmt->execute([':course_id' => $courseId, ':department_id' => $departmentId]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$course) {
    header('Location: /chair/courses');
    exit;
}

// Get programs
$programs = $schedulingService->getDepartmentPrograms($departmentId);

// Define $stats for sidebar
$stats = ['pendingApprovals' => count($schedulingService->getPendingApprovals($departmentId))];

// Handle POST for editing course
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseCode = $_POST['course_code'] ?? $course['course_code'];
    $courseName = $_POST['course_name'] ?? $course['course_name'];
    $programId = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
    $units = (int)($_POST['units'] ?? $course['units']);
    $lectureHours = (int)($_POST['lecture_hours'] ?? $course['lecture_hours']);
    $labHours = (int)($_POST['lab_hours'] ?? $course['lab_hours']);
    $semester = $_POST['semester'] ?? $course['semester'];
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $updateQuery = "UPDATE courses SET 
                    course_code = :course_code, 
                    course_name = :course_name, 
                    program_id = :program_id, 
                    units = :units, 
                    lecture_hours = :lecture_hours, 
                    lab_hours = :lab_hours, 
                    semester = :semester, 
                    is_active = :is_active 
                    WHERE course_id = :course_id AND department_id = :department_id";
    $stmt = $db->prepare($updateQuery);
    $stmt->execute([
        ':course_code' => $courseCode,
        ':course_name' => $courseName,
        ':program_id' => $programId,
        ':units' => $units,
        ':lecture_hours' => $lectureHours,
        ':lab_hours' => $labHours,
        ':semester' => $semester,
        ':is_active' => $isActive,
        ':course_id' => $courseId,
        ':department_id' => $departmentId
    ]);

    header('Location: /chair/courses');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Course | PRMSU</title>
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
                <h1 class="text-2xl font-bold text-gray-900 mb-6">Edit Course: <?= htmlspecialchars($course['course_code']) ?></h1>

                <div class="bg-white shadow rounded-lg overflow-hidden p-6">
                    <form method="POST">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Course Code</label>
                            <input type="text" name="course_code" value="<?= htmlspecialchars($course['course_code']) ?>"
                                required class="w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Course Name</label>
                            <input type="text" name="course_name" value="<?= htmlspecialchars($course['course_name']) ?>"
                                required class="w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Program</label>
                            <select name="program_id" class="w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">None</option>
                                <?php foreach ($programs as $program): ?>
                                    <option value="<?= $program['program_id'] ?>" <?= $program['program_id'] == $course['program_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($program['program_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Units</label>
                            <input type="number" name="units" min="1" max="255" value="<?= htmlspecialchars($course['units']) ?>"
                                required class="w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div class="mb-4 grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Lecture Hours</label>
                                <input type="number" name="lecture_hours" min="0" max="255" value="<?= htmlspecialchars($course['lecture_hours']) ?>"
                                    class="w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Lab Hours</label>
                                <input type="number" name="lab_hours" min="0" max="255" value="<?= htmlspecialchars($course['lab_hours']) ?>"
                                    class="w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Semester</label>
                            <select name="semester" class="w-full rounded-md border-gray-300 shadow-sm">
                                <option value="1st" <?= $course['semester'] === '1st' ? 'selected' : '' ?>>1st</option>
                                <option value="2nd" <?= $course['semester'] === '2nd' ? 'selected' : '' ?>>2nd</option>
                                <option value="Summer" <?= $course['semester'] === 'Summer' ? 'selected' : '' ?>>Summer</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="is_active" <?= $course['is_active'] ? 'checked' : '' ?>
                                    class="rounded border-gray-300 text-blue-600 shadow-sm">
                                <span class="ml-2 text-sm font-medium text-gray-700">Active?</span>
                            </label>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <a href="/chair/courses" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Cancel</a>
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