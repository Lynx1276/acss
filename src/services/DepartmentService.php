<?php
require_once __DIR__ . '/../config/Database.php';

use App\config\Database;

class DepartmentService
{
    private $db;

    public function __construct()
    {
        $this->db = (new Database())->connect();
    }

    public function getDepartmentSchedule($departmentId, $semesterId)
    {
        $query = "SELECT 
                    s.*,
                    c.course_code,
                    c.course_name,
                    CONCAT(f.first_name, ' ', f.last_name) AS faculty_name,
                    r.room_name,
                    r.building,
                    sec.section_name,
                    sec.year_level,
                    CASE 
                        WHEN s.schedule_type = 'F2F' THEN 'Face-to-Face'
                        WHEN s.schedule_type = 'Online' THEN 'Online'
                        WHEN s.schedule_type = 'Hybrid' THEN 'Hybrid'
                        WHEN s.schedule_type = 'Asynchronous' THEN 'Asynchronous'
                    END as schedule_type_display,
                    TIME_FORMAT(s.start_time, '%h:%i %p') as start_time_display,
                    TIME_FORMAT(s.end_time, '%h:%i %p') as end_time_display
                  FROM schedules s
                  JOIN courses c ON s.course_id = c.course_id
                  JOIN faculty f ON s.faculty_id = f.faculty_id
                  LEFT JOIN classrooms r ON s.room_id = r.room_id
                  LEFT JOIN sections sec ON s.section_id = sec.section_id
                  WHERE c.department_id = :departmentId 
                  AND s.semester_id = :semesterId
                  ORDER BY s.day_of_week, s.start_time";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':departmentId', $departmentId);
        $stmt->bindParam(':semesterId', $semesterId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingApprovals($departmentId)
    {
        $query = "SELECT 
                s.schedule_id,
                c.course_code,
                c.course_name,
                CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
                r.room_name,
                r.building,
                s.day_of_week,
                TIME_FORMAT(s.start_time, '%h:%i %p') as start_time,
                TIME_FORMAT(s.end_time, '%h:%i %p') as end_time,
                s.status,
                s.created_at
              FROM schedules s
              JOIN courses c ON s.course_id = c.course_id
              JOIN faculty f ON s.faculty_id = f.faculty_id
              LEFT JOIN classrooms r ON s.room_id = r.room_id
              WHERE c.department_id = :departmentId
              AND s.status = 'Pending'
              ORDER BY s.created_at DESC";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':departmentId', $departmentId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSettings($departmentId)
    {
        $query = "SELECT 
                    s.*,
                    d.department_name,
                    col.college_name
                  FROM settings s
                  JOIN departments d ON s.department_id = d.department_id
                  JOIN colleges col ON d.college_id = col.college_id
                  WHERE s.department_id = :departmentId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':departmentId', $departmentId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getCourses($departmentId)
    {
        $query = "SELECT 
                    c.*,
                    d.department_name,
                    (SELECT COUNT(*) FROM course_offerings co 
                     WHERE co.course_id = c.course_id) as offerings_count
                  FROM courses c
                  JOIN departments d ON c.department_id = d.department_id
                  WHERE c.department_id = :departmentId 
                  AND c.is_active = TRUE
                  ORDER BY c.course_code";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':departmentId', $departmentId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRoomUtilization($semesterId)
    {
        $query = "SELECT 
                    r.room_id,
                    r.room_name,
                    r.building,
                    r.capacity,
                    r.is_lab,
                    COUNT(s.schedule_id) as scheduled_classes,
                    SEC_TO_TIME(SUM(TIME_TO_SEC(TIMEDIFF(s.end_time, s.start_time)))) as total_hours,
                    (SELECT COUNT(*) FROM room_reservations rr 
                     WHERE rr.room_id = r.room_id 
                     AND rr.approval_status = 'Approved') as reservations_count
                  FROM classrooms r
                  LEFT JOIN schedules s ON r.room_id = s.room_id AND s.semester_id = :semesterId
                  WHERE r.is_active = TRUE
                  GROUP BY r.room_id
                  ORDER BY r.building, r.room_name";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':semesterId', $semesterId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSemesterInfo($semesterId)
    {
        $query = "SELECT 
                    s.*,
                    (SELECT COUNT(*) FROM course_offerings co 
                     WHERE co.semester_id = s.semester_id) as course_count,
                    (SELECT COUNT(*) FROM schedules sc 
                     WHERE sc.semester_id = s.semester_id) as schedule_count,
                    (SELECT COUNT(DISTINCT sc.faculty_id) FROM schedules sc 
                     WHERE sc.semester_id = s.semester_id) as faculty_count
                  FROM semesters s
                  WHERE s.semester_id = :semesterId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':semesterId', $semesterId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Additional methods would follow the same pattern...
    // public function ule($scheduleIds, $approvedBy) { ... }
    // public function getCurrentSemester() { ... }
    // public function getFacultyCount($departmentId) { ... }
    // public function getActiveCourseCount($departmentId) { ... }
    // public function getPendingScheduleApprovals($departmentId) { ... }
    // public function getScheduleConflicts($departmentId, $semesterId) { ... }
    // public function getRecentScheduleChanges($departmentId, $limit = 5) { ... }
    // public function getClassroomUtilization($departmentId, $semesterId) { ... }
    // public function getDepartmentCurricula($departmentId) { ... }
    // public function getCurriculumVersions($curriculumId) { ... }
    // public function getCurrentCurriculumVersion($curriculumId) { ... }
    // public function createCurriculum($departmentId, $data) { ... }
    // public function getDepartmentCourses($departmentId) { ... }
    // public function getDepartmentPrograms($departmentId) { ... }
    // public function updateScheduleStatus($scheduleId, $status, $departmentId) { ... }
    // public function generateScheduleReport($departmentId) { ... }
    // public function getPendingRequestsByCollege($collegeId) { ... }
    // public function getCollegeFacultyStats($collegeId) { ... }
    // public function getPendingRequestsByDepartment($collegeId) { ... }
    // public function getDepartmentFacultyStats($departmentId) { ... }
    // public function getDepartmentSchedules($departmentId, $semesterId = null) { ... }
    // public function updateRequestStatus($requestId, $status, $approvedBy) { ... }
    // private function logActivity($userId, $actionType, $description, $entityType, $entityId) { ... }
    // public function generateFacultyLoadReport($departmentId) { ... }
    // public function createCurriculumWithFile($departmentId, $data, $uploadedBy) { ... }
    // public function updateCurriculumFile($curriculumId, $file, $uploadedBy, $versionNotes) { ... }
    // private function isValidCourseCode($code) { ... }
    // private function getCourseByCode($courseCode, $departmentId = null) { ... }
    // private function parseTextLines($text, $departmentId = null) { ... }
    // private function validateYearLevel($yearLevel) { ... }
    // private function validateSemester($semester) { ... }
    // public function getLatestCurriculumVersion($curriculumId) { ... }
    // public function getDepartmentById($departmentId) { ... }
}
