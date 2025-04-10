<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../services/SchedulingService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class FacultyController
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
        AuthMiddleware::handle('faculty');
        try {
            if (!isset($_SESSION['user']['faculty_id'])) {
                $_SESSION['error'] = "Faculty ID not set. Please log in again.";
                header('Location: /login');
                exit;
            }

            // Ensure user details are complete
            if (!isset($_SESSION['user']['first_name']) || !isset($_SESSION['user']['last_name'])) {
                $this->completeUserSession();
            }

            $facultyId = $_SESSION['user']['faculty_id'];
            $semesterId = $_SESSION['user']['semester_id'] ?? null;
            $schedule = $this->schedulingService->getFacultySchedule($facultyId, $semesterId);
            $stats = $this->schedulingService->getFacultyStats($facultyId, $semesterId);
            // Get current URI for active menu highlighting
            $currentUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

            $viewFile = 'faculty/dashboard.php';
            $fullPath = __DIR__ . '/../views/' . $viewFile;

            if (!file_exists($fullPath)) {
                throw new Exception("View file not found: $viewFile");
            }

            // Pass variables to the view
            require $fullPath;
        } catch (Exception $e) {
            error_log("Faculty dashboard error: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            header('Location: /login');
            exit;
        }
    }

    public function schedule()
    {
        AuthMiddleware::handle('faculty');
        try {
            if (!isset($_SESSION['user']['faculty_id'])) {
                $_SESSION['error'] = "Faculty ID not set. Please log in again.";
                header('Location: /login');
                exit;
            }

            $facultyId = $_SESSION['user']['faculty_id'];
            $selectedSemesterId = $_GET['semester_id'] ?? null;
            $semesters = $this->schedulingService->getSemesters();
            $schedule = $this->schedulingService->getFacultySchedule($facultyId, $selectedSemesterId);

            // Fetch the selected semester details
            $selectedSemester = null;
            if ($selectedSemesterId) {
                foreach ($semesters as $semester) {
                    if ($semester['semester_id'] == $selectedSemesterId) {
                        $selectedSemester = $semester;
                        break;
                    }
                }
            } else {
                // Default to the current semester (is_current = 1)
                foreach ($semesters as $semester) {
                    if (isset($semester['is_current']) && $semester['is_current'] == 1) {
                        $selectedSemester = $semester;
                        break;
                    }
                }
            }

            $currentUri = '/faculty/schedule';
            require __DIR__ . '/../views/faculty/schedule.php';
        } catch (Exception $e) {
            error_log("Faculty schedule error: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            header('Location: /faculty/schedule');
            exit;
        }
    }

    public function requests()
    {
        AuthMiddleware::handle('faculty');
        try {
            if (!isset($_SESSION['user']['faculty_id'])) {
                $_SESSION['error'] = "Faculty ID not set. Please log in again.";
                header('Location: /login');
                exit;
            }

            $facultyId = $_SESSION['user']['faculty_id'];
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $scheduleId = (int)($_POST['schedule_id'] ?? 0);
                $requestType = $_POST['request_type'] ?? '';
                $details = trim($_POST['details'] ?? '');

                if ($scheduleId && in_array($requestType, ['time_change', 'room_change']) && $details) {
                    $this->schedulingService->submitScheduleRequest($facultyId, $scheduleId, $requestType, $details);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Request submitted successfully'];
                    header('Location: /faculty/requests');
                    exit;
                } else {
                    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid request data'];
                }
            }

            $schedules = $this->schedulingService->getFacultySchedule($facultyId, null, true); // No semester filter for dropdown
            $requests = $this->schedulingService->getFacultyRequests($facultyId);
            $currentUri = '/faculty/requests';
            require __DIR__ . '/../views/faculty/requests.php';
        } catch (Exception $e) {
            error_log("Faculty requests error: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            header('Location: /faculty/requests');
            exit;
        }
    }

    public function profile()
    {
        AuthMiddleware::handle('faculty');
        try {
            if (!isset($_SESSION['user']['faculty_id'])) {
                $_SESSION['error'] = "Faculty ID not set. Please log in again.";
                header('Location: /login');
                exit;
            }

            $facultyId = $_SESSION['user']['faculty_id'];

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (isset($_POST['update_profile'])) {
                    $firstName = trim($_POST['first_name'] ?? '');
                    $lastName = trim($_POST['last_name'] ?? '');
                    $email = trim($_POST['email'] ?? '');
                    $phone = trim($_POST['phone'] ?? '');

                    if ($firstName && $lastName && $email) {
                        $this->schedulingService->updateFacultyProfile($facultyId, $firstName, $lastName, $email, $phone);
                        $_SESSION['user']['first_name'] = $firstName;
                        $_SESSION['user']['last_name'] = $lastName;
                        $_SESSION['user']['email'] = $email;
                        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Profile updated successfully'];
                    } else {
                        $_SESSION['flash'] = ['type' => 'error', 'message' => 'All required fields must be filled'];
                    }
                } elseif (isset($_POST['add_specialization'])) {
                    $subjectName = trim($_POST['subject_name'] ?? '');
                    $expertiseLevel = $_POST['expertise_level'] ?? 'Intermediate';

                    if ($subjectName && in_array($expertiseLevel, ['Beginner', 'Intermediate', 'Expert'])) {
                        $this->schedulingService->addFacultySpecialization($facultyId, $subjectName, $expertiseLevel);
                        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Specialization added successfully'];
                    } else {
                        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid specialization data'];
                    }
                } elseif (isset($_POST['edit_specialization'])) {
                    $specializationId = (int)($_POST['specialization_id'] ?? 0);
                    // For simplicity, redirect to a separate edit form or handle inline editing later
                    $_SESSION['flash'] = ['type' => 'info', 'message' => 'Edit functionality TBD'];
                } elseif (isset($_POST['delete_specialization'])) {
                    $specializationId = (int)($_POST['specialization_id'] ?? 0);
                    if ($specializationId) {
                        $this->schedulingService->deleteFacultySpecialization($facultyId, $specializationId);
                        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Specialization deleted successfully'];
                    } else {
                        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid specialization ID'];
                    }
                }
                header('Location: /faculty/profile');
                exit;
            }

            $faculty = $this->schedulingService->getFacultyProfile($facultyId);
            $department = $this->schedulingService->getDepartmentById($faculty['department_id']);
            $specializations = $this->schedulingService->getFacultySpecializations($facultyId);
            $currentUri = '/faculty/profile';
            require __DIR__ . '/../views/faculty/profile.php';
        } catch (Exception $e) {
            error_log("Faculty profile error: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            header('Location: /faculty/profile');
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
