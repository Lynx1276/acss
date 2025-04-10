<?php
require_once __DIR__ . '/../config/Database.php';

$db = (new Database())->connect();
$semesterId = 2;
$departmentId = 14;

// Get all active courses for the department
$courses = $db->query("SELECT course_id FROM courses 
                      WHERE department_id = $departmentId AND is_active = TRUE")
    ->fetchAll(PDO::FETCH_ASSOC);

foreach ($courses as $course) {
    $courseId = $course['course_id'];

    // Check if offering already exists
    $exists = $db->query("SELECT 1 FROM course_offerings 
                         WHERE course_id = $courseId AND semester_id = $semesterId")
        ->fetchColumn();

    if (!$exists) {
        $expectedStudents = 30; // Default value
        $db->query("INSERT INTO course_offerings 
                   (course_id, semester_id, expected_students, status, created_at)
                   VALUES 
                   ($courseId, $semesterId, $expectedStudents, 'Pending', NOW())");

        echo "Created offering for course ID: $courseId<br>";
    }
}

echo "Done creating offerings for semester $semesterId";
