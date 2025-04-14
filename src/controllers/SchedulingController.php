<?php
require_once __DIR__ . '/../services/SchedulingService.php';

class SchedulingController {
    private $schedulingService;

    public function __construct() {
        $this->schedulingService = new SchedulingService();
    }

    public function detectConflicts() {
        // Only allow POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        // Get and validate input
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['schedule'], $data['department_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request data']);
            exit;
        }

        // Check authentication
        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        // Verify user has access to this department
        if ($_SESSION['user']['department_id'] != $data['department_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden - department mismatch']);
            exit;
        }

        // Detect conflicts
        $conflicts = $this->schedulingService->detectConflicts(
            $data['schedule'],
            $data['department_id']
        );

        header('Content-Type: application/json');
        echo json_encode($conflicts);
        exit;
    }
}