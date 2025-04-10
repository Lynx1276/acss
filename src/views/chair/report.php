<?php
// views/chair/reports.php
// No require_once or AuthMiddleware here—handled by index.php and ChairController
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | PRMSU</title>
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
    </style>
</head>

<body class="bg-gray-50">
    <?php include __DIR__ . '/../partials/chair/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden md:ml-64">
        <?php include __DIR__ . '/../partials/chair/header.php'; ?>

        <main class="flex-1 overflow-y-auto p-6">
            <div class="max-w-7xl mx-auto">
                <h1 class="text-2xl font-bold text-gray-900 mb-6">Generate Reports</h1>

                <div class="bg-white shadow rounded-lg p-6">
                    <h2 class="text-lg font-semibold mb-4">Available Reports</h2>
                    <div class="space-y-4">
                        <form method="POST" action="/chair/reports" class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-700 font-medium">Course Schedule Report</p>
                                <p class="text-sm text-gray-500">Download a CSV of all approved course schedules for your department.</p>
                            </div>
                            <button type="submit" name="report_type" value="schedule" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                                <i class="fas fa-download mr-2"></i> Download
                            </button>
                        </form>
                        <form method="POST" action="/chair/reports" class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-700 font-medium">Faculty Load Report</p>
                                <p class="text-sm text-gray-500">Download a CSV of faculty teaching loads for your department.</p>
                            </div>
                            <button type="submit" name="report_type" value="faculty_load" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                                <i class="fas fa-download mr-2"></i> Download
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>

</html>