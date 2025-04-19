<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;
use App\config\Database;

class SchedulingService
{
    private $db;
    private $semesterId; // Declare the semesterId property

    public function __construct()
    {
        $this->db = (new Database())->connect();
    }

    // ======================
    // MAIN SCHEDULING METHODS
    // ======================


    public function findTimeSlots($qualifiedFaculty, $requiredHours, $constraints = [])
    {
        try {
            error_log("findTimeSlots: Finding slots for required_hours=$requiredHours, faculty_count=" . count($qualifiedFaculty));

            $timeSlots = [];
            $hoursAssigned = 0;
            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

            // Get faculty loads to prioritize those with lower loads
            $facultyLoads = $this->getFacultyLoads($qualifiedFaculty, $this->semesterId);

            // Sort faculty by load (ascending) to prioritize less loaded faculty
            usort($qualifiedFaculty, function ($a, $b) use ($facultyLoads) {
                $loadA = $facultyLoads[$a['faculty_id']] ?? 0;
                $loadB = $facultyLoads[$b['faculty_id']] ?? 0;
                return $loadA <=> $loadB;
            });

            foreach ($qualifiedFaculty as $faculty) {
                $facultyId = $faculty['faculty_id'];
                $currentLoad = $facultyLoads[$facultyId] ?? 0;

                // Skip faculty with high load (e.g., max 18 hours)
                if ($currentLoad >= 18) {
                    error_log("findTimeSlots: Skipping faculty_id=$facultyId (load=$currentLoad)");
                    continue;
                }

                $usedTimes = [];

                // Generate possible time slots (e.g., 7 AM to 6 PM)
                $generatedSlots = $this->generateFallbackSlots($days, $requiredHours - $hoursAssigned);

                foreach ($generatedSlots as $slot) {
                    if ($hoursAssigned >= $requiredHours) break;

                    $slotKey = "{$slot['day_of_week']}:{$slot['start_time']}-{$slot['end_time']}";
                    if (isset($usedTimes[$slotKey])) continue;

                    // Check for conflicts
                    $conflictQuery = "SELECT COUNT(*) FROM schedules 
                            WHERE faculty_id = :facultyId 
                            AND semester_id = :semesterId 
                            AND day_of_week = :day 
                            AND (
                                (:start <= end_time AND :end >= start_time)
                            )";
                    $stmt = $this->db->prepare($conflictQuery);
                    $stmt->execute([
                        ':facultyId' => $facultyId,
                        ':semesterId' => $this->semesterId,
                        ':day' => $slot['day_of_week'],
                        ':start' => $slot['start_time'],
                        ':end' => $slot['end_time']
                    ]);

                    if ($stmt->fetchColumn() > 0) {
                        error_log("findTimeSlots: Conflict for generated slot for faculty_id=$facultyId: " . json_encode($slot));
                        continue;
                    }

                    $slotDuration = $this->calculateDuration($slot['start_time'], $slot['end_time']);
                    if ($slotDuration <= 0) {
                        error_log("findTimeSlots: Invalid duration for generated slot: " . json_encode($slot));
                        continue;
                    }

                    $timeSlots[] = [
                        'faculty_id' => $facultyId,
                        'day_of_week' => $slot['day_of_week'],
                        'start_time' => $slot['start_time'],
                        'end_time' => $slot['end_time']
                    ];
                    $hoursAssigned += $slotDuration;
                    $usedTimes[$slotKey] = true;

                    error_log("findTimeSlots: Assigned generated slot for faculty_id=$facultyId: " . json_encode($timeSlots[count($timeSlots) - 1]));
                }

                if ($hoursAssigned >= $requiredHours) break;
            }

            if (empty($timeSlots) && $requiredHours > 0) {
                error_log("findTimeSlots: No slots assigned, using fallback for lowest-loaded faculty");
                // Assign to the faculty with the lowest load
                $facultyId = array_key_first($facultyLoads);
                $randomDay = $days[array_rand($days)];
                $startHour = rand(7, 18); // 7 AM to 6 PM
                $endHour = min($startHour + ceil($requiredHours), 22); // Cap at 10 PM
                $timeSlots[] = [
                    'faculty_id' => $facultyId,
                    'day_of_week' => $randomDay,
                    'start_time' => sprintf('%02d:00:00', $startHour),
                    'end_time' => sprintf('%02d:00:00', $endHour)
                ];
                error_log("findTimeSlots: Ultimate fallback slot: " . json_encode($timeSlots[0]));
            }

            error_log("findTimeSlots: Returning " . count($timeSlots) . " slots");
            return $timeSlots;
        } catch (Exception $e) {
            error_log("findTimeSlots error: " . $e->getMessage());
            return [];
        }
    }

