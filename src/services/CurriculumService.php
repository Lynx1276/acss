<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../services/SchedulingService.php';

use App\config\Database;

class CurriculumService
{
    private $db;
    private $schedulingService;

    public function __construct()
    {
        $this->db = (new Database())->connect();
        $this->schedulingService = new SchedulingService();
    }

    /**
     * Retrieve all curricula for a department
     * @param int $departmentId
     * @return array
     */
    public function getDepartmentCurricula($departmentId)
    {
        $query = "SELECT curriculum_id, curriculum_name, curriculum_code, effective_year, total_units, status
                  FROM curricula 
                  WHERE department_id = :department_id 
                  ORDER BY effective_year DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':department_id' => $departmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve all courses for a department
     * @param int $departmentId
     * @return array
     */
    public function getDepartmentCourses($departmentId)
    {
        $query = "SELECT course_id, course_code, course_name, units, semester, year_level
                  FROM courses 
                  WHERE department_id = :department_id AND is_active = 1 
                  ORDER BY course_code";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':department_id' => $departmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve all departments
     * @return array
     */
    public function getAllDepartments()
    {
        $query = "SELECT department_id, department_name 
                  FROM departments 
                  ORDER BY department_name";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a course by ID
     * @param int $courseId
     * @return array|null
     */
    public function getCourseById($courseId)
    {
        $query = "SELECT course_id, course_code, course_name, units, lecture_hours, lab_hours, semester, year_level
                  FROM courses 
                  WHERE course_id = :course_id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':course_id' => $courseId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function searchCourses($searchTerm, $departmentId = null)
    {
        $query = "SELECT c.course_id, c.course_code, c.course_name, c.units, d.department_name, c.year_level, c.semester
                  FROM courses c
                  JOIN departments d ON c.department_id = d.department_id
                  WHERE (c.course_code LIKE :search_term OR c.course_name LIKE :search_term)";
        $params = ['search_term' => "%$searchTerm%"];
        if ($departmentId) {
            $query .= " AND c.department_id = :department_id";
            $params['department_id'] = $departmentId;
        }
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createCurriculumManually($departmentId, $data, $userId)
    {
        $this->db->beginTransaction();
        try {
            $query = "INSERT INTO curricula (curriculum_name, curriculum_code, description, total_units, department_id, effective_year, status, created_at, updated_at)
                      VALUES (:name, :code, :description, :total_units, :department_id, :effective_year, 'Draft', NOW(), NOW())";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'name' => $data['name'],
                'code' => $data['code'],
                'description' => $data['program_name'],
                'total_units' => 0, // Will be updated
                'department_id' => $departmentId,
                'effective_year' => $data['effective_year']
            ]);
            $curriculumId = $this->db->lastInsertId();

            $totalUnits = 0;
            if (!empty($data['courses'])) {
                $query = "INSERT INTO curriculum_courses (curriculum_id, course_id, year_level, semester, subject_type, is_core, created_at)
                          VALUES (:curriculum_id, :course_id, :year_level, :semester, :subject_type, 1, NOW())";
                $stmt = $this->db->prepare($query);
                foreach ($data['courses'] as $courseId => $courseData) {
                    if (isset($courseData['selected'])) {
                        $courseQuery = "SELECT units FROM courses WHERE course_id = :course_id";
                        $courseStmt = $this->db->prepare($courseQuery);
                        $courseStmt->execute(['course_id' => $courseId]);
                        $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
                        $totalUnits += $course['units'];

                        $stmt->execute([
                            'curriculum_id' => $curriculumId,
                            'course_id' => $courseId,
                            'year_level' => $courseData['year_level'],
                            'semester' => $courseData['semester'],
                            'subject_type' => $courseData['subject_type']
                        ]);
                    }
                }
            }

            $updateQuery = "UPDATE curricula SET total_units = :total_units WHERE curriculum_id = :curriculum_id";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->execute(['total_units' => $totalUnits, 'curriculum_id' => $curriculumId]);

            $approvalQuery = "INSERT INTO curriculum_approvals (curriculum_id, requested_by, approval_level, status, created_at, updated_at)
                             VALUES (:curriculum_id, :requested_by, 1, 'Pending', NOW(), NOW())";
            $approvalStmt = $this->db->prepare($approvalQuery);
            $approvalStmt->execute(['curriculum_id' => $curriculumId, 'requested_by' => $userId]);

            $this->db->commit();
            return $curriculumId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function createCourse($data)
    {
        $query = "INSERT INTO courses (course_code, course_name, units, lecture_hours, lab_hours, semester, year_level, department_id, created_at, updated_at)
                  VALUES (:course_code, :course_name, :units, :lecture_hours, :lab_hours, :semester, :year_level, :department_id, NOW(), NOW())";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'course_code' => $data['course_code'],
            'course_name' => $data['course_name'],
            'units' => $data['units'],
            'lecture_hours' => $data['lecture_hours'],
            'lab_hours' => $data['lab_hours'],
            'semester' => $data['semester'],
            'year_level' => $data['year_level'],
            'department_id' => $data['department_id']
        ]);
        return $this->db->lastInsertId();
    }

    public function getCurriculumById($curriculumId)
    {
        $query = "SELECT curriculum_id, curriculum_name, curriculum_code, description, total_units, department_id, effective_year, status
                  FROM curricula WHERE curriculum_id = :curriculum_id";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['curriculum_id' => $curriculumId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getCurriculumCourses($curriculumId)
    {
        $query = "SELECT cc.course_id, c.course_code, c.course_name, c.units, cc.year_level, cc.semester, cc.subject_type
                  FROM curriculum_courses cc
                  JOIN courses c ON cc.course_id = c.course_id
                  WHERE cc.curriculum_id = :curriculum_id";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['curriculum_id' => $curriculumId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateCurriculum($curriculumId, $data, $departmentId)
    {
        $this->db->beginTransaction();
        try {
            $query = "UPDATE curricula 
                      SET curriculum_name = :name, curriculum_code = :code, description = :description, 
                          effective_year = :effective_year, total_units = :total_units, updated_at = NOW()
                      WHERE curriculum_id = :curriculum_id AND department_id = :department_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'name' => $data['name'],
                'code' => $data['code'],
                'description' => $data['program_name'],
                'effective_year' => $data['effective_year'],
                'total_units' => 0, // Will be updated
                'curriculum_id' => $curriculumId,
                'department_id' => $departmentId
            ]);

            $deleteQuery = "DELETE FROM curriculum_courses WHERE curriculum_id = :curriculum_id";
            $deleteStmt = $this->db->prepare($deleteQuery);
            $deleteStmt->execute(['curriculum_id' => $curriculumId]);

            $totalUnits = 0;
            if (!empty($data['courses'])) {
                $insertQuery = "INSERT INTO curriculum_courses (curriculum_id, course_id, year_level, semester, subject_type, is_core, created_at)
                                VALUES (:curriculum_id, :course_id, :year_level, :semester, :subject_type, 1, NOW())";
                $insertStmt = $this->db->prepare($insertQuery);
                foreach ($data['courses'] as $courseId => $courseData) {
                    if (isset($courseData['selected'])) {
                        $courseQuery = "SELECT units FROM courses WHERE course_id = :course_id";
                        $courseStmt = $this->db->prepare($courseQuery);
                        $courseStmt->execute(['course_id' => $courseId]);
                        $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
                        $totalUnits += $course['units'];

                        $insertStmt->execute([
                            'curriculum_id' => $curriculumId,
                            'course_id' => $courseId,
                            'year_level' => $courseData['year_level'],
                            'semester' => $courseData['semester'],
                            'subject_type' => $courseData['subject_type']
                        ]);
                    }
                }
            }

            $updateQuery = "UPDATE curricula SET total_units = :total_units WHERE curriculum_id = :curriculum_id";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->execute(['total_units' => $totalUnits, 'curriculum_id' => $curriculumId]);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteCurriculum($curriculumId, $departmentId)
    {
        $this->db->beginTransaction();
        try {
            $deleteCourses = "DELETE FROM curriculum_courses WHERE curriculum_id = :curriculum_id";
            $stmtCourses = $this->db->prepare($deleteCourses);
            $stmtCourses->execute(['curriculum_id' => $curriculumId]);

            $deleteApprovals = "DELETE FROM curriculum_approvals WHERE curriculum_id = :curriculum_id";
            $stmtApprovals = $this->db->prepare($deleteApprovals);
            $stmtApprovals->execute(['curriculum_id' => $curriculumId]);

            $deleteQuery = "DELETE FROM curricula WHERE curriculum_id = :curriculum_id AND department_id = :department_id";
            $stmt = $this->db->prepare($deleteQuery);
            $stmt->execute(['curriculum_id' => $curriculumId, 'department_id' => $departmentId]);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function toggleCurriculumStatus($curriculumId, $departmentId)
    {
        $curriculum = $this->getCurriculumById($curriculumId);
        if (!$curriculum || $curriculum['department_id'] != $departmentId) {
            throw new Exception("Curriculum not found or unauthorized");
        }
        $newStatus = $curriculum['status'] === 'Active' ? 'Inactive' : 'Active';
        $query = "UPDATE curricula SET status = :status, updated_at = NOW() 
                  WHERE curriculum_id = :curriculum_id AND department_id = :department_id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            'status' => $newStatus,
            'curriculum_id' => $curriculumId,
            'department_id' => $departmentId
        ]);
        return $newStatus;
    }

    public function createCurriculumFromFile($departmentId, $file, $userId)
    {
        // Implement file upload logic as needed
        // Placeholder for existing functionality
        return null;
    }

    public function getCurriculumVersions($curriculumId)
    {
        $query = "SELECT 
                v.version_id,
                v.version_number,
                v.approval_status,
                v.approval_date,
                u.username as approved_by
              FROM curriculum_versions v
              LEFT JOIN users u ON v.approved_by = u.user_id
              WHERE v.curriculum_id = :curriculumId
              ORDER BY v.approval_date DESC";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':curriculumId', $curriculumId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCurrentCurriculumVersion($curriculumId)
    {
        $query = "SELECT 
                v.version_id,
                v.version_number,
                v.approval_status,
                v.approval_date,
                u.username as approved_by
              FROM curriculum_versions v
              LEFT JOIN users u ON v.approved_by = u.user_id
              WHERE v.curriculum_id = :curriculumId
              AND v.approval_status = 'Approved'
              ORDER BY v.approval_date DESC
              LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':curriculumId', $curriculumId);
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

    public function createCurriculum($departmentId, $data)
    {
        $this->db->beginTransaction();

        try {
            // Insert curriculum
            $query = "INSERT INTO curricula (
                    curriculum_name, 
                    curriculum_code, 
                    description, 
                    department_id,
                    status
                  ) VALUES (
                    :name, 
                    :code, 
                    :description, 
                    :departmentId,
                    'Draft'
                  )";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':name' => $data['name'],
                ':code' => $data['code'],
                ':description' => $data['description'],
                ':departmentId' => $departmentId
            ]);

            $curriculumId = $this->db->lastInsertId();

            // Insert version
            $versionQuery = "INSERT INTO curriculum_versions (
                          curriculum_id,
                          version_number,
                          approval_status
                        ) VALUES (
                          :curriculumId,
                          '1.0',
                          'Pending'
                        )";

            $stmt = $this->db->prepare($versionQuery);
            $stmt->execute([':curriculumId' => $curriculumId]);

            // Insert courses
            foreach ($data['courses'] as $courseId) {
                $courseQuery = "INSERT INTO curriculum_courses (
                              curriculum_id,
                              course_id,
                              year_level,
                              semester,
                              is_core
                            ) VALUES (
                              :curriculumId,
                              :courseId,
                              '1st Year',
                              '1st',
                              TRUE
                            )";

                $stmt = $this->db->prepare($courseQuery);
                $stmt->execute([
                    ':curriculumId' => $curriculumId,
                    ':courseId' => $courseId
                ]);
            }

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new Exception("Failed to create curriculum: " . $e->getMessage());
        }
    }

    public function getDefaultCurriculumId($departmentId)
    {
        $query = "SELECT curriculum_id 
                  FROM curricula 
                  WHERE department_id = :department_id 
                  AND status = 'Active' 
                  ORDER BY effective_year DESC 
                  LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':department_id' => $departmentId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            throw new Exception("No active curriculum found for this department");
        }
        return $result['curriculum_id'];
    }


