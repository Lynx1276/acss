<?php
require_once __DIR__ . '/../services/AuthService.php';

use App\config\Database;

class AuthController
{
    private $db;
    private $authService;

    public function __construct()
    {
        $this->db = (new Database())->connect();
        $this->authService = new AuthService();
    }

    public function showLogin()
    {
        if ($this->isLoggedIn()) {
            // Instead of redirecting, check role and show login if mismatch
            $roleId = $_SESSION['user']['role_id'] ?? null;
            if ($roleId === null) {
                error_log("showLogin: Invalid role_id, keeping at login");
            } else {
                $this->redirectBasedOnRole();
            }
        }
        require __DIR__ . '/../views/auth/login.php';
    }

    public function showRegister()
    {
        if ($this->isLoggedIn()) {
            $this->redirectBasedOnRole();
        }

        try {
            // Get colleges and roles for dropdowns
            $colleges = $this->db->query("SELECT * FROM colleges ORDER BY college_name")->fetchAll();
            $roles = $this->db->query("SELECT * FROM roles WHERE role_id IN (1,2,3,4,5,6) ORDER BY role_id")->fetchAll();

            // Get departments if college was selected previously
            $filteredDepartments = [];
            if (isset($_SESSION['form_data']['college_id'])) {
                $stmt = $this->db->prepare("SELECT * FROM departments WHERE college_id = ? ORDER BY department_name");
                $stmt->execute([$_SESSION['form_data']['college_id']]);
                $filteredDepartments = $stmt->fetchAll();
            }

            require __DIR__ . '/../views/auth/register.php';
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database error: " . $e->getMessage();
            header("Location: /register");
            exit();
        }
    }

