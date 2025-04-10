<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requests | PRMSU Faculty</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --prmsu-blue: #0056b3;
            --prmsu-gold: #FFD700;
            --prmsu-light: #f8f9fa;
            --prmsu-dark: #343a40;
        }

        .request-card {
            transition: all 0.3s ease;
            border-left: 3px solid;
        }

        .request-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .request-type-change {
            border-left-color: #3B82F6;
        }

        .request-type-room {
            border-left-color: #10B981;
        }

        .status-pending {
            background-color: #FEF3C7;
            color: #92400E;
        }

        .status-approved {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .status-rejected {
            background-color: #FEE2E2;
            color: #991B1B;
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
                        <h1 class="text-3xl font-bold text-gray-900">Schedule Requests</h1>
                        <p class="text-gray-600 mt-2">Manage your schedule change requests</p>
                    </div>
                </div>

                <!-- Request Form Card -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-plus-circle text-blue-500 mr-2"></i> New Request
                        </h2>
                    </div>

                    <form method="POST" action="/faculty/requests" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="schedule_id" class="block text-sm font-medium text-gray-700 mb-1">Select Schedule</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-calendar-alt text-gray-400"></i>
                                    </div>
                                    <select name="schedule_id" id="schedule_id" class="pl-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-2" required>
                                        <option value="">Choose a schedule</option>
                                        <?php foreach ($schedules as $schedule): ?>
                                            <option value="<?= $schedule['schedule_id'] ?>">
                                                <?= htmlspecialchars($schedule['course_code'] . ' - ' . $schedule['day_of_week'] . ' ' . $schedule['start_time_display'] . '-' . $schedule['end_time_display'] . ' (' . ($schedule['room_name'] ?? 'N/A') . ')') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label for="request_type" class="block text-sm font-medium text-gray-700 mb-1">Request Type</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-tag text-gray-400"></i>
                                    </div>
                                    <select name="request_type" id="request_type" class="pl-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-2" required>
                                        <option value="time_change">Change Time Slot</option>
                                        <option value="room_change">Change Room</option>
                                    </select>
                                </div>
                            </div>

                            <div class="md:col-span-2">
                                <label for="details" class="block text-sm font-medium text-gray-700 mb-1">Details</label>
                                <div class="relative">
                                    <div class="absolute top-3 left-3">
                                        <i class="fas fa-align-left text-gray-400"></i>
                                    </div>
                                    <textarea name="details" id="details" rows="3" class="pl-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-2" placeholder="Please provide details about your request (e.g., preferred new time or room)" required></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-paper-plane mr-2"></i> Submit Request
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Request History -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-200 flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-history text-blue-500 mr-2"></i> Request History
                        </h2>
                        <div class="flex items-center space-x-2">
                            <span class="text-sm text-gray-500">Total: <?= count($requests) ?></span>
                        </div>
                    </div>

                    <?php if (empty($requests)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-inbox text-gray-300 text-5xl mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900">No requests yet</h3>
                            <p class="mt-1 text-sm text-gray-500">Submit your first request using the form above</p>
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-gray-200">
                            <?php foreach ($requests as $request): ?>
                                <div class="request-card bg-white p-6 request-type-<?= strpos($request['request_type'], 'time') !== false ? 'change' : 'room' ?>">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center space-x-3">
                                                <h3 class="text-lg font-medium text-gray-900">
                                                    <?= htmlspecialchars($request['course_code']) ?>
                                                </h3>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?=
                                                                                                                                        $request['status'] === 'approved' ? 'status-approved' : ($request['status'] === 'rejected' ? 'status-rejected' : 'status-pending')
                                                                                                                                        ?>">
                                                    <?= htmlspecialchars(ucfirst($request['status'])) ?>
                                                </span>
                                            </div>
                                            <p class="mt-1 text-sm text-gray-500">
                                                <i class="fas fa-clock mr-1"></i> <?= htmlspecialchars($request['time_slot']) ?>
                                                <span class="mx-2">•</span>
                                                <i class="fas fa-door-open mr-1"></i> <?= htmlspecialchars($request['room_name'] ?? 'N/A') ?>
                                            </p>
                                            <p class="mt-2 text-sm text-gray-700">
                                                <span class="font-medium">Request:</span> <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $request['request_type']))) ?>
                                            </p>
                                            <p class="mt-1 text-sm text-gray-700">
                                                <span class="font-medium">Details:</span> <?= htmlspecialchars($request['details']) ?>
                                            </p>
                                        </div>
                                        <div class="ml-4 flex-shrink-0 flex flex-col items-end">
                                            <p class="text-sm text-gray-500">
                                                <i class="far fa-calendar mr-1"></i> <?= date('M j, Y', strtotime($request['created_at'])) ?>
                                            </p>
                                            <?php if ($request['status'] === 'pending'): ?>
                                                <button class="mt-2 inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                    <i class="fas fa-edit mr-1"></i> Edit
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>

</html>