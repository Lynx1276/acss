<?php
require_once __DIR__ . '/../config/Database.php';


use App\config\Database;

class AuthService
{
    private $db;
    private $pepper = "PRMSU_SECURE_PEPPER"; // Should be a long random string in production

    public function __construct()
    {
        $this->db = (new Database())->connect();
    }

    public function register(
        string $employeeId,
        string $username,
        string $email,
        string $password,
        int $roleId,
        int $departmentId,
        int $collegeId,
        string $firstName,
        string $middleName,
        string $lastName,
        string $suffix,
        ?string $academicRank = null,
        ?string $classification = null,
        ?string $employmentType = null,
        ?int $primaryProgramId = null
    ): bool {
        $this->db->beginTransaction();

        try {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            // Insert into users table
            $stmt = $this->db->prepare("
                INSERT INTO users (
                    employee_id, username, email, password_hash,
                    first_name, middle_name, last_name, suffix,
                    role_id, department_id, college_id,
                    is_active, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
            ");
            $stmt->execute([
                $employeeId,
                $username,
                $email,
                $passwordHash,
                $firstName,
                $middleName,
                $lastName,
                $suffix,
                $roleId,
                $departmentId,
                $collegeId
            ]);

            $userId = $this->db->lastInsertId();

            // Insert into faculty table if role is Faculty (role_id = 6)
            if ($roleId === 6) {
                $stmt = $this->db->prepare("
                    INSERT INTO faculty (
                        employee_id, first_name, middle_name, last_name, suffix,
                        email, academic_rank, classification, employment_type,
                        department_id, primary_program_id, user_id,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $employeeId,
                    $firstName,
                    $middleName,
                    $lastName,
                    $suffix,
                    $email,
                    $academicRank,
                    $classification,
                    $employmentType,
                    $departmentId,
                    $primaryProgramId,
                    $userId
                ]);
            }

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Registration failed in AuthService: " . $e->getMessage());
            throw new Exception("Registration failed: " . $e->getMessage());
        }
    }

    public function login(string $username, string $password): bool
    {
        $db = (new Database())->connect();
        try {
            $stmt = $db->prepare("
                SELECT u.*, r.role_name, f.faculty_id 
                FROM users u
                JOIN roles r ON u.role_id = r.role_id 
                LEFT JOIN faculty f ON u.user_id = f.user_id
                WHERE username = ? AND is_active = 1
            ");
            $stmt->execute([trim($username)]);
            $user = $stmt->fetch();

            if (!$user) {
                error_log("Login failed: User '$username' not found");
                return false;
            }

            if (!password_verify(trim($password), $user['password_hash'])) {
                error_log("Login failed: Password mismatch for '$username'");
                return false;
            }

            // Set session
            $_SESSION['user'] = [
                'id' => (int)$user['user_id'],
                'employee_id' => $user['employee_id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role_id' => (int)$user['role_id'],
                'role_name' => $user['role_name'],
                'department_id' => (int)$user['department_id'],
                'college_id' => (int)$user['college_id'],
                'faculty_id' => $user['faculty_id'] ? (int)$user['faculty_id'] : null,
                'first_name' => $user['first_name'],
                'middle_name' => $user['middle_name'],
                'last_name' => $user['last_name'],
                'suffix' => $user['suffix']
            ];

            error_log("Session set: " . json_encode($_SESSION['user']));
            return true;
        } catch (PDOException $e) {
            error_log("Login database error: " . $e->getMessage());
            return false;
        }
    }

    private function validateRegistrationInputs(
        string $username,
        string $email,
        string $password,
        int $roleId,
        ?int $departmentId, // Changed to ?int
        int $collegeId,
        string $firstName,
        string $lastName
    ): void {
        // Validate required fields
        $required = [
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'first_name' => $firstName,
            'last_name' => $lastName
        ];

        foreach ($required as $field => $value) {
            if (empty(trim($value))) {
                throw new Exception("The $field field is required");
            }
        }

        // Validate IDs
        if ($roleId <= 0 || $collegeId <= 0) {
            throw new Exception("Invalid role or college selection");
        }
        if ($roleId !== 5 && ($departmentId === null || $departmentId <= 0)) { // Allow null for Dean
            throw new Exception("Invalid department selection");
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }

        // Validate password strength
        $this->validatePasswordStrength($password);
    }

    private function validatePasswordStrength(string $password): void
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
    }

    private function userExists(string $username, string $email): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1 FROM users 
            WHERE username = ? OR email = ?
        ");
        $stmt->execute([$username, $email]);
        return $stmt->rowCount() > 0;
    }

    private function isValidDepartment(?int $departmentId, int $collegeId): bool
    {
        if ($departmentId === null) {
            return true; // Valid for Dean
        }
        $stmt = $this->db->prepare("
            SELECT 1 FROM departments 
            WHERE department_id = ? AND college_id = ?
        ");
        $stmt->execute([$departmentId, $collegeId]);
        return $stmt->rowCount() > 0;
    }

    private function createFacultyRecord(
        string $firstName,
        string $lastName,
        string $email,
        string $position,
        string $employmentType,
        int $departmentId
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO faculty (
                first_name, last_name, email, position,
                employment_type, department_id, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $firstName,
            $lastName,
            $email,
            $position,
            $employmentType,
            $departmentId
        ]);
        return $this->db->lastInsertId();
    }

    private function createUserRecord(
        string $username,
        string $email,
        string $passwordHash,
        int $roleId,
        ?int $departmentId, // Changed to ?int
        int $collegeId,
        string $firstName,
        string $lastName,
        ?int $facultyId
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO users (
                username, email, password_hash,
                first_name, last_name,
                role_id, department_id, college_id, faculty_id,
                is_active, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");
        $stmt->execute([
            $username,
            $email,
            $passwordHash,
            $firstName,
            $lastName,
            $roleId,
            $departmentId,
            $collegeId,
            $facultyId
        ]);
        return $this->db->lastInsertId();
    }

    private function updateLastLogin(int $userId): void
    {
        $stmt = $this->db->prepare("
            UPDATE users SET last_login = NOW() 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
    }

    private function setUserSession(array $user): void
    {
        $_SESSION['user'] = [
            'id' => (int)$user['user_id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role_id' => (int)$user['role_id'],
            'role_name' => $user['role_name'],
            'department_id' => $user['department_id'] ? (int)$user['department_id'] : null, // Handle NULL
            'college_id' => (int)$user['college_id'],
            'faculty_id' => $user['faculty_id'] ? (int)$user['faculty_id'] : null
        ];
    }

    public function logout(): bool
    {
        if (isset($_SESSION['user'])) {
            $this->logAuthAction($_SESSION['user']['id'], 'logout');
            unset($_SESSION['user']);
        }
        session_destroy();
        return true;
    }

    private function hashPassword(string $password): string
    {
        $options = ['cost' => 12];
        $peppered = $password . $this->pepper;
        return password_hash($peppered, PASSWORD_BCRYPT, $options);
    }

    private function verifyPassword(string $password, string $hash): bool
    {
        $peppered = $password . $this->pepper;
        return password_verify($peppered, $hash);
    }

    private function logAuthAction(?int $userId, string $action, ?string $identifier = null): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        try {
            $stmt = $this->db->prepare("
                INSERT INTO auth_logs 
                (user_id, action, ip_address, user_agent, identifier) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $action, $ip, $userAgent, $identifier]);
        } catch (PDOException $e) {
            error_log("Failed to log auth action: " . $e->getMessage());
        }
    }
}
