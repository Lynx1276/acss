<?php
// src/views/dean/profile.php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../services/DeanService.php';

use App\config\Database;

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check for college_id in session
$collegeId = $_SESSION['user']['college_id'] ?? null;
if (!$collegeId) {
    die("College ID not found in session");
}

// Initialize DeanService
$deanService = new DeanService();
$userId = $_SESSION['user']['user_id'] ?? null;
if (!$userId) {
    die("User ID not found in session");
}

// Fetch user profile with department and college names
$user = $deanService->getUserProfile($userId);
if (!$user) {
    die("User profile not found");
}

// Prepare user data
$userName = isset($user['first_name']) && isset($user['last_name'])
    ? htmlspecialchars($user['first_name'] . ' ' . ($user['middle_name'] ? $user['middle_name'] . ' ' : '') . $user['last_name'] . ($user['suffix'] ? ' ' . $user['suffix'] : ''))
    : htmlspecialchars($user['username'] ?? 'Dean');
$email = htmlspecialchars($user['email'] ?? '');
$username = htmlspecialchars($user['username'] ?? '');
$phone = htmlspecialchars($user['phone'] ?? '');
$employee_id = htmlspecialchars($user['employee_id'] ?? '');
$department_name = htmlspecialchars($user['department_name'] ?? 'N/A');
$college_name = htmlspecialchars($user['college_name'] ?? 'N/A');
$profile_picture = htmlspecialchars($user['profile_picture'] ?? '/images/default-profile.png');

// Handle form submissions
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_method']) && $_POST['_method'] === 'PATCH') {
    try {
        if (isset($_POST['update_profile'])) {
            $data = [
                'first_name' => trim($_POST['first_name'] ?? ''),
                'middle_name' => trim($_POST['middle_name'] ?? ''),
                'last_name' => trim($_POST['last_name'] ?? ''),
                'suffix' => trim($_POST['suffix'] ?? ''),
                'username' => trim($_POST['username'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'current_profile_picture' => $user['profile_picture'] ?? '/images/default-profile.png'
            ];
            if (empty($data['first_name']) || empty($data['last_name']) || empty($data['username']) || empty($data['email'])) {
                throw new Exception("Required fields are missing");
            }
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format");
            }
            $file = $_FILES['profile_picture'] ?? null;
            $deanService->updateProfile($userId, $data, $file);
            $_SESSION['user'] = $deanService->getUserProfile($userId); // Refresh session
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Profile updated successfully'];
        } elseif (isset($_POST['change_password'])) {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                throw new Exception("All password fields are required");
            }
            if ($newPassword !== $confirmPassword) {
                throw new Exception("New passwords do not match");
            }
            if (strlen($newPassword) < 8) {
                throw new Exception("New password must be at least 8 characters");
            }
            $deanService->changePassword($userId, $currentPassword, $newPassword);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Password changed successfully'];
        }
        header('Location: /dean/profile');
        exit;
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
    }
}

// For sidebar
$currentUri = $_SERVER['REQUEST_URI'];
$pendingRequests = $deanService->getPendingFacultyRequests($collegeId);
$pendingCount = !empty($pendingRequests) && isset($pendingRequests[0]['count']) ? (int)$pendingRequests[0]['count'] : 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | PRMSU</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'prmsu-gold': '#EFBB0F',
                        'prmsu-gold-light': '#F9F3E5',
                        'prmsu-gold-dark': '#D4A013',
                        'prmsu-gray-dark': '#333333',
                        'prmsu-gray': '#666666',
                        'prmsu-gray-light': '#F5F5F5',
                    },
                    fontFamily: {
                        sans: ['Inter', 'Segoe UI', 'sans-serif'],
                    },
                    boxShadow: {
                        'card': '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
                        'input': '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #BDBDBD;
            border-radius: 8px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #EFBB0F;
        }

        /* Form focus effects */
        .form-input:focus {
            border-color: #EFBB0F;
            box-shadow: 0 0 0 3px rgba(239, 187, 15, 0.2);
        }

        /* Profile picture hover effect */
        .profile-picture-container:hover .profile-picture-overlay {
            opacity: 1;
        }

        /* Transition effects */
        .transition-all {
            transition: all 0.3s ease;
        }
    </style>
</head>

