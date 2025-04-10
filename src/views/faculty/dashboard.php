<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard | PRMSU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --prmsu-blue: #0056b3;
            --prmsu-gold: #FFD700;
            --prmsu-light: #f8f9fa;
            --prmsu-dark: #343a40;
        }

        .stat-card {
            transition: all 0.3s ease;
            border-top: 4px solid;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .stat-card-courses {
            border-top-color: #3B82F6;
        }

        .stat-card-hours {
            border-top-color: #10B981;
        }

        .stat-card-requests {
            border-top-color: #F59E0B;
        }

        .schedule-item {
            transition: all 0.2s ease;
        }

        .schedule-item:hover {
            background-color: #f8fafc;
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php include __DIR__ . '/../partials/faculty/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden md:ml-64">
        <?php include __DIR__ . '/../partials/faculty/header.php'; ?>

        <main class="flex-1 overflow-y-auto p-6">
            <div class="max-w-7xl mx-auto">
                <!-- Welcome Header -->
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Welcome, <?= htmlspecialchars($_SESSION['user']['first_name'] . ' ' . $_SESSION['user']['last_name']) ?></h1>
                        <p class="text-gray-600 mt-2">Here's your teaching overview</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                            <i class="fas fa-user-tie mr-2"></i> Faculty
                        </span>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="stat-card stat-card-courses bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-lg bg-blue-100 text-blue-600 mr-4">
                                <i class="fas fa-book-open text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Total Courses</p>
                                <p class="text-3xl font-bold text-gray-900 mt-1"><?= $stats['totalCourses'] ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card stat-card-hours bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-lg bg-green-100 text-green-600 mr-4">
                                <i class="fas fa-clock text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Teaching Hours</p>
                                <p class="text-3xl font-bold text-gray-900 mt-1"><?= $stats['totalHours'] ?> hrs</p>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card stat-card-requests bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-lg bg-yellow-100 text-yellow-600 mr-4">
                                <i class="fas fa-file-signature text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Pending Requests</p>
                                <p class="text-3xl font-bold text-gray-900 mt-1"><?= $stats['pendingRequests'] ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Current Schedule -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-200 flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-calendar-alt text-blue-500 mr-2"></i> Current Schedule
                        </h2>
                        <a href="/faculty/schedule" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                            View Full Schedule <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>

                    <?php if (empty($schedule)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-calendar-times text-gray-300 text-5xl mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900">No schedule assigned</h3>
                            <p class="mt-1 text-sm text-gray-500">Your schedule will appear here once assigned</p>
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-gray-200">
                            <?php foreach ($schedule as $entry): ?>
                                <div class="schedule-item p-6 hover:bg-gray-50">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center space-x-3">
                                                <h3 class="text-lg font-medium text-gray-900">
                                                    <?= htmlspecialchars($entry['course_code']) ?>
                                                </h3>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?=
                                                                                                                                        $entry['status'] === 'approved' ? 'bg-green-100 text-green-800' : ($entry['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800')
                                                                                                                                        ?>">
                                                    <?= htmlspecialchars(ucfirst($entry['status'])) ?>
                                                </span>
                                            </div>
                                            <p class="mt-1 text-sm text-gray-500">
                                                <i class="fas fa-clock mr-1"></i> <?= htmlspecialchars($entry['time_slot']) ?>
                                                <span class="mx-2">•</span>
                                                <i class="fas fa-door-open mr-1"></i> <?= htmlspecialchars($entry['room_name']) ?>
                                            </p>
                                        </div>
                                        <div class="ml-4 flex-shrink-0">
                                            <a href="/faculty/schedule/request?schedule_id=<?= $entry['schedule_id'] ?>" class="inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                <i class="fas fa-exchange-alt mr-1"></i> Request Change
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <a href="/faculty/schedule" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-center">
                            <div class="p-3 rounded-lg bg-blue-100 text-blue-600 mr-4">
                                <i class="fas fa-calendar-alt text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">View Schedule</h3>
                                <p class="mt-1 text-sm text-gray-500">Check your complete teaching schedule</p>
                            </div>
                        </div>
                    </a>

                    <a href="/faculty/requests" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-center">
                            <div class="p-3 rounded-lg bg-yellow-100 text-yellow-600 mr-4">
                                <i class="fas fa-file-signature text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Submit Request</h3>
                                <p class="mt-1 text-sm text-gray-500">Request schedule changes</p>
                            </div>
                        </div>
                    </a>

                    <a href="/faculty/profile" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-center">
                            <div class="p-3 rounded-lg bg-green-100 text-green-600 mr-4">
                                <i class="fas fa-user text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Update Profile</h3>
                                <p class="mt-1 text-sm text-gray-500">Manage your personal information</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </main>
    </div>
</body>

</html>