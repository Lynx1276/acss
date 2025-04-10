<?php
// api/DepartmentsController.php
require_once __DIR__ . '/../config/Database.php';

class DepartmentsController
{
    public function getDepartments()
    {
        try {
            $db = (new Database())->connect();

            if (!isset($_GET['college_id'])) {
                throw new Exception("College ID is required");
            }

            $collegeId = (int)$_GET['college_id'];
            $stmt = $db->prepare("SELECT * FROM departments WHERE college_id = ? ORDER BY department_name");
            $stmt->execute([$collegeId]);
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $departments
            ]);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}

?>