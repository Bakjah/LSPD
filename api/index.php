<?php
/**
 * API Entry Point
 * Main router and request handler
 */

// Define base path
define('API_ROOT', __DIR__);

// Load configuration first
require_once __DIR__ . '/config.php';

// CORS Headers - Apply immediately for all responses
// Must set specific origin when using credentials (withCredentials: true in Axios)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ALLOWED_ORIGINS;

// Check if origin is allowed
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin", true);
} else {
    // Allow any localhost variant for development
    if (strpos($origin, 'localhost') !== false || strpos($origin, '127.0.0.1') !== false) {
        header("Access-Control-Allow-Origin: $origin", true);
    }
}

// Only set credentials header if we have a valid origin
if ($origin) {
    header('Access-Control-Allow-Credentials: true', true);
}

header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS', true);
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With', true);
header('Access-Control-Max-Age: 86400', true);
header('X-Content-Type-Options: nosniff', true);

// Handle preflight requests immediately
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Load helpers
require_once __DIR__ . '/helpers/database.php';
require_once __DIR__ . '/helpers/jwt.php';
require_once __DIR__ . '/helpers/logging.php';

// Load middleware
require_once __DIR__ . '/middleware/auth.php';

// Parse request - Get the path relative to /api/
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

// Extract the /api/ part from the request
$apiPath = '/api';

// Find where /api starts in the URI
$apiPos = strpos($requestUri, $apiPath);

// Get the path after /api/
if ($apiPos !== false) {
    $pathAfterApi = substr($requestUri, $apiPos + strlen($apiPath));
} else {
    $pathAfterApi = '/';
}

// Remove query string and leading/trailing slashes
$pathAfterApi = parse_url($pathAfterApi, PHP_URL_PATH);
$pathAfterApi = trim($pathAfterApi, '/');
$pathParts = $pathAfterApi ? explode('/', $pathAfterApi) : [];

// Get route module and action
$module = $pathParts[0] ?? '';
$action = $pathParts[1] ?? 'index';
$id = $pathParts[2] ?? null;

// Handle empty module (just /api/)
if (empty($module)) {
    // Return API info
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'api' => 'Los Santos Roleplay Community API',
        'version' => '1.0',
        'endpoints' => [
            'auth' => '/api/auth',
            'departments' => '/api/departments',
            'forums' => '/api/forums',
            'topics' => '/api/topics',
            'posts' => '/api/posts',
            'portal' => '/api/portal',
            'members' => '/api/members',
            'profile' => '/api/profile',
            'notifications' => '/api/notifications',
            'messages' => '/api/messages',
        ],
    ], JSON_PRETTY_PRINT);
    exit;
}

// Load route module
$routeFile = __DIR__ . '/modules/' . $module . '/index.php';

if (!file_exists($routeFile)) {
    // Check if there's a standalone PHP file for this module
    $standaloneFile = __DIR__ . '/' . $module . '.php';
    if (file_exists($standaloneFile)) {
        require_once $standaloneFile;
        exit;
    }

    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Endpoint not found',
        'path' => $module,
        'available_endpoints' => ['auth', 'departments', 'forums', 'topics', 'posts', 'portal', 'members', 'profile', 'notifications', 'messages'],
    ]);
    exit;
}

// Change to API directory so relative paths in modules work correctly
chdir(__DIR__);

require_once $routeFile;

// Execute route handler
if (function_exists('handleRoute')) {
    handleRoute($_SERVER['REQUEST_METHOD'], $action, $id);
} else {
    errorResponse('Invalid route module', 500);
}