    public function login($username, $password)
    {
        try {
            $username = trim($username);
            $password = trim($password);

            $stmt = $this->db->prepare("
            SELECT u.*, r.role_name, f.faculty_id 
            FROM users u
            JOIN roles r ON u.role_id = r.role_id 
            LEFT JOIN faculty f ON u.user_id = f.user_id
            WHERE username = ? AND is_active = 1
        ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if (!$user) {
                throw new Exception("Invalid username or password");
            }

            if (!password_verify($password, $user['password_hash'])) {
                throw new Exception("Invalid username or password");
            }

            // Successful login
            $_SESSION['user'] = [
                'user_id' => (int)$user['user_id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role_id' => (int)$user['role_id'],
                'role_name' => $user['role_name'],
                'department_id' => $user['department_id'] ? (int)$user['department_id'] : null, // Handle NULL
                'college_id' => (int)$user['college_id'],
                'faculty_id' => $user['faculty_id'] ? (int)$user['faculty_id'] : null
            ];
            error_log("Login successful for user: $username, session: " . json_encode($_SESSION['user']));

            $this->redirectBasedOnRole();
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $_SESSION['error'] = $e->getMessage();
            header('Location: /login');
            exit();
        }
    }


    private function setUserSession(array $user): void
    {
        $_SESSION['user'] = [
            'id' => (int)$user['user_id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role_id' => (int)$user['role_id'],
            'role_name' => $user['role_name'],
            'department_id' => (int)$user['department_id'],
            'college_id' => (int)$user['college_id'],
            'faculty_id' => isset($user['faculty_id']) ? (int)$user['faculty_id'] : null
        ];

        // For faculty, ensure faculty_id is set
        if ((int)$user['role_id'] === 6 && !isset($user['faculty_id'])) {
            $stmt = $this->db->prepare("SELECT faculty_id FROM faculty WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);
            $faculty = $stmt->fetch();
            if ($faculty) {
                $_SESSION['user']['faculty_id'] = (int)$faculty['faculty_id'];
            }
        }

        error_log("Session set: " . json_encode($_SESSION['user']));
    }

    public function handleRegistration()
    {
        try {
            if (!isset($_POST['register_submit'])) {
                header("Location: /register");
                exit();
            }

            // Authorization check
            if (isset($_SESSION['user'])) {
                $currentUserRole = $_SESSION['user']['role_id'];
                $requestedRole = (int)$_POST['role_id'];
                $allowedRegistrations = [
                    1 => [1, 2, 3, 4, 5, 6], // Admin
                    2 => [4, 5, 6],          // VPAA
                    3 => [6]                 // DI
                ];
                if (!in_array($requestedRole, $allowedRegistrations[$currentUserRole] ?? [])) {
                    throw new Exception("You're not authorized to register this role");
                }
            } else {
                if (!in_array((int)$_POST['role_id'], [4, 5, 6])) {
                    throw new Exception("Public registration not allowed for this role");
                }
            }

            // Validate required fields
            $requiredFields = [
                'username',
                'email',
                'password',
                'confirm_password',
                'role_id',
                'college_id',
                'department_id',
                'first_name',
                'last_name',
                'employee_id'
            ];

            // Faculty-specific required fields
            if ((int)$_POST['role_id'] === 6) {
                $requiredFields = array_merge($requiredFields, [
                    'academic_rank',
                    'classification',
                    'employment_type'
                ]);
            }

            foreach ($requiredFields as $field) {
                if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                    throw new Exception("The $field field is required");
                }
            }

            if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format");
            }
            if ($_POST['password'] !== $_POST['confirm_password']) {
                throw new Exception("Passwords do not match");
            }

            $roleId = (int)$_POST['role_id'];
            $departmentId = (int)$_POST['department_id'];

            // Validate department belongs to college
            $stmt = $this->db->prepare("SELECT 1 FROM departments WHERE department_id = ? AND college_id = ?");
            $stmt->execute([$departmentId, $_POST['college_id']]);
            if ($stmt->rowCount() === 0) {
                throw new Exception("Selected department doesn't belong to the chosen college");
            }

            // Check if employee ID already exists
            $stmt = $this->db->prepare("SELECT 1 FROM users WHERE employee_id = ?");
            $stmt->execute([$_POST['employee_id']]);
            if ($stmt->rowCount() > 0) {
                throw new Exception("Employee ID already exists");
            }

            // Prepare registration data
            $userData = [
                'employee_id' => trim($_POST['employee_id']),
                'username' => trim($_POST['username']),
                'email' => trim($_POST['email']),
                'password' => $_POST['password'],
                'role_id' => $roleId,
                'department_id' => $departmentId,
                'college_id' => (int)$_POST['college_id'],
                'first_name' => trim($_POST['first_name']),
                'middle_name' => trim($_POST['middle_name'] ?? ''),
                'last_name' => trim($_POST['last_name']),
                'suffix' => trim($_POST['suffix'] ?? ''),
                'academic_rank' => ($roleId == 6) ? ($_POST['academic_rank'] ?? 'Instructor') : null,
                'classification' => ($roleId == 6) ? ($_POST['classification'] ?? null) : null,
                'employment_type' => ($roleId == 6) ? ($_POST['employment_type'] ?? 'Regular') : null,
                'primary_program_id' => ($roleId == 6) ? ($_POST['primary_program_id'] ?? null) : null
            ];

            // Register the user
            $this->authService->register(
                $userData['employee_id'],
                $userData['username'],
                $userData['email'],
                $userData['password'],
                $userData['role_id'],
                $userData['department_id'],
                $userData['college_id'],
                $userData['first_name'],
                $userData['middle_name'],
                $userData['last_name'],
                $userData['suffix'],
                $userData['academic_rank'],
                $userData['classification'],
                $userData['employment_type'],
                $userData['primary_program_id']
            );

            error_log("Registration complete for user: " . $userData['username']);

            // Login the user
            $loginSuccess = $this->authService->login($userData['username'], $userData['password']);
            if (!$loginSuccess) {
                throw new Exception("Login failed after registration");
            }

            unset($_SESSION['form_data']);
            $this->redirectBasedOnRole();
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['form_data'] = $_POST;
            header("Location: /register");
            exit();
        }
    }

    private function registerUser(array $userData)
    {
        $this->db->beginTransaction();

        try {
            $passwordHash = password_hash($userData['password'], PASSWORD_BCRYPT);

            $stmt = $this->db->prepare("
                INSERT INTO users (
                    username, email, password_hash,
                    first_name, last_name,
                    role_id, department_id, college_id,
                    is_active, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
            ");
            $stmt->execute([
                $userData['username'],  
                $userData['email'],
                $passwordHash,
                $userData['first_name'],
                $userData['last_name'],
                $userData['role_id'],
                $userData['department_id'], // NULL for Dean
                $userData['college_id']
            ]);

            $userId = $this->db->lastInsertId();

            if ((int)$userData['role_id'] === 6) {
                $stmt = $this->db->prepare("
                    INSERT INTO faculty (
                        first_name, last_name, email, phone,
                        position, employment_type, department_id,
                        user_id, created_at, updated_at
                    ) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $userData['first_name'],
                    $userData['last_name'],
                    $userData['email'],
                    $userData['position'] ?? 'Instructor',
                    $userData['employment_type'] ?? 'Regular',
                    $userData['department_id'],
                    $userId
                ]);
            }

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Registration failed: " . $e->getMessage());
            throw new Exception("Registration failed. Please try again.");
        }
    }

    private function validatePasswordStrength($password)
    {
        if (strlen($password) < 8) {
            throw new Exception("Password must be at least 8 characters");
        }
        if (!preg_match('/[A-Z]/', $password)) {
            throw new Exception("Password must contain at least one uppercase letter");
        }
        if (!preg_match('/[a-z]/', $password)) {
            throw new Exception("Password must contain at least one lowercase letter");
        }
        if (!preg_match('/[0-9]/', $password)) {
            throw new Exception("Password must contain at least one number");
        }

        return true;
    }

    public function logout()
    {
        session_unset();
        session_destroy();
        header('Location: /login');
        exit();
    }

    public function getDepartments()
    {
        try {
            $collegeId = $_GET['college_id'] ?? null;

            if (!$collegeId) {
                throw new Exception('College ID required');
            }

            $stmt = $this->db->prepare("
            SELECT department_id, department_name 
            FROM departments 
            WHERE college_id = ? 
            ORDER BY department_name
        ");
            $stmt->execute([$collegeId]);
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $departments
            ]);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit();
    }

    private function isLoggedIn()
    {
        return isset($_SESSION['user']);
    }

    private function redirectBasedOnRole()
    {
        if (!isset($_SESSION['user'])) {
            header('Location: /login');
            exit();
        }

        $roleId = (int)$_SESSION['user']['role_id'];

        switch ($roleId) {
            case 1: // Admin
                header('Location: /admin/dashboard');
                break;
            case 2: // VPAA
                header('Location: /vp/dashboard');
                break;
            case 3: // DI
                header('Location: /di/dashboard');
                break;
            case 4: // Dean
                header('Location: /dean/dashboard');
                break;
            case 5: // chair
                header('Location: /chair/dashboard');
                break;
            case 6: // Faculty
                header('Location: /faculty/dashboard');
                break;
            default:
                $this->logout();
                exit();
        }
        exit();
    }
}
