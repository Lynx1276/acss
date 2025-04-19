<?php
require_once __DIR__ . '/../config/Database.php';

use App\config\Database;

class FacultyService
{
    private $db;

    public function __construct()
    {
        $this->db = (new Database())->connect();
    }

    public function getFacultyAvailabilityForSemester($facultyId, $semesterId)
    {
        error_log("Fetching availability for faculty_id=$facultyId, semester_id=$semesterId");

        $query = "SELECT * FROM faculty_availability 
             WHERE faculty_id = :faculty_id 
             AND semester_id = :semester_id
             ORDER BY day_of_week, start_time";

        $stmt = $this->db->prepare($query);
        if (!$stmt) {
            error_log("Prepare error: " . print_r($this->db->errorInfo(), true));
            return [];
        }

        $success = $stmt->execute([
            ':faculty_id' => $facultyId,
            ':semester_id' => $semesterId
        ]);

        if (!$success) {
            error_log("Execute error: " . print_r($stmt->errorInfo(), true));
            return [];
        }

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Found records: " . print_r($results, true));

        return $results;
    }

    public function getFacultySchedule($facultyId, $semesterId = null, $forDropdown = false)
    {
        try {
            $query = "SELECT 
                        s.schedule_id,
                        c.course_code,
                        c.course_name,
                        r.room_name,
                        r.building,
                        sec.section_name,
                        TIME_FORMAT(s.start_time, '%h:%i %p') as start_time_display,
                        TIME_FORMAT(s.end_time, '%h:%i %p') as end_time_display,
                        s.day_of_week,
                        s.status,
                        CONCAT(sem.semester_name, ' ', sem.academic_year) AS semester_name,
                        CASE s.day_of_week
                            WHEN 'Monday' THEN 1
                            WHEN 'Tuesday' THEN 2
                            WHEN 'Wednesday' THEN 3
                            WHEN 'Thursday' THEN 4
                            WHEN 'Friday' THEN 5
                            WHEN 'Saturday' THEN 6
                            WHEN 'Sunday' THEN 7
                        END as day_order
                      FROM schedules s
                      JOIN courses c ON s.course_id = c.course_id
                      LEFT JOIN classrooms r ON s.room_id = r.room_id
                      LEFT JOIN sections sec ON s.section_id = sec.section_id
                      JOIN semesters sem ON s.semester_id = sem.semester_id
                      WHERE s.faculty_id = :faculty_id";

            if ($semesterId) {
                $query .= " AND s.semester_id = :semester_id";
            } elseif (!$forDropdown) {
                $query .= " AND sem.is_current = 1"; // Default to current semester unless for dropdown
            }

            $query .= " ORDER BY day_order, s.start_time";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':faculty_id', $facultyId, PDO::PARAM_INT);
            if ($semesterId) {
                $stmt->bindParam(':semester_id', $semesterId, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch faculty schedule: " . $e->getMessage());
            return [];
        }
    }

    public function submitScheduleRequest($facultyId, $scheduleId, $requestType, $details)
    {
        try {
            $query = "INSERT INTO schedule_requests (faculty_id, schedule_id, request_type, details, status, created_at)
                      VALUES (:faculty_id, :schedule_id, :request_type, :details, 'pending', NOW())";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':faculty_id' => $facultyId,
                ':schedule_id' => $scheduleId,
                ':request_type' => $requestType,
                ':details' => $details
            ]);
        } catch (Exception $e) {
            error_log("Failed to submit request: " . $e->getMessage());
            throw $e;
        }
    }

    public function getFacultyRequests($facultyId)
    {
        try {
            $query = "SELECT sr.request_id, c.course_code, 
                         CONCAT(s.day_of_week, ' ', TIME_FORMAT(s.start_time, '%h:%i %p'), '-', TIME_FORMAT(s.end_time, '%h:%i %p')) AS time_slot,
                         sr.request_type, sr.details, sr.status, sr.created_at
                  FROM schedule_requests sr
                  JOIN schedules s ON sr.schedule_id = s.schedule_id
                  JOIN courses c ON s.course_id = c.course_id
                  WHERE sr.faculty_id = :faculty_id
                  ORDER BY sr.created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':faculty_id' => $facultyId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch faculty requests: " . $e->getMessage());
            return [];
        }
    }

    public function getFacultyStats($facultyId, $semesterId = null)
    {
        try {
            $stats = ['totalCourses' => 0, 'totalHours' => 0, 'pendingRequests' => 0];

            $query = "SELECT COUNT(s.schedule_id) AS total_courses, 
                             SUM(c.lecture_hours + c.lab_hours) AS total_hours
                      FROM schedules s
                      JOIN courses c ON s.course_id = c.course_id
                      JOIN semesters sem ON s.semester_id = sem.semester_id
                      WHERE s.faculty_id = :faculty_id";
            if ($semesterId) {
                $query .= " AND s.semester_id = :semester_id";
            } else {
                $query .= " AND sem.is_current = 1";
            }
            $stmt = $this->db->prepare($query);
            $params = [':faculty_id' => $facultyId];
            if ($semesterId) {
                $params[':semester_id'] = $semesterId;
            }
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['totalCourses'] = $result['total_courses'] ?? 0;
            $stats['totalHours'] = $result['total_hours'] ?? 0;

            $query = "SELECT COUNT(*) AS pending 
                      FROM schedules s
                      JOIN semesters sem ON s.semester_id = sem.semester_id
                      WHERE s.faculty_id = :faculty_id AND s.status = 'pending'";
            if ($semesterId) {
                $query .= " AND s.semester_id = :semester_id";
            } else {
                $query .= " AND sem.is_current = 1";
            }
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $stats['pendingRequests'] = $stmt->fetchColumn() ?? 0;

            return $stats;
        } catch (Exception $e) {
            error_log("Failed to fetch faculty stats: " . $e->getMessage());
            return ['totalCourses' => 0, 'totalHours' => 0, 'pendingRequests' => 0];
        }
    }

    // Additional methods would follow the same pattern...
    // public function getFacultyProfile($facultyId) { ... }
    public function getFacultyProfile($facultyId)
    {
        try {
            $query = "SELECT faculty_id, first_name, last_name, email, phone, position, employment_type, department_id
                      FROM faculty
                      WHERE faculty_id = :faculty_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':faculty_id', $facultyId, PDO::PARAM_INT);
            $stmt->execute();
            $faculty = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$faculty) {
                throw new Exception("Faculty profile not found");
            }
            return $faculty;
        } catch (Exception $e) {
            error_log("Failed to fetch faculty profile: " . $e->getMessage());
            throw $e;
        }
    }
    // public function updateFacultyProfile($facultyId, $firstName, $lastName, $email, $phone) { ... }
    public function updateFacultyProfile($facultyId, $firstName, $lastName, $email, $phone)
    {
        try {
            $query = "UPDATE faculty 
                      SET first_name = :first_name, last_name = :last_name, email = :email, phone = :phone, updated_at = NOW()
                      WHERE faculty_id = :faculty_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':faculty_id' => $facultyId,
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':email' => $email,
                ':phone' => $phone ?: null // Handle empty phone as NULL
            ]);
        } catch (Exception $e) {
            error_log("Failed to update faculty profile: " . $e->getMessage());
            throw $e;
        }
    }
    // public function getFacultySpecializations($facultyId) { ... }
    // public function addFacultySpecialization($facultyId, $subjectName, $expertiseLevel) { ... }
    // public function deleteFacultySpecialization($facultyId, $specializationId) { ... }
    // public function updateFacultySpecialization($facultyId, $specializationId, $subjectName, $expertiseLevel) { ... }
    public function getFacultySpecializations($facultyId)
    {
        try {
            $query = "SELECT specialization_id, subject_name, expertise_level
                      FROM specializations
                      WHERE faculty_id = :faculty_id
                      ORDER BY created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':faculty_id', $facultyId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch faculty specializations: " . $e->getMessage());
            return [];
        }
    }

    public function addFacultySpecialization($facultyId, $subjectName, $expertiseLevel)
    {
        try {
            $query = "INSERT INTO specializations (faculty_id, subject_name, expertise_level, created_at)
                      VALUES (:faculty_id, :subject_name, :expertise_level, NOW())";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':faculty_id' => $facultyId,
                ':subject_name' => $subjectName,
                ':expertise_level' => $expertiseLevel
            ]);
        } catch (Exception $e) {
            error_log("Failed to add faculty specialization: " . $e->getMessage());
            throw $e;
        }
    }

    public function deleteFacultySpecialization($facultyId, $specializationId)
    {
        try {
            $query = "DELETE FROM specializations 
                      WHERE specialization_id = :specialization_id AND faculty_id = :faculty_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':specialization_id' => $specializationId,
                ':faculty_id' => $facultyId
            ]);
            if ($stmt->rowCount() === 0) {
                throw new Exception("Specialization not found or not owned by this faculty");
            }
        } catch (Exception $e) {
            error_log("Failed to delete faculty specialization: " . $e->getMessage());
            throw $e;
        }
    }

    // Placeholder for edit (to be expanded later if needed)
    public function updateFacultySpecialization($facultyId, $specializationId, $subjectName, $expertiseLevel)
    {
        try {
            $query = "UPDATE specializations 
                      SET subject_name = :subject_name, expertise_level = :expertise_level
                      WHERE specialization_id = :specialization_id AND faculty_id = :faculty_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':specialization_id' => $specializationId,
                ':faculty_id' => $facultyId,
                ':subject_name' => $subjectName,
                ':expertise_level' => $expertiseLevel
            ]);
            if ($stmt->rowCount() === 0) {
                throw new Exception("Specialization not found or not owned by this faculty");
            }
        } catch (Exception $e) {
            error_log("Failed to update faculty specialization: " . $e->getMessage());
            throw $e;
        }
    }
    // public function getFacultyByDepartment($departmentId) { ... }
    // public function addFacultyLoad($facultyId, $courseId, $semesterId, $roomId, $timeSlots) { ... }
}
