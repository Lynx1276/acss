<?php
// Define current URI at the top
$currentUri = $_SERVER['REQUEST_URI'];

// Set page title for header
$pageTitle = "Faculty Availability Management";
$pageSubtitle = "Manage faculty schedules and time preferences";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | PRMSU Scheduling System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --prmsu-blue: #0056b3;
            --prmsu-gold: #FFD700;
            --prmsu-light: #f8f9fa;
            --prmsu-dark: #343a40;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: #f9fafb;
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

        .availability-card {
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .availability-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transform: translateY(-1px);
        }

        .time-slot-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .preference-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .preferred {
            background-color: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .neutral {
            background-color: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .avoid {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Sidebar adjustment for content */
        main {
            margin-left: 16rem;
            /* Match sidebar width */
            padding-top: 4rem;
            /* Match header height */
        }

        @media (max-width: 768px) {
            main {
                margin-left: 0;
            }
        }
    </style>
</head>

<body class="min-h-screen">
    <!-- Include Header -->
    <?php include __DIR__ . '/../partials/chair/header.php'; ?>

    <!-- Include Sidebar -->
    <?php include __DIR__ . '/../partials/chair/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="px-6 py-4">
        <div class="max-w-7xl mx-auto py-8">
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900"><?= $pageTitle ?></h1>
                    <p class="text-gray-600 mt-1"><?= $pageSubtitle ?></p>
                </div>
                <div class="bg-white px-4 py-2 rounded-lg border border-gray-200 shadow-xs">
                    <span class="font-medium text-gray-700">Current Semester:</span>
                    <span class="text-blue-600 font-semibold"><?= htmlspecialchars($currentSemester['semester_name'] . ' ' . $currentSemester['academic_year']) ?></span>
                </div>
            </div>

            <!-- Flash Message -->
            <?php if (isset($_SESSION['flash'])): ?>
                <div class="mb-6 p-4 rounded-lg <?= $_SESSION['flash']['type'] === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?> flex items-start">
                    <i class="fas <?= $_SESSION['flash']['type'] === 'success' ? 'fa-check-circle mt-0.5' : 'fa-exclamation-circle mt-0.5' ?> mr-3"></i>
                    <div><?= $_SESSION['flash']['message'] ?></div>
                </div>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>

            <!-- Faculty List -->
            <div class="space-y-6">
                <?php foreach ($faculty as $member): ?>
                    <div class="availability-card bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <!-- Faculty Header -->
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                                    <span class="w-8 h-8 rounded-full bg-blue-100 text-blue-800 flex items-center justify-center">
                                        <i class="fas fa-user text-sm"></i>
                                    </span>
                                    <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>
                                </h3>
                                <div class="flex items-center gap-3 mt-1">
                                    <span class="text-sm text-gray-500"><?= htmlspecialchars($member['position']) ?></span>
                                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded">ID: <?= htmlspecialchars($member['faculty_id']) ?></span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-medium bg-blue-100 text-blue-800 px-2 py-1 rounded-full">
                                    <?= count($availability[$member['faculty_id']] ?? []) ?> time slots
                                </span>
                            </div>
                        </div>

                        <!-- Current Availability -->
                        <div class="p-6">
                            <h4 class="font-medium text-gray-700 mb-4 flex items-center gap-2">
                                <i class="far fa-calendar text-blue-500"></i>
                                Current Availability
                            </h4>

                            <?php if (!empty($availability[$member['faculty_id']])): ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Day</th>
                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Preference</th>
                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($availability[$member['faculty_id']] as $slot): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?= htmlspecialchars($slot['day_of_week']) ?>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                        <?= date('g:i A', strtotime($slot['start_time'])) ?> - <?= date('g:i A', strtotime($slot['end_time'])) ?>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap">
                                                        <span class="time-slot-badge rounded-full px-2.5 py-0.5 text-xs font-medium <?= $slot['is_available'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                            <?= $slot['is_available'] ? 'Available' : 'Unavailable' ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap">
                                                        <?php
                                                        $preferenceClass = '';
                                                        if ($slot['preference_level'] === 'Preferred') {
                                                            $preferenceClass = 'preferred';
                                                        } elseif ($slot['preference_level'] === 'Avoid If Possible') {
                                                            $preferenceClass = 'avoid';
                                                        } else {
                                                            $preferenceClass = 'neutral';
                                                        }
                                                        ?>
                                                        <span class="preference-badge rounded-md <?= $preferenceClass ?>">
                                                            <?= htmlspecialchars($slot['preference_level']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                        <?= !empty($slot['reason']) ? htmlspecialchars($slot['reason']) : '—' ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="bg-gray-50 rounded-lg p-6 text-center">
                                    <i class="far fa-calendar-times text-gray-400 text-3xl mb-3"></i>
                                    <p class="text-gray-500 font-medium">No availability recorded</p>
                                    <p class="text-gray-400 text-sm mt-1">Add availability slots below</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Add New Availability -->
                        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                            <h4 class="font-medium text-gray-700 mb-3 flex items-center gap-2">
                                <i class="fas fa-plus-circle text-blue-500"></i>
                                Add New Time Slot
                            </h4>

                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="faculty_id" value="<?= $member['faculty_id'] ?>">

                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Day</label>
                                        <select name="day_of_week" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                            <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day): ?>
                                                <option value="<?= $day ?>"><?= $day ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                                        <input type="time" name="start_time" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md" required>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                                        <input type="time" name="end_time" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md" required>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Preference</label>
                                        <select name="preference_level" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                            <option value="Preferred">Preferred</option>
                                            <option value="Neutral" selected>Neutral</option>
                                            <option value="Avoid If Possible">Avoid If Possible</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="flex items-center">
                                        <input id="is_available_<?= $member['faculty_id'] ?>" name="is_available" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" checked>
                                        <label for="is_available_<?= $member['faculty_id'] ?>" class="ml-2 block text-sm text-gray-700">Available</label>
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                                        <input type="text" name="reason" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md" placeholder="Optional notes">
                                    </div>
                                </div>

                                <div class="flex justify-end pt-2">
                                    <button type="submit" name="update_availability" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-save mr-2"></i>
                                        Save Time Slot
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <!-- Mobile sidebar overlay -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-0 md:hidden hidden"></div>

    <script>
        // Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.querySelectorAll('#sidebar-toggle');
            const sidebarOverlay = document.getElementById('sidebar-overlay');

            sidebarToggle.forEach(button => {
                button.addEventListener('click', function() {
                    sidebar.classList.toggle('-translate-x-full');
                    sidebarOverlay.classList.toggle('hidden');

                    // Toggle icon
                    if (sidebar.classList.contains('-translate-x-full')) {
                        document.querySelector('#sidebar-toggle i').className = 'fas fa-bars';
                    } else {
                        document.querySelector('#sidebar-toggle i').className = 'fas fa-times';
                    }
                });
            });

            // Close sidebar when clicking overlay
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
                document.querySelector('#sidebar-toggle i').className = 'fas fa-bars';
            });
        });
    </script>
</body>

</html>