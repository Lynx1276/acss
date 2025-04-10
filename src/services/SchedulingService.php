<?php
require_once __DIR__ . '/../config/Database.php';

class SchedulingService
{
    private $db;

    public function __construct()
    {
        $this->db = (new Database())->connect();
    }

    // ======================
    // MAIN SCHEDULING METHODS
    // ======================

    public function generateSchedule($semesterId, $departmentId, $maxSections = 5, $constraints = [])
    {
        try {
            error_log("Starting schedule generation for semester $semesterId, department $departmentId");

            $offerings = $this->getCourseOfferings($semesterId, $departmentId);
            error_log("Found " . count($offerings) . " course offerings");

            if (empty($offerings)) {
                error_log("No offerings found - creating default offerings");
                $offerings = $this->createDefaultOfferings($semesterId, $departmentId);
            }

            $classrooms = $this->getAvailableClassrooms();
            error_log("Found " . count($classrooms) . " available classrooms");

            if (empty($classrooms)) {
                error_log("No available classrooms found - aborting");
                throw new Exception("No available classrooms found");
            }

            $facultyAvailability = $this->getFacultyAvailability($departmentId, $semesterId);
            error_log("Found availability data for " . count($facultyAvailability) . " faculty members");

            if (empty($facultyAvailability)) {
                error_log("No faculty availability data found - aborting");
                throw new Exception("No faculty availability data found");
            }

            $schedule = [];
            $sectionCounts = [];

            foreach ($offerings as $offering) {
                if ($offering['status'] === 'Cancelled') {
                    error_log("Skipping cancelled offering: " . $offering['course_code']);
                    continue;
                }

                $courseId = $offering['course_id'];
                $sectionCounts[$courseId] = ($sectionCounts[$courseId] ?? 0) + 1;

                if ($sectionCounts[$courseId] > $maxSections) {
                    error_log("Max sections reached ($maxSections) for course: " . $offering['course_code']);
                    continue;
                }

                $qualifiedFaculty = $this->getQualifiedFaculty($offering['course_id'], $facultyAvailability);

                if (empty($qualifiedFaculty)) {
                    error_log("No qualified faculty found for course: " . $offering['course_code']);
                    continue;
                }

                $requiredHours = $offering['lecture_hours'] + $offering['lab_hours'];
                error_log("Processing course: " . $offering['course_code'] . " (requires $requiredHours hours)");

                $timeSlots = $this->findTimeSlots($qualifiedFaculty, $requiredHours, $constraints);

                if (empty($timeSlots)) {
                    error_log("Could not find suitable time slots for course: " . $offering['course_code']);
                    continue;
                }

                $classroom = $this->assignClassroom($offering, $timeSlots, $classrooms, $constraints);

                if (!$classroom) {
                    error_log("Could not assign classroom for course: " . $offering['course_code']);
                    continue;
                }

                if ($this->hasScheduleConflict($classroom['room_id'], $timeSlots, $semesterId) && in_array('course_conflicts', $constraints)) {
                    error_log("Schedule conflict detected for room " . $classroom['room_name'] . " - course: " . $offering['course_code']);
                    continue;
                }

                $sectionId = $this->ensureSectionExists($offering['course_id'], $semesterId, $offering['course_code']);
                $sectionQuery = "SELECT section_name FROM sections WHERE section_id = :sectionId";
                $stmt = $this->db->prepare($sectionQuery);
                $stmt->execute([':sectionId' => $sectionId]);
                $sectionName = $stmt->fetchColumn();

                $schedule[] = [
                    'offering_id' => $offering['offering_id'],
                    'course_id' => $offering['course_id'],
                    'course_code' => $offering['course_code'],
                    'course_name' => $offering['course_name'],
                    'section_id' => $sectionId,
                    'section_name' => $sectionName,
                    'faculty_id' => $qualifiedFaculty[0]['faculty_id'],
                    'faculty_name' => $qualifiedFaculty[0]['first_name'] . ' ' . $qualifiedFaculty[0]['last_name'],
                    'room_id' => $classroom['room_id'],
                    'room_name' => $classroom['room_name'],
                    'building' => $classroom['building'],
                    'time_slots' => $timeSlots,
                    'lecture_hours' => $offering['lecture_hours'],
                    'lab_hours' => $offering['lab_hours']
                ];
            

            error_log("Successfully scheduled: " . $offering['course_code'] . " - " . $sectionName . 
                     " with " . $qualifiedFaculty[0]['first_name'] . " " . $qualifiedFaculty[0]['last_name'] . 
                     " in " . $classroom['room_name']);
        }

        error_log("Schedule generation completed. Generated " . count($schedule) . " schedule entries");
        return $schedule;
        } catch (Exception $e) {
            error_log("Scheduling failed: " . $e->getMessage());
            return [];
        }
    }

