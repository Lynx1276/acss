<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PRMSU Class Schedules</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Poppins', sans-serif;
        }

        .hero-pattern {
            background-color: #1e40af;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'%3E%3Cg fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath opacity='.5' d='M96 95h4v1h-4v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h4v1h-4v9h4v1h-4v9h4v1h-4v9h4v1h-4v9h4v1h-4v9h4v1h-4v9h4v1h-4v9h4v1h-4v9zm-1 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-9-10h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm9-10v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-9-10h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm9-10v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-9-10h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm9-10v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-9-10h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9z'/%3E%3Cpath d='M6 5V0H5v5H0v1h5v94h1V6h94V5H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .form-input {
            @apply rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 focus:ring-opacity-50 transition duration-150;
        }

        .btn-primary {
            @apply bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50;
        }

        .schedule-card {
            transition: transform 0.2s;
        }

        .schedule-card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Header with Unified Login -->
    <header class="hero-pattern text-white shadow-lg">
        <div class="container mx-auto px-4 py-8">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="flex items-center mb-4 md:mb-0">
                    <div class="mr-4">
                        <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center">
                            <img src="/api/placeholder/48/48" alt="PRMSU Logo" class="w-10 h-10" />
                        </div>
                    </div>
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold">President Ramon Magsaysay State University</h1>
                        <p class="text-blue-200 text-sm md:text-base">Academic Schedule Management System</p>
                    </div>
                </div>
                <nav class="flex space-x-2">
                    <a href="/auth/login" id="loginBtn" class="bg-white hover:bg-blue-50 text-blue-700 py-2 px-4 rounded-lg flex items-center transition-all">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        <span>Login</span>
                    </a>
                    <a href="/auth/register" id="registerBtn" class="bg-blue-700 hover:bg-blue-600 text-white py-2 px-4 rounded-lg flex items-center transition-all">
                        <i class="fas fa-user-plus mr-2"></i>
                        <span>Register</span>
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Announcement Banner (Optional) -->
    <div class="bg-yellow-50 border-b border-yellow-100">
        <div class="container mx-auto px-4 py-3">
            <div class="flex items-center justify-center text-yellow-800">
                <i class="fas fa-bell mr-2"></i>
                <p class="text-sm font-medium">Registration for Second Semester 2025-2026 is now open! Please check with your department for details.</p>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <!-- Hero Banner -->
        <div class="bg-gradient-to-r from-blue-700 to-blue-900 rounded-2xl shadow-xl mb-8 overflow-hidden">
            <div class="flex flex-col md:flex-row">
                <div class="p-8 md:w-3/5">
                    <h2 class="text-3xl font-bold text-white mb-4">Find Your Class Schedule</h2>
                    <p class="text-blue-100 mb-6">Access comprehensive class schedules for all courses, departments, and instructors at PRMSU.</p>
                    <div class="flex space-x-4">
                        <a href="#search-form" class="bg-white text-blue-700 hover:bg-blue-50 font-medium py-2 px-6 rounded-lg transition">
                            Search Now
                        </a>
                        <a href="#" class="border border-white text-white hover:bg-white hover:bg-opacity-10 font-medium py-2 px-6 rounded-lg transition">
                            Learn More
                        </a>
                    </div>
                </div>
                <div class="md:w-2/5 bg-blue-800 flex items-center justify-center p-8">
                    <img src="/api/placeholder/300/200" alt="University Campus" class="rounded-lg shadow-lg" />
                </div>
            </div>
        </div>

        <!-- Search Filters -->
        <div id="search-form" class="bg-white rounded-xl shadow-lg p-6 mb-8">
            <div class="flex items-center mb-6">
                <div class="bg-blue-100 p-2 rounded-lg mr-4">
                    <i class="fas fa-search text-blue-600 text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Find Class Schedules</h2>
                    <p class="text-gray-500 text-sm">Filter by semester, department, or course code</p>
                </div>
            </div>
            <form action="/public/search" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div>
                    <label for="semester" class="block text-sm font-medium text-gray-700 mb-2">Semester</label>
                    <div class="relative">
                        <select id="semester" name="semester_id" class="w-full form-input pl-10 py-3">
                            <option value="">All Semesters</option>
                            <?php foreach ($semesters as $semester): ?>
                                <option value="<?= $semester['semester_id'] ?>" <?= isset($_GET['semester_id']) && $_GET['semester_id'] == $semester['semester_id'] ? 'selected' : '' ?>>
                                    <?= $semester['semester_name'] ?> <?= $semester['academic_year'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i class="fas fa-calendar-alt text-gray-400"></i>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="department" class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                    <div class="relative">
                        <select id="department" name="department_id" class="w-full form-input pl-10 py-3">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['department_id'] ?>" <?= isset($_GET['department_id']) && $_GET['department_id'] == $dept['department_id'] ? 'selected' : '' ?>>
                                    <?= $dept['department_name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i class="fas fa-building text-gray-400"></i>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="course" class="block text-sm font-medium text-gray-700 mb-2">Course Code</label>
                    <div class="relative">
                        <input type="text" id="course" name="course_code" placeholder="e.g. CS 101"
                            value="<?= $_GET['course_code'] ?? '' ?>"
                            class="w-full form-input pl-10 py-3">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i class="fas fa-book text-gray-400"></i>
                        </div>
                    </div>
                </div>

                <div class="flex items-end">
                    <button type="submit" class="btn-primary w-full py-3 flex items-center justify-center">
                        <i class="fas fa-search mr-2"></i>
                        <span>Search Schedules</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Schedule Results -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Class Schedules</h2>
                    <p class="text-sm text-gray-600">
                        <span class="font-medium"><?= count($schedules) ?></span> classes found
                        <?= isset($_GET['semester_id']) ? 'for ' . htmlspecialchars($semesters[array_search($_GET['semester_id'], array_column($semesters, 'semester_id'))]['semester_name']) . ' ' .
                            htmlspecialchars($semesters[array_search($_GET['semester_id'], array_column($semesters, 'semester_id'))]['academic_year']) : '' ?>
                    </p>
                </div>
                <div class="text-gray-500">
                    <button class="p-2 hover:bg-gray-100 rounded-lg transition" title="Print results">
                        <i class="fas fa-print"></i>
                    </button>
                    <button class="p-2 hover:bg-gray-100 rounded-lg transition ml-2" title="Export as CSV">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
            </div>

            <?php if (empty($schedules)): ?>
                <div class="p-12 text-center">
                    <div class="inline-flex rounded-full bg-blue-50 p-4 mb-4">
                        <i class="fas fa-search text-blue-500 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-800 mb-2">No schedules found</h3>
                    <p class="text-gray-500 max-w-md mx-auto">Try adjusting your search filters or browse all available schedules.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Schedule</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Faculty</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($schedules as $schedule): ?>
                                <tr class="hover:bg-blue-50 transition-colors schedule-card">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-blue-600"><?= $schedule['course_code'] ?></div>
                                        <div class="text-sm text-gray-500"><?= $schedule['course_name'] ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                                            <?= $schedule['section_name'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium flex items-center">
                                            <i class="far fa-calendar-alt mr-2 text-gray-400"></i>
                                            <?= $schedule['day_of_week'] ?>
                                        </div>
                                        <div class="text-sm text-gray-500 flex items-center">
                                            <i class="far fa-clock mr-2 text-gray-400"></i>
                                            <?= date('g:i A', strtotime($schedule['start_time'])) ?> - <?= date('g:i A', strtotime($schedule['end_time'])) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <i class="fas fa-map-marker-alt mr-2 text-gray-400"></i>
                                            <?= $schedule['room_name'] ? $schedule['room_name'] . ' (' . $schedule['building'] . ')' : 'TBA' ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <i class="fas fa-user-tie mr-2 text-gray-400"></i>
                                            <?= $schedule['faculty_name'] ?? 'TBA' ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <i class="fas fa-building mr-2 text-gray-400"></i>
                                            <?= $schedule['department_name'] ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <button class="text-blue-600 hover:text-blue-800" title="View details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Info Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
            <div class="bg-white p-6 rounded-xl shadow-md">
                <div class="rounded-full bg-green-100 w-12 h-12 flex items-center justify-center mb-4">
                    <i class="fas fa-calendar-check text-green-600"></i>
                </div>
                <h3 class="font-semibold text-lg mb-2">Academic Calendar</h3>
                <p class="text-gray-600 mb-4">View important dates, holidays, and academic deadlines for the current semester.</p>
                <a href="#" class="text-green-600 hover:text-green-700 font-medium flex items-center">
                    View Calendar <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-md">
                <div class="rounded-full bg-purple-100 w-12 h-12 flex items-center justify-center mb-4">
                    <i class="fas fa-book-open text-purple-600"></i>
                </div>
                <h3 class="font-semibold text-lg mb-2">Course Catalog</h3>
                <p class="text-gray-600 mb-4">Browse all available courses, their descriptions, prerequisites, and credit units.</p>
                <a href="#" class="text-purple-600 hover:text-purple-700 font-medium flex items-center">
                    Browse Courses <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-md">
                <div class="rounded-full bg-orange-100 w-12 h-12 flex items-center justify-center mb-4">
                    <i class="fas fa-question-circle text-orange-600"></i>
                </div>
                <h3 class="font-semibold text-lg mb-2">Need Help?</h3>
                <p class="text-gray-600 mb-4">Contact the registrar's office or your department for scheduling assistance.</p>
                <a href="#" class="text-orange-600 hover:text-orange-700 font-medium flex items-center">
                    Contact Support <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white mt-12">
        <div class="container mx-auto px-4 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-xl font-semibold mb-4">PRMSU</h3>
                    <p class="text-gray-300 mb-4">President Ramon Magsaysay State University is committed to excellence in education and research.</p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-300 hover:text-white"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-gray-300 hover:text-white"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-gray-300 hover:text-white"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-gray-300 hover:text-white"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-semibold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-300 hover:text-white">Academic Calendar</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white">Admission</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white">Student Portal</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white">Library</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-lg font-semibold mb-4">Resources</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-300 hover:text-white">Student Handbook</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white">Campus Map</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white">Events</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white">News & Announcements</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-lg font-semibold mb-4">Contact Us</h3>
                    <ul class="space-y-2">
                        <li class="flex items-center"><i class="fas fa-map-marker-alt mr-2 text-gray-400"></i> <span class="text-gray-300">University Address, Iba, Zambales</span></li>
                        <li class="flex items-center"><i class="fas fa-phone mr-2 text-gray-400"></i> <span class="text-gray-300">(+63) 123-456-7890</span></li>
                        <li class="flex items-center"><i class="fas fa-envelope mr-2 text-gray-400"></i> <span class="text-gray-300">info@prmsu.edu.ph</span></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-8 text-center">
                <p>&copy; <?= date('Y') ?> President Ramon Magsaysay State University. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Add any JavaScript functionality here
        document.addEventListener('DOMContentLoaded', function() {
            // You can add interactions like filter toggles, animations, etc.
        });
    </script>
</body>

</html>