<?php
require __DIR__ . '/../vendor/autoload.php';

// Load environment
Dotenv\Dotenv::createImmutable(__DIR__ . '/../')->load();

// Initialize secure session
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

// Define route handler functions first
require_once __DIR__ . '/../src/middleware/AuthMiddleware.php';

function handleChairRoutes($path)
{
    switch ($path) {
        case '/chair/dashboard':
            require __DIR__ . '/../src/controllers/ChairController.php';
            (new ChairController())->dashboard();
            exit;
        case '/chair/view_schedule':
            require __DIR__ . '/../src/controllers/ChairController.php';
            (new ChairController())->schedule();
            exit;
        case '/chair/generate_schedule':
            require __DIR__ . '/../src/controllers/ChairController.php';
            (new ChairController())->generateSchedule();
            exit;
        case '/chair/classroom':
            require __DIR__ . '/../src/controllers/ChairController.php';
            (new ChairController())->classrooms();
            exit;
        case '/chair/sections':
            require __DIR__ . '/../src/controllers/ChairController.php';
            (new ChairController())->sections();
            exit;
        case '/chair/create_offerings':
            require __DIR__ . '/../src/controllers/ChairController.php';
            (new ChairController())->createOfferings();
            exit;
        case '/chair/faculty':
            require __DIR__ . '/../src/controllers/ChairController.php';
            (new ChairController())->faculty();
            exit;
        case '/chair/courses':
            require __DIR__ . '/../src/controllers/ChairController.php';
            (new ChairController())->courses();
            exit;
        case '/chair/approvals':
            require __DIR__ . '/../src/controllers/ChairController.php';
            (new ChairController())->approvals();
            exit;
        case '/chair/report':
            require __DIR__ . '/../src/controllers/ChairController.php';
            (new ChairController())->reports();
            exit;
        case '/chair/settings':
            require __DIR__ . '/../src/controllers/ChairController.php';
            (new ChairController())->settings();
            exit;
        case '/chair/curriculum':
            require __DIR__ . '/../src/controllers/ChairController.php';
            (new ChairController())->curriculum();
            exit;
        case '/chair/curriculum/versions':
            require __DIR__ . '/../src/controllers/ChairController.php';
            (new ChairController())->curriculumVersions();
            exit;
        case '/chair/curriculum/new':
            require __DIR__ . '/../src/controllers/ChairController.php';
            (new ChairController())->newCurriculum();
            exit;
            // Add this to your handleChairRoutes function in index.php
        case '/chair/profile':
            require __DIR__ . '/../src/controllers/ChairController.php';
            (new ChairController())->profile();
            exit;
            // Add these to your handleChairRoutes function in index.php
        case '/chair/update_profile':
            require __DIR__ . '/../src/controllers/ChairController.php';
            (new ChairController())->updateProfile();
            exit;
        case '/chair/change_password':
            require __DIR__ . '/../src/controllers/ChairController.php';
            (new ChairController())->changePassword();
            exit;
        case '/chair/logout':
            require __DIR__ . '/../src/controllers/AuthController.php';
            (new AuthController())->logout();
            exit;
        default:
            http_response_code(404);
            echo "404 Not Found";
            exit;
    }
}

function handleAdminRoutes($path)
{
    switch ($path) {
        case '/admin/dashboard':
            require __DIR__ . '/../src/controllers/AdminController.php';
            (new AdminController())->dashboard();
            exit;
        case '/admin/users':
            require __DIR__ . '/../src/controllers/AdminController.php';
            (new AdminController())->users();
            exit;
    }
}

function handleVpaaRoutes($path) {}
function handleDiRoutes($path) {}

