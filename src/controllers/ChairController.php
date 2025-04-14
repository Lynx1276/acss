<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../services/SchedulingService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class ChairController {
    private $db;
    private $schedulingService;

    public function __construct() {
        $this->db = (new Database())->connect();
        $this->schedulingService = new SchedulingService();
    }

    public function dashboard()
    {
        AuthMiddleware::handle('chair');
        try {
            // Verify user session and department
            if (!isset($_SESSION['user']['department_id'])) {
                throw new Exception("User department not set");
            }
            

            $departmentId = $_SESSION['user']['department_id'];

            // Get dashboard statistics
            $stats = [
                'facultyCount' => $this->getFacultyCount($departmentId),
                'courseCount' => $this->getCourseCount($departmentId),
                'approvalCount' => $this->getPendingApprovalCount($departmentId)
            ];

            // Get semester data
            $semesters = $this->getAllSemesters();
            $currentSemester = $this->getCurrentSemester();

            // Get schedule data
            $scheduleData = [
                'schedule' => $this->schedulingService->getDepartmentSchedule(
                    $departmentId,
                    $currentSemester['semester_id']
                ),
                'facultyAvailability' => $this->schedulingService->getFacultyAvailability(
                    $departmentId,
                    $currentSemester['semester_id']
                ),
                'timeSlots' => $this->generateTimeSlots()
            ];

            // Extract view path from class name
            $viewPath = str_replace('Controller', '', basename(get_class($this)));
            $viewFile = strtolower($viewPath) . '/dashboard.php';
            $fullPath = __DIR__ . '/../views/' . $viewFile;

            // Verify view exists
            if (!file_exists($fullPath)) {
                throw new Exception("View file not found: $viewFile");
            }
                
            // Pass data to view
            extract(array_merge($stats, $scheduleData, [
                'semesters' => $semesters,
                'currentSemester' => $currentSemester
            ]));

            require $fullPath;

        } catch (Exception $e) {
            // Log error and show error page
            error_log("ChairController error: " . $e->getMessage());
            $this->showError("An error occurred while loading the dashboard");
        }
    }

    public function schedule()
    {
        AuthMiddleware::handle('chair');
        try {
            if (!isset($_SESSION['user']['department_id'])) {
                throw new Exception("Unauthorized access");
            }

            $departmentId = $_SESSION['user']['department_id'];
            $semesters = $this->getAllSemesters();
            $currentSemester = $this->getCurrentSemester();

            // Get schedule data
            $scheduleData = [
                'schedule' => $this->schedulingService->getDepartmentSchedule(
                    $departmentId,
                    $currentSemester['semester_id']
                ),
                'facultyAvailability' => $this->schedulingService->getFacultyAvailability(
                    $departmentId,
                    $currentSemester['semester_id']
                ),
                'timeSlots' => $this->generateTimeSlots()
            ];

            // Extract view path from class name
            $viewPath = str_replace('Controller', '', basename(get_class($this)));
            $viewFile = strtolower($viewPath) . '/view_schedule.php';
            $fullPath = __DIR__ . '/../views/' . $viewFile;

            // Verify view exists
            if (!file_exists($fullPath)) {
                throw new Exception("View file not found: $viewFile");
            }

            require $fullPath;
        } catch (Exception $e) {
            // Log error and show error page
            error_log("ChairController error: " . $e->getMessage());
            $this->showError("An error occurred while loading the schedule");
        }
    }

    public function faculty()
    {
        AuthMiddleware::handle('chair');
        try {
            if (!isset($_SESSION['user']['department_id'])) {
                throw new Exception("Unauthorized access");
            }

            $departmentId = $_SESSION['user']['department_id'];
            $schedulingService = new SchedulingService();

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_load'])) {
                $facultyId = $_POST['faculty_id'] ?? null;
                $courseId = $_POST['course_id'] ?? null;
                $semesterId = $_POST['semester_id'] ?? null;
                $roomId = $_POST['room_id'] ?? null;
                $timeSlots = $_POST['time_slots'] ?? [];

                if ($facultyId && $courseId && $semesterId) {
                    if ($schedulingService->addFacultyLoad($facultyId, $courseId, $semesterId, $roomId, $timeSlots)) {
                        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Faculty load added successfully'];
                        header('Location: /chair/faculty');
                        exit;
                    } else {
                        throw new Exception("Failed to add faculty load");
                    }
                } else {
                    throw new Exception("Missing required fields for adding load");
                }
            }

            $faculty = $schedulingService->getFacultyMembers($departmentId);
            $courses = $schedulingService->getDepartmentCourses($departmentId);
            $semesters = $schedulingService->getAllSemesters();
            $classrooms = $schedulingService->getAvailableClassrooms();
            $stats = ['pendingApprovals' => count($schedulingService->getPendingApprovals($departmentId))];

            // Extract view path from class name
            $viewPath = str_replace('Controller', '', basename(get_class($this)));
            $viewFile = strtolower($viewPath) . '/faculty.php';
            $fullPath = __DIR__ . '/../views/' . $viewFile;

            // Verify view exists
            if (!file_exists($fullPath)) {
                throw new Exception("View file not found: $viewFile");
            }

            require $fullPath;
        } catch (Exception $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            header('Location: /chair/faculty');
            exit;
        }
    }

    public function facultyAvailability()
    {
        AuthMiddleware::handle('chair');
        try {
            if (!isset($_SESSION['user']['department_id'])) {
                throw new Exception("Unauthorized access");
            }

            $departmentId = $_SESSION['user']['department_id'];
            $currentSemester = $this->schedulingService->getCurrentSemester();
            $semesterId = $currentSemester['semester_id'];

            // Handle form submission to update availability
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_availability'])) {
                $facultyId = $_POST['faculty_id'] ?? null;
                $dayOfWeek = $_POST['day_of_week'] ?? null;
                $startTime = $_POST['start_time'] ?? null;
                $endTime = $_POST['end_time'] ?? null;
                $isAvailable = isset($_POST['is_available']) ? 1 : 0;
                $preferenceLevel = $_POST['preference_level'] ?? 'Neutral';
                $reason = $_POST['reason'] ?? null;

                if ($facultyId && $dayOfWeek && $startTime && $endTime) {
                    $this->updateFacultyAvailability(
                        $facultyId,
                        $semesterId,
                        $dayOfWeek,
                        $startTime,
                        $endTime,
                        $isAvailable,
                        $preferenceLevel,
                        $reason
                    );
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Availability updated successfully'];
                    header('Location: /chair/faculty_availability');
                    exit;
                }
            }

            // Get faculty members and their availability
            $faculty = $this->schedulingService->getFacultyMembers($departmentId);
            $availability = [];

            foreach ($faculty as $member) {
                $availability[$member['faculty_id']] = $this->schedulingService->getFacultyAvailabilityForSemester(
                    $member['faculty_id'],
                    $semesterId
                );
            }

            $stats = ['pendingApprovals' => count($this->schedulingService->getPendingApprovals($departmentId))];

            require __DIR__ . '/../views/chair/faculty_availability.php';
        } catch (Exception $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            header('Location: /chair/faculty_availability');
            exit;
        }
    }

    private function updateFacultyAvailability($facultyId, $semesterId, $dayOfWeek, $startTime, $endTime, $isAvailable, $preferenceLevel, $reason)
    {
        // Check if availability already exists
        $query = "SELECT availability_id FROM faculty_availability 
              WHERE faculty_id = :faculty_id 
              AND semester_id = :semester_id
              AND day_of_week = :day_of_week
              AND start_time = :start_time
              AND end_time = :end_time";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':faculty_id' => $facultyId,
            ':semester_id' => $semesterId,
            ':day_of_week' => $dayOfWeek,
            ':start_time' => $startTime,
            ':end_time' => $endTime
        ]);

        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update existing record
            $updateQuery = "UPDATE faculty_availability SET 
                        is_available = :is_available,
                        preference_level = :preference_level,
                        reason = :reason
                        WHERE availability_id = :availability_id";

            $stmt = $this->db->prepare($updateQuery);
            $stmt->execute([
                ':is_available' => $isAvailable,
                ':preference_level' => $preferenceLevel,
                ':reason' => $reason,
                ':availability_id' => $existing['availability_id']
            ]);
        } else {
            // Insert new record
            $insertQuery = "INSERT INTO faculty_availability 
                       (faculty_id, semester_id, day_of_week, start_time, end_time, is_available, preference_level, reason)
                       VALUES 
                       (:faculty_id, :semester_id, :day_of_week, :start_time, :end_time, :is_available, :preference_level, :reason)";

            $stmt = $this->db->prepare($insertQuery);
            $stmt->execute([
                ':faculty_id' => $facultyId,
                ':semester_id' => $semesterId,
                ':day_of_week' => $dayOfWeek,
                ':start_time' => $startTime,
                ':end_time' => $endTime,
                ':is_available' => $isAvailable,
                ':preference_level' => $preferenceLevel,
                ':reason' => $reason
            ]);
        }
    }

    public function classrooms()
    {
        AuthMiddleware::handle('chair');
        try {
            if (!isset($_SESSION['user']['department_id'])) {
                throw new Exception("Unauthorized access");
            }

            $departmentId = $_SESSION['user']['department_id'];

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_classroom'])) {
                $roomName = $_POST['room_name'] ?? '';
                $building = $_POST['building'] ?? '';
                $capacity = (int)($_POST['capacity'] ?? 0);
                $isLab = isset($_POST['is_lab']) ? 1 : 0;
                $hasProjector = isset($_POST['has_projector']) ? 1 : 0;
                $hasSmartboard = isset($_POST['has_smartboard']) ? 1 : 0;
                $hasComputers = isset($_POST['has_computers']) ? 1 : 0;
                $shared = isset($_POST['shared']) ? 1 : 0;
                $isActive = isset($_POST['is_active']) ? 1 : 0;

                $query = "INSERT INTO classrooms (room_name, building, capacity, is_lab, has_projector, has_smartboard, has_computers, shared, is_active, department_id) 
                          VALUES (:room_name, :building, :capacity, :is_lab, :has_projector, :has_smartboard, :has_computers, :shared, :is_active, :department_id)";
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    ':room_name' => $roomName,
                    ':building' => $building,
                    ':capacity' => $capacity,
                    ':is_lab' => $isLab,
                    ':has_projector' => $hasProjector,
                    ':has_smartboard' => $hasSmartboard,
                    ':has_computers' => $hasComputers,
                    ':shared' => $shared,
                    ':is_active' => $isActive,
                    ':department_id' => $departmentId
                ]);

                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Classroom added successfully'];
                header('Location: /chair/classroom');
                exit;
            }

            $classrooms = $this->schedulingService->getAvailableClassrooms($departmentId);
            $stats = ['pendingApprovals' => count($this->schedulingService->getPendingApprovals($departmentId))];

            // Extract view path from class name
            $viewPath = str_replace('Controller', '', basename(get_class($this)));
            $viewFile = strtolower($viewPath) . '/classroom.php';
            $fullPath = __DIR__ . '/../views/' . $viewFile;

            // Verify view exists
            if (!file_exists($fullPath)) {
                throw new Exception("View file not found: $viewFile");
            }

            require $fullPath;
        } catch (Exception $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            header('Location: /chair/classroom');
            exit;
        }
    }

    public function editClassroom()
    {
        AuthMiddleware::handle('chair');
        try {
            if (!isset($_SESSION['user']['department_id'])) {
                throw new Exception("Unauthorized access");
            }

            $departmentId = $_SESSION['user']['department_id'];
            $roomId = $_GET['id'] ?? null;
            if (!$roomId) {
                throw new Exception("Classroom ID not provided");
            }

            $query = "SELECT * FROM classrooms WHERE room_id = :room_id AND (department_id = :department_id OR shared = 1)";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':room_id' => $roomId, ':department_id' => $departmentId]);
            $classroom = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$classroom) {
                throw new Exception("Classroom not found or not accessible");
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $roomName = $_POST['room_name'] ?? $classroom['room_name'];
                $building = $_POST['building'] ?? $classroom['building'];
                $capacity = (int)($_POST['capacity'] ?? $classroom['capacity']);
                $isLab = isset($_POST['is_lab']) ? 1 : 0;
                $hasProjector = isset($_POST['has_projector']) ? 1 : 0;
                $hasSmartboard = isset($_POST['has_smartboard']) ? 1 : 0;
                $hasComputers = isset($_POST['has_computers']) ? 1 : 0;
                $shared = isset($_POST['shared']) ? 1 : 0;
                $isActive = isset($_POST['is_active']) ? 1 : 0;

                $updateQuery = "UPDATE classrooms SET 
                                room_name = :room_name, 
                                building = :building, 
                                capacity = :capacity, 
                                is_lab = :is_lab, 
                                has_projector = :has_projector, 
                                has_smartboard = :has_smartboard, 
                                has_computers = :has_computers, 
                                shared = :shared, 
                                is_active = :is_active 
                                WHERE room_id = :room_id AND (department_id = :department_id OR shared = 1)";
                $stmt = $this->db->prepare($updateQuery);
                $stmt->execute([
                    ':room_name' => $roomName,
                    ':building' => $building,
                    ':capacity' => $capacity,
                    ':is_lab' => $isLab,
                    ':has_projector' => $hasProjector,
                    ':has_smartboard' => $hasSmartboard,
                    ':has_computers' => $hasComputers,
                    ':shared' => $shared,
                    ':is_active' => $isActive,
                    ':room_id' => $roomId,
                    ':department_id' => $departmentId
                ]);

                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Classroom updated successfully'];
                header('Location: /chair/classroom');
                exit;
            }

            $stats = ['pendingApprovals' => count($this->schedulingService->getPendingApprovals($departmentId))];
            require __DIR__ . '/../views/chair/edit_classroom.php';
        } catch (Exception $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            header('Location: /chair/classroom');
            exit;
        }
    }

    public function editFaculty()
    {
        AuthMiddleware::handle('chair');
        try {
            if (!isset($_SESSION['user']['department_id'])) {
                throw new Exception("Unauthorized access");
            }

            $departmentId = $_SESSION['user']['department_id'];
            $facultyId = $_GET['id'] ?? null;
            if (!$facultyId) {
                throw new Exception("Faculty ID not provided");
            }

            $facultyMember = $this->schedulingService->getFacultyMembers($departmentId);
            $facultyMember = array_filter($facultyMember, fn($f) => $f['faculty_id'] == $facultyId);
            $facultyMember = reset($facultyMember);
            if (!$facultyMember) {
                throw new Exception("Faculty member not found");
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $firstName = $_POST['first_name'] ?? $facultyMember['first_name'];
                $lastName = $_POST['last_name'] ?? $facultyMember['last_name'];
                $position = $_POST['position'] ?? $facultyMember['position'];
                $specializations = $_POST['specializations'] ?? $facultyMember['specializations'];

                $query = "UPDATE faculty SET first_name = :first_name, last_name = :last_name, position = :position 
                          WHERE faculty_id = :faculty_id AND department_id = :department_id";
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    ':first_name' => $firstName,
                    ':last_name' => $lastName,
                    ':position' => $position,
                    ':faculty_id' => $facultyId,
                    ':department_id' => $departmentId
                ]);

                // Update specializations
                $this->db->prepare("DELETE FROM specializations WHERE faculty_id = :faculty_id")->execute([':faculty_id' => $facultyId]);
                if (!empty($specializations)) {
                    $specializationsArray = explode(',', $specializations);
                    $specQuery = "INSERT INTO specializations (faculty_id, subject_name) VALUES (:faculty_id, :subject_name)";
                    $specStmt = $this->db->prepare($specQuery);
                    foreach ($specializationsArray as $spec) {
                        $specStmt->execute([':faculty_id' => $facultyId, ':subject_name' => trim($spec)]);
                    }
                }

                header('Location: /chair/faculty');
                exit;
            }

            require __DIR__ . '/../views/chair/edit_faculty.php';
        } catch (Exception $e) {
            error_log("ChairController error: " . $e->getMessage());
            $this->showError("An error occurred while editing faculty");
        }
    }

    public function settings()
    {
        AuthMiddleware::handle('chair');
        try {
            if (!isset($_SESSION['user']['department_id'])) {
                throw new Exception("Unauthorized access");
            }

            $departmentId = $_SESSION['user']['department_id'];
            $settings = $this->schedulingService->getSettings($departmentId);

            require __DIR__ . '/../views/chair/settings.php';
        } catch (Exception $e) {
            // Log error and show error page
            error_log("ChairController error: " . $e->getMessage());
            $this->showError("An error occurred while loading the settings page");
        }
    }

    public function courses()
    {
        AuthMiddleware::handle('chair');
        try {
            if (!isset($_SESSION['user']['department_id'])) {
                throw new Exception("Unauthorized access");
            }

            $departmentId = $_SESSION['user']['department_id'];

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
                $courseCode = $_POST['course_code'] ?? '';
                $courseName = $_POST['course_name'] ?? '';
                $programId = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
                $units = (int)($_POST['units'] ?? 0);
                $lectureHours = (int)($_POST['lecture_hours'] ?? 0);
                $labHours = (int)($_POST['lab_hours'] ?? 0);
                $semester = $_POST['semester'] ?? '1st';
                $isActive = isset($_POST['is_active']) ? 1 : 0;

                $query = "INSERT INTO courses (course_code, course_name, department_id, program_id, units, lecture_hours, lab_hours, semester, is_active) 
                          VALUES (:course_code, :course_name, :department_id, :program_id, :units, :lecture_hours, :lab_hours, :semester, :is_active)";
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    ':course_code' => $courseCode,
                    ':course_name' => $courseName,
                    ':department_id' => $departmentId,
                    ':program_id' => $programId,
                    ':units' => $units,
                    ':lecture_hours' => $lectureHours,
                    ':lab_hours' => $labHours,
                    ':semester' => $semester,
                    ':is_active' => $isActive
                ]);

                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Course added successfully'];
                header('Location: /chair/courses');
                exit;
            }

            $courses = $this->schedulingService->getDepartmentCourses($departmentId);
            $programs = $this->schedulingService->getDepartmentPrograms($departmentId);
            $stats = ['pendingApprovals' => count($this->schedulingService->getPendingApprovals($departmentId))];

            // Extract view path from class name
            $viewPath = str_replace('Controller', '', basename(get_class($this)));
            $viewFile = strtolower($viewPath) . '/courses.php';
            $fullPath = __DIR__ . '/../views/' . $viewFile;

            // Verify view exists
            if (!file_exists($fullPath)) {
                throw new Exception("View file not found: $viewFile");
            }

            require $fullPath;
        } catch (Exception $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            header('Location: /chair/courses');
            exit;
        }
    }

    public function editCourse()
    {
        AuthMiddleware::handle('chair');
        try {
            if (!isset($_SESSION['user']['department_id'])) {
                throw new Exception("Unauthorized access");
            }

            $departmentId = $_SESSION['user']['department_id'];
            $courseId = $_GET['id'] ?? null;
            if (!$courseId) {
                throw new Exception("Course ID not provided");
            }

            $query = "SELECT * FROM courses WHERE course_id = :course_id AND department_id = :department_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':course_id' => $courseId, ':department_id' => $departmentId]);
            $course = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$course) {
                throw new Exception("Course not found");
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $courseCode = $_POST['course_code'] ?? $course['course_code'];
                $courseName = $_POST['course_name'] ?? $course['course_name'];
                $programId = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
                $units = (int)($_POST['units'] ?? $course['units']);
                $lectureHours = (int)($_POST['lecture_hours'] ?? $course['lecture_hours']);
                $labHours = (int)($_POST['lab_hours'] ?? $course['lab_hours']);
                $semester = $_POST['semester'] ?? $course['semester'];
                $isActive = isset($_POST['is_active']) ? 1 : 0;

                $updateQuery = "UPDATE courses SET 
                                course_code = :course_code, 
                                course_name = :course_name, 
                                program_id = :program_id, 
                                units = :units, 
                                lecture_hours = :lecture_hours, 
                                lab_hours = :lab_hours, 
                                semester = :semester, 
                                is_active = :is_active 
                                WHERE course_id = :course_id AND department_id = :department_id";
                $stmt = $this->db->prepare($updateQuery);
                $stmt->execute([
                    ':course_code' => $courseCode,
                    ':course_name' => $courseName,
                    ':program_id' => $programId,
                    ':units' => $units,
                    ':lecture_hours' => $lectureHours,
                    ':lab_hours' => $labHours,
                    ':semester' => $semester,
                    ':is_active' => $isActive,
                    ':course_id' => $courseId,
                    ':department_id' => $departmentId
                ]);

                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Course updated successfully'];
                header('Location: /chair/courses');
                exit;
            }

            $programs = $this->schedulingService->getDepartmentPrograms($departmentId);
            $stats = ['pendingApprovals' => count($this->schedulingService->getPendingApprovals($departmentId))];
            require __DIR__ . '/../views/chair/edit_course.php';
        } catch (Exception $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            header('Location: /chair/courses');
            exit;
        }
    }

    // In ChairController.php
    public function generateSchedule()
    {
        AuthMiddleware::handle('chair');

        try {
            if (!isset($_SESSION['user']['department_id'])) {
                throw new Exception("Unauthorized access");
            }

            $departmentId = $_SESSION['user']['department_id'];
            $schedulingService = new SchedulingService();

            // Get data for the view
            $semesters = $this->getAllSemesters();
            $currentSemester = $schedulingService->getCurrentSemester();
            $selectedSemesterId = $currentSemester['semester_id'];

            // Handle form submissions
            $generatedSchedule = [];
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (isset($_POST['generate'])) {
                    $selectedSemesterId = (int)($_POST['semester_id'] ?? $selectedSemesterId);
                    $maxSections = (int)($_POST['max_sections'] ?? 5);
                    $algorithm = $_POST['algorithm'] ?? 'basic';
                    $constraints = $_POST['constraints'] ?? [];

                    $generatedSchedule = $schedulingService->generateSchedule(
                        $selectedSemesterId,
                        $departmentId,
                        $maxSections,
                        $constraints
                    );

                    // Store in session for later saving
                    $_SESSION['generated_schedule'] = $generatedSchedule;
                    $_SESSION['generated_semester'] = $selectedSemesterId;
                } elseif (isset($_POST['save_schedule'])) {
                    $scheduleData = json_decode($_POST['schedule_data'], true);
                    $selectedSemesterId = (int)($_POST['semester_id'] ?? $selectedSemesterId);

                    if ($schedulingService->saveGeneratedSchedule($scheduleData, $selectedSemesterId)) {
                        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Schedule saved successfully'];
                        header("Location: semester_id=$selectedSemesterId");
                        exit;
                    } else {
                        throw new Exception("Failed to save schedule");
                    }
                }
            }

            // Other data
            $courses = $schedulingService->getDepartmentCourses($departmentId);
            $faculty = $schedulingService->getFacultyMembers($departmentId);
            $classrooms = $schedulingService->getAvailableClassrooms();
            $stats = ['pendingApprovals' => count($schedulingService->getPendingApprovals($departmentId))];

            // Include the view with all prepared data
            require __DIR__ . '/../views/chair/generate_schedule.php';
        } catch (Exception $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            header("Location: /chair/generate_schedule");
            exit;
        }
    }

    public function createOfferings()
    {
        AuthMiddleware::handle('chair');

        try {
            $departmentId = $_SESSION['user']['department_id'];
            $semesterId = $_POST['semester_id'] ?? null;

            if (!$semesterId) {
                throw new Exception("Semester ID is required");
            }

            $schedulingService = new SchedulingService();
            $result = $schedulingService->createDefaultOffering($semesterId, $departmentId);

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => "Successfully created offerings for " . count($result) . " courses"
            ];

            header("Location: /chair/generate_schedule?semester_id=$semesterId");
            exit;
        } catch (Exception $e) {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => $e->getMessage()
            ];
            header("Location: /chair/generate_schedule");
            exit;
        }
    }

    private function getAllSemesters()
    {
        $query = "SELECT semester_id, semester_name, academic_year, is_current FROM semesters ORDER BY year_start DESC, semester_name";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getFacultyCount($departmentId)
    {
        $query = "SELECT COUNT(*) as count FROM faculty WHERE department_id = :departmentId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':departmentId', $departmentId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }

    private function getCourseCount($departmentId)
    {
        $query = "SELECT COUNT(*) as count FROM courses 
                 WHERE department_id = :departmentId AND is_active = TRUE";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':departmentId', $departmentId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }

    private function getPendingApprovalCount($departmentId)
    {
        $query = "SELECT COUNT(*) as count FROM schedules s
              JOIN courses c ON s.course_id = c.course_id
              WHERE c.department_id = :departmentId
              AND s.status = 'Pending'";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':departmentId', $departmentId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }

    private function getCurrentSemester()
    {
        $query = "SELECT * FROM semesters WHERE is_current = TRUE LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?? [];
    }

    public function curriculum()
    {
        AuthMiddleware::handle('chair');
        try {
            if (!isset($_SESSION['user']['department_id'])) {
                throw new Exception("Unauthorized access");
            }

            $departmentId = $_SESSION['user']['department_id'];
            $curricula = $this->schedulingService->getDepartmentCurricula($departmentId);
            $courses = $this->schedulingService->getDepartmentCourses($departmentId);

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_curriculum'])) {
                $curriculumId = null;
                if (isset($_FILES['curriculum_file']) && $_FILES['curriculum_file']['error'] === UPLOAD_ERR_OK) {
                    // File upload
                    $curriculumId = $this->schedulingService->createCurriculumFromFile(
                        $departmentId,
                        $_FILES['curriculum_file'],
                        $_SESSION['user']['user_id']
                    );
                } else {
                    // Manual entry
                    $data = [
                        'name' => $_POST['name'] ?? throw new Exception("Curriculum name required"),
                        'code' => $_POST['code'] ?? throw new Exception("Curriculum code required"),
                        'effective_year' => $_POST['effective_year'] ?? throw new Exception("Effective year required"),
                        'total_units' => $_POST['total_units'] ?? throw new Exception("Total units required"),
                        'program_name' => $_POST['program_name'] ?? throw new Exception("Program name required"),
                        'courses' => $_POST['courses'] ?? []
                    ];
                    $curriculumId = $this->schedulingService->createCurriculumManually(
                        $departmentId,
                        $data,
                        $_SESSION['user']['user_id']
                    );
                }

                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Curriculum created successfully'];
                header('Location: /chair/curriculum');
                exit;
            }

            require __DIR__ . '/../views/chair/curriculum.php';
        } catch (Exception $e) {
            error_log("ChairController error: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            header('Location: /chair/curriculum');
            exit;
        }
    }

    public function uploadCurriculumFile()
    {
        AuthMiddleware::handle('chair');
        try {
            if (!isset($_SESSION['user']['department_id'])) {
                throw new Exception("Unauthorized access");
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file']) && isset($_FILES['curriculum_file'])) {
                $curriculumId = $_POST['curriculum_id'] ?? throw new Exception("Curriculum ID required");

                $query = "SELECT 1 FROM curricula WHERE curriculum_id = :curriculum_id AND department_id = :department_id";
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    ':curriculum_id' => $curriculumId,
                    ':department_id' => $_SESSION['user']['department_id']
                ]);
                if (!$stmt->fetch()) {
                    throw new Exception("Curriculum not found or not accessible");
                }

                $this->schedulingService->updateCurriculumFile(
                    $curriculumId,
                    $_FILES['curriculum_file'],
                    $_SESSION['user']['user_id']
                );

                $_SESSION['flash'] = ['type' => 'success', 'message' => 'File uploaded successfully'];
                header('Location: /chair/curriculum');
                exit;
            }

            throw new Exception("Invalid request");
        } catch (Exception $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            header('Location: /chair/curriculum');
            exit;
        }
    }

    public function curriculumVersions()
    {
        AuthMiddleware::handle('chair');

        try {
            if (!isset($_SESSION['user']['department_id'])) {
                throw new Exception("Department ID not found in session");
            }

            $departmentId = $_SESSION['user']['department_id'];
            $curriculumId = $_GET['id'] ?? null;

            if (!$curriculumId) {
                throw new Exception("Curriculum ID not provided");
            }

            // Get curriculum versions
            $versions = $this->schedulingService->getCurriculumVersions($curriculumId);
            $currentVersion = $this->schedulingService->getCurrentCurriculumVersion($curriculumId);

            require __DIR__ . '/../views/chair/curriculum_versions.php';
        } catch (Exception $e) {
            error_log("ChairController curriculumVersions error: " . $e->getMessage());
            $this->showError("An error occurred while loading curriculum versions");
        }
    }

    public function newCurriculum()
    {
        AuthMiddleware::handle('chair');

        try {
            if (!isset($_SESSION['user']['department_id'])) {
                throw new Exception("Department ID not found in session");
            }

            $departmentId = $_SESSION['user']['department_id'];
            $courses = $this->schedulingService->getCourses($departmentId);

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $data = [
                    'name' => $_POST['name'] ?? '',
                    'code' => $_POST['code'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'courses' => $_POST['courses'] ?? []
                ];

                $result = $this->schedulingService->createCurriculum($departmentId, $data);

                if ($result) {
                    $_SESSION['success'] = "Curriculum created successfully!";
                    header("Location: /chair/curriculum");
                    exit;
                }
            }

            require __DIR__ . '/../views/chair/new_curriculum.php';
        } catch (Exception $e) {
            error_log("ChairController newCurriculum error: " . $e->getMessage());
            $_SESSION['error'] = "Failed to create curriculum: " . $e->getMessage();
            $this->showError("An error occurred while creating new curriculum");
        }
    }

    public function approvals()
    {
        AuthMiddleware::handle('chair');
        try {
            if (!isset($_SESSION['user']['department_id'])) {
                $_SESSION['error'] = "Department ID not set. Please log in again.";
                header('Location: /login');
                exit;
            }

            $departmentId = $_SESSION['user']['department_id'];

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $action = $_POST['action'] ?? '';
                $scheduleId = (int)($_POST['schedule_id'] ?? 0);

                if ($action === 'approve' || $action === 'reject') {
                    $status = $action === 'approve' ? 'approved' : 'rejected';
                    $this->schedulingService->updateScheduleStatus($scheduleId, $status, $departmentId);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => "Schedule $status successfully"];
                    header('Location: /chair/approvals');
                    exit;
                }
            }

            $pendingApprovals = $this->schedulingService->getPendingApprovals($departmentId);
            $stats = ['pendingApprovals' => count($pendingApprovals)];
            $currentUri = '/chair/approvals'; // For sidebar highlighting
            require __DIR__ . '/../views/chair/approvals.php';
        } catch (Exception $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            header('Location: /chair/approvals');
            exit;
        }
    }

    public function reports()
    {
        AuthMiddleware::handle('chair');
        try {
            if (!isset($_SESSION['user']['department_id'])) {
                $_SESSION['error'] = "Department ID not set. Please log in again.";
                header('Location: /login');
                exit;
            }

            $departmentId = $_SESSION['user']['department_id'];

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_type'])) {
                $reportType = $_POST['report_type'];
                if ($reportType === 'schedule') {
                    $this->schedulingService->generateScheduleReport($departmentId);
                } elseif ($reportType === 'faculty_load') {
                    $this->schedulingService->generateFacultyLoadReport($departmentId);
                }
                exit;
            }

            $stats = ['pendingApprovals' => count($this->schedulingService->getPendingApprovals($departmentId))];
            $currentUri = '/chair/reports'; // For sidebar highlighting
            require __DIR__ . '/../views/chair/report.php';
        } catch (Exception $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            header('Location: /chair/reports');
            exit;
        }
    }

    private function generateTimeSlots()
    {
        return [
            '07:00 AM' => '07:00 AM',
            '08:00 AM' => '08:00 AM',
            '09:00 AM' => '09:00 AM',
            '10:00 AM' => '10:00 AM',
            '11:00 AM' => '11:00 AM',
            '12:00 PM' => '12:00 PM',
            '01:00 PM' => '01:00 PM',
            '02:00 PM' => '02:00 PM',
            '03:00 PM' => '03:00 PM',
            '04:00 PM' => '04:00 PM',
            '05:00 PM' => '05:00 PM',
            '06:00 PM' => '06:00 PM'
        ];
    }

    private function showError($message)
    {
        // You could create a dedicated error view file
        echo "<div class='alert alert-danger'>$message</div>";
    }
}
?>