    public function getDepartmentSections($departmentId, $yearLevel = null)
    {
        $query = "SELECT s.section_id, s.section_name, s.year_level, s.semester, s.academic_year, 
                         s.max_students, s.current_students, c.curriculum_name
                  FROM sections s
                  JOIN curricula c ON s.curriculum_id = c.curriculum_id
                  WHERE s.department_id = :department_id AND s.is_active = 1";
        $params = [':department_id' => $departmentId];
        if ($yearLevel) {
            $query .= " AND s.year_level = :year_level";
            $params[':year_level'] = $yearLevel;
        }
        $query .= " ORDER BY s.section_name";
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new section
     * @param array $data
     * @return int Section ID
     * @throws Exception
     */
    public function createSection($data)
    {
        // Validate required fields
        if (empty($data['section_name'])) {
            throw new Exception("Section name is required");
        }
        if (empty($data['year_level'])) {
            throw new Exception("Year level is required");
        }
        if (empty($data['academic_year']) || !preg_match('/^\d{4}-\d{4}$/', $data['academic_year'])) {
            throw new Exception("Academic year must be in format YYYY-YYYY");
        }
        if (empty($data['department_id'])) {
            throw new Exception("Department ID is required");
        }

        // Get default curriculum and semester
        $data['curriculum_id'] = $this->getDefaultCurriculumId($data['department_id']);
        $data['semester'] = $this->schedulingService->getCurrentSemester();

        // Check for duplicate section name within department and academic year
        $query = "SELECT COUNT(*) FROM sections 
                  WHERE section_name = :section_name 
                  AND department_id = :department_id 
                  AND academic_year = :academic_year";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':section_name' => $data['section_name'],
            ':department_id' => $data['department_id'],
            ':academic_year' => $data['academic_year']
        ]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Section name already exists in this department for this academic year");
        }

