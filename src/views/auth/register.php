<?php
require_once __DIR__ . '/../../config/Database.php';
$db = (new Database())->connect();

// Initialize form data from session if available
$formData = $_SESSION['form_data'] ?? [];

// Get all colleges and roles
$colleges = $db->query("SELECT * FROM colleges ORDER BY college_name")->fetchAll();
$roles = $db->query("SELECT * FROM roles WHERE role_id IN (1, 2, 3, 4, 5, 6) ORDER BY role_id")->fetchAll();

// Get departments if college was pre-selected
$selectedCollegeId = $formData['college_id'] ?? null;
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
    <style>
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-lg shadow-md p-8">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-800">Create Account</h1>
                <p class="text-gray-600">Join PRMSU Scheduling System</p>
            </div>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?= htmlspecialchars($_SESSION['success']) ?>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form id="registrationForm" action="/auth/register" method="POST" class="space-y-6" onsubmit="return validateForm()">
                <input type="hidden" name="register_submit" value="1">
                <!-- Personal Information -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                        <input type="text" id="first_name" name="first_name" required value="<?= htmlspecialchars($formData['first_name'] ?? '') ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                        <input type="text" id="last_name" name="last_name" required value="<?= htmlspecialchars($formData['last_name'] ?? '') ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <!-- Account Information -->
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                    <input type="text" id="username" name="username" required value="<?= htmlspecialchars($formData['username'] ?? '') ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($formData['email'] ?? '') ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" id="password" name="password" required value="<?= htmlspecialchars($formData['password'] ?? '') ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required value="<?= htmlspecialchars($formData['confirm_password'] ?? '') ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <!-- Role Selection -->
                <div>
                    <label for="role_id" class="block text-sm font-medium text-gray-700">Role</label>
                    <select id="role_id" name="role_id" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
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
                    <div>
                        <label for="position" class="block text-sm font-medium text-gray-700">Position</label>
                        <select id="position" name="position" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="Instructor" <?= ($formData['position'] ?? '') === 'Instructor' ? 'selected' : '' ?>>Instructor</option>
                            <option value="Assistant Professor" <?= ($formData['position'] ?? '') === 'Assistant Professor' ? 'selected' : '' ?>>Assistant Professor</option>
                            <option value="Associate Professor" <?= ($formData['position'] ?? '') === 'Associate Professor' ? 'selected' : '' ?>>Associate Professor</option>
                            <option value="Professor" <?= ($formData['position'] ?? '') === 'Professor' ? 'selected' : '' ?>>Professor</option>
                        </select>
                    </div>
                    <div>
                        <label for="employment_type" class="block text-sm font-medium text-gray-700">Employment Type</label>
                        <select id="employment_type" name="employment_type" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="Regular" <?= ($formData['employment_type'] ?? '') === 'Regular' ? 'selected' : '' ?>>Regular</option>
                            <option value="Contractual" <?= ($formData['employment_type'] ?? '') === 'Contractual' ? 'selected' : '' ?>>Contractual</option>
                            <option value="Part-time" <?= ($formData['employment_type'] ?? '') === 'Part-time' ? 'selected' : '' ?>>Part-time</option>
                        </select>
                    </div>
                </div>
                <!-- College and Department Selection -->
                <div>
                    <label for="college_id" class="block text-sm font-medium text-gray-700">College</label>
                    <select id="college_id" name="college_id" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select college</option>
                        <?php foreach ($colleges as $college): ?>
                            <option value="<?= $college['college_id'] ?>" <?= ($selectedCollegeId ?? '') == $college['college_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($college['college_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="department_id" class="block text-sm font-medium text-gray-700">Department</label>
                    <select id="department_id" name="department_id" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select department</option>
                        <?php foreach ($filteredDepartments as $dept): ?>
                            <option value="<?= $dept['department_id'] ?>" <?= ($formData['department_id'] ?? '') == $dept['department_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['department_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Register
                    </button>
                </div>
            </form>
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Already have an account? <a href="/login" class="font-medium text-blue-600 hover:text-blue-500">Sign in</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        function validateForm() {
            const deptSelect = document.getElementById('department_id');
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (deptSelect.value === "") {
                alert("Please select a department");
                deptSelect.focus();
                return false;
            }
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return false;
            }
            return true;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('role_id');
            const facultyFields = document.getElementById('faculty_fields');
            const collegeSelect = document.getElementById('college_id');
            const deptSelect = document.getElementById('department_id');

            function toggleFacultyFields() {
                facultyFields.classList.toggle('hidden', roleSelect.value !== '6');
            }

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

            toggleFacultyFields();
            roleSelect.addEventListener('change', toggleFacultyFields);

            if (collegeSelect.value) {
                collegeSelect.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>

</html>