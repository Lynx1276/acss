<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | PRMSU Scheduling System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* PRMSU Color Palette - Refined */
        :root {
            --prmsu-gray-dark: #2A2A2A;
            --prmsu-gray: #555555;
            --prmsu-gray-light: #f8f9fa;
            --prmsu-gold: #EFBB0F;
            --prmsu-gold-light: #FFF8E1;
            --prmsu-gold-dark: #D9A800;
            --prmsu-white: #ffffff;
            --prmsu-accent: #0056b3;
            --prmsu-accent-light: #E3F2FD;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--prmsu-gray-light);
        }

        .sidebar {
            background: linear-gradient(180deg, var(--prmsu-gray-dark) 0%, #3A3A3A 100%);
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            background-color: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .nav-item {
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            margin-bottom: 2px;
        }

        .nav-item:hover {
            background-color: rgba(239, 187, 15, 0.15);
        }

        .nav-item.active {
            background-color: rgba(239, 187, 15, 0.2);
            border-left: 3px solid var(--prmsu-gold);
        }

        .badge {
            background-color: var(--prmsu-gold);
            color: var(--prmsu-gray-dark);
        }

        .card {
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }

        .profile-card {
            border-top: none;
            position: relative;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--prmsu-gold-light) 0%, #FFEFC3 100%);
            height: 80px;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            position: relative;
        }

        .profile-picture-container {
            position: absolute;
            top: 30px;
            left: 50%;
            transform: translateX(-50%);
        }

        .profile-picture {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid white;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            background-color: var(--prmsu-accent-light);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .profile-picture img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info {
            padding-top: 60px;
        }

        .stat-card {
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .gold-btn {
            background-color: var(--prmsu-gold);
            color: var(--prmsu-gray-dark);
            font-weight: 600;
            transition: all 0.3s;
        }

        .gold-btn:hover {
            background-color: var(--prmsu-gold-dark);
            transform: translateY(-1px);
        }

        .blue-btn {
            background-color: var(--prmsu-accent);
            color: white;
            font-weight: 600;
            transition: all 0.3s;
        }

        .blue-btn:hover {
            background-color: #004494;
            transform: translateY(-1px);
        }

        .info-label {
            font-size: 0.875rem;
            color: #6B7280;
            font-weight: 500;
        }

        .info-value {
            font-size: 1rem;
            color: #111827;
            font-weight: 500;
        }

        .card-header {
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #111827;
        }

        .modal-container {
            backdrop-filter: blur(5px);
        }

        .modal-content {
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .input-field {
            border-radius: 8px;
            border: 1px solid #E5E7EB;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            transition: all 0.3s;
        }

        .input-field:focus {
            border-color: var(--prmsu-accent);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
            outline: none;
        }

        .file-input {
            border: 1px dashed #D1D5DB;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-input:hover {
            border-color: var(--prmsu-accent);
            background-color: var(--prmsu-accent-light);
        }

        /* Improved Stats Design */
        .stat-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        .stat-item {
            padding: 0.75rem;
            border-radius: 10px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .stat-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background-color: currentColor;
            opacity: 0.8;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            position: relative;
            z-index: 1;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #4B5563;
            font-weight: 500;
            position: relative;
            z-index: 1;
        }

        .stat-bg {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0.1;
            z-index: 0;
        }

        /* Navigation Indicator */
        .breadcrumb {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            color: var(--prmsu-gray);
        }

        .breadcrumb-item {
            display: flex;
            align-items: center;
        }

        .breadcrumb-separator {
            margin: 0 0.5rem;
            color: #9CA3AF;
        }
    </style>
</head>

<body class="bg-gray-50 flex h-screen">
    <!-- Sidebar goes here - imported from partial -->
    <?php require_once __DIR__ . '/../partials/chair/sidebar.php'; ?>
    <!-- Main content -->
    <div class="w-full ml-0 md:ml-64 transition-all duration-300">
        <?php require_once __DIR__ . '/../partials/chair/header.php'; ?>
        <div class="container mx-auto px-4 py-6">
            <div class="flex flex-col lg:flex-row gap-6">
                <!-- Left Column - Profile Summary -->
                <div class="w-full lg:w-1/3 space-y-6">
                    <!-- Profile Card -->
                    <div class="card profile-card bg-white">
                        <div class="profile-header"></div>
                        <div class="profile-picture-container">
                            <div class="profile-picture">
                                <?php if ($user['profile_picture']): ?>
                                    <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile Picture">
                                <?php else: ?>
                                    <span class="text-4xl text-blue-600 font-bold"><?= substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="profile-info text-center p-6">
                            <h2 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h2>
                            <div class="flex items-center justify-center mt-1">
                                <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">Program Chair</span>
                            </div>
                            <p class="text-gray-600 mt-1"><?= htmlspecialchars($department['department_name']) ?></p>
                            <p class="text-gray-500 text-sm"><?= htmlspecialchars($college['college_name']) ?></p>

                            <div class="border-t border-gray-100 mt-4 pt-4">
                                <div class="flex items-center justify-center space-x-1">
                                    <i class="fas fa-envelope text-gray-400"></i>
                                    <span class="text-gray-600 text-sm"><?= htmlspecialchars($user['email']) ?></span>
                                </div>
                                <?php if ($user['phone']): ?>
                                    <div class="flex items-center justify-center space-x-1 mt-2">
                                        <i class="fas fa-phone text-gray-400"></i>
                                        <span class="text-gray-600 text-sm"><?= htmlspecialchars($user['phone']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <button onclick="toggleEditModal()" class="mt-6 w-full flex items-center justify-center blue-btn rounded-lg py-2.5 px-4 text-sm">
                                <i class="fas fa-user-edit mr-2"></i>Edit Profile
                            </button>
                        </div>
                    </div>

                    <!-- Stats Card -->
                    <div class="card bg-white p-6">
                        <h3 class="font-semibold text-gray-700 mb-4">Quick Statistics</h3>
                        <div class="stat-container">
                            <div class="stat-item bg-blue-50 text-blue-600">
                                <div class="stat-number"><?= $stats['facultyCount'] ?></div>
                                <div class="stat-label">Faculty</div>
                                <div class="stat-bg bg-blue-600"></div>
                            </div>
                            <div class="stat-item bg-green-50 text-green-600">
                                <div class="stat-number"><?= $stats['courseCount'] ?></div>
                                <div class="stat-label">Courses</div>
                                <div class="stat-bg bg-green-600"></div>
                            </div>
                            <div class="stat-item bg-purple-50 text-purple-600">
                                <div class="stat-number"><?= $stats['approvalCount'] ?></div>
                                <div class="stat-label">Pending</div>
                                <div class="stat-bg bg-purple-600"></div>
                            </div>
                            <div class="stat-item bg-amber-50 text-amber-600">
                                <div class="stat-number"><?= $semester['semester_name'] ?></div>
                                <div class="stat-label">Semester</div>
                                <div class="stat-bg bg-amber-600"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Account Security Card -->
                    <div class="card bg-white p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-semibold text-gray-700">Account Security</h3>
                            <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full">Secure</span>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <div class="info-label">Last Login</div>
                                <div class="info-value flex items-center mt-1">
                                    <i class="fas fa-clock text-gray-400 mr-2"></i>
                                    <?= date('F j, Y, g:i a', strtotime($lastLogin)) ?>
                                </div>
                            </div>
                            <div class="info-label mt-2">Password</div>
                            <div class="info-value flex items-center">
                                <i class="fas fa-circle text-xs text-gray-400 mr-2"></i>
                                <i class="fas fa-circle text-xs text-gray-400 mr-2"></i>
                                <i class="fas fa-circle text-xs text-gray-400 mr-2"></i>
                                <i class="fas fa-circle text-xs text-gray-400 mr-2"></i>
                                <i class="fas fa-circle text-xs text-gray-400 mr-2"></i>
                                <i class="fas fa-circle text-xs text-gray-400 mr-2"></i>
                                <i class="fas fa-circle text-xs text-gray-400 mr-2"></i>
                                <i class="fas fa-circle text-xs text-gray-400 mr-2"></i>
                                <span class="text-gray-400 ml-1">•••••••</span>
                            </div>
                            <button onclick="togglePasswordModal()" class="mt-4 w-full gold-btn rounded-lg py-2.5 px-4 text-sm flex items-center justify-center">
                                <i class="fas fa-key mr-2"></i>Change Password
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Detailed Info -->
                <div class="w-full lg:w-2/3 space-y-6">
                    <!-- Personal Information Card -->
                    <div class="card bg-white p-6">
                        <div class="card-header flex justify-between items-center">
                            <h2 class="card-title flex items-center">
                                <i class="fas fa-user-circle text-blue-600 mr-2"></i>
                                Personal Information
                            </h2>
                            <button onclick="toggleEditModal()" class="text-blue-600 hover:text-blue-800 transition-colors text-sm flex items-center">
                                <i class="fas fa-pencil-alt mr-1"></i>
                                Edit
                            </button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <div>
                                <div class="info-label">First Name</div>
                                <div class="info-value mt-1"><?= htmlspecialchars($user['first_name']) ?></div>
                            </div>
                            <div>
                                <div class="info-label">Middle Name</div>
                                <div class="info-value mt-1"><?= htmlspecialchars($user['middle_name'] ?? 'N/A') ?></div>
                            </div>
                            <div>
                                <div class="info-label">Last Name</div>
                                <div class="info-value mt-1"><?= htmlspecialchars($user['last_name']) ?></div>
                            </div>
                            <div>
                                <div class="info-label">Suffix</div>
                                <div class="info-value mt-1"><?= htmlspecialchars($user['suffix'] ?? 'N/A') ?></div>
                            </div>
                            <div>
                                <div class="info-label">Email Address</div>
                                <div class="info-value mt-1"><?= htmlspecialchars($user['email']) ?></div>
                            </div>
                            <div>
                                <div class="info-label">Phone Number</div>
                                <div class="info-value mt-1"><?= htmlspecialchars($user['phone'] ?? 'Not provided') ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Department Information Card -->
                    <div class="card bg-white p-6">
                        <div class="card-header flex justify-between items-center">
                            <h2 class="card-title flex items-center">
                                <i class="fas fa-building text-blue-600 mr-2"></i>
                                Department Information
                            </h2>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <div>
                                <div class="info-label">Employee ID</div>
                                <div class="info-value mt-1"><?= htmlspecialchars($user['employee_id']) ?></div>
                            </div>
                            <div>
                                <div class="info-label">Username</div>
                                <div class="info-value mt-1"><?= htmlspecialchars($user['username']) ?></div>
                            </div>
                            <div>
                                <div class="info-label">Department</div>
                                <div class="info-value mt-1"><?= htmlspecialchars($department['department_name']) ?></div>
                            </div>
                            <div>
                                <div class="info-label">College</div>
                                <div class="info-value mt-1"><?= htmlspecialchars($college['college_name']) ?></div>
                            </div>
                            <div>
                                <div class="info-label">College Code</div>
                                <div class="info-value mt-1"><?= htmlspecialchars($college['college_code']) ?></div>
                            </div>
                            <div>
                                <div class="info-label">Role</div>
                                <div class="info-value mt-1 flex items-center">
                                    <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                        <?= htmlspecialchars(ucfirst($user['role_name'])) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Card -->
                    <div class="card bg-white p-6">
                        <div class="card-header flex justify-between items-center">
                            <h2 class="card-title flex items-center">
                                <i class="fas fa-chart-line text-blue-600 mr-2"></i>
                                Recent Activity
                            </h2>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-start gap-3">
                                <div class="mt-1 bg-blue-100 text-blue-600 rounded-full p-2 flex-shrink-0">
                                    <i class="fas fa-sign-in-alt"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800">Last Login</h4>
                                    <p class="text-sm text-gray-600"><?= date('F j, Y, g:i a', strtotime($lastLogin)) ?></p>
                                </div>
                            </div>

                            <!-- Additional activity entries would go here -->
                            <div class="flex items-start gap-3">
                                <div class="mt-1 bg-green-100 text-green-600 rounded-full p-2 flex-shrink-0">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800">Profile Updated</h4>
                                    <p class="text-sm text-gray-600">Your profile was last updated on <?= date('F j, Y', strtotime('-3 days')) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 modal-container flex items-center justify-center z-50 hidden">
        <div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-lg mx-4">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-gray-800">Edit Profile</h3>
                    <button onclick="toggleEditModal()" class="text-gray-500 hover:text-gray-700 transition-colors">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form action="/chair/update_profile" method="POST" enctype="multipart/form-data">
                    <div class="space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" class="input-field w-full focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div>
                                <label for="middle_name" class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                                <input type="text" id="middle_name" name="middle_name" value="<?= htmlspecialchars($user['middle_name'] ?? '') ?>" class="input-field w-full focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" class="input-field w-full focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div>
                                <label for="suffix" class="block text-sm font-medium text-gray-700 mb-1">Suffix</label>
                                <input type="text" id="suffix" name="suffix" value="<?= htmlspecialchars($user['suffix'] ?? '') ?>" class="input-field w-full focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" class="input-field w-full focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <div>
                            <label for="profile_picture" class="block text-sm font-medium text-gray-700 mb-1">Profile Picture</label>
                            <div class="file-input">
                                <input type="file" id="profile_picture" name="profile_picture" class="hidden">
                                <label for="profile_picture" class="flex flex-col items-center justify-center cursor-pointer">
                                    <i class="fas fa-cloud-upload-alt text-blue-500 text-2xl mb-2"></i>
                                    <span class="text-sm text-gray-600">Click to upload or drag and drop</span>
                                    <span class="text-xs text-gray-500 mt-1">JPG, PNG or GIF (max. 2MB)</span>
                                </label>
                            </div>
                        </div>

                        <div class="pt-4 flex justify-end space-x-3">
                            <button type="button" onclick="toggleEditModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" class="blue-btn rounded-lg px-4 py-2">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div id="passwordModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-800">Change Password</h3>
                    <button onclick="togglePasswordModal()" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form action="/chair/change_password" method="POST">
                    <div class="space-y-4">
                        <div>
                            <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                        </div>

                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                            <input type="password" id="new_password" name="new_password" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                        </div>

                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                        </div>

                        <div class="pt-4 flex justify-end space-x-3">
                            <button type="button" onclick="togglePasswordModal()" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Change Password
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleEditModal() {
            document.getElementById('editModal').classList.toggle('hidden');
        }

        function togglePasswordModal() {
            document.getElementById('passwordModal').classList.toggle('hidden');
        }
    </script>

</body>

</html>