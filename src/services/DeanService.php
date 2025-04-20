<?php
// src/services/DeanService.php
require_once __DIR__ . '/../services/SchedulingService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../config/Database.php';

use App\config\Database;

class DeanService
{
    private $db;
    private $schedulingService;

    public function __construct()
    {
        $this->db = (new Database())->connect();
        $this->schedulingService = new SchedulingService();
    }

    public function getCollegeMetrics($collegeId)
    {
        $metrics = [];
        $query = "SELECT COUNT(*) FROM departments WHERE college_id = :college_id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':college_id' => $collegeId]);
        $metrics['departments'] = (int)$stmt->fetchColumn();

        $query = "SELECT COUNT(*) 
                  FROM sections s
                  JOIN section_courses sc ON s.section_id = sc.section_id
                  JOIN courses c ON sc.course_id = c.course_id
                  JOIN departments d ON c.department_id = d.department_id
                  WHERE d.college_id = :college_id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':college_id' => $collegeId]);
        $metrics['sections'] = (int)$stmt->fetchColumn();

        $query = "SELECT COUNT(*) 
                  FROM courses c
                  JOIN departments d ON c.department_id = d.department_id
                  WHERE d.college_id = :college_id AND c.is_active = 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':college_id' => $collegeId]);
        $metrics['courses'] = (int)$stmt->fetchColumn();

        $query = "SELECT COUNT(*) 
                  FROM faculty_requests fr
                  WHERE fr.college_id = :college_id AND fr.status = 'pending'";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':college_id' => $collegeId]);
        $metrics['pending_faculty_requests'] = (int)$stmt->fetchColumn();

        $query = "SELECT COUNT(*) 
                  FROM schedules s
                  JOIN courses c ON s.course_id = c.course_id
                  JOIN departments d ON c.department_id = d.department_id
                  JOIN semesters sem ON s.semester_id = sem.semester_id
                  WHERE d.college_id = :college_id AND sem.is_current = 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':college_id' => $collegeId]);
        $metrics['schedules'] = (int)$stmt->fetchColumn();

        return $metrics;
    }

    public function updateFacultyRequestStatus($requestId, $status, $deanId)
    {
        if (!in_array($status, ['approved', 'rejected'])) {
            throw new Exception("Invalid status");
        }

        $this->db->beginTransaction();
        try {
            $query = "SELECT college_id FROM users WHERE user_id = :dean_id AND role_id = 4";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':dean_id' => $deanId]);
            $dean = $stmt->fetch();
            if (!$dean) {
                throw new Exception("Invalid dean");
            }

            $query = "UPDATE faculty_requests 
                      SET status = :status, updated_at = NOW() 
                      WHERE request_id = :request_id AND college_id = :college_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':status' => $status,
                ':request_id' => $requestId,
                ':college_id' => $dean['college_id']
            ]);
            if ($stmt->rowCount() === 0) {
                throw new Exception("Request not found or unauthorized");
            }

            if ($status === 'approved') {
                $query = "SELECT employee_id, first_name, middle_name, last_name, suffix, email, 
                                 username, password_hash, department_id, college_id, 
                                 academic_rank, employment_type
                          FROM faculty_requests 
                          WHERE request_id = :request_id";
                $stmt = $this->db->prepare($query);
                $stmt->execute([':request_id' => $requestId]);
                $request = $stmt->fetch(PDO::FETCH_ASSOC);

                $query = "INSERT INTO users (employee_id, username, password_hash, email, 
                                            first_name, middle_name, last_name, suffix, 
                                            role_id, department_id, college_id, is_active) 
                          VALUES (:employee_id, :username, :password_hash, :email, 
                                  :first_name, :middle_name, :last_name, :suffix, 
                                  6, :department_id, :college_id, 1)";
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    ':employee_id' => $request['employee_id'],
                    ':username' => $request['username'],
                    ':password_hash' => $request['password_hash'],
                    ':email' => $request['email'],
                    ':first_name' => $request['first_name'],
                    ':middle_name' => $request['middle_name'],
                    ':last_name' => $request['last_name'],
                    ':suffix' => $request['suffix'],
                    ':department_id' => $request['department_id'],
                    ':college_id' => $request['college_id']
                ]);
                $userId = $this->db->lastInsertId();

                $query = "INSERT INTO faculty (employee_id, first_name, middle_name, last_name, suffix, 
                                              email, academic_rank, employment_type, department_id, user_id) 
                          VALUES (:employee_id, :first_name, :middle_name, :last_name, :suffix, 
                                  :email, :academic_rank, :employment_type, :department_id, :user_id)";
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    ':employee_id' => $request['employee_id'],
                    ':first_name' => $request['first_name'],
                    ':middle_name' => $request['middle_name'],
                    ':last_name' => $request['last_name'],
                    ':suffix' => $request['suffix'],
                    ':email' => $request['email'],
                    ':academic_rank' => $request['academic_rank'],
                    ':employment_type' => $request['employment_type'],
                    ':department_id' => $request['department_id'],
                    ':user_id' => $userId
                ]);
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Failed to process request: " . $e->getMessage());
        }
    }

    public function deactivateAccount($userId, $deanId)
    {
        $query = "SELECT u.role_id, u.college_id 
                  FROM users u
                  WHERE u.user_id = :user_id AND u.role_id IN (5, 6)";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':user_id' => $userId]);
        $targetUser = $stmt->fetch();

        if (!$targetUser) {
            throw new Exception("User not found or invalid role");
        }

        $query = "SELECT college_id FROM users WHERE user_id = :dean_id AND role_id = 4";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':dean_id' => $deanId]);
        $dean = $stmt->fetch();

        if (!$dean || $dean['college_id'] != $targetUser['college_id']) {
            throw new Exception("Dean can only deactivate accounts in their college");
        }

        $query = "UPDATE users 
                  SET is_active = 0 
                  WHERE user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':user_id' => $userId]);
        if ($stmt->rowCount() === 0) {
            throw new Exception("User not found");
        }
    }

    public function getCollegeDepartments($collegeId)
    {
        return $this->schedulingService->getCollegeDepartments($collegeId);
    }

    public function getCurrentSemester()
    {
        return $this->schedulingService->getCurrentSemester();
    }

    public function getClassSchedules($collegeId, $departmentId = null)
    {
        return $this->schedulingService->getClassSchedules($collegeId, $departmentId);
    }

    public function getPendingFacultyRequests($collegeId, $departmentId = null)
    {
        $query = "SELECT fr.request_id, fr.first_name, fr.last_name, fr.username, 
                         fr.email, fr.academic_rank, d.department_name, fr.created_at,
                         COUNT(*) OVER () as count
                  FROM faculty_requests fr
                  JOIN departments d ON fr.department_id = d.department_id
                  WHERE fr.college_id = :college_id AND fr.status = 'pending'";
        $params = [':college_id' => $collegeId];
        if ($departmentId) {
            $query .= " AND fr.department_id = :department_id";
            $params[':department_id'] = $departmentId;
        }
        $query .= " ORDER BY fr.created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserProfile($userId)
    {
        $query = "SELECT u.*, d.department_name, c.college_name
                  FROM users u
                  LEFT JOIN departments d ON u.department_id = d.department_id
                  LEFT JOIN colleges c ON u.college_id = c.college_id
                  WHERE u.user_id = :user_id AND u.role_id = 4";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateProfile($userId, $data, $file = null)
    {
        // Check for duplicate username or email
        $query = "SELECT user_id FROM users 
                  WHERE (username = :username OR email = :email) 
                  AND user_id != :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':username' => $data['username'],
            ':email' => $data['email'],
            ':user_id' => $userId
        ]);
        if ($stmt->fetch()) {
            throw new Exception("Username or email already in use");
        }

        // Handle profile picture upload
        $profilePicture = $data['current_profile_picture'];
        if ($file && $file['tmp_name']) {
            $uploadDir = __DIR__ . '/../../public/uploads/profiles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $userId . '_' . time() . '.' . $ext;
            $destination = $uploadDir . $filename;
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $profilePicture = '/uploads/profiles/' . $filename;
                // Delete old profile picture if it exists and is not default
                if ($data['current_profile_picture'] && $data['current_profile_picture'] !== '/images/default-profile.png' && file_exists(__DIR__ . '/../../public' . $data['current_profile_picture'])) {
                    unlink(__DIR__ . '/../../public' . $data['current_profile_picture']);
                }
            } else {
                throw new Exception("Failed to upload profile picture");
            }
        }

        // Update user data
        $query = "UPDATE users 
                  SET first_name = :first_name, middle_name = :middle_name, 
                      last_name = :last_name, suffix = :suffix, 
                      username = :username, email = :email, phone = :phone,
                      profile_picture = :profile_picture, updated_at = NOW()
                  WHERE user_id = :user_id AND role_id = 4";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':first_name' => $data['first_name'],
            ':middle_name' => $data['middle_name'] ?: null,
            ':last_name' => $data['last_name'],
            ':suffix' => $data['suffix'] ?: null,
            ':username' => $data['username'],
            ':email' => $data['email'],
            ':phone' => $data['phone'] ?: null,
            ':profile_picture' => $profilePicture,
            ':user_id' => $userId
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception("Failed to update profile or unauthorized");
        }

        // Update session
        $_SESSION['user'] = array_merge($_SESSION['user'], [
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'],
            'last_name' => $data['last_name'],
            'suffix' => $data['suffix'],
            'username' => $data['username'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'profile_picture' => $profilePicture
        ]);
    }

    public function changePassword($userId, $currentPassword, $newPassword)
    {
        // Verify current password
        $query = "SELECT password_hash FROM users WHERE user_id = :user_id AND role_id = 4";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':user_id' => $userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            throw new Exception("Current password is incorrect");
        }

        // Update password
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $query = "UPDATE users 
                  SET password_hash = :password_hash, updated_at = NOW()
                  WHERE user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':password_hash' => $newPasswordHash,
            ':user_id' => $userId
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception("Failed to update password");
        }
    }
}