    private function getFacultyLoads($faculty, $semesterId)
    {
        $loads = [];
        foreach ($faculty as $fac) {
            $facultyId = $fac['faculty_id'];
            $query = "SELECT SUM(c.lecture_hours + c.lab_hours) as total_hours 
                 FROM schedules s 
                 JOIN course_offerings co ON s.course_id = co.course_id 
                 JOIN courses c ON co.course_id = c.course_id 
                 WHERE s.faculty_id = :facultyId AND s.semester_id = :semesterId";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':facultyId' => $facultyId,
                ':semesterId' => $semesterId
            ]);
            $loads[$facultyId] = (float)($stmt->fetchColumn() ?: 0);
        }
        return $loads;
    }

    private function generateFallbackSlots($days, $requiredHours)
    {
        $slots = [];
        $hoursPerSlot = min($requiredHours, 3); // Prefer 3-hour slots
        $remainingHours = $requiredHours;

        while ($remainingHours > 0) {
            $day = $days[array_rand($days)];
            $startHour = rand(7, 18); // 7 AM to 6 PM
            $endHour = min($startHour + $hoursPerSlot, 22); // Cap at 10 PM

            $slots[] = [
                'day_of_week' => $day,
                'start_time' => sprintf('%02d:00:00', $startHour),
                'end_time' => sprintf('%02d:00:00', $endHour)
            ];

            $remainingHours -= $hoursPerSlot;
            $hoursPerSlot = min($remainingHours, 3);
        }

        return $slots;
    }


    private function calculateDuration($startTime, $endTime)
    {
        try {
            $start = new DateTime($startTime);
            $end = new DateTime($endTime);
            $interval = $start->diff($end);
            $hours = $interval->h + ($interval->i / 60);
            return $hours > 0 ? $hours : 0;
        } catch (Exception $e) {
            error_log("calculateDuration error: " . $e->getMessage());
            return 0;
        }
    }


    public function generateSchedule($semesterId, $departmentId, $maxSections = 5, $constraints = [], $yearLevel = 'all')
    {
        try {
            $this->semesterId = $semesterId;
            error_log("generateSchedule: semester_id=$semesterId, department_id=$departmentId, year_level=$yearLevel");

            $this->assignRandomSpecializations($semesterId, $departmentId);

            $offerings = $this->getCourseOfferings($semesterId, $departmentId, $yearLevel);
            error_log("generateSchedule: Found " . count($offerings) . " offerings");

            if (empty($offerings)) {
                error_log("generateSchedule: No offerings for semester_id=$semesterId, year_level=$yearLevel");
                return [];
            }

            $classrooms = $this->getAvailableClassrooms();
            $facultyAvailability = $this->getFacultyAvailability($departmentId, $semesterId);

            $schedule = [];
            $sectionCounts = [];

            foreach ($offerings as $offering) {
                if ($offering['status'] === 'Cancelled') {
                    error_log("generateSchedule: Skipping cancelled offering: {$offering['course_code']}");
                    continue;
                }

                $courseId = $offering['course_id'];
                $sectionCounts[$courseId] = ($sectionCounts[$courseId] ?? 0) + 1;

                if ($sectionCounts[$courseId] > $maxSections) {
                    error_log("generateSchedule: Max sections reached for course_id=$courseId");
                    continue;
                }

                $qualifiedFaculty = $this->getQualifiedFaculty($courseId, $facultyAvailability);

                if (empty($qualifiedFaculty)) {
                    error_log("generateSchedule: No qualified faculty for course: {$offering['course_code']}");
                    continue;
                }

                $requiredHours = $offering['lecture_hours'] + $offering['lab_hours'];
                $timeSlots = $this->findTimeSlots($qualifiedFaculty, $requiredHours, $constraints);

                if (empty($timeSlots)) {
                    error_log("generateSchedule: No time slots for course: {$offering['course_code']}");
                    continue;
                }

                $classroom = $this->assignClassroom($offering, $timeSlots, $classrooms, $constraints);

                if (!$classroom) {
                    error_log("generateSchedule: No classroom for course: {$offering['course_code']}");
                    continue;
                }

                $sectionId = $this->ensureSectionExists($courseId, $semesterId, $offering['course_code']);
                $sectionQuery = "SELECT section_name FROM sections WHERE section_id = :sectionId";
                $stmt = $this->db->prepare($sectionQuery);
                $stmt->execute([':sectionId' => $sectionId]);
                $sectionName = $stmt->fetchColumn();

                // Use the faculty_id from the first time slot (assuming all slots for this course use the same faculty)
                $facultyId = $timeSlots[0]['faculty_id'] ?? $qualifiedFaculty[0]['faculty_id'];

                $schedule[] = [
                    'offering_id' => $offering['offering_id'],
                    'course_id' => $courseId,
                    'course_code' => $offering['course_code'],
                    'course_name' => $offering['course_name'],
                    'section_id' => $sectionId,
                    'section_name' => $sectionName,
                    'faculty_id' => $facultyId,
                    'room_id' => $classroom['room_id'],
                    'year_level' => $offering['year_level'] ?? ($yearLevel === 'all' ? '1st Year' : $yearLevel),
                    'time_slots' => array_map(function ($slot) {
                        // Remove faculty_id from time_slots for consistency
                        return [
                            'day_of_week' => $slot['day_of_week'],
                            'start_time' => $slot['start_time'],
                            'end_time' => $slot['end_time']
                        ];
                    }, $timeSlots),
                    'lecture_hours' => $offering['lecture_hours'],
                    'lab_hours' => $offering['lab_hours']
                ];
                error_log("generateSchedule: Added course {$offering['course_code']}, year_level={$schedule[count($schedule) - 1]['year_level']}, faculty_id=$facultyId");
            }

            error_log("generateSchedule: Generated " . count($schedule) . " schedule entries");
            return $schedule;
        } catch (Exception $e) {
            error_log("generateSchedule error: " . $e->getMessage());
            return [];
        }
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

    public function assignClassroom($offering, $timeSlots, $classrooms, $constraints = [])
    {
        try {
            error_log("assignClassroom: Assigning for course {$offering['course_code']}");

            shuffle($classrooms); // Randomize to avoid picking same room

            foreach ($classrooms as $classroom) {
                if (isset($constraints['room_capacity']) && $classroom['capacity'] < $offering['expected_students']) {
                    error_log("assignClassroom: Skipping room_id={$classroom['room_id']} (capacity={$classroom['capacity']} < {$offering['expected_students']})");
                    continue;
                }

                // Check for conflicts
                $conflictQuery = "SELECT COUNT(*) FROM schedules 
                            WHERE room_id = :roomId 
                            AND semester_id = :semesterId 
                            AND day_of_week = :day 
                            AND (
                                (:start <= end_time AND :end >= start_time)
                            )";
                $stmt = $this->db->prepare($conflictQuery);

                $noConflict = true;
                foreach ($timeSlots as $slot) {
                    $stmt->execute([
                        ':roomId' => $classroom['room_id'],
                        ':semesterId' => $this->semesterId,
                        ':day' => $slot['day_of_week'],
                        ':start' => $slot['start_time'],
                        ':end' => $slot['end_time']
                    ]);
                    if ($stmt->fetchColumn() > 0) {
                        $noConflict = false;
                        error_log("assignClassroom: Conflict for room_id={$classroom['room_id']}, slot=" . json_encode($slot));
                        break;
                    }
                }

                if ($noConflict) {
                    error_log("assignClassroom: Assigned room_id={$classroom['room_id']} for {$offering['course_code']}");
                    return $classroom;
                }
            }

            error_log("assignClassroom: No suitable classroom for {$offering['course_code']}");
            return null;
        } catch (Exception $e) {
            error_log("assignClassroom error: " . $e->getMessage());
            return null;
        }
    }

    public function saveGeneratedSchedule($scheduleData, $semesterId)
    {
        try {
            error_log("saveGeneratedSchedule: Called with semester_id=$semesterId, entries=" . count($scheduleData));

            if (empty($scheduleData) || !is_array($scheduleData)) {
                error_log("saveGeneratedSchedule: No valid schedule data provided");
                return false;
            }

            $this->db->beginTransaction();

            // Clear existing schedules for this semester
            $deleteQuery = "DELETE FROM schedules WHERE semester_id = :semesterId";
            $stmt = $this->db->prepare($deleteQuery);
            $stmt->execute([':semesterId' => $semesterId]);
            error_log("saveGeneratedSchedule: Cleared existing schedules for semester_id=$semesterId");

            $insertQuery = "INSERT INTO schedules (
            course_id, section_id, room_id, semester_id, faculty_id,
            schedule_type, day_of_week, start_time, end_time, status,
            created_at
        ) VALUES (
            :course_id, :section_id, :room_id, :semester_id, :faculty_id,
            'F2F', :day_of_week, :start_time, :end_time, 'Pending',
            NOW()
        )";

            $stmt = $this->db->prepare($insertQuery);
            $savedEntries = 0;

            foreach ($scheduleData as $schedule) {
                if (!isset($schedule['course_id'], $schedule['section_id'], $schedule['room_id'], $schedule['faculty_id'], $schedule['time_slots'])) {
                    error_log("saveGeneratedSchedule: Skipping invalid schedule entry: " . json_encode($schedule));
                    continue;
                }

                $sectionId = $this->ensureSectionExists(
                    $schedule['course_id'],
                    $semesterId,
                    $schedule['course_code'] ?? 'Unknown'
                );

                foreach ($schedule['time_slots'] as $slot) {
                    if (empty($slot['day_of_week']) || empty($slot['start_time']) || empty($slot['end_time'])) {
                        error_log("saveGeneratedSchedule: Skipping slot for course_id={$schedule['course_id']}: Missing time/day - " . json_encode($slot));
                        continue;
                    }

                    $stmt->execute([
                        ':course_id' => $schedule['course_id'],
                        ':section_id' => $sectionId,
                        ':room_id' => $schedule['room_id'],
                        ':semester_id' => $semesterId,
                        ':faculty_id' => $schedule['faculty_id'],
                        ':day_of_week' => $slot['day_of_week'],
                        ':start_time' => $slot['start_time'],
                        ':end_time' => $slot['end_time']
                    ]);
                    $savedEntries++;
                }

                // Update teaching load (assumes method exists)
                if (isset($schedule['lecture_hours'], $schedule['lab_hours'])) {
                    $this->updateTeachingLoad(
                        $schedule['faculty_id'],
                        $schedule['course_id'], // Use course_id instead of offering_id
                        $sectionId,
                        $schedule['lecture_hours'] + $schedule['lab_hours']
                    );
                }
            }

            // Update course_offerings status
            $courseIds = array_column($scheduleData, 'course_id');
            if (!empty($courseIds)) {
                $placeholders = implode(',', array_fill(0, count($courseIds), '?'));
                $updateQuery = "UPDATE course_offerings 
                           SET status = 'Scheduled', updated_at = NOW() 
                           WHERE semester_id = ? AND course_id IN ($placeholders)";
                $params = array_merge([$semesterId], $courseIds);
                $stmt = $this->db->prepare($updateQuery);
                $stmt->execute($params);
                error_log("saveGeneratedSchedule: Updated course_offerings for " . count($courseIds) . " courses");
            }

            $this->db->commit();
            error_log("saveGeneratedSchedule: Saved $savedEntries schedule entries for semester_id=$semesterId");
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("saveGeneratedSchedule error: " . $e->getMessage());
            throw new Exception("Failed to save schedule: " . $e->getMessage());
        }
    }

    private function calculateTotalHours($timeSlots)
    {
        $totalMinutes = 0;
        foreach ($timeSlots as $slot) {
            $start = new DateTime($slot['start_time']);
            $end = new DateTime($slot['end_time']);
            $diff = $start->diff($end);
            $totalMinutes += $diff->h * 60 + $diff->i;
        }
        return ceil($totalMinutes / 60); // Return in hours
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

    public function getQualifiedFaculty($courseId, $facultyAvailability)
    {
        try {
            // Get course_code for the course_id
            $query = "SELECT course_code FROM courses WHERE course_id = :courseId";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':courseId' => $courseId]);
            $courseCode = $stmt->fetchColumn();
            error_log("getQualifiedFaculty: Course ID $courseId maps to course_code $courseCode");

            // Find faculty specialized in the course
            $query = "SELECT f.faculty_id, f.first_name, f.last_name 
                 FROM faculty f 
                 JOIN specializations s ON f.faculty_id = s.faculty_id 
                 WHERE s.subject_name = :courseCode 
                 AND f.faculty_id IN (" . implode(',', array_keys($facultyAvailability)) . ")";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':courseCode' => $courseCode]);
            $qualifiedFaculty = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("getQualifiedFaculty: Found " . count($qualifiedFaculty) . " qualified faculty for $courseCode: " . json_encode($qualifiedFaculty));

            return $qualifiedFaculty;
        } catch (Exception $e) {
            error_log("getQualifiedFaculty error for course_id=$courseId: " . $e->getMessage());
            return [];
        }
    }

    public function assignRandomSpecializations($semesterId, $departmentId)
    {
        try {
            // Step 1: Get all course codes for the semester and department
            $query = "SELECT c.course_code 
             FROM course_offerings co 
             JOIN courses c ON co.course_id = c.course_id 
             WHERE co.semester_id = :semesterId AND c.department_id = :departmentId";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':semesterId' => $semesterId,
                ':departmentId' => $departmentId
            ]);
            $courses = $stmt->fetchAll(PDO::FETCH_COLUMN);
            error_log("Courses found: " . json_encode($courses));

            if (empty($courses)) {
                error_log("No course offerings found for semester_id=$semesterId, department_id=$departmentId");
                return;
            }

            // Step 2: Get all faculty members in the department
            $query = "SELECT DISTINCT f.faculty_id 
             FROM faculty f 
             WHERE f.department_id = :departmentId";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':departmentId' => $departmentId
            ]);
            $facultyIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            error_log("Faculty in department: " . json_encode($facultyIds));

            // Rest of the method remains the same...
            // [Keep the existing specialization assignment logic]
        } catch (Exception $e) {
            error_log("Error in assignRandomSpecializations: " . $e->getMessage());
        }
    }

    public function getFacultyAvailability($departmentId, $semesterId)
    {
        error_log("Getting faculty information for department: $departmentId");

        $query = "SELECT 
            f.faculty_id, 
            f.first_name, 
            f.last_name,
            f.max_hours,
            (SELECT SUM(c.lecture_hours + c.lab_hours) 
             FROM schedules s 
             JOIN courses c ON s.course_id = c.course_id 
             WHERE s.faculty_id = f.faculty_id AND s.semester_id = :semesterId) as current_load
          FROM faculty f
          WHERE f.department_id = :departmentId
          ORDER BY f.last_name, f.first_name";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':departmentId' => $departmentId,
            ':semesterId' => $semesterId
        ]);

        $faculty = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Convert to the expected format (array keyed by faculty_id)
        $result = [];
        foreach ($faculty as $f) {
            $result[$f['faculty_id']] = [$f]; // Wrapping in array to maintain compatibility
        }

        error_log("Found " . count($result) . " faculty members");
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

    /**
     * Gets course offerings for a semester and department.
     *
     * @param int $semesterId
     * @param int $departmentId
     * @param string $yearLevel
     * @return array
     */
    public function getCourseOfferings($semesterId, $departmentId, $yearLevel = 'all')
    {
        try {
            $query = "SELECT co.offering_id, co.course_id, c.course_code, c.course_name, 
                        co.expected_sections, co.expected_students, 
                        c.lecture_hours, c.lab_hours, co.status, 
                        c.year_level
                 FROM course_offerings co
                 JOIN courses c ON co.course_id = c.course_id
                 WHERE co.semester_id = :semesterId 
                 AND c.department_id = :departmentId";

            if ($yearLevel !== 'all') {
                $query .= " AND c.year_level = :yearLevel";
            }

            $stmt = $this->db->prepare($query);
            $params = [
                ':semesterId' => $semesterId,
                ':departmentId' => $departmentId
            ];
            if ($yearLevel !== 'all') {
                $params[':yearLevel'] = $yearLevel;
            }
            $stmt->execute($params);
            $offerings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("getCourseOfferings: Found " . count($offerings) . " offerings for semester_id=$semesterId, year_level=$yearLevel");
            foreach ($offerings as $offering) {
                error_log("getCourseOfferings: Offering course_code={$offering['course_code']}, year_level={$offering['year_level']}");
            }
            return $offerings;
        } catch (Exception $e) {
            error_log("getCourseOfferings error: " . $e->getMessage());
            return [];
        }
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

    public function detectConflicts(array $scheduleData, int $departmentId): array
    {
        $conflicts = [];

        // Get all existing schedules for the department
        $existingSchedules = $this->getDepartmentSchedule($departmentId, $scheduleData['semester_id']);

        // Check for faculty conflicts
        foreach ($scheduleData['schedule'] as $newItem) {
            foreach ($newItem['time_slots'] as $slot) {
                // Check against existing schedules
                foreach ($existingSchedules as $existing) {
                    foreach ($existing['time_slots'] as $existingSlot) {
                        // Faculty teaching two classes at same time
                        if (
                            $newItem['faculty_id'] == $existing['faculty_id'] &&
                            $slot['day_of_week'] == $existingSlot['day_of_week'] &&
                            $this->timeOverlap(
                                $slot['start_time'],
                                $slot['end_time'],
                                $existingSlot['start_time'],
                                $existingSlot['end_time']
                            )
                        ) {
                            $conflicts[] = [
                                'type' => 'faculty',
                                'message' => "Faculty {$newItem['faculty_name']} is already teaching {$existing['course_code']} at this time",
                                'item' => $newItem,
                                'conflicting_with' => $existing
                            ];
                        }

                        // Room double booking
                        if (
                            $newItem['room_id'] == $existing['room_id'] &&
                            $slot['day_of_week'] == $existingSlot['day_of_week'] &&
                            $this->timeOverlap(
                                $slot['start_time'],
                                $slot['end_time'],
                                $existingSlot['start_time'],
                                $existingSlot['end_time']
                            )
                        ) {
                            $conflicts[] = [
                                'type' => 'room',
                                'message' => "Room {$newItem['room_name']} is already booked for {$existing['course_code']} at this time",
                                'item' => $newItem,
                                'conflicting_with' => $existing
                            ];
                        }
                    }
                }

                // Check against other new items
                foreach ($scheduleData['schedule'] as $otherNewItem) {
                    if ($newItem === $otherNewItem) continue;

                    foreach ($otherNewItem['time_slots'] as $otherSlot) {
                        // Faculty conflict within new schedule
                        if (
                            $newItem['faculty_id'] == $otherNewItem['faculty_id'] &&
                            $slot['day_of_week'] == $otherSlot['day_of_week'] &&
                            $this->timeOverlap(
                                $slot['start_time'],
                                $slot['end_time'],
                                $otherSlot['start_time'],
                                $otherSlot['end_time']
                            )
                        ) {
                            $conflicts[] = [
                                'type' => 'faculty',
                                'message' => "Faculty {$newItem['faculty_name']} is scheduled for multiple classes at this time",
                                'item' => $newItem,
                                'conflicting_with' => $otherNewItem
                            ];
                        }

                        // Room conflict within new schedule
                        if (
                            $newItem['room_id'] == $otherNewItem['room_id'] &&
                            $slot['day_of_week'] == $otherSlot['day_of_week'] &&
                            $this->timeOverlap(
                                $slot['start_time'],
                                $slot['end_time'],
                                $otherSlot['start_time'],
                                $otherSlot['end_time']
                            )
                        ) {
                            $conflicts[] = [
                                'type' => 'room',
                                'message' => "Room {$newItem['room_name']} is double-booked in this schedule",
                                'item' => $newItem,
                                'conflicting_with' => $otherNewItem
                            ];
                        }
                    }
                }
            }
        }

        return $conflicts;
    }

    private function timeOverlap($start1, $end1, $start2, $end2): bool
    {
        return ($start1 < $end2) && ($end1 > $start2);
    }

    public function createCurriculumWithFile($departmentId, $data, $uploadedBy)
    {
        try {
            $this->db->beginTransaction();

            $fileName = null;
            $fileType = null;
            $filePath = null;
            $courses = []; // Array to store [course_id, year_level, semester]

            if (!empty($data['file']) && $data['file']['tmp_name']) {
                $allowedTypes = ['doc', 'docx', 'pdf', 'xlsx'];
                $maxSize = 5 * 1024 * 1024;
                $ext = strtolower(pathinfo($data['file']['name'], PATHINFO_EXTENSION));

                if (!in_array($ext, $allowedTypes)) {
                    throw new Exception("Invalid file type. Allowed: doc, docx, pdf, xlsx");
                }
                if ($data['file']['size'] > $maxSize) {
                    throw new Exception("File size exceeds 5MB");
                }
                if ($data['file']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("File upload error: " . $data['file']['error']);
                }
                if (!is_uploaded_file($data['file']['tmp_name'])) {
                    throw new Exception("Invalid upload attempt");
                }

                $uploadDir = __DIR__ . '/../src/views/chair/curriculum.php';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileName = $data['file']['name'];
                $uniqueFileName = time() . '_' . str_replace(' ', '_', $fileName);
                $filePath = 'curricula/' . $uniqueFileName;
                $fullPath = $uploadDir . $uniqueFileName;
                if (!move_uploaded_file($data['file']['tmp_name'], $fullPath)) {
                    throw new Exception("Failed to save file");
                }
                $fileType = $ext;

                // Parse file based on type
                if ($ext === 'xlsx') {
                    try {
                        $spreadsheet = SpreadsheetIOFactory::load($fullPath);
                        $sheet = $spreadsheet->getActiveSheet();
                        foreach ($sheet->getRowIterator() as $row) {
                            $courseCode = trim($sheet->getCell('A' . $row->getRowIndex())->getValue() ?? '');
                            $yearLevel = trim($sheet->getCell('B' . $row->getRowIndex())->getValue() ?? '1st Year');
                            $semester = trim($sheet->getCell('C' . $row->getRowIndex())->getValue() ?? '1st');
                            if ($courseCode && $this->isValidCourseCode($courseCode)) {
                                $course = $this->getCourseByCode($courseCode, $departmentId);
                                if ($course) {
                                    $courses[] = [
                                        'course_id' => $course['course_id'],
                                        'year_level' => $this->validateYearLevel($yearLevel),
                                        'semester' => $this->validateSemester($semester)
                                    ];
                                } else {
                                    error_log("Course code not found in XLSX: $courseCode");
                                }
                            }
                        }
                    } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
                        error_log("XLSX parsing error: " . $e->getMessage());
                        throw new Exception("Failed to parse XLSX file");
                    }
                } elseif ($ext === 'doc' || $ext === 'docx') {
                    try {
                        $phpWord = WordIOFactory::load($fullPath);
                        $text = '';
                        foreach ($phpWord->getSections() as $section) {
                            foreach ($section->getElements() as $element) {
                                if (method_exists($element, 'getText')) {
                                    $text .= $element->getText() . "\n";
                                }
                            }
                        }
                        $courses = array_merge($courses, $this->parseTextLines($text, $departmentId));
                    } catch (\PhpOffice\PhpWord\Exception\Exception $e) {
                        error_log("DOC/DOCX parsing error: " . $e->getMessage());
                        throw new Exception("Failed to parse DOC/DOCX file");
                    }
                } elseif ($ext === 'pdf') {
                    try {
                        $parser = new PdfParser();
                        $pdf = $parser->parseFile($fullPath);
                        $text = $pdf->getText();
                        $courses = array_merge($courses, $this->parseTextLines($text, $departmentId));
                    } catch (\Exception $e) {
                        error_log("PDF parsing error: " . $e->getMessage());
                        throw new Exception("Failed to parse PDF file");
                    }
                }
            }

            // Insert into curricula
            $query = "INSERT INTO curricula (
                          curriculum_name, curriculum_code, description, total_units, department_id, 
                          effective_year, status, file_name, file_type, file_path, uploaded_by, created_at, updated_at
                      ) VALUES (
                          :name, :code, :description, :total_units, :department_id, 
                          :effective_year, 'Draft', :file_name, :file_type, :file_path, :uploaded_by, NOW(), NOW()
                      )";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':name' => $data['name'],
                ':code' => $data['code'],
                ':description' => $data['description'] ?: '',
                ':total_units' => $data['total_units'] ?? 0,
                ':department_id' => $departmentId,
                ':effective_year' => $data['effective_year'] ?? date('Y'),
                ':file_name' => $fileName,
                ':file_type' => $fileType,
                ':file_path' => $filePath,
                ':uploaded_by' => $uploadedBy
            ]);
            $curriculumId = $this->db->lastInsertId();

            // Insert into curriculum_courses
            foreach (array_unique($courses, SORT_REGULAR) as $course) {
                $query = "INSERT INTO curriculum_courses (
                              curriculum_id, course_id, year_level, semester, subject_type, is_core, created_at
                          ) VALUES (
                              :curriculum_id, :course_id, :year_level, :semester, :subject_type, :is_core, NOW()
                          )";
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    ':curriculum_id' => $curriculumId,
                    ':course_id' => $course['course_id'],
                    ':year_level' => $course['year_level'],
                    ':semester' => $course['semester'],
                    ':subject_type' => 'Major', // Adjust based on your logic
                    ':is_core' => 1
                ]);
            }

            // Insert into curriculum_versions
            $query = "INSERT INTO curriculum_versions (
                          curriculum_id, version_number, approval_status, created_at
                      ) VALUES (
                          :curriculum_id, '1.0', 'Pending', NOW()
                      )";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':curriculum_id' => $curriculumId]);

            // Insert into curriculum_approvals
            $query = "INSERT INTO curriculum_approvals (
                          curriculum_id, requested_by, approval_level, status, created_at, updated_at
                      ) VALUES (
                          :curriculum_id, :requested_by, 1, 'Pending', NOW(), NOW()
                      )";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':curriculum_id' => $curriculumId,
                ':requested_by' => $uploadedBy
            ]);

            $this->db->commit();
            return $curriculumId;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Create curriculum error: " . $e->getMessage());
            throw $e;
        }
    }

    public function updateCurriculumFile($curriculumId, $file, $uploadedBy, $versionNotes)
    {
        try {
            $this->db->beginTransaction();

            $allowedTypes = ['doc', 'docx', 'pdf', 'xlsx'];
            $maxSize = 5 * 1024 * 1024;
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedTypes)) {
                throw new Exception("Invalid file type. Allowed: doc, docx, pdf, xlsx");
            }
            if ($file['size'] > $maxSize) {
                throw new Exception("File size exceeds 5MB");
            }
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("File upload error: " . $file['error']);
            }
            if (!is_uploaded_file($file['tmp_name'])) {
                throw new Exception("Invalid upload attempt");
            }

            $uploadDir = __DIR__ . '/../../public/Uploads/curricula/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Delete old file
            $query = "SELECT file_path FROM curricula WHERE curriculum_id = :curriculum_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':curriculum_id' => $curriculumId]);
            $oldFilePath = $stmt->fetchColumn();
            if ($oldFilePath && file_exists(__DIR__ . '/../../public/' . $oldFilePath)) {
                unlink(__DIR__ . '/../../public/' . $oldFilePath);
            }

            $fileName = $file['name'];
            $uniqueFileName = $curriculumId . '_' . time() . '_' . str_replace(' ', '_', $fileName);
            $filePath = 'curricula/' . $uniqueFileName;
            $fullPath = $uploadDir . $uniqueFileName;
            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                throw new Exception("Failed to save file");
            }

            // Parse file based on type
            $courses = [];
            if ($ext === 'xlsx') {
                try {
                    $spreadsheet = SpreadsheetIOFactory::load($fullPath);
                    $sheet = $spreadsheet->getActiveSheet();
                    foreach ($sheet->getRowIterator() as $row) {
                        $courseCode = trim($sheet->getCell('A' . $row->getRowIndex())->getValue() ?? '');
                        $yearLevel = trim($sheet->getCell('B' . $row->getRowIndex())->getValue() ?? '1st Year');
                        $semester = trim($sheet->getCell('C' . $row->getRowIndex())->getValue() ?? '1st');
                        if ($courseCode && $this->isValidCourseCode($courseCode)) {
                            $course = $this->getCourseByCode($courseCode);
                            if ($course) {
                                $courses[] = [
                                    'course_id' => $course['course_id'],
                                    'year_level' => $this->validateYearLevel($yearLevel),
                                    'semester' => $this->validateSemester($semester)
                                ];
                            } else {
                                error_log("Course code not found in XLSX: $courseCode");
                            }
                        }
                    }
                } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
                    error_log("XLSX parsing error: " . $e->getMessage());
                    throw new Exception("Failed to parse XLSX file");
                }
            } elseif ($ext === 'doc' || $ext === 'docx') {
                try {
                    $phpWord = WordIOFactory::load($fullPath);
                    $text = '';
                    foreach ($phpWord->getSections() as $section) {
                        foreach ($section->getElements() as $element) {
                            if (method_exists($element, 'getText')) {
                                $text .= $element->getText() . "\n";
                            }
                        }
                    }
                    $courses = $this->parseTextLines($text);
                } catch (\PhpOffice\PhpWord\Exception\Exception $e) {
                    error_log("DOC/DOCX parsing error: " . $e->getMessage());
                    throw new Exception("Failed to parse DOC/DOCX file");
                }
            } elseif ($ext === 'pdf') {
                try {
                    $parser = new PdfParser();
                    $pdf = $parser->parseFile($fullPath);
                    $text = $pdf->getText();
                    $courses = $this->parseTextLines($text);
                } catch (\Exception $e) {
                    error_log("PDF parsing error: " . $e->getMessage());
                    throw new Exception("Failed to parse PDF file");
                }
            }

            // Update curricula
            $query = "UPDATE curricula 
                      SET file_name = :file_name, file_type = :file_type, file_path = :file_path, 
                          uploaded_by = :uploaded_by, updated_at = NOW()
                      WHERE curriculum_id = :curriculum_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':file_name' => $fileName,
                ':file_type' => $ext,
                ':file_path' => $filePath,
                ':uploaded_by' => $uploadedBy,
                ':curriculum_id' => $curriculumId
            ]);

            // Update curriculum_courses if courses provided
            if ($courses) {
                $query = "DELETE FROM curriculum_courses WHERE curriculum_id = :curriculum_id";
                $stmt = $this->db->prepare($query);
                $stmt->execute([':curriculum_id' => $curriculumId]);

                foreach (array_unique($courses, SORT_REGULAR) as $course) {
                    $query = "INSERT INTO curriculum_courses (
                                  curriculum_id, course_id, year_level, semester, subject_type, is_core, created_at
                              ) VALUES (
                                  :curriculum_id, :course_id, :year_level, :semester, :subject_type, :is_core, NOW()
                              )";
                    $stmt = $this->db->prepare($query);
                    $stmt->execute([
                        ':curriculum_id' => $curriculumId,
                        ':course_id' => $course['course_id'],
                        ':year_level' => $course['year_level'],
                        ':semester' => $course['semester'],
                        ':subject_type' => 'Major',
                        ':is_core' => 1
                    ]);
                }
            }

            // Insert curriculum_versions
            $query = "SELECT version_number FROM curriculum_versions 
                      WHERE curriculum_id = :curriculum_id 
                      ORDER BY version_id DESC LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':curriculum_id' => $curriculumId]);
            $lastVersion = $stmt->fetchColumn() ?: '1.0';
            $newVersion = number_format((float)$lastVersion + 0.1, 1);

            $query = "INSERT INTO curriculum_versions (
                          curriculum_id, version_number, approval_status, notes, created_at
                      ) VALUES (
                          :curriculum_id, :version_number, 'Pending', :notes, NOW()
                      )";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':curriculum_id' => $curriculumId,
                ':version_number' => $newVersion,
                ':notes' => $versionNotes ?: null
            ]);

            // Insert curriculum_approvals
            $query = "INSERT INTO curriculum_approvals (
                          curriculum_id, requested_by, approval_level, status, created_at, updated_at
                      ) VALUES (
                          :curriculum_id, :requested_by, 1, 'Pending', NOW(), NOW()
                      )";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':curriculum_id' => $curriculumId,
                ':requested_by' => $uploadedBy
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Update file error: " . $e->getMessage());
            throw $e;
        }
    }

    private function isValidCourseCode($code)
    {
        // Matches CS101, MATH-102, IT 201L, up to 20 chars
        return preg_match('/^[A-Z]{2,10}[- ]?[\dA-Z]{1,10}$/', $code);
    }

    private function getCourseByCode($courseCode, $departmentId = null)
    {
        $query = "SELECT course_id, year_level, semester 
                  FROM courses 
                  WHERE course_code = :course_code AND is_active = 1";
        if ($departmentId) {
            $query .= " AND department_id = :department_id";
        }
        $stmt = $this->db->prepare($query);
        $params = [':course_code' => $courseCode];
        if ($departmentId) {
            $params[':department_id'] = $departmentId;
        }
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function parseTextLines($text, $departmentId = null)
    {
        $courses = [];
        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line) {
                continue;
            }
            // Try comma-separated: CS101, 1st Year, 1st Semester
            if (preg_match('/^([A-Z]{2,10}[- ]?[\dA-Z]{1,10}),\s*([^,]+),\s*([^,]+)/i', $line, $matches)) {
                $courseCode = trim($matches[1]);
                $yearLevel = trim($matches[2]);
                $semester = trim($matches[3]);
                if ($this->isValidCourseCode($courseCode)) {
                    $course = $this->getCourseByCode($courseCode, $departmentId);
                    if ($course) {
                        $courses[] = [
                            'course_id' => $course['course_id'],
                            'year_level' => $this->validateYearLevel($yearLevel),
                            'semester' => $this->validateSemester($semester)
                        ];
                    } else {
                        error_log("Course code not found: $courseCode");
                    }
                }
            } elseif ($this->isValidCourseCode($line)) {
                // Single course code
                $course = $this->getCourseByCode($line, $departmentId);
                if ($course) {
                    $courses[] = [
                        'course_id' => $course['course_id'],
                        'year_level' => $course['year_level'] ?? '1st Year',
                        'semester' => $course['semester'] ?? '1st'
                    ];
                } else {
                    error_log("Course code not found: $line");
                }
            }
        }
        return $courses;
    }

    private function validateYearLevel($yearLevel)
    {
        $valid = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $yearLevel = trim($yearLevel);
        return in_array($yearLevel, $valid) ? $yearLevel : '1st Year';
    }

    private function validateSemester($semester)
    {
        $valid = ['1st', '2nd', 'Summer'];
        $semester = trim(str_replace('Semester', '', $semester));
        return in_array($semester, $valid) ? $semester : '1st';
    }

    public function getLatestCurriculumVersion($curriculumId)
    {
        try {
            $query = "SELECT version_number 
                      FROM curriculum_versions 
                      WHERE curriculum_id = :curriculum_id 
                      ORDER BY version_id DESC LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':curriculum_id' => $curriculumId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Fetch version error: " . $e->getMessage());
            return [];
        }
    }
}
