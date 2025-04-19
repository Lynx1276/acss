<?php
require_once __DIR__ . '/../../config/Database.php';
session_start();
use App\config\Database;
$db = (new Database())->connect();

// Initialize form data from session if available
$formData = $_SESSION['form_data'] ?? [];

// Get all colleges and roles
$colleges = $db->query("SELECT * FROM colleges ORDER BY college_name")->fetchAll();
$roles = $db->query("SELECT * FROM roles WHERE role_id IN (1, 2, 3, 4, 5, 6) ORDER BY role_id")->fetchAll();

// Get departments if college was pre-selected
$selectedCollegeId = $formData['college_id'] ?? null;
$selectedRoleId = $formData['role_id'] ?? null;
$filteredDepartments = [];
if ($selectedCollegeId) {
    $stmt = $db->prepare("SELECT * FROM departments WHERE college_id = ? ORDER BY department_name");
    $stmt->execute([$selectedCollegeId]);
    $filteredDepartments = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PRMSU - Register</title>
    <link href="/assets/css/app.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        gold: {
                            50: '#FFF9E5',
                            100: '#FFF3CC',
                            200: '#FFE799',
                            300: '#FFDB66',
                            400: '#FFCF33',
                            500: '#FFC300',
                            600: '#CC9C00',
                            700: '#997500',
                            800: '#664E00',
                            900: '#332700',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .hidden {
            display: none;
        }

        /* Custom focus styles */
        input:focus,
        select:focus {
            --tw-ring-color: #FFC300;
            --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
            --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
            box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
            border-color: #FFC300;
        }
    </style>
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen py-8">
    <div class="w-full max-w-3xl px-4"> <!-- Increased max width -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Header with gold accent -->
            <div class="bg-gradient-to-r from-gold-500 to-gold-400 p-6 text-center">
                <h1 class="text-2xl font-bold text-gray-900">Create Account</h1>
                <p class="text-gray-800">Join PRMSU Scheduling System</p>
            </div>

            <div class="p-8">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                        <?= htmlspecialchars($_SESSION['success']) ?>
                        <?php unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                        <?= htmlspecialchars($_SESSION['error']) ?>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <form id="registrationForm" action="/auth/register" method="POST" class="space-y-6" onsubmit="return validateForm()">
                    <input type="hidden" name="register_submit" value="1">

                    <!-- Form sections with separator -->
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white text-gray-500 font-medium">PERSONAL INFORMATION</span>
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <div class="grid grid-cols-4 gap-4">
                        <div class="col-span-1">
                            <label for="employee_id" class="block text-sm font-medium text-gray-700">Employee ID*</label>
                            <input type="text" id="employee_id" name="employee_id" required
                                value="<?= htmlspecialchars($formData['employee_id'] ?? '') ?>"
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2">
                        </div>
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700">First Name*</label>
                            <input type="text" id="first_name" name="first_name" required
                                value="<?= htmlspecialchars($formData['first_name'] ?? '') ?>"
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2">
                        </div>
                        <div>
                            <label for="middle_name" class="block text-sm font-medium text-gray-700">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name"
                                value="<?= htmlspecialchars($formData['middle_name'] ?? '') ?>"
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2">
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name*</label>
                            <input type="text" id="last_name" name="last_name" required
                                value="<?= htmlspecialchars($formData['last_name'] ?? '') ?>"
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2">
                        </div>
                    </div>

                    <div>
                        <label for="suffix" class="block text-sm font-medium text-gray-700">Suffix</label>
                        <select id="suffix" name="suffix" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2">
                            <option value="">None</option>
                            <option value="Jr" <?= ($formData['suffix'] ?? '') === 'Jr' ? 'selected' : '' ?>>Jr</option>
                            <option value="Sr" <?= ($formData['suffix'] ?? '') === 'Sr' ? 'selected' : '' ?>>Sr</option>
                            <option value="II" <?= ($formData['suffix'] ?? '') === 'II' ? 'selected' : '' ?>>II</option>
                            <option value="III" <?= ($formData['suffix'] ?? '') === 'III' ? 'selected' : '' ?>>III</option>
                            <option value="IV" <?= ($formData['suffix'] ?? '') === 'IV' ? 'selected' : '' ?>>IV</option>
                        </select>
                    </div>

                    <!-- Account Information -->
                    <div class="relative mt-8">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white text-gray-500 font-medium">ACCOUNT INFORMATION</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700">Username*</label>
                            <input type="text" id="username" name="username" required
                                value="<?= htmlspecialchars($formData['username'] ?? '') ?>"
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email*</label>
                            <input type="email" id="email" name="email" required
                                value="<?= htmlspecialchars($formData['email'] ?? '') ?>"
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">Password*</label>
                            <input type="password" id="password" name="password" required
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2">
                        </div>
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password*</label>
                            <input type="password" id="confirm_password" name="confirm_password" required
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2">
                        </div>
                    </div>

                    <!-- Role Selection -->
                    <div class="relative mt-8">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white text-gray-500 font-medium">POSITION DETAILS</span>
                        </div>
                    </div>

                    <div>
                        <label for="role_id" class="block text-sm font-medium text-gray-700">Role*</label>
                        <select id="role_id" name="role_id" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2">
                            <option value="">Select your role</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['role_id'] ?>" <?= ($formData['role_id'] ?? '') == $role['role_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($role['role_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Faculty-specific fields -->
                    <div id="faculty_fields" class="hidden space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="academic_rank" class="block text-sm font-medium text-gray-700">Academic Rank*</label>
                                <select id="academic_rank" name="academic_rank"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2">
                                    <option value="">Select rank</option>
                                    <option value="Instructor" <?= ($formData['academic_rank'] ?? '') === 'Instructor' ? 'selected' : '' ?>>Instructor</option>
                                    <option value="Assistant Professor" <?= ($formData['academic_rank'] ?? '') === 'Assistant Professor' ? 'selected' : '' ?>>Assistant Professor</option>
                                    <option value="Associate Professor" <?= ($formData['academic_rank'] ?? '') === 'Associate Professor' ? 'selected' : '' ?>>Associate Professor</option>
                                    <option value="Professor" <?= ($formData['academic_rank'] ?? '') === 'Professor' ? 'selected' : '' ?>>Professor</option>
                                    <option value="Distinguished Professor" <?= ($formData['academic_rank'] ?? '') === 'Distinguished Professor' ? 'selected' : '' ?>>Distinguished Professor</option>
                                </select>
                            </div>
                            <div>
                                <label for="classification" class="block text-sm font-medium text-gray-700">Classification*</label>
                                <select id="classification" name="classification"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2">
                                    <option value="">Select classification</option>
                                    <option value="TL" <?= ($formData['classification'] ?? '') === 'TL' ? 'selected' : '' ?>>TL</option>
                                    <option value="VSL" <?= ($formData['classification'] ?? '') === 'VSL' ? 'selected' : '' ?>>VSL</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="employment_type" class="block text-sm font-medium text-gray-700">Employment Type*</label>
                                <select id="employment_type" name="employment_type"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2">
                                    <option value="">Select type</option>
                                    <option value="Regular" <?= ($formData['employment_type'] ?? '') === 'Regular' ? 'selected' : '' ?>>Regular</option>
                                    <option value="Contractual" <?= ($formData['employment_type'] ?? '') === 'Contractual' ? 'selected' : '' ?>>Contractual</option>
                                    <option value="Part-time" <?= ($formData['employment_type'] ?? '') === 'Part-time' ? 'selected' : '' ?>>Part-time</option>
                                </select>
                            </div>
                            <div>
                                <label for="primary_program_id" class="block text-sm font-medium text-gray-700">Primary Program</label>
                                <select id="primary_program_id" name="primary_program_id"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2">
                                    <option value="">Select program</option>
                                    <!-- Will be populated via AJAX based on department -->
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- College and Department Selection -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="college_id" class="block text-sm font-medium text-gray-700">College*</label>
                            <select id="college_id" name="college_id" required
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2">
                                <option value="">Select college</option>
                                <?php foreach ($colleges as $college): ?>
                                    <option value="<?= $college['college_id'] ?>" <?= ($selectedCollegeId ?? '') == $college['college_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($college['college_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="department_id" class="block text-sm font-medium text-gray-700">Department*</label>
                            <select id="department_id" name="department_id" required
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2">
                                <option value="">Select department</option>
                                <?php foreach ($filteredDepartments as $dept): ?>
                                    <option value="<?= $dept['department_id'] ?>" <?= ($formData['department_id'] ?? '') == $dept['department_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-gray-900 bg-gold-400 hover:bg-gold-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gold-500 transition">
                            Create Account
                        </button>
                    </div>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600">
                        Already have an account? <a href="/login" class="font-medium text-gold-600 hover:text-gold-700">Sign in</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function validateForm() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const roleId = document.getElementById('role_id').value;
            const departmentId = document.getElementById('department_id').value;
            const employeeId = document.getElementById('employee_id').value;

            // Basic validation
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return false;
            }

            if (!employeeId) {
                alert('Employee ID is required');
                return false;
            }

            // Faculty-specific validation
            if (roleId === '6') {
                const academicRank = document.getElementById('academic_rank').value;
                const classification = document.getElementById('classification').value;
                const employmentType = document.getElementById('employment_type').value;

                if (!academicRank || !classification || !employmentType) {
                    alert('Please fill all faculty-specific fields');
                    return false;
                }
            }

            // Department validation for all roles
            if (!departmentId) {
                alert('Please select a department');
                return false;
            }

            return true;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('role_id');
            const facultyFields = document.getElementById('faculty_fields');
            const collegeSelect = document.getElementById('college_id');
            const deptSelect = document.getElementById('department_id');
            const primaryProgramSelect = document.getElementById('primary_program_id');

            function toggleFields() {
                // Show faculty fields only for Faculty (role_id = 6)
                facultyFields.classList.toggle('hidden', roleSelect.value !== '6');

                // Make faculty specific fields required only when faculty role is selected
                const facultyRequiredFields = document.querySelectorAll('#faculty_fields select');
                facultyRequiredFields.forEach(field => {
                    field.required = roleSelect.value === '6';
                });
            }

            // Load departments when college changes
            collegeSelect.addEventListener('change', function() {
                const collegeId = this.value;
                if (!collegeId) {
                    deptSelect.innerHTML = '<option value="">Select department</option>';
                    return;
                }

                deptSelect.innerHTML = '<option value="">Loading departments...</option>';
                deptSelect.disabled = true;

                fetch(`/api/departments?college_id=${collegeId}`)
                    .then(response => response.json())
                    .then(data => {
                        deptSelect.disabled = false;
                        if (data.success && data.data.length) {
                            deptSelect.innerHTML = '<option value="">Select department</option>';
                            data.data.forEach(dept => {
                                const option = document.createElement('option');
                                option.value = dept.department_id;
                                option.textContent = dept.department_name;

                                const savedDept = '<?= json_encode($formData['department_id'] ?? null) ?>';
                                if (dept.department_id == JSON.parse(savedDept)) {
                                    option.selected = true;
                                }
                                deptSelect.appendChild(option);
                            });
                        } else {
                            deptSelect.innerHTML = '<option value="">No departments available</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error loading departments:', error);
                        deptSelect.innerHTML = '<option value="">Error loading departments</option>';
                        deptSelect.disabled = false;
                    });
            });

            // Load programs when department changes (for faculty)
            deptSelect.addEventListener('change', function() {
                const deptId = this.value;
                if (!deptId || roleSelect.value !== '6') {
                    primaryProgramSelect.innerHTML = '<option value="">Select program</option>';
                    return;
                }

                primaryProgramSelect.innerHTML = '<option value="">Loading programs...</option>';
                primaryProgramSelect.disabled = true;

                fetch(`/api/programs?department_id=${deptId}`)
                    .then(response => response.json())
                    .then(data => {
                        primaryProgramSelect.disabled = false;
                        if (data.success && data.data.length) {
                            primaryProgramSelect.innerHTML = '<option value="">Select program</option>';
                            data.data.forEach(program => {
                                const option = document.createElement('option');
                                option.value = program.program_id;
                                option.textContent = program.program_name;
                                primaryProgramSelect.appendChild(option);
                            });
                        } else {
                            primaryProgramSelect.innerHTML = '<option value="">No programs available</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error loading programs:', error);
                        primaryProgramSelect.innerHTML = '<option value="">Error loading programs</option>';
                        primaryProgramSelect.disabled = false;
                    });
            });

            toggleFields();
            roleSelect.addEventListener('change', toggleFields);

            // Trigger initial load if college is pre-selected
            if (collegeSelect.value) {
                collegeSelect.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>

</html>