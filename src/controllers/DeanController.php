<?php
// src/controllers/DeanController.php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../services/DeanService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

use App\config\Database;

class DeanController
{
    private $db;
    private $deanService;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->db = (new Database())->connect();
        $this->deanService = new DeanService();
    }

    private function checkSession()
    {
        if (!isset($_SESSION['user']['college_id']) || !isset($_SESSION['user']['user_id']) || !isset($_SESSION['user']['role_id']) || $_SESSION['user']['role_id'] != 4) {
            error_log("Invalid session for dean: " . json_encode($_SESSION['user'] ?? 'null'));
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Please log in as a dean.'];
            header('Location: /login');
            exit;
        }
    }

    public function dashboard()
    {
        AuthMiddleware::handle('dean');
        try {
            $this->checkSession();
            $collegeId = $_SESSION['user']['college_id'];
            $departmentFilter = $_GET['department_id'] ?? null;

            $pendingRequests = $this->deanService->getPendingFacultyRequests($collegeId, $departmentFilter);
            $currentSemester = $this->deanService->getCurrentSemester();
            $departments = $this->deanService->getCollegeDepartments($collegeId);
            $metrics = $this->deanService->getCollegeMetrics($collegeId);
            $schedules = $this->deanService->getClassSchedules($collegeId, $departmentFilter);

            $currentUri = '/dean/dashboard';
            require __DIR__ . '/../views/dean/dashboard.php';
        } catch (Exception $e) {
            error_log("Dean dashboard error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to load dashboard. Please try again later.'];
            header('Location: /login');
            exit;
        }
    }

    public function schedules()
    {
        AuthMiddleware::handle('dean');
        try {
            $this->checkSession();
            $collegeId = $_SESSION['user']['college_id'];
            $departmentFilter = $_GET['department_id'] ?? null;

            $currentSemester = $this->deanService->getCurrentSemester();
            $departments = $this->deanService->getCollegeDepartments($collegeId);
            $schedules = $this->deanService->getClassSchedules($collegeId, $departmentFilter);

            $currentUri = '/dean/schedules';
            require __DIR__ . '/../views/dean/schedules.php';
        } catch (Exception $e) {
            error_log("Dean schedules error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to load schedules. Please try again later.'];
            header('Location: /dean/schedules');
            exit;
        }
    }

    public function facultyRequests()
    {
        AuthMiddleware::handle('dean');
        try {
            $this->checkSession();
            $collegeId = $_SESSION['user']['college_id'];
            $userId = $_SESSION['user']['user_id'];
            $departmentFilter = $_GET['department_id'] ?? null;

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $requestId = (int)($_POST['request_id'] ?? 0);
                $action = $_POST['action'] ?? '';

                if ($requestId && in_array($action, ['approve', 'reject'])) {
                    $this->deanService->updateFacultyRequestStatus($requestId, $action === 'approve' ? 'approved' : 'rejected', $userId);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => "Faculty request " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully"];
                    header('Location: /dean/faculty-requests');
                    exit;
                } else {
                    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid request action'];
                }
            }

            $requests = $this->deanService->getPendingFacultyRequests($collegeId, $departmentFilter);
            $departments = $this->deanService->getCollegeDepartments($collegeId);
            $currentUri = '/dean/faculty-requests';
            require __DIR__ . '/../views/dean/faculty-requests.php';
        } catch (Exception $e) {
            error_log("Dean faculty requests error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to load faculty requests. Please try again later.'];
            header('Location: /dean/faculty-requests');
            exit;
        }
    }

    public function accounts()
    {
        AuthMiddleware::handle('dean');
        try {
            $this->checkSession();
            $collegeId = $_SESSION['user']['college_id'];
            $userId = $_SESSION['user']['user_id'];
            $departmentFilter = $_GET['department_id'] ?? null;

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_account'])) {
                $targetUserId = (int)($_POST['user_id'] ?? 0);
                try {
                    $this->deanService->deactivateAccount($targetUserId, $userId);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Account deactivated successfully'];
                    header('Location: /dean/accounts');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
                }
            }

            $query = "SELECT u.user_id, u.first_name, u.last_name, u.username, r.role_name, 
                             d.department_name
                      FROM users u
                      JOIN roles r ON u.role_id = r.role_id
                      LEFT JOIN departments d ON u.department_id = d.department_id
                      WHERE u.college_id = :college_id AND u.is_active = 1 
                            AND u.role_id IN (5, 6)";
            $params = [':college_id' => $collegeId];
            if ($departmentFilter) {
                $query .= " AND u.department_id = :department_id";
                $params[':department_id'] = $departmentFilter;
            }
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $departments = $this->deanService->getCollegeDepartments($collegeId);
            $currentUri = '/dean/accounts';
            require __DIR__ . '/../views/dean/accounts.php';
        } catch (Exception $e) {
            error_log("Dean accounts error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to load accounts. Please try again later.'];
            header('Location: /dean/accounts');
            exit;
        }
    }

    public function profile()
    {
        AuthMiddleware::handle('dean');
        try {
            $this->checkSession();
            $collegeId = $_SESSION['user']['college_id'];
            $userId = $_SESSION['user']['user_id'];

            // Fetch user profile with department and college names
            $_SESSION['user'] = $this->deanService->getUserProfile($userId);

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
                            'current_profile_picture' => $_SESSION['user']['profile_picture'] ?? '/images/default-profile.png'
                        ];
                        if (empty($data['first_name']) || empty($data['last_name']) || empty($data['username']) || empty($data['email'])) {
                            throw new Exception("Required fields are missing");
                        }
                        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                            throw new Exception("Invalid email format");
                        }
                        $file = $_FILES['profile_picture'] ?? null;
                        $this->deanService->updateProfile($userId, $data, $file);
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
                        $this->deanService->changePassword($userId, $currentPassword, $newPassword);
                        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Password changed successfully'];
                    }
                    header('Location: /dean/profile');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
                }
            }

            $pendingRequests = $this->deanService->getPendingFacultyRequests($collegeId);
            $pendingCount = !empty($pendingRequests) && isset($pendingRequests[0]['count']) ? (int)$pendingRequests[0]['count'] : 0;
            $currentUri = '/dean/profile';
            require __DIR__ . '/../views/dean/profile.php';
        } catch (Exception $e) {
            error_log("Dean profile error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to load profile. Please try again later.'];
            header('Location: /dean/profile');
            exit;
        }
    
    }

    public function settings()
    {
        AuthMiddleware::handle('dean');
        try {
            $this->checkSession();
            $currentUri = '/dean/settings';
            require __DIR__ . '/../views/dean/settings.php';
        } catch (Exception $e) {
            error_log("Dean settings error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to load settings. Please try again later.'];
            header('Location: /dean/settings');
            exit;
        }
    }
}
