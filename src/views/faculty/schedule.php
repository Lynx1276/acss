<?php
// views/faculty/schedule.php
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule | PRMSU Faculty</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --prmsu-blue: #0056b3;
            --prmsu-gold: #FFD700;
            --prmsu-light: #f8f9fa;
            --prmsu-dark: #343a40;
        }

        .schedule-card {
            transition: all 0.3s ease;
            border-left: 4px solid var(--prmsu-blue);
        }

        .schedule-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .status-pending {
            background-color: #FEF3C7;
            color: #92400E;
        }

        .status-approved {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .day-monday {
            border-left-color: #3B82F6;
        }

        .day-tuesday {
            border-left-color: #10B981;
        }

        .day-wednesday {
            border-left-color: #F59E0B;
        }

        .day-thursday {
            border-left-color: #8B5CF6;
        }

        .day-friday {
            border-left-color: #EC4899;
        }

        .day-saturday {
            border-left-color: #6366F1;
        }

        .day-sunday {
            border-left-color: #EF4444;
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php include __DIR__ . '/../partials/faculty/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden md:ml-64">
        <?php include __DIR__ . '/../partials/faculty/header.php'; ?>

        <main class="flex-1 overflow-y-auto p-6">
            <div class="max-w-7xl mx-auto">
                <!-- Page Header -->
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Teaching Schedule</h1>
                        <p class="text-gray-600 mt-2">View and manage your class schedules</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                            <i class="fas fa-calendar-alt mr-2"></i>
                            <?= $selectedSemester ? htmlspecialchars($selectedSemester['semester_name'] . ' ' . ($selectedSemester['academic_year'] ?? '')) : 'Current Semester' ?>
                        </span>
                    </div>
                </div>

                <!-- Semester Filter -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
                    <form method="GET" action="/faculty/schedule" class="flex flex-col md:flex-row md:items-end space-y-4 md:space-y-0 md:space-x-4">
                        <div class="flex-1">
                            <label for="semester_id" class="block text-sm font-medium text-gray-700 mb-1">Select Semester</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-calendar text-gray-400"></i>
                                </div>
                                <select name="semester_id" id="semester_id" class="pl-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-2">
                                    <option value="">Current Semester</option>
                                    <?php foreach ($semesters as $semester): ?>
                                        <option value="<?= $semester['semester_id'] ?>" <?= $selectedSemesterId == $semester['semester_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($semester['semester_name'] . ' ' . ($semester['academic_year'] ?? '')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div>
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-filter mr-2"></i> Filter Schedule
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Schedule Display -->
                <?php if (empty($schedule)): ?>
                    <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                        <i class="fas fa-calendar-times text-gray-300 text-5xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900">No schedule assigned</h3>
                        <p class="mt-1 text-sm text-gray-500">Your teaching schedule will appear here once assigned</p>
                        <div class="mt-6">
                            <a href="/faculty/requests" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-question-circle mr-2"></i> Contact Administrator
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Weekly View Tabs -->
                    <div class="mb-6">
                        <div class="sm:hidden">
                            <label for="tabs" class="sr-only">Select a day</label>
                            <select id="tabs" class="block w-full rounded-md border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                                <option>All Days</option>
                                <option>Monday</option>
                                <option>Tuesday</option>
                                <option>Wednesday</option>
                                <option>Thursday</option>
                                <option>Friday</option>
                                <option>Saturday</option>
                            </select>
                        </div>
                        <div class="hidden sm:block">
                            <nav class="flex space-x-4" aria-label="Tabs">
                                <a href="#" class="px-3 py-2 font-medium text-sm rounded-md bg-blue-100 text-blue-700">All Days</a>
                                <a href="#" class="px-3 py-2 font-medium text-sm rounded-md text-gray-500 hover:text-gray-700">Monday</a>
                                <a href="#" class="px-3 py-2 font-medium text-sm rounded-md text-gray-500 hover:text-gray-700">Tuesday</a>
                                <a href="#" class="px-3 py-2 font-medium text-sm rounded-md text-gray-500 hover:text-gray-700">Wednesday</a>
                                <a href="#" class="px-3 py-2 font-medium text-sm rounded-md text-gray-500 hover:text-gray-700">Thursday</a>
                                <a href="#" class="px-3 py-2 font-medium text-sm rounded-md text-gray-500 hover:text-gray-700">Friday</a>
                                <a href="#" class="px-3 py-2 font-medium text-sm rounded-md text-gray-500 hover:text-gray-700">Saturday</a>
                            </nav>
                        </div>
                    </div>

                    <!-- Schedule Cards -->
                    <div class="space-y-4">
                        <?php foreach ($schedule as $entry): ?>
                            <div class="schedule-card bg-white rounded-lg shadow-sm p-6 day-<?= strtolower($entry['day_of_week']) ?>">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center space-x-3">
                                            <h3 class="text-xl font-bold text-gray-900">
                                                <?= htmlspecialchars($entry['course_code']) ?>
                                            </h3>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?=
                                                                                                                                    $entry['status'] === 'approved' ? 'status-approved' : ($entry['status'] === 'pending' ? 'status-pending' : 'bg-gray-100 text-gray-800')
                                                                                                                                    ?>">
                                                <?= htmlspecialchars(ucfirst($entry['status'])) ?>
                                            </span>
                                        </div>
                                        <p class="mt-1 text-gray-600">
                                            <?= htmlspecialchars($entry['course_name']) ?>
                                        </p>
                                        <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div class="flex items-start">
                                                <div class="flex-shrink-0 h-5 w-5 text-gray-400">
                                                    <i class="fas fa-users"></i>
                                                </div>
                                                <div class="ml-2">
                                                    <p class="text-sm text-gray-500">Section</p>
                                                    <p class="text-sm font-medium text-gray-900">
                                                        <?= htmlspecialchars($entry['section_name'] ?? 'N/A') ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="flex items-start">
                                                <div class="flex-shrink-0 h-5 w-5 text-gray-400">
                                                    <i class="fas fa-clock"></i>
                                                </div>
                                                <div class="ml-2">
                                                    <p class="text-sm text-gray-500">Time</p>
                                                    <p class="text-sm font-medium text-gray-900">
                                                        <?= htmlspecialchars($entry['start_time_display'] . ' - ' . $entry['end_time_display']) ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="flex items-start">
                                                <div class="flex-shrink-0 h-5 w-5 text-gray-400">
                                                    <i class="fas fa-door-open"></i>
                                                </div>
                                                <div class="ml-2">
                                                    <p class="text-sm text-gray-500">Room</p>
                                                    <p class="text-sm font-medium text-gray-900">
                                                        <?= htmlspecialchars($entry['room_name'] ?? 'N/A') ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-4 md:mt-0 md:ml-4 flex-shrink-0 flex flex-col space-y-2">
                                        <a href="/faculty/schedule/request?schedule_id=<?= $entry['schedule_id'] ?>" class="inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-exchange-alt mr-1"></i> Request Change
                                        </a>
                                        <a href="#" class="inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-info-circle mr-1"></i> Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>