<body class="bg-gray-50 font-sans">
    <?php include __DIR__ . '/../partials/dean/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden md:ml-64">
        <?php include __DIR__ . '/../partials/dean/header.php'; ?>

        <main class="container mx-auto py-8 px-4 md:px-6">
            <div class="flex flex-col md:flex-row gap-8">
                <!-- Left column with profile picture and user info -->
                <div class="w-full md:w-1/3">
                    <div class="bg-white rounded-xl shadow-card p-6 mb-6">
                        <div class="flex flex-col items-center">
                            <div class="relative profile-picture-container group mb-4">
                                <div class="w-32 h-32 rounded-full overflow-hidden border-4 border-prmsu-gold-light">
                                    <img src="/images/default-profile.png" alt="Profile Picture" class="w-full h-full object-cover">
                                </div>
                                <div class="absolute inset-0 bg-black bg-opacity-50 rounded-full flex items-center justify-center opacity-0 transition-opacity duration-300 profile-picture-overlay cursor-pointer">
                                    <i class="fas fa-camera text-white text-xl"></i>
                                </div>
                                <input type="file" id="profile_picture_trigger" class="hidden" accept="image/*">
                            </div>

                            <h3 class="text-xl font-bold text-gray-800"><?= $userName ?></h3>
                            <p class="text-prmsu-gray mt-1 text-sm"><?= $college_name ?></p>
                            <p class="text-prmsu-gray text-sm"><?= $department_name ?></p>

                            <div class="mt-6 w-full">
                                <div class="flex items-center py-3 border-b border-gray-100">
                                    <div class="w-10 flex-shrink-0 text-prmsu-gold">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="ml-3 text-sm text-gray-700 truncate"><?= $email ?></div>
                                </div>
                                <div class="flex items-center py-3 border-b border-gray-100">
                                    <div class="w-10 flex-shrink-0 text-prmsu-gold">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="ml-3 text-sm text-gray-700"><?= $username ?></div>
                                </div>
                                <div class="flex items-center py-3 border-b border-gray-100">
                                    <div class="w-10 flex-shrink-0 text-prmsu-gold">
                                        <i class="fas fa-phone"></i>
                                    </div>
                                    <div class="ml-3 text-sm text-gray-700"><?= $phone ?: 'Not provided' ?></div>
                                </div>
                                <div class="flex items-center py-3">
                                    <div class="w-10 flex-shrink-0 text-prmsu-gold">
                                        <i class="fas fa-id-card"></i>
                                    </div>
                                    <div class="ml-3 text-sm text-gray-700"><?= $employee_id ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right column with forms -->
                <div class="w-full md:w-2/3">
                    <!-- Flash Messages -->
                    <?php if ($flash): ?>
                        <div class="<?= $flash['type'] === 'success' ? 'bg-green-50 text-green-800 border-green-200' : 'bg-red-50 text-red-800 border-red-200' ?> border rounded-lg p-4 mb-6 flex items-start">
                            <div class="flex-shrink-0 mt-0.5">
                                <i class="fas <?= $flash['type'] === 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500' ?>"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium"><?= htmlspecialchars($flash['message']) ?></p>
                            </div>
                            <button class="ml-auto flex-shrink-0 text-gray-400 hover:text-gray-500 focus:outline-none" id="dismiss-alert">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Tabs for Profile and Password -->
                    <div class="bg-white rounded-xl shadow-card overflow-hidden">
                        <div class="flex border-b border-gray-200">
                            <button id="tab-profile" class="flex-1 text-center py-4 px-4 font-medium text-prmsu-gold border-b-2 border-prmsu-gold">
                                <i class="fas fa-user-edit mr-2"></i>Personal Information
                            </button>
                            <button id="tab-password" class="flex-1 text-center py-4 px-4 font-medium text-gray-500 hover:text-gray-800 transition-colors">
                                <i class="fas fa-key mr-2"></i>Change Password
                            </button>
                        </div>

                        <!-- Profile Form -->
                        <div id="form-profile" class="p-6">
                            <form action="/dean/profile" method="POST" enctype="multipart/form-data" id="profile-form">
                                <input type="hidden" name="_method" value="PATCH">

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                        <input type="text" name="first_name" id="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none form-input" required>
                                    </div>
                                    <div>
                                        <label for="middle_name" class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                                        <input type="text" name="middle_name" id="middle_name" value="<?= htmlspecialchars($user['middle_name'] ?? '') ?>" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none form-input">
                                    </div>
                                    <div>
                                        <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                        <input type="text" name="last_name" id="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none form-input" required>
                                    </div>
                                    <div>
                                        <label for="suffix" class="block text-sm font-medium text-gray-700 mb-1">Suffix</label>
                                        <input type="text" name="suffix" id="suffix" value="<?= htmlspecialchars($user['suffix'] ?? '') ?>" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none form-input">
                                    </div>
                                    <div>
                                        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                                        <input type="text" name="username" id="username" value="<?= $username ?>" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none form-input" required>
                                    </div>
                                    <div>
                                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-envelope text-gray-400"></i>
                                            </div>
                                            <input type="email" name="email" id="email" value="<?= $email ?>" class="w-full pl-10 px-4 py-2 rounded-lg border border-gray-300 focus:outline-none form-input" required>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-phone text-gray-400"></i>
                                            </div>
                                            <input type="tel" name="phone" id="phone" value="<?= $phone ?>" class="w-full pl-10 px-4 py-2 rounded-lg border border-gray-300 focus:outline-none form-input">
                                        </div>
                                    </div>
                                    <div>
                                        <label for="employee_id" class="block text-sm font-medium text-gray-700 mb-1">Employee ID</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-id-card text-gray-400"></i>
                                            </div>
                                            <input type="text" name="employee_id" id="employee_id" value="<?= $employee_id ?>" class="w-full pl-10 px-4 py-2 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none form-input" readonly>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <label for="profile_picture" class="block text-sm font-medium text-gray-700 mb-1">Profile Picture</label>
                                    <div class="mt-1 flex items-center">
                                        <input type="file" name="profile_picture" id="profile_picture" accept="image/*" class="hidden">
                                        <label for="profile_picture" class="cursor-pointer flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                            <i class="fas fa-upload mr-2"></i>
                                            Choose File
                                        </label>
                                        <span id="file-name" class="ml-3 text-sm text-gray-500">No file chosen</span>
                                    </div>
                                </div>

                                <div class="mt-8 flex justify-end">
                                    <button type="submit" name="update_profile" class="inline-flex justify-center py-2 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-prmsu-gold hover:bg-prmsu-gold-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-prmsu-gold transition-colors">
                                        <i class="fas fa-save mr-2"></i> Update Profile
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Password Form -->
                        <div id="form-password" class="p-6 hidden">
                            <form action="/dean/profile" method="POST" id="password-form">
                                <input type="hidden" name="_method" value="PATCH">

                                <div class="mb-6">
                                    <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-lock text-gray-400"></i>
                                        </div>
                                        <input type="password" name="current_password" id="current_password" class="w-full pl-10 px-4 py-2 rounded-lg border border-gray-300 focus:outline-none form-input" required>
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                            <button type="button" class="toggle-password text-gray-400 hover:text-gray-600 focus:outline-none" data-target="current_password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-6">
                                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-key text-gray-400"></i>
                                        </div>
                                        <input type="password" name="new_password" id="new_password" class="w-full pl-10 px-4 py-2 rounded-lg border border-gray-300 focus:outline-none form-input" required>
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                            <button type="button" class="toggle-password text-gray-400 hover:text-gray-600 focus:outline-none" data-target="new_password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mt-1">
                                        <div class="text-xs text-gray-500">Password must be at least 8 characters</div>
                                        <div id="password-strength" class="mt-2">
                                            <div class="w-full bg-gray-200 rounded-full h-1.5">
                                                <div class="bg-gray-400 h-1.5 rounded-full" style="width: 0%"></div>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">Password strength: <span id="strength-text">Too weak</span></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-6">
                                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-check-circle text-gray-400"></i>
                                        </div>
                                        <input type="password" name="confirm_password" id="confirm_password" class="w-full pl-10 px-4 py-2 rounded-lg border border-gray-300 focus:outline-none form-input" required>
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                            <button type="button" class="toggle-password text-gray-400 hover:text-gray-600 focus:outline-none" data-target="confirm_password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div id="password-match" class="text-xs text-gray-500 mt-1 hidden">
                                        <i class="fas fa-check-circle text-green-500 mr-1"></i> Passwords match
                                    </div>
                                    <div id="password-mismatch" class="text-xs text-red-500 mt-1 hidden">
                                        <i class="fas fa-times-circle mr-1"></i> Passwords do not match
                                    </div>
                                </div>

                                <div class="mt-8 flex justify-end">
                                    <button type="submit" name="change_password" class="inline-flex justify-center py-2 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-prmsu-gold hover:bg-prmsu-gold-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-prmsu-gold transition-colors">
                                        <i class="fas fa-key mr-2"></i> Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <footer class="bg-white py-6 mt-8">
            <div class="container mx-auto px-6">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <div class="text-sm text-gray-600">
                        © 2025 President Ramon Magsaysay State University. All rights reserved.
                    </div>
                    <div class="mt-4 md:mt-0">
                        <a href="#" class="text-gray-500 hover:text-prmsu-gold mx-2 text-sm">Terms of Service</a>
                        <a href="#" class="text-gray-500 hover:text-prmsu-gold mx-2 text-sm">Privacy Policy</a>
                        <a href="#" class="text-gray-500 hover:text-prmsu-gold mx-2 text-sm">Contact Support</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle
            const sidebar = document.querySelector('.sidebar');
            const sidebarToggle = document.querySelector('#sidebar-toggle');
            if (sidebar && sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('-translate-x-full');
                });
            }

            // Tab switching
            const tabProfile = document.getElementById('tab-profile');
            const tabPassword = document.getElementById('tab-password');
            const formProfile = document.getElementById('form-profile');
            const formPassword = document.getElementById('form-password');

            tabProfile.addEventListener('click', function() {
                formProfile.classList.remove('hidden');
                formPassword.classList.add('hidden');
                tabProfile.classList.add('text-prmsu-gold', 'border-b-2', 'border-prmsu-gold');
                tabProfile.classList.remove('text-gray-500');
                tabPassword.classList.remove('text-prmsu-gold', 'border-b-2', 'border-prmsu-gold');
                tabPassword.classList.add('text-gray-500');
            });

            tabPassword.addEventListener('click', function() {
                formProfile.classList.add('hidden');
                formPassword.classList.remove('hidden');
                tabPassword.classList.add('text-prmsu-gold', 'border-b-2', 'border-prmsu-gold');
                tabPassword.classList.remove('text-gray-500');
                tabProfile.classList.remove('text-prmsu-gold', 'border-b-2', 'border-prmsu-gold');
                tabProfile.classList.add('text-gray-500');
            });

            // Dismiss flash alert
            const dismissAlert = document.getElementById('dismiss-alert');
            if (dismissAlert) {
                dismissAlert.addEventListener('click', function() {
                    this.parentElement.remove();
                });
            }

            // File input display
            const profilePicture = document.getElementById('profile_picture');
            const fileName = document.getElementById('file-name');
            if (profilePicture && fileName) {
                profilePicture.addEventListener('change', function(e) {
                    if (this.files && this.files[0]) {
                        fileName.textContent = this.files[0].name;

                        // Preview image
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const profileImg = document.querySelector('.profile-picture-container img');
                            if (profileImg) {
                                profileImg.src = e.target.result;
                            }
                        };
                        reader.readAsDataURL(this.files[0]);
                    } else {
                        fileName.textContent = 'No file chosen';
                    }
                });
            }

            // Profile picture click to upload
            const profilePicContainer = document.querySelector('.profile-picture-container');
            const profilePictureTrigger = document.getElementById('profile_picture_trigger');

            if (profilePicContainer && profilePictureTrigger) {
                profilePicContainer.addEventListener('click', function() {
                    profilePictureTrigger.click();
                });

                profilePictureTrigger.addEventListener('change', function(e) {
                    if (this.files && this.files[0]) {
                        // Set the file to the actual form input
                        const fileTransfer = new DataTransfer();
                        fileTransfer.items.add(this.files[0]);
                        profilePicture.files = fileTransfer.files;

                        // Trigger change event on the actual file input
                        const event = new Event('change', {
                            bubbles: true
                        });
                        profilePicture.dispatchEvent(event);
                    }
                });
            }

            // Toggle password visibility
            const togglePasswordButtons = document.querySelectorAll('.toggle-password');
            togglePasswordButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const input = document.getElementById(targetId);
                    const icon = this.querySelector('i');

                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        input.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            });

            // Password strength meter
            const newPassword = document.getElementById('new_password');
            const strengthBar = document.querySelector('#password-strength .bg-gray-400');
            const strengthText = document.getElementById('strength-text');

            if (newPassword && strengthBar && strengthText) {
                newPassword.addEventListener('input', function() {
                    const value = this.value;
                    let strength = 0;

                    if (value.length >= 8) strength += 20;
                    if (value.match(/[A-Z]/)) strength += 20;
                    if (value.match(/[a-z]/)) strength += 20;
                    if (value.match(/[0-9]/)) strength += 20;
                    if (value.match(/[^A-Za-z0-9]/)) strength += 20;

                    strengthBar.style.width = strength + '%';

                    if (strength < 40) {
                        strengthBar.className = 'bg-red-500 h-1.5 rounded-full';
                        strengthText.textContent = 'Too weak';
                    } else if (strength < 80) {
                        strengthBar.className = 'bg-yellow-500 h-1.5 rounded-full';
                        strengthText.textContent = 'Moderate';
                    } else {
                        strengthBar.className = 'bg-green-500 h-1.5 rounded-full';
                        strengthText.textContent = 'Strong';
                    }
                });
            }

            // Password match validation
            const confirmPassword = document.getElementById('confirm_password');
            const passwordMatch = document.getElementById('password-match');
            const passwordMismatch = document.getElementById('password-mismatch');
            if (confirmPassword && passwordMatch && passwordMismatch) {
                confirmPassword.addEventListener('input', function() {
                    if (this.value === newPassword.value) {
                        passwordMatch.classList.remove('hidden');
                        passwordMismatch.classList.add('hidden');
                    } else {
                        passwordMatch.classList.add('hidden');
                        passwordMismatch.classList.remove('hidden');
                    }
                });
            }
        });
    </script>
</body>

</html>