    private function hasScheduleConflict($roomId, $timeSlots, $semesterId)
    {
        foreach ($timeSlots as $slot) {
            $query = "SELECT COUNT(*) as conflict_count 
                     FROM schedules 
                     WHERE room_id = :roomId 
                     AND semester_id = :semesterId
                     AND day_of_week = :dayOfWeek
                     AND (
                         (start_time < :endTime AND end_time > :startTime)
                         OR (start_time = :startTime AND end_time = :endTime)
                     )";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':roomId' => $roomId,
                ':semesterId' => $semesterId,
                ':dayOfWeek' => $slot['day_of_week'],
                ':startTime' => $slot['start_time'],
                ':endTime' => $slot['end_time']
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['conflict_count'] > 0) {
                return true;
            }
        }
        return false;
    }

    private function assignClassroom($offering, $timeSlots, $classrooms)
    {
        // Filter classrooms by capacity and lab requirements
        $requiredLab = ($offering['lab_hours'] > 0);
        $filteredClassrooms = [];

        foreach ($classrooms as $classroom) {
            if (
                $classroom['capacity'] >= $offering['expected_students'] &&
                (!$requiredLab || $classroom['is_lab'])
            ) {
                $filteredClassrooms[] = $classroom;
            }
        }

        // Sort by capacity (closest to expected students first)
        usort($filteredClassrooms, function ($a, $b) use ($offering) {
            $diffA = abs($a['capacity'] - $offering['expected_students']);
            $diffB = abs($b['capacity'] - $offering['expected_students']);
            return $diffA <=> $diffB;
        });

        return count($filteredClassrooms) > 0 ? $filteredClassrooms[0] : null;
    }

    private function findTimeSlots($qualifiedFaculty, $requiredHours)
    {
        $timeSlots = [];

        foreach ($qualifiedFaculty as $faculty) {
            // Get faculty availability from database
            $query = "SELECT day_of_week, start_time, end_time 
                      FROM faculty_availability 
                      WHERE faculty_id = :facultyId 
                      AND is_available = TRUE
                      ORDER BY preference_level, day_of_week, start_time";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':facultyId', $faculty['faculty_id']);
            $stmt->execute();
            $availableSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($availableSlots) >= $requiredHours) {
                // Try to find consecutive slots on the same day first
                $groupedSlots = [];
                foreach ($availableSlots as $slot) {
                    $groupedSlots[$slot['day_of_week']][] = $slot;
                }

                foreach ($groupedSlots as $day => $slots) {
                    if (count($slots) >= $requiredHours) {
                        $timeSlots = array_slice($slots, 0, $requiredHours);
                        break 2;
                    }
                }

                // If no same-day consecutive slots, take first available
                $timeSlots = array_slice($availableSlots, 0, $requiredHours);
                break;
            }
        }
        return $timeSlots;
    }

    public function saveGeneratedSchedule($scheduleData, $semesterId)
    {
        try {
            $this->db->beginTransaction();

            // First clear existing schedules for these offerings
            $offeringIds = array_column($scheduleData, 'offering_id');
            $placeholders = implode(',', array_fill(0, count($offeringIds), '?'));

            $deleteQuery = "DELETE FROM schedules 
                           WHERE offering_id IN ($placeholders)";
            $stmt = $this->db->prepare($deleteQuery);
            $stmt->execute($offeringIds);

            // Insert new schedules
            $insertQuery = "INSERT INTO schedules (
                course_id, section_id, room_id, semester_id, faculty_id,
                schedule_type, day_of_week, start_time, end_time, status,
                offering_id, created_at
            ) VALUES (
                :course_id, :section_id, :room_id, :semester_id, :faculty_id,
                'F2F', :day_of_week, :start_time, :end_time, 'Pending',
                :offering_id, NOW()
            )";

            $stmt = $this->db->prepare($insertQuery);

            foreach ($scheduleData as $schedule) {
                // Create a section if none exists
                $sectionId = $this->ensureSectionExists(
                    $schedule['course_id'],
                    $semesterId,
                    $schedule['course_code']
                );

                foreach ($schedule['time_slots'] as $slot) {
                    $stmt->execute([
                        ':course_id' => $schedule['course_id'],
                        ':section_id' => $sectionId,
                        ':room_id' => $schedule['room_id'],
                        ':semester_id' => $semesterId,
                        ':faculty_id' => $schedule['faculty_id'],
                        ':day_of_week' => $slot['day_of_week'],
                        ':start_time' => $slot['start_time'],
                        ':end_time' => $slot['end_time'],
                        ':offering_id' => $schedule['offering_id']
                    ]);
                }

                // Update teaching load
                $this->updateTeachingLoad(
                    $schedule['faculty_id'],
                    $schedule['offering_id'],
                    $sectionId,
                    $schedule['lecture_hours'] + $schedule['lab_hours']
                );
            }

            // Update offering status to 'Scheduled'
            $updateQuery = "UPDATE course_offerings 
                           SET status = 'Scheduled', 
                           updated_at = NOW() 
                           WHERE offering_id IN ($placeholders)";
            $stmt = $this->db->prepare($updateQuery);
            $stmt->execute($offeringIds);

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new Exception("Failed to save schedule: " . $e->getMessage());
        }
    }

    private function createDefaultOfferings($semesterId, $departmentId)
    {
        $courses = $this->db->query("SELECT course_id FROM courses 
                               WHERE department_id = $departmentId AND is_active = TRUE")
            ->fetchAll(PDO::FETCH_ASSOC);

        foreach ($courses as $course) {
            $courseId = $course['course_id'];
            $expectedStudents = 60; // Default value

            $this->db->query("INSERT INTO course_offerings 
                         (course_id, semester_id, expected_students, status, created_at)
                         VALUES 
                         ($courseId, $semesterId, $expectedStudents, 'Pending', NOW())");
        }

        return $this->getCourseOfferings($semesterId, $departmentId);
    }

    public function createDefaultOffering($semesterId, $departmentId)
    {
        $this->db->beginTransaction();

        try {
            // Get all active courses for the department
            $courses = $this->db->query(
                "SELECT course_id, course_code, course_name 
             FROM courses 
             WHERE department_id = $departmentId AND is_active = TRUE"
            )->fetchAll(PDO::FETCH_ASSOC);

            if (empty($courses)) {
                throw new Exception("No active courses found for this department");
            }

            $created = [];

            foreach ($courses as $course) {
                $courseId = $course['course_id'];

                // Check if offering already exists
                $exists = $this->db->query(
                    "SELECT 1 FROM course_offerings 
                 WHERE course_id = $courseId AND semester_id = $semesterId"
                )->fetchColumn();

                if (!$exists) {
                    // Set default expected students based on course type
                    $expectedStudents = (strpos($course['course_code'], 'GEC') === 0) ? 40 : 30;

                    $this->db->prepare(
                        "INSERT INTO course_offerings 
                     (course_id, semester_id, expected_students, status, created_at)
                     VALUES (?, ?, ?, 'Pending', NOW())"
                    )->execute([$courseId, $semesterId, $expectedStudents]);

                    $created[] = $course;
                }
            }

            $this->db->commit();
            return $created;
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new Exception("Failed to create offerings: " . $e->getMessage());
        }
    }

    private function getQualifiedFaculty($course_id, $facultyAvailability)
    {
        // Get faculty qualified to teach this course
        $query = "SELECT f.faculty_id, f.first_name, f.last_name, f.position,
                 s.expertise_level, s.subject_name
                 FROM faculty f
                 JOIN specializations s ON f.faculty_id = s.faculty_id
                 WHERE s.subject_name = (
                     SELECT course_name FROM courses WHERE course_id = :courseId
                 )
                 ORDER BY s.expertise_level DESC, f.position DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':courseId', $course_id);
        $stmt->execute();
        $qualifiedFaculty = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Filter the provided availability list and merge data
        $result = [];
        foreach ($qualifiedFaculty as $faculty) {
            foreach ($facultyAvailability as $availableFaculty) {
                if ($availableFaculty['faculty_id'] == $faculty['faculty_id']) {
                    $result[] = array_merge($faculty, $availableFaculty);
                    break;
                }
            }
        }

        return $result;
    }

    public function getFacultyAvailability($departmentId, $semesterId)
    {
        $query = "SELECT 
                    fa.faculty_id,
                    f.first_name,
                    f.last_name,
                    f.position,
                    GROUP_CONCAT(
                        CONCAT(fa.day_of_week, '|', fa.start_time, '|', fa.end_time, '|', fa.preference_level)
                        ORDER BY fa.preference_level, fa.day_of_week, fa.start_time
                        SEPARATOR ','
                    ) AS available_slots
                  FROM faculty_availability fa
                  JOIN faculty f ON fa.faculty_id = f.faculty_id
                  WHERE f.department_id = :departmentId 
                  AND fa.semester_id = :semesterId
                  AND fa.is_available = TRUE
                  GROUP BY fa.faculty_id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':departmentId', $departmentId);
        $stmt->bindParam(':semesterId', $semesterId);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format the availability data
        foreach ($result as &$row) {
            $slots = [];
            foreach (explode(',', $row['available_slots']) as $slot) {
                list($day, $start, $end, $preference) = explode('|', $slot);
                $slots[] = [
                    'day_of_week' => $day,
                    'start_time' => $start,
                    'end_time' => $end,
                    'preference_level' => $preference
                ];
            }
            $row['available_slots'] = $slots;
        }

        return $result;
    }

    public function getAvailableClassrooms($departmentId = null)
    {
        try {
            $query = "SELECT room_id, room_name, building, capacity, is_lab, has_projector, has_smartboard, has_computers, shared, is_active 
                      FROM classrooms 
                      WHERE is_active = 1";
            if ($departmentId) {
                $query .= " AND (department_id = :department_id OR shared = 1)";
            }
            $query .= " ORDER BY building, room_name";
            $stmt = $this->db->prepare($query);
            if ($departmentId) {
                $stmt->bindParam(':department_id', $departmentId);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch classrooms: " . $e->getMessage());
            return [];
        }
    }

    public function getCourseOfferings($semester_id, $departmentId = null)
    {
        error_log("Debug: Checking for semester_id=$semester_id, departmentId=$departmentId");

        // First verify the semester exists
        $semesterCheck = "SELECT * FROM semesters WHERE semester_id = :semester_id";
        $stmt = $this->db->prepare($semesterCheck);
        $stmt->execute([':semester_id' => $semester_id]);
        $semester = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$semester) {
            error_log("Error: Semester $semester_id does not exist");
            return [];
        } else {
            error_log("Semester found: " . $semester['semester_name'] . " " . $semester['academic_year']);
        }

        // Verify department exists if provided
        if ($departmentId) {
            $deptCheck = "SELECT * FROM departments WHERE department_id = :departmentId";
            $stmt = $this->db->prepare($deptCheck);
            $stmt->execute([':departmentId' => $departmentId]);
            $department = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$department) {
                error_log("Error: Department $departmentId does not exist");
                return [];
            } else {
                error_log("Department found: " . $department['department_name']);
            }
        }

        // Now check for course offerings
        $query = "SELECT 
                co.*,
                c.course_code,
                c.course_name,
                c.lecture_hours,
                c.lab_hours,
                c.department_id,
                (SELECT COUNT(*) FROM sections s 
                 WHERE s.course_id = c.course_id 
                 AND s.academic_year = (SELECT academic_year FROM semesters WHERE semester_id = :semester_id)
                 AND s.semester = (SELECT semester_name FROM semesters WHERE semester_id = :semester_id)
                ) as existing_sections
              FROM course_offerings co
              JOIN courses c ON co.course_id = c.course_id
              WHERE co.semester_id = :semester_id";

        if ($departmentId !== null) {
            $query .= " AND c.department_id = :departmentId";
        }

        $query .= " ORDER BY c.course_code";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':semester_id', $semester_id, PDO::PARAM_INT);

        if ($departmentId !== null) {
            $stmt->bindParam(':departmentId', $departmentId);
        }

        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("Found " . count($results) . " course offerings");
        if (count($results) > 0) {
            error_log("Sample offering: " . $results[0]['course_code'] . " - " . $results[0]['course_name']);
        }

        return $results;
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

    private function updateTeachingLoad($facultyId, $offeringId, $sectionId, $assignedHours)
    {
        $query = "INSERT INTO teaching_loads (
            faculty_id, offering_id, section_id, assigned_hours, status,
            assigned_at
        ) VALUES (
            :faculty_id, :offering_id, :section_id, :assigned_hours, 'Approved',
            NOW()
        ) ON DUPLICATE KEY UPDATE 
            assigned_hours = VALUES(assigned_hours),
            status = VALUES(status),
            assigned_at = NOW()";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':faculty_id' => $facultyId,
            ':offering_id' => $offeringId,
            ':section_id' => $sectionId,
            ':assigned_hours' => $assignedHours
        ]);
    }

    private function ensureSectionExists($courseId, $semesterId, $courseCode = '')
    {
        // Get semester info
        $semesterQuery = "SELECT semester_name, academic_year 
                         FROM semesters 
                         WHERE semester_id = :semesterId";
        $stmt = $this->db->prepare($semesterQuery);
        $stmt->bindParam(':semesterId', $semesterId);
        $stmt->execute();
        $semester = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if section exists
        $sectionQuery = "SELECT section_id 
                        FROM sections 
                        WHERE course_id = :courseId 
                        AND academic_year = :academicYear
                        AND semester = :semester
                        LIMIT 1";
        $stmt = $this->db->prepare($sectionQuery);
        $stmt->execute([
            ':courseId' => $courseId,
            ':academicYear' => $semester['academic_year'],
            ':semester' => $semester['semester_name']
        ]);

        $section = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($section) {
            return $section['section_id'];
        }

        // Create new section with a meaningful name
        $sectionName = $courseCode . '-' . substr($semester['academic_year'], 2, 2) .
            substr($semester['semester_name'], 0, 1) . '01';

        $insertQuery = "INSERT INTO sections (
            section_name, year_level, course_id, academic_year, semester,
            created_at
        ) VALUES (
            :section_name, '1st Year', :courseId, :academicYear, :semester,
            NOW()
        )";

        $stmt = $this->db->prepare($insertQuery);
        $stmt->execute([
            ':section_name' => $sectionName,
            ':courseId' => $courseId,
            ':academicYear' => $semester['academic_year'],
            ':semester' => $semester['semester_name']
        ]);

        return $this->db->lastInsertId();
    }

    public function getFacultyMembers($departmentId)
    {
        $query = "SELECT 
                    f.*,
                    (SELECT COUNT(*) FROM teaching_loads tl 
                     WHERE tl.faculty_id = f.faculty_id 
                     AND tl.status = 'Approved') as current_load,
                    (SELECT GROUP_CONCAT(s.subject_name SEPARATOR ', ') 
                     FROM specializations s 
                     WHERE s.faculty_id = f.faculty_id) as specializations
                  FROM faculty f 
                  WHERE f.department_id = :departmentId
                  ORDER BY f.last_name, f.first_name";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':departmentId', $departmentId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    public function getSemesters()
    {
        try {
            $query = "SELECT semester_id, semester_name, academic_year, is_current 
                      FROM semesters 
                      ORDER BY year_start DESC, semester_name";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch semesters: " . $e->getMessage());
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

    public function getDepartmentById($departmentId)
    {
        try {
            $query = "SELECT department_name FROM departments WHERE department_id = :department_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':department_id', $departmentId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch department: " . $e->getMessage());
            return null;
        }
    }

    // In SchedulingService.php

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

    // Removed duplicate method declaration to resolve the error.

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

    public function approveSchedule($scheduleIds, $approvedBy)
    {
        try {
            $this->db->beginTransaction();

            $placeholders = implode(',', array_fill(0, count($scheduleIds), '?'));

            $query = "UPDATE schedules 
                     SET status = 'Approved',
                         approved_by = :approvedBy,
                         approval_date = NOW()
                     WHERE schedule_id IN ($placeholders)";
            $stmt = $this->db->prepare($query);

            // Combine parameters
            $params = $scheduleIds;
            array_unshift($params, $approvedBy);

            $stmt->execute($params);

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new Exception("Approval failed: " . $e->getMessage());
        }
    }

    /**
     * Get the current active semester
     * @return array Semester data
     */
    public function getCurrentSemester()
    {
        $query = "SELECT * FROM semesters WHERE is_current = TRUE LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get count of faculty in department
     * @param int $departmentId
     * @return int Faculty count
     */
    public function getFacultyCount($departmentId)
    {
        $query = "SELECT COUNT(*) as count FROM faculty 
              WHERE department_id = :departmentId";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':departmentId', $departmentId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }

    /**
     * Get count of active courses in department
     * @param int $departmentId
     * @return int Course count
     */
    public function getActiveCourseCount($departmentId)
    {
        $query = "SELECT COUNT(*) as count FROM courses 
              WHERE department_id = :departmentId AND is_active = TRUE";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':departmentId', $departmentId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }

    /**
     * Get pending schedule approvals for department
     * @param int $departmentId
     * @return array Pending approvals
     */
    public function getPendingScheduleApprovals($departmentId)
    {
        $query = "SELECT COUNT(*) as count FROM schedules s
              JOIN courses c ON s.course_id = c.course_id
              WHERE c.department_id = :departmentId
              AND s.status = 'Pending'";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':departmentId', $departmentId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }

    /**
     * Get schedule conflicts for department in semester
     * @param int $departmentId
     * @param int $semesterId
     * @return array Conflict count and details
     */
    public function getScheduleConflicts($departmentId, $semesterId)
    {
        // Get count of faculty teaching at same time
        $facultyConflictsQuery = "SELECT COUNT(*) as count FROM (
        SELECT s1.faculty_id, s1.day_of_week, s1.start_time, s1.end_time
        FROM schedules s1
        JOIN schedules s2 ON s1.schedule_id != s2.schedule_id
            AND s1.faculty_id = s2.faculty_id
            AND s1.day_of_week = s2.day_of_week
            AND s1.start_time < s2.end_time
            AND s1.end_time > s2.start_time
        JOIN courses c ON s1.course_id = c.course_id
        WHERE c.department_id = :departmentId
        AND s1.semester_id = :semesterId
        GROUP BY s1.faculty_id, s1.day_of_week, s1.start_time, s1.end_time
    ) as conflicts";

        $stmt = $this->db->prepare($facultyConflictsQuery);
        $stmt->execute([
            ':departmentId' => $departmentId,
            ':semesterId' => $semesterId
        ]);
        $facultyConflicts = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get count of room double bookings
        $roomConflictsQuery = "SELECT COUNT(*) as count FROM (
        SELECT s1.room_id, s1.day_of_week, s1.start_time, s1.end_time
        FROM schedules s1
        JOIN schedules s2 ON s1.schedule_id != s2.schedule_id
            AND s1.room_id = s2.room_id
            AND s1.day_of_week = s2.day_of_week
            AND s1.start_time < s2.end_time
            AND s1.end_time > s2.start_time
        JOIN courses c ON s1.course_id = c.course_id
        WHERE c.department_id = :departmentId
        AND s1.semester_id = :semesterId
        GROUP BY s1.room_id, s1.day_of_week, s1.start_time, s1.end_time
    ) as conflicts";

        $stmt = $this->db->prepare($roomConflictsQuery);
        $stmt->execute([
            ':departmentId' => $departmentId,
            ':semesterId' => $semesterId
        ]);
        $roomConflicts = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'faculty_conflicts' => (int)$facultyConflicts['count'],
            'room_conflicts' => (int)$roomConflicts['count'],
            'total_conflicts' => (int)$facultyConflicts['count'] + (int)$roomConflicts['count']
        ];
    }

    /**
     * Get recent schedule changes
     * @param int $departmentId
     * @param int $limit Number of changes to return
     * @return array Recent changes
     */
    public function getRecentScheduleChanges($departmentId, $limit = 5)
    {
        $query = "SELECT 
                s.*, 
                c.course_code,
                c.course_name,
                CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
                r.room_name,
                r.building,
                TIME_FORMAT(s.start_time, '%h:%i %p') as start_time_display,
                TIME_FORMAT(s.end_time, '%h:%i %p') as end_time_display,
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
              JOIN faculty f ON s.faculty_id = f.faculty_id
              LEFT JOIN classrooms r ON s.room_id = r.room_id
              WHERE c.department_id = :departmentId
              ORDER BY s.created_at DESC
              LIMIT :limit";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':departmentId', $departmentId);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get faculty availability summary
     * @param int $departmentId
     * @param int $semesterId
     * @return array Availability statistics
     */
    public function getFacultyAvailabilitySummary($departmentId, $semesterId)
    {
        // Total available hours per faculty
        $query = "SELECT 
                f.faculty_id,
                CONCAT(f.first_name, ' ', f.last_name) as faculty_name,
                COUNT(fa.availability_id) as available_slots,
                SEC_TO_TIME(SUM(TIME_TO_SEC(TIMEDIFF(fa.end_time, fa.start_time)))) as total_hours
              FROM faculty_availability fa
              JOIN faculty f ON fa.faculty_id = f.faculty_id
              WHERE f.department_id = :departmentId
              AND fa.semester_id = :semesterId
              AND fa.is_available = TRUE
              GROUP BY f.faculty_id
              ORDER BY total_hours DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':departmentId' => $departmentId,
            ':semesterId' => $semesterId
        ]);
        $facultyAvailability = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate summary stats
        $totalFaculty = count($facultyAvailability);
        $totalHours = 0;
        $preferredTimes = [];

        foreach ($facultyAvailability as $faculty) {
            $totalHours += strtotime($faculty['total_hours']) - strtotime('TODAY');

            // Count preferred times (you would need to query this separately for accuracy)
            $preferredTimes[$faculty['faculty_id']] = [
                'morning' => rand(0, 5), // Placeholder - implement actual query
                'afternoon' => rand(0, 5),
                'evening' => rand(0, 5)
            ];
        }

        $avgHours = $totalFaculty > 0 ? gmdate('H:i', $totalHours / $totalFaculty) : '00:00';

        return [
            'total_faculty' => $totalFaculty,
            'total_hours' => gmdate('H:i', $totalHours),
            'average_hours' => $avgHours,
            'faculty_availability' => $facultyAvailability,
            'preferred_times' => $preferredTimes
        ];
    }

    /**
     * Get classroom utilization statistics
     * @param int $departmentId
     * @param int $semesterId
     * @return array Utilization data
     */
    public function getClassroomUtilization($departmentId, $semesterId)
    {
        // Get classrooms assigned to department's courses
        $query = "SELECT 
                r.room_id,
                r.room_name,
                r.building,
                r.capacity,
                r.is_lab,
                COUNT(s.schedule_id) as scheduled_classes,
                SEC_TO_TIME(SUM(TIME_TO_SEC(TIMEDIFF(s.end_time, s.start_time)))) as scheduled_hours,
                (SELECT COUNT(*) FROM room_reservations rr 
                 WHERE rr.room_id = r.room_id 
                 AND rr.approval_status = 'Approved') as reservations_count
              FROM classrooms r
              JOIN schedules s ON r.room_id = s.room_id
              JOIN courses c ON s.course_id = c.course_id
              WHERE c.department_id = :departmentId
              AND s.semester_id = :semesterId
              GROUP BY r.room_id
              ORDER BY r.building, r.room_name";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':departmentId' => $departmentId,
            ':semesterId' => $semesterId
        ]);
        $utilization = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate utilization percentage
        $totalRooms = count($utilization);
        $totalHours = 0;
        $totalCapacity = 0;
        $usedCapacity = 0;

        foreach ($utilization as &$room) {
            $roomHours = strtotime($room['scheduled_hours']) - strtotime('TODAY');
            $totalHours += $roomHours;
            $totalCapacity += $room['capacity'];

            // Estimate used capacity (simplified - would need student counts)
            $usedCapacity += $room['capacity'] * min(1, $roomHours / 40); // 40 = max weekly hours

            // Add utilization percentage
            $room['utilization_percent'] = min(100, round(($roomHours / 40) * 100));
        }

        $avgUtilization = $totalRooms > 0 ? round(($totalHours / ($totalRooms * 40)) * 100) : 0;
        $capacityUtilization = $totalCapacity > 0 ? round(($usedCapacity / $totalCapacity) * 100) : 0;

        return [
            'classrooms' => $utilization,
            'total_classrooms' => $totalRooms,
            'total_hours' => gmdate('H:i', $totalHours),
            'average_utilization' => $avgUtilization,
            'capacity_utilization' => $capacityUtilization
        ];
    }

    // Removed duplicate method declaration to resolve the error.

    public function getDepartmentCurricula($departmentId)
    {
        try {
            $query = "SELECT 
                    c.curriculum_id, 
                    c.curriculum_name, 
                    c.curriculum_code,
                    c.status,
                    COUNT(cc.curriculum_course_id) as course_count
                  FROM curricula c
                  LEFT JOIN curriculum_courses cc ON c.curriculum_id = cc.curriculum_id
                  WHERE c.department_id = :departmentId
                  GROUP BY c.curriculum_id
                  ORDER BY c.curriculum_name";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':departmentId', $departmentId);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []; // Return empty array if no results
        } catch (PDOException $e) {
            error_log("Error fetching curricula: " . $e->getMessage());
            return []; // Return empty array on error
        }
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

    public function getDepartmentCourses($departmentId)
    {
        try {
            $query = "SELECT c.*, p.program_name 
                      FROM courses c 
                      LEFT JOIN programs p ON c.program_id = p.program_id 
                      WHERE c.department_id = :department_id 
                      AND c.is_active = 1 
                      ORDER BY c.course_code";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':department_id' => $departmentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch courses: " . $e->getMessage());
            return [];
        }
    }

    public function getDepartmentPrograms($departmentId)
    {
        try {
            $query = "SELECT program_id, program_name 
                      FROM programs 
                      WHERE department_id = :department_id 
                      ORDER BY program_name";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':department_id' => $departmentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch programs: " . $e->getMessage());
            return [];
        }
    }

    public function addFacultyLoad($facultyId, $courseId, $semesterId, $roomId, $timeSlots)
    {
        try {
            $this->db->beginTransaction();

            // Ensure course offering exists
            $offeringQuery = "SELECT offering_id FROM course_offerings 
                              WHERE course_id = :course_id AND semester_id = :semester_id";
            $stmt = $this->db->prepare($offeringQuery);
            $stmt->execute([':course_id' => $courseId, ':semester_id' => $semesterId]);
            $offeringId = $stmt->fetchColumn();

            if (!$offeringId) {
                $insertOffering = "INSERT INTO course_offerings (course_id, semester_id, status) 
                                   VALUES (:course_id, :semester_id, 'Pending')";
                $stmt = $this->db->prepare($insertOffering);
                $stmt->execute([':course_id' => $courseId, ':semester_id' => $semesterId]);
                $offeringId = $this->db->lastInsertId();
            }

            // Create or get section
            $sectionId = $this->ensureSectionExists($courseId, $semesterId, $this->getCourseCode($courseId));

            // Insert into schedule
            $scheduleQuery = "INSERT INTO schedules (offering_id, section_id, faculty_id, room_id, status) 
                              VALUES (:offering_id, :section_id, :faculty_id, :room_id, 'Pending')";
            $stmt = $this->db->prepare($scheduleQuery);
            $stmt->execute([
                ':offering_id' => $offeringId,
                ':section_id' => $sectionId,
                ':faculty_id' => $facultyId,
                ':room_id' => $roomId
            ]);
            $scheduleId = $this->db->lastInsertId();

            // Insert time slots
            $timeSlotQuery = "INSERT INTO schedule_timeslots (schedule_id, day_of_week, start_time, end_time) 
                              VALUES (:schedule_id, :day_of_week, :start_time, :end_time)";
            $stmt = $this->db->prepare($timeSlotQuery);
            foreach ($timeSlots as $slot) {
                $stmt->execute([
                    ':schedule_id' => $scheduleId,
                    ':day_of_week' => $slot['day_of_week'],
                    ':start_time' => $slot['start_time'],
                    ':end_time' => $slot['end_time']
                ]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Failed to add faculty load: " . $e->getMessage());
            return false;
        }
    }

    public function getAllSemesters()
    {
        $query = "SELECT semester_id, semester_name, academic_year, is_current 
                  FROM semesters ORDER BY year_start DESC, semester_name";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getCourseCode($courseId)
    {
        $query = "SELECT course_code FROM courses WHERE course_id = :course_id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':course_id' => $courseId]);
        return $stmt->fetchColumn();
    }

    public function updateScheduleStatus($scheduleId, $status, $departmentId)
    {
        try {
            $query = "UPDATE schedules s
                      JOIN courses c ON s.course_id = c.course_id
                      SET s.status = :status
                      WHERE s.schedule_id = :schedule_id 
                      AND c.department_id = :department_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':status' => $status,
                ':schedule_id' => $scheduleId,
                ':department_id' => $departmentId
            ]);
        } catch (Exception $e) {
            error_log("Failed to update schedule status: " . $e->getMessage());
            throw $e;
        }
    }

    public function generateScheduleReport($departmentId)
    {
        $query = "SELECT c.course_code, c.course_name, CONCAT(f.first_name, ' ', f.last_name) AS faculty_name, 
                         s.time_slot, r.room_name, s.status
                  FROM schedules s
                  JOIN courses c ON s.course_id = c.course_id
                  JOIN faculty f ON s.faculty_id = f.faculty_id
                  JOIN classrooms r ON s.room_id = r.room_id
                  WHERE c.department_id = :department_id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':department_id' => $departmentId]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="schedule_report_' . date('Ymd') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Course Code', 'Course Name', 'Faculty', 'Time Slot', 'Room', 'Status']);
        foreach ($data as $row) {
            fputcsv($output, [
                $row['course_code'],
                $row['course_name'],
                $row['faculty_name'],
                $row['time_slot'],
                $row['room_name'],
                $row['status']
            ]);
        }
        fclose($output);
    }

    public function getPendingRequestsByCollege($collegeId)
    {
        try {
            $query = "SELECT sr.request_id, sr.faculty_id, f.first_name, f.last_name, c.course_code, 
                             s.day_of_week, TIME_FORMAT(s.start_time, '%h:%i %p') AS start_time, 
                             TIME_FORMAT(s.end_time, '%h:%i %p') AS end_time, sr.request_type, sr.details, sr.created_at
                      FROM schedule_requests sr
                      JOIN schedules s ON sr.schedule_id = s.schedule_id
                      JOIN faculty f ON sr.faculty_id = f.faculty_id
                      JOIN courses c ON s.course_id = c.course_id
                      JOIN departments d ON f.department_id = d.department_id
                      WHERE d.college_id = :college_id AND sr.status = 'pending'
                      ORDER BY sr.created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':college_id', $collegeId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch pending requests: " . $e->getMessage());
            return [];
        }
    }

    public function getCollegeFacultyStats($collegeId)
    {
        try {
            $stats = ['totalFaculty' => 0, 'totalCourses' => 0, 'pendingRequests' => 0];
            $query = "SELECT COUNT(*) AS total_faculty 
                      FROM faculty f
                      JOIN departments d ON f.department_id = d.department_id
                      WHERE d.college_id = :college_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':college_id' => $collegeId]);
            $stats['totalFaculty'] = $stmt->fetchColumn();

            $query = "SELECT COUNT(DISTINCT s.schedule_id) AS total_courses 
                      FROM schedules s 
                      JOIN faculty f ON s.faculty_id = f.faculty_id 
                      JOIN departments d ON f.department_id = d.department_id
                      JOIN semesters sem ON s.semester_id = sem.semester_id
                      WHERE d.college_id = :college_id AND sem.is_current = 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':college_id' => $collegeId]);
            $stats['totalCourses'] = $stmt->fetchColumn();

            $query = "SELECT COUNT(*) AS pending 
                      FROM schedule_requests sr 
                      JOIN faculty f ON sr.faculty_id = f.faculty_id 
                      JOIN departments d ON f.department_id = d.department_id
                      WHERE d.college_id = :college_id AND sr.status = 'pending'";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':college_id' => $collegeId]);
            $stats['pendingRequests'] = $stmt->fetchColumn();

            return $stats;
        } catch (Exception $e) {
            error_log("Failed to fetch college stats: " . $e->getMessage());
            return ['totalFaculty' => 0, 'totalCourses' => 0, 'pendingRequests' => 0];
        }
    }

    public function getPendingRequestsByDepartment($collegeId)
    {
        try {
            $query = "SELECT sr.request_id, sr.faculty_id, f.first_name, f.last_name, c.course_code, 
                         s.day_of_week, TIME_FORMAT(s.start_time, '%h:%i %p') AS start_time, 
                         TIME_FORMAT(s.end_time, '%h:%i %p') AS end_time, sr.request_type, sr.details, sr.created_at
                  FROM schedule_requests sr
                  JOIN schedules s ON sr.schedule_id = s.schedule_id
                  JOIN faculty f ON sr.faculty_id = f.faculty_id
                  JOIN courses c ON s.course_id = c.course_id
                  JOIN departments d ON f.department_id = d.department_id
                  WHERE d.college_id = :college_id AND sr.status = 'pending'
                  ORDER BY sr.created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':college_id', $collegeId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch pending requests: " . $e->getMessage());
            return [];
        }
    }

    public function getDepartmentFacultyStats($departmentId)
    {
        try {
            $stats = ['totalFaculty' => 0, 'totalCourses' => 0, 'pendingRequests' => 0];
            $query = "SELECT COUNT(*) AS total_faculty FROM faculty WHERE department_id = :department_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':department_id' => $departmentId]);
            $stats['totalFaculty'] = $stmt->fetchColumn();

            $query = "SELECT COUNT(DISTINCT s.schedule_id) AS total_courses 
                      FROM schedules s 
                      JOIN faculty f ON s.faculty_id = f.faculty_id 
                      JOIN semesters sem ON s.semester_id = sem.semester_id
                      WHERE f.department_id = :department_id AND sem.is_current = 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':department_id' => $departmentId]);
            $stats['totalCourses'] = $stmt->fetchColumn();

            $query = "SELECT COUNT(*) AS pending 
                      FROM schedule_requests sr 
                      JOIN faculty f ON sr.faculty_id = f.faculty_id 
                      WHERE f.department_id = :department_id AND sr.status = 'pending'";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':department_id' => $departmentId]);
            $stats['pendingRequests'] = $stmt->fetchColumn();

            return $stats;
        } catch (Exception $e) {
            error_log("Failed to fetch department stats: " . $e->getMessage());
            return ['totalFaculty' => 0, 'totalCourses' => 0, 'pendingRequests' => 0];
        }
    }

    public function getDepartmentSchedules($departmentId, $semesterId = null)
    {
        try {
            $query = "SELECT s.schedule_id, f.first_name, f.last_name, c.course_code, c.course_name, 
                             r.room_name, sec.section_name, s.day_of_week, 
                             TIME_FORMAT(s.start_time, '%h:%i %p') AS start_time_display,
                             TIME_FORMAT(s.end_time, '%h:%i %p') AS end_time_display, s.status
                      FROM schedules s
                      JOIN faculty f ON s.faculty_id = f.faculty_id
                      JOIN courses c ON s.course_id = c.course_id
                      LEFT JOIN classrooms r ON s.room_id = r.room_id
                      LEFT JOIN sections sec ON s.section_id = sec.section_id
                      JOIN semesters sem ON s.semester_id = sem.semester_id
                      WHERE f.department_id = :department_id";
            if ($semesterId) {
                $query .= " AND s.semester_id = :semester_id";
            } else {
                $query .= " AND sem.is_current = 1";
            }
            $query .= " ORDER BY f.last_name, s.day_of_week, s.start_time";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':department_id', $departmentId, PDO::PARAM_INT);
            if ($semesterId) {
                $stmt->bindParam(':semester_id', $semesterId, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch department schedules: " . $e->getMessage());
            return [];
        }
    }

    public function updateRequestStatus($requestId, $status, $approvedBy)
    {
        try {
            $query = "UPDATE schedule_requests 
                      SET status = :status, updated_at = NOW() 
                      WHERE request_id = :request_id AND status = 'pending'";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':request_id' => $requestId,
                ':status' => $status
            ]);
            if ($stmt->rowCount() === 0) {
                throw new Exception("Request not found or already processed");
            }

            // Log the action
            $this->logActivity($approvedBy, 'update_request', "Updated request #$requestId to $status", 'schedule_requests', $requestId);
        } catch (Exception $e) {
            error_log("Failed to update request status: " . $e->getMessage());
            throw $e;
        }
    }

    public function getFacultyByDepartment($departmentId)
    {
        try {
            $query = "SELECT f.faculty_id, f.first_name, f.last_name, f.email, f.phone, f.position, f.employment_type,
                             p1.program_name AS primary_program, p2.program_name AS secondary_program
                      FROM faculty f
                      LEFT JOIN programs p1 ON f.primary_program_id = p1.program_id
                      LEFT JOIN programs p2 ON f.secondary_program_id = p2.program_id
                      WHERE f.department_id = :department_id
                      ORDER BY f.last_name, f.first_name";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':department_id', $departmentId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch faculty by department: " . $e->getMessage());
            return [];
        }
    }

    private function logActivity($userId, $actionType, $description, $entityType, $entityId)
    {
        try {
            $query = "INSERT INTO activity_logs (user_id, action_type, action_description, entity_type, entity_id, created_at)
                      VALUES (:user_id, :action_type, :description, :entity_type, :entity_id, NOW())";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':user_id' => $userId,
                ':action_type' => $actionType,
                ':description' => $description,
                ':entity_type' => $entityType,
                ':entity_id' => $entityId
            ]);
        } catch (Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }

    public function generateFacultyLoadReport($departmentId)
    {
        $query = "SELECT CONCAT(f.first_name, ' ', f.last_name) AS faculty_name, 
                         COUNT(s.schedule_id) AS course_count, 
                         SUM(c.lecture_hours + c.lab_hours) AS total_hours
                  FROM faculty f
                  LEFT JOIN schedules s ON f.faculty_id = s.faculty_id
                  LEFT JOIN courses c ON s.course_id = c.course_id
                  WHERE f.department_id = :department_id
                  GROUP BY f.faculty_id, f.first_name, f.last_name";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':department_id' => $departmentId]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="faculty_load_report_' . date('Ymd') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Faculty Name', 'Number of Courses', 'Total Hours']);
        foreach ($data as $row) {
            fputcsv($output, [
                $row['faculty_name'],
                $row['course_count'],
                $row['total_hours']
            ]);
        }
        fclose($output);
    }

}
