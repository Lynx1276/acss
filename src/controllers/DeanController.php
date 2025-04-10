<?php
// src/controllers/DeanController.php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../services/SchedulingService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class DeanController
{
    private $db;
    private $schedulingService;

    public function __construct()
    {
        $this->db = (new Database())->connect();
        $this->schedulingService = new SchedulingService();
    }

    public function dashboard()
    {
        error_log("DeanController: Entering dashboard");
        AuthMiddleware::handle('Dean');
        error_log("DeanController: After AuthMiddleware");

        try {
            // Verify user session and department
            if (!isset($_SESSION['user']['college_id'])) {
                throw new Exception("User college not set");
            }

            // Ensure user details are complete
            if (!isset($_SESSION['user']['first_name']) || !isset($_SESSION['user']['last_name'])) {
                $this->completeUserSession();
            }

            $userId = $_SESSION['user'];
            $collegeId = $_SESSION['user']['college_id'];

            $pendingRequests = $this->schedulingService->getPendingRequestsByCollege($collegeId);
            error_log("DeanController: Fetched pending requests");
            $facultyStats = $this->schedulingService->getCollegeFacultyStats($collegeId);
            error_log("DeanController: Fetched faculty stats");
            $currentSemester = $this->schedulingService->getCurrentSemester();
            error_log("DeanController: Fetched current semester");

            $currentUri = '/dean/dashboard';
            error_log("DeanController: Loading view");
            require __DIR__ . '/../views/dean/dashboard.php';
            error_log("DeanController: View loaded");
        } catch (Exception $e) {
            error_log("Dean dashboard error: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            header('Location: /login');
            exit;
        }
    }

    public function schedules()
    {
        AuthMiddleware::handle('Dean');
        try {
            if (!isset($_SESSION['user']['college_id'])) {
                $_SESSION['error'] = "Department ID not set. Please log in again.";
                header('Location: /login');
                exit;
            }

            $departmentId = $_SESSION['user']['college_id'];
            $selectedSemesterId = $_GET['semester_id'] ?? null;
            $semesters = $this->schedulingService->getSemesters();
            $schedules = $this->schedulingService->getDepartmentSchedules($departmentId, $selectedSemesterId);
            $currentUri = '/dean/schedule';
            require __DIR__ . '/../views/dean/schedule.php';
        } catch (Exception $e) {
            error_log("Dean schedules error: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            header('Location: /dean/schedules');
            exit;
        }
    }

    public function requests()
    {
        AuthMiddleware::handle('Dean');
        try {
            if (!isset($_SESSION['user']['college_id'])) {
                $_SESSION['error'] = "Department ID not set. Please log in again.";
                header('Location: /login');
                exit;
            }

            $departmentId = $_SESSION['user']['college_id'];
            $userId = $_SESSION['user'];

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $requestId = (int)($_POST['request_id'] ?? 0);
                $action = $_POST['action'] ?? '';

                if ($requestId && in_array($action, ['approve', 'reject'])) {
                    $this->schedulingService->updateRequestStatus($requestId, $action === 'approve' ? 'approved' : 'rejected', $userId);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => "Request " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully"];
                    header('Location: /dean/requests');
                    exit;
                } else {
                    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid request action'];
                }
            }

            $requests = $this->schedulingService->getPendingRequestsByDepartment($departmentId);
            $currentUri = '/dean/requests';
            require __DIR__ . '/../views/dean/requests.php';
        } catch (Exception $e) {
            error_log("Dean requests error: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            header('Location: /dean/requests');
            exit;
        }
    }

    public function faculty()
    {
        AuthMiddleware::handle('Dean');
        try {
            if (!isset($_SESSION['user']['college_id'])) {
                $_SESSION['error'] = "Department ID not set. Please log in again.";
                header('Location: /login');
                exit;
            }

            $departmentId = $_SESSION['user']['college_id'];
            $facultyList = $this->schedulingService->getFacultyByDepartment($departmentId);
            $currentUri = '/dean/faculty';
            require __DIR__ . '/../views/dean/faculty.php';
        } catch (Exception $e) {
            error_log("Dean faculty error: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            header('Location: /dean/faculty');
            exit;
        }
    }

    private function completeUserSession()
    {
        $stmt = $this->db->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user']['id']]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user']['first_name'] = $user['first_name'];
            $_SESSION['user']['last_name'] = $user['last_name'];
        } else {
            throw new Exception("User details not found");
        }
    }
}