        $query = "INSERT INTO sections 
                  (section_name, curriculum_id, department_id, year_level, semester, academic_year, 
                   max_students, current_students, is_active, created_at, updated_at)
                  VALUES (:section_name, :curriculum_id, :department_id, :year_level, :semester, 
                          :academic_year, :max_students, 0, 1, NOW(), NOW())";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':section_name' => $data['section_name'],
            ':curriculum_id' => $data['curriculum_id'],
            ':department_id' => $data['department_id'],
            ':year_level' => $data['year_level'],
            ':semester' => $data['semester'],
            ':academic_year' => $data['academic_year'],
            ':max_students' => $data['max_students'] ?? 40
        ]);
        return $this->db->lastInsertId();
    }

    /**
     * Get available courses for a section (from its curriculum)
     * @param int $sectionId
     * @return array
     */
    public function getAvailableCoursesForSection($sectionId)
    {
        $query = "SELECT c.course_id, c.course_code, c.course_name, c.units
                  FROM courses c
                  JOIN curriculum_courses cc ON c.course_id = cc.course_id
                  JOIN sections s ON cc.curriculum_id = s.curriculum_id
                  WHERE s.section_id = :section_id
                  AND cc.year_level = s.year_level
                  AND cc.semester = s.semester
                  AND c.course_id NOT IN (
                      SELECT course_id FROM section_courses WHERE section_id = :section_id
                  )
                  ORDER BY c.course_code";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':section_id' => $sectionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Assign courses to a section
     * @param int $sectionId
     * @param array $courses
     * @throws Exception
     */
    public function assignCoursesToSection($sectionId, $courses)
    {
        try {
            $this->db->beginTransaction();

            foreach ($courses as $courseId => $courseData) {
                if (isset($courseData['selected']) && $courseData['selected']) {
                    $query = "SELECT COUNT(*) 
                              FROM curriculum_courses cc
                              JOIN sections s ON cc.curriculum_id = s.curriculum_id
                              WHERE s.section_id = :section_id
                              AND cc.course_id = :course_id
                              AND cc.year_level = s.year_level
                              AND cc.semester = s.semester";
                    $stmt = $this->db->prepare($query);
                    $stmt->execute([
                        ':section_id' => $sectionId,
                        ':course_id' => $courseId
                    ]);
                    if ($stmt->fetchColumn() == 0) {
                        throw new Exception("Course ID $courseId is not valid for this section");
                    }

                    $query = "INSERT INTO section_courses 
                              (section_id, course_id, created_at)
                              VALUES (:section_id, :course_id, NOW())";
                    $stmt = $this->db->prepare($query);
                    $stmt->execute([
                        ':section_id' => $sectionId,
                        ':course_id' => $courseId
                    ]);
                }
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
