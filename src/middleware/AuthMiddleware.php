<?php
class AuthMiddleware
{
    private static $roleMap = [
        'admin' => 1,
        'vpaa' => 2,
        'di' => 3,
        'dean' => 4,
        'chair' => 5,
        'faculty' => 6
    ];

    public static function handle($requiredRole = null)
    {
        if (!isset($_SESSION['user'])) {
            error_log("AuthMiddleware: No user session, redirecting to /login");
            header('Location: /login');
            exit;
        }

        if ($requiredRole) {
            $requiredRoleId = is_numeric($requiredRole) ?
                $requiredRole : (self::$roleMap[strtolower($requiredRole)] ?? null);

            if (!isset($_SESSION['user']['role_id']) || $_SESSION['user']['role_id'] !== $requiredRoleId) {
                error_log("AuthMiddleware: Role mismatch, expected $requiredRoleId, got " . ($_SESSION['user']['role_id'] ?? 'none'));
                header('Location: /unauthorized');
                exit;
            }
        }
        error_log("AuthMiddleware: Access granted for role " . ($_SESSION['user']['role_id'] ?? 'unknown'));
    }
}
