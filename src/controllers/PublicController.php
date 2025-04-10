<?php
require_once __DIR__ . '/../config/Database.php';

class PublicController
{
    private $db;

    public function __construct()
    {
        $this->db = (new Database())->connect();
    }

    public function showHomepage()
    {
        // Get current semester
        $currentSemester = $this->getCurrentSemester();

        // Get public schedules (using current semester if available)
        $semesterId = $currentSemester['semester_id'] ?? null;
        $schedules = $this->getPublicSchedules($semesterId);

        // Get list of departments and semesters for filtering
        $departments = $this->getDepartments();
        $semesters = $this->getAllSemesters(); // Add this line

        require __DIR__ . '/../views/public/home.php';
    }

    public function searchSchedules()
    {
        $semesterId = $_GET['semester_id'] ?? null;
        $departmentId = $_GET['department_id'] ?? null;
        $courseCode = $_GET['course_code'] ?? null;

        $schedules = $this->getPublicSchedules($semesterId, $departmentId, $courseCode);
        $departments = $this->getDepartments();
        $semesters = $this->getAllSemesters();

        require __DIR__ . '/../views/public/home.php';
    }

    // Removed duplicate showHomepage method

    private function getPublicSchedules($semesterId = null, $departmentId = null, $courseCode = null)
    {
        $sql = "SELECT 
                    s.schedule_id, s.day_of_week, s.start_time, s.end_time,
                    c.course_code, c.course_name,
                    sec.section_name,
                    r.room_name, r.building,
                    d.department_name,
                    CONCAT(f.first_name, ' ', f.last_name) AS faculty_name
                FROM schedules s
                JOIN sections sec ON s.section_id = sec.section_id
                JOIN courses c ON sec.course_id = c.course_id
                JOIN departments d ON c.department_id = d.department_id
                LEFT JOIN faculty f ON s.faculty_id = f.faculty_id
                LEFT JOIN classrooms r ON s.room_id = r.room_id
                WHERE s.is_public = TRUE";

        $params = [];

        if ($semesterId) {
            $sql .= " AND s.semester_id = ?";
            $params[] = $semesterId;
        }

        if ($departmentId) {
            $sql .= " AND c.department_id = ?";
            $params[] = $departmentId;
        }

        if ($courseCode) {
            $sql .= " AND c.course_code LIKE ?";
            $params[] = "%$courseCode%";
        }

        $sql .= " ORDER BY s.day_of_week, s.start_time";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getCurrentSemester()
    {
        $stmt = $this->db->query("SELECT * FROM semesters WHERE is_current = TRUE LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getAllSemesters()
    {
        $stmt = $this->db->query("SELECT * FROM semesters ORDER BY year_start DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getDepartments()
    {
        $stmt = $this->db->query("SELECT * FROM departments ORDER BY department_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
