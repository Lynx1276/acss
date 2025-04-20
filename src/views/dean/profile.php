<?php
        require_once __DIR__ . '/../../config/Database.php';
        require_once __DIR__ . '/../../services/DeanService.php';

use App\config\Database;

$currentUri = $_SERVER['REQUEST_URI'];
$collegeId = $_SESSION['user']['college_id'] ?? null;
if (!$collegeId) {
    die("College ID not found in session");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | PRMSU</title>
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
            background-color: var(--prmsu-gray-dark);
        }

        .nav-item:hover {
            background-color: rgba(244, 147, 12, 0.15);
        }

        .nav-item.active {
            background-color: rgba(212, 175, 55, 0.2);
            border-left: 4px solid var(--prmsu-gold);
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php include __DIR__ . '/../partials/dean/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden md:ml-64">
        <?php include __DIR__ . '/../partials/dean/header.php'; ?>

        <main class="flex-1 overflow-y-auto p-6">
            <div class="max-w-7xl mx-auto">
                <h1 class="text-2xl font-bold text-gray-900 mb-6">Profile</h1>
                <div class="bg-white rounded-lg shadow p-6">
                    <p class="text-gray-700">Profile page under construction.</p>
                </div>
            </div>
        </main>
    </div>
</body>

</html>