function handleDeanRoutes($path)
{
    switch ($path) {
        case '/dean/dashboard':
            require_once __DIR__ . '/../src/controllers/DeanController.php';
            (new DeanController())->dashboard();
            break;
        case '/dean/schedules':
            require_once __DIR__ . '/../src/controllers/DeanController.php';
            (new DeanController())->schedules();
            break;
        case '/dean/faculty-requests':
            require_once __DIR__ . '/../src/controllers/DeanController.php';
            (new DeanController())->facultyRequests();
            break;
        case '/dean/accounts':
            require_once __DIR__ . '/../src/controllers/DeanController.php';
            (new DeanController())->accounts();
            break;
        case '/dean/profile':
            require_once __DIR__ . '/../src/controllers/DeanController.php';
            (new DeanController())->profile();
            break;
        case '/dean/settings':
            require_once __DIR__ . '/../src/controllers/DeanController.php';
            (new DeanController())->settings();
            break;
        case '/logout':
            require_once __DIR__ . '/../src/controllers/AuthController.php';
            (new AuthController())->logout();
            break;
        default:
            http_response_code(404);
            echo "404 Not Found";
    }
    exit;
}

function handleFacultyRoutes($path)
{
    switch ($path) {
        case '/faculty/dashboard':
            require __DIR__ . '/../src/controllers/FacultyController.php';
            (new FacultyController())->dashboard();
            exit;
        case '/faculty/schedule':
            require __DIR__ . '/../src/controllers/FacultyController.php';
            (new FacultyController())->schedule();
            exit;
        case '/faculty/requests':
            require __DIR__ . '/../src/controllers/FacultyController.php';
            (new FacultyController())->requests();
            exit;
        case '/faculty/profile':
            require __DIR__ . '/../src/controllers/FacultyController.php';
            (new FacultyController())->profile();
            exit;
        case '/faculty/logout':
            require __DIR__ . '/../src/controllers/AuthController.php';
            (new AuthController())->logout();
            exit;
    }
}

// Simple router
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Public routes that don't require authentication
$publicRoutes = ['/login', '/register', '/', '/home', '/public/search', '/api/departments'];
if (in_array($path, $publicRoutes) || $path === '/auth/register') {
    switch ($path) {
        case '/login':
            require __DIR__ . '/../src/controllers/AuthController.php';
            $controller = new AuthController();
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $controller->showLogin();
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->login($_POST['username'] ?? '', $_POST['password'] ?? '');
            }
            exit;

        case '/register':
            require __DIR__ . '/../src/controllers/AuthController.php';
            $controller = new AuthController();
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $controller->showRegister();
            }
            exit;

        case '/auth/register':
            require __DIR__ . '/../src/controllers/AuthController.php';
            $controller = new AuthController();
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $controller->showRegister();
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->handleRegistration();
            }
            exit;

        case '/api/departments':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require __DIR__ . '/../src/controllers/AuthController.php';
                (new AuthController())->getDepartments();
            }
            exit;

        case '/home':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                require __DIR__ . '/../src/controllers/PublicController.php';
                (new PublicController())->showHomepage();
            }
            exit;

        case '/public/search':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                require __DIR__ . '/../src/controllers/PublicController.php';
                (new PublicController())->searchSchedules();
            }
            exit;

        case '/':
            header('Location: /home');
            exit;

        default:
            http_response_code(404);
            echo 'Page not found';
            exit;
    }
}

// Protected routes - require authentication
if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit;
}

// Get user role from session
$roleId = $_SESSION['user']['role_id'] ?? null;

// Handle role-specific routes
switch ($roleId) {
    case 1: // Admin
        handleAdminRoutes($path);
        break;
    case 2: // VPAA
        handleVpaaRoutes($path);
        break;
    case 3: // DI
        handleDiRoutes($path);
        break;
    case 4: // Dean
        handleDeanRoutes($path);
        break;
    case 5: // Chair
        handleChairRoutes($path);
        break;
    case 6: // Faculty
        handleFacultyRoutes($path);
        break;
    default:
        AuthMiddleware::handle(null);
        http_response_code(403);
        echo 'Unauthorized role';
        exit;
}

// If no route matched, show 404
http_response_code(404);
echo 'Page not found';
exit;
