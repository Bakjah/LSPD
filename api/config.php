<?php
/**
 * API Configuration
 * Core configuration and settings loader
 */

// Prevent direct access
if (basename($_SERVER['REQUEST_URI']) === 'config.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

// Environment
define('ENVIRONMENT', 'development'); // development | production

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'lspd_portal');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Base URLs
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = dirname($_SERVER['SCRIPT_NAME']);
$basePath = str_replace('/api', '', $basePath);
$basePath = rtrim($basePath, '/');

define('BASE_URL', $scheme . '://' . $host . $basePath);
define('API_URL', BASE_URL . '/api');
define('ASSETS_URL', BASE_URL . '/assets');

// CORS Headers
define('ALLOWED_ORIGINS', [
    'http://localhost:3000',
    'http://localhost:5173',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:5173',
    'http://localhost',
    'http://127.0.0.1',
]);

/**
 * Apply CORS headers - Call this at the start of each PHP file
 * Sets headers only once to avoid duplicates
 */
function applyCORS(): void
{
    static $applied = false;
    if ($applied) return;
    $applied = true;

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    // Set specific origin (not wildcard) when using credentials
    if (in_array($origin, ALLOWED_ORIGINS, true)) {
        header("Access-Control-Allow-Origin: $origin", true);
    } else {
        // Allow any localhost variant for development
        if (strpos($origin, 'localhost') !== false || strpos($origin, '127.0.0.1') !== false) {
            header("Access-Control-Allow-Origin: $origin", true);
        }
    }

    // Only set credentials if we have a valid origin
    if ($origin) {
        header('Access-Control-Allow-Credentials: true', true);
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS', true);
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With', true);
    header('Access-Control-Max-Age: 86400', true);
    header('X-Content-Type-Options: nosniff', true);

    // Handle preflight
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// NOTE: Do NOT auto-apply CORS here - call applyCORS() at the start of each entry point file

// Session Configuration
define('SESSION_NAME', 'lspd_session');
define('SESSION_LIFETIME', 7200);

// Rate Limiting
define('RATE_LIMIT_WINDOW', 300); // 5 minutes
define('RATE_LIMIT_MAX', 60); // requests per window

// Upload Configuration
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'zip']);
define('UPLOAD_AVATARS_DIR', __DIR__ . '/../../assets/images/avatars');
define('UPLOAD_BANNERS_DIR', __DIR__ . '/../../assets/images/banners');

// Error Reporting
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Settings Cache
$GLOBALS['settings'] = null;

/**
 * Load settings from database
 */
function loadSettings(): void
{
    if ($GLOBALS['settings'] !== null) {
        return;
    }

    $GLOBALS['settings'] = [];

    try {
        $db = getDB();
        $result = $db->query("SELECT `key`, `value`, `type` FROM settings WHERE autoload = 1");

        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $GLOBALS['settings'][$row['key']] = match ($row['type']) {
                'int' => (int) $row['value'],
                'float' => (float) $row['value'],
                'bool' => $row['value'] === '1' || $row['value'] === 'true',
                default => $row['value'],
            };
        }
    } catch (Exception $e) {
        // Database not ready, use empty settings
        $GLOBALS['settings'] = [];
    }
}

/**
 * Get setting value
 */
function getSetting(string $key, mixed $default = null): mixed
{
    loadSettings();
    return $GLOBALS['settings'][$key] ?? $default;
}

/**
 * Update setting value
 */
function updateSetting(string $key, mixed $value): bool
{
    $db = getDB();
    $type = gettype($value);

    if ($type === 'boolean') {
        $value = $value ? '1' : '0';
        $type = 'bool';
    } elseif ($type === 'integer' || $type === 'double') {
        $type = is_int($value) ? 'int' : 'float';
        $value = (string) $value;
    } else {
        $type = 'string';
    }

    $stmt = $db->prepare("UPDATE settings SET `value` = ?, `type` = ?, updated_at = NOW() WHERE `key` = ?");
    $stmt->bind_param('sss', $value, $type, $key);

    if ($stmt->execute()) {
        // Clear cache
        $GLOBALS['settings'][$key] = $value;
        return true;
    }

    return false;
}