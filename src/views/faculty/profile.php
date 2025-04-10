<?php
// views/faculty/profile.php
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | PRMSU Faculty</title>
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
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
        }
        
        .profile-card {
            background: linear-gradient(135deg, rgba(255,215,0,0.1) 0%, rgba(0,86,179,0.05) 100%);
            border-left: 4px solid var(--prmsu-blue);
        }
        
        .specialization-card {
            transition: all 0.3s ease;
            border-left: 3px solid var(--prmsu-gold);
        }
        
        .specialization-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary {
            background-color: var(--prmsu-blue);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: #004494;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background-color: var(--prmsu-gold);
            color: #333;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background-color: #e6c200;
            transform: translateY(-1px);
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
                        <h1 class="text-3xl font-bold text-gray-900">Faculty Profile</h1>
                        <p class="text-gray-600 mt-2">Manage your personal information and specializations</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                            <i class="fas fa-user-tie mr-2"></i> <?= htmlspecialchars($faculty['position']) ?>
                        </span>
                    </div>
                </div>

                <?php if (isset($_SESSION['flash'])): ?>
                    <div class="mb-6 p-4 rounded-lg <?= $_SESSION['flash']['type'] === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?> flex items-start">
                        <div class="flex-shrink-0">
                            <?= $_SESSION['flash']['type'] === 'success' ? 
                                '<i class="fas fa-check-circle text-green-500 mr-3 mt-1"></i>' : 
                                '<i class="fas fa-exclamation-circle text-red-500 mr-3 mt-1"></i>' ?>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium"><?= htmlspecialchars($_SESSION['flash']['message']) ?></p>
                        </div>
                        <button type="button" class="ml-auto -mx-1.5 -my-1.5 p-1.5 rounded-lg inline-flex items-center justify-center h-8 w-8 <?= $_SESSION['flash']['type'] === 'success' ? 'text-green-500 hover:bg-green-100' : 'text-red-500 hover:bg-red-100' ?>" onclick="this.parentElement.remove()">
                            <span class="sr-only">Dismiss</span>
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <?php unset($_SESSION['flash']); ?>
                <?php endif; ?>

                <!-- Profile Section -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Profile Card -->
                    <div class="lg:col-span-2 profile-card rounded-xl shadow-sm p-6">
                        <div class="flex items-center mb-6">
                            <div class="flex-shrink-0 h-16 w-16 rounded-full bg-blue-600 flex items-center justify-center text-white text-2xl font-bold">
                                <?= strtoupper(substr($faculty['first_name'], 0, 1) . strtoupper(substr($faculty['last_name'], 0, 1))) ?>
                            </div>
                            <div class="ml-4">
                                <h2 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']) ?></h2>
                                <p class="text-gray-600"><?= htmlspecialchars($department['department_name'] ?? 'N/A') ?></p>
                            </div>
                        </div>

                        <form method="POST" action="/faculty/profile" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-user text-gray-400"></i>
                                        </div>
                                        <input type="text" name="first_name" id="first_name" value="<?= htmlspecialchars($faculty['first_name']) ?>" class="pl-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-2" required>
                                    </div>
                                </div>
                                <div>
                                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-user text-gray-400"></i>
                                        </div>
                                        <input type="text" name="last_name" id="last_name" value="<?= htmlspecialchars($faculty['last_name']) ?>" class="pl-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-2" required>
                                    </div>
                                </div>
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-envelope text-gray-400"></i>
                                        </div>
                                        <input type="email" name="email" id="email" value="<?= htmlspecialchars($faculty['email']) ?>" class="pl-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-2" required>
                                    </div>
                                </div>
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-phone text-gray-400"></i>
                                        </div>
                                        <input type="text" name="phone" id="phone" value="<?= htmlspecialchars($faculty['phone'] ?? '') ?>" class="pl-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-2" placeholder="+63 912 345 6789">
                                    </div>
                                </div>
                                <div>
                                    <label for="position" class="block text-sm font-medium text-gray-700 mb-1">Position</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-briefcase text-gray-400"></i>
                                        </div>
                                        <input type="text" id="position" value="<?= htmlspecialchars($faculty['position']) ?>" class="pl-10 block w-full rounded-md border-gray-300 bg-gray-50 sm:text-sm py-2" readonly>
                                    </div>
                                </div>
                                <div>
                                    <label for="employment_type" class="block text-sm font-medium text-gray-700 mb-1">Employment Type</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-id-card text-gray-400"></i>
                                        </div>
                                        <input type="text" id="employment_type" value="<?= htmlspecialchars($faculty['employment_type']) ?>" class="pl-10 block w-full rounded-md border-gray-300 bg-gray-50 sm:text-sm py-2" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="pt-4">
                                <button type="submit" name="update_profile" class="btn-primary inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-save mr-2"></i> Update Profile
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Quick Stats Card -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-chart-pie text-blue-500 mr-2"></i> Quick Stats
                        </h3>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="p-2 rounded-full bg-blue-100 text-blue-600 mr-3">
                                        <i class="fas fa-book-open"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Courses Assigned</p>
                                        <p class="text-lg font-semibold text-gray-900">12</p>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="p-2 rounded-full bg-yellow-100 text-yellow-600 mr-3">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Teaching Hours</p>
                                        <p class="text-lg font-semibold text-gray-900">18 hrs/wk</p>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="p-2 rounded-full bg-green-100 text-green-600 mr-3">
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Specializations</p>
                                        <p class="text-lg font-semibold text-gray-900"><?= count($specializations) ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 pt-4 border-t border-gray-200">
                            <a href="#" class="text-sm font-medium text-blue-600 hover:text-blue-500 flex items-center">
                                <i class="fas fa-calendar-alt mr-2"></i> View Teaching Schedule
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Specializations Section -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                            <i class="fas fa-graduation-cap text-blue-500 mr-2"></i> Your Specializations
                        </h2>
                        <button type="button" onclick="document.getElementById('addSpecializationModal').classList.remove('hidden')" class="btn-secondary inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                            <i class="fas fa-plus mr-2"></i> Add Specialization
                        </button>
                    </div>

                    <?php if (empty($specializations)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-graduation-cap text-gray-300 text-5xl mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900">No specializations yet</h3>
                            <p class="mt-1 text-sm text-gray-500">Add your areas of expertise to help with course assignments.</p>
                            <div class="mt-6">
                                <button type="button" onclick="document.getElementById('addSpecializationModal').classList.remove('hidden')" class="btn-secondary inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                                    <i class="fas fa-plus mr-2"></i> Add Your First Specialization
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($specializations as $spec): ?>
                                <div class="specialization-card bg-white rounded-lg border border-gray-200 overflow-hidden">
                                    <div class="p-4">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 h-10 w-10 rounded-full bg-yellow-100 flex items-center justify-center text-yellow-600">
                                                <i class="fas fa-star"></i>
                                            </div>
                                            <div class="ml-4">
                                                <h3 class="text-lg font-medium text-gray-900"><?= htmlspecialchars($spec['subject_name']) ?></h3>
                                                <p class="text-sm text-gray-500 mt-1">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= 
                                                        $spec['expertise_level'] === 'Expert' ? 'bg-green-100 text-green-800' : 
                                                        ($spec['expertise_level'] === 'Intermediate' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800')
                                                    ?>">
                                                        <?= htmlspecialchars($spec['expertise_level']) ?>
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 px-4 py-3 flex justify-end space-x-2">
                                        <form method="POST" action="/faculty/profile" class="inline">
                                            <input type="hidden" name="specialization_id" value="<?= $spec['specialization_id'] ?>">
                                            <button type="submit" name="edit_specialization" class="inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                <i class="fas fa-edit mr-1"></i> Edit
                                            </button>
                                        </form>
                                        <form method="POST" action="/faculty/profile" class="inline">
                                            <input type="hidden" name="specialization_id" value="<?= $spec['specialization_id'] ?>">
                                            <button type="submit" name="delete_specialization" onclick="return confirm('Are you sure you want to delete this specialization?')" class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                                <i class="fas fa-trash mr-1"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Specialization Modal -->
    <div id="addSpecializationModal" class="hidden fixed inset-0 overflow-y-auto z-50">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fas fa-graduation-cap text-blue-600"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Add New Specialization</h3>
                            <div class="mt-4">
                                <form method="POST" action="/faculty/profile" id="specializationForm">
                                    <div class="space-y-4">
                                        <div>
                                            <label for="modal_subject_name" class="block text-sm font-medium text-gray-700">Subject Name</label>
                                            <input type="text" name="subject_name" id="modal_subject_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-2" required>
                                        </div>
                                        <div>
                                            <label for="modal_expertise_level" class="block text-sm font-medium text-gray-700">Expertise Level</label>
                                            <select name="expertise_level" id="modal_expertise_level" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm py-2">
                                                <option value="Beginner">Beginner</option>
                                                <option value="Intermediate" selected>Intermediate</option>
                                                <option value="Expert">Expert</option>
                                            </select>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" form="specializationForm" name="add_specialization" class="btn-primary w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 text-base font-medium text-white sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-plus mr-2"></i> Add Specialization
                    </button>
                    <button type="button" onclick="document.getElementById('addSpecializationModal').classList.add('hidden')" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-times mr-2"></i> Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Close modal when clicking outside
        document.getElementById('addSpecializationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    </script>
</body>

</html>