<?php
/**
 * Database Helper
 * PDO Database Connection and Utilities
 */

require_once __DIR__ . '/../config.php';

/**
 * Get PDO Database Connection
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (ENVIRONMENT === 'development') {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'error' => 'Database connection failed',
                    'message' => $e->getMessage(),
                ], JSON_PRETTY_PRINT);
            } else {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Database connection failed. Please try again later.']);
            }
            exit;
        }
    }

    return $pdo;
}

/**
 * Generate UUID v4
 */
function generateUUID(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Generate random token
 */
function generateToken(int $length = 32): string
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Hash password using bcrypt
 */
function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT, [
        'cost' => 12,
    ]);
}

/**
 * Verify password
 */
function verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * Sanitize string input
 */
function sanitize(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Slugify string
 */
function slugify(string $text): string
{
    $text = preg_replace('/[^a-zA-Z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', trim($text));
    $text = preg_replace('/-+/', '-', $text);
    return strtolower(substr($text, 0, 100));
}

/**
 * Format datetime to relative time
 */
function timeAgo(string $datetime): string
{
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return $diff . 's ago';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . 'm ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . 'h ago';
    }
    if ($diff < 604800) {
        return floor($diff / 86400) . 'd ago';
    }
    if ($diff < 2592000) {
        return floor($diff / 604800) . 'w ago';
    }
    if ($diff < 31536000) {
        return floor($diff / 2592000) . 'mo ago';
    }

    return floor($diff / 31536000) . 'y ago';
}

/**
 * Format date for display
 */
function formatDate(string $datetime, string $format = 'd M Y'): string
{
    return date($format, strtotime($datetime));
}

/**
 * Validate email
 */
function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate username
 */
function isValidUsername(string $username): array
{
    $errors = [];

    $minLength = getSetting('min_username_length', 3);
    $maxLength = getSetting('max_username_length', 50);

    if (strlen($username) < $minLength) {
        $errors[] = "Username must be at least {$minLength} characters";
    }

    if (strlen($username) > $maxLength) {
        $errors[] = "Username must not exceed {$maxLength} characters";
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username can only contain letters, numbers, and underscores";
    }

    return $errors;
}

/**
 * Validate password
 */
function isValidPassword(string $password): array
{
    $errors = [];

    $minLength = getSetting('min_password_length', 6);
    $maxLength = getSetting('max_password_length', 128);

    if (strlen($password) < $minLength) {
        $errors[] = "Password must be at least {$minLength} characters";
    }

    if (strlen($password) > $maxLength) {
        $errors[] = "Password must not exceed {$maxLength} characters";
    }

    return $errors;
}

/**
 * Check if user is rate limited
 */
function isRateLimited(string $identifier): bool
{
    $cacheFile = sys_get_temp_dir() . "/lspd_rate_{$identifier}.json";
    $data = [];

    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true) ?? [];
    }

    $window = getSetting('rate_limit_window', RATE_LIMIT_WINDOW);
    $maxAttempts = getSetting('rate_limit_max', RATE_LIMIT_MAX);
    $now = time();

    // Filter old entries
    $data = array_filter($data, fn($t) => ($now - $t) < $window);

    if (count($data) >= $maxAttempts) {
        return true;
    }

    $data[] = $now;
    file_put_contents($cacheFile, json_encode($data));

    return false;
}

/**
 * Get client IP address
 */
function getClientIP(): string
{
    $ip = '';

    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }

    return trim($ip);
}

/**
 * Get user agent
 */
function getUserAgent(): string
{
    return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
}

/**
 * JSON Response
 */
function jsonResponse(mixed $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Error Response
 */
function errorResponse(string $message, int $code = 400, array $details = []): void
{
    $response = ['error' => $message];

    if (!empty($details)) {
        $response['details'] = $details;
    }

    jsonResponse($response, $code);
}

/**
 * Success Response
 */
function successResponse(string $message, array $data = [], int $code = 200): void
{
    $response = [
        'success' => true,
        'message' => $message,
    ];

    if (!empty($data)) {
        $response['data'] = $data;
    }

    jsonResponse($response, $code);
}

/**
 * Paginated Response
 */
function paginatedResponse(array $data, int $total, int $page, int $perPage): void
{
    $totalPages = ceil($total / $perPage);

    jsonResponse([
        'success' => true,
        'data' => $data,
        'pagination' => [
            'total' => (int) $total,
            'page' => (int) $page,
            'per_page' => (int) $perPage,
            'total_pages' => (int) $totalPages,
            'has_more' => $page < $totalPages,
        ],
    ]);
}

/**
 * Validate required fields
 */
function validateRequired(array $data, array $fields): array
{
    $errors = [];

    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
            $errors[] = "The {$field} field is required";
        }
    }

    return $errors;
}

/**
 * Get pagination parameters
 */
function getPaginationParams(): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? getSetting('topics_per_page', 20))));
    $offset = ($page - 1) * $perPage;

    return [
        'page' => $page,
        'per_page' => $perPage,
        'offset' => $offset,
    ];
}

/**
 * Upload file
 */
function uploadFile(array $file, string $directory, array $allowedTypes = []): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by extension',
        ];

        return [
            'success' => false,
            'error' => $errors[$file['error']] ?? 'Unknown upload error',
        ];
    }

    // Check file size
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return [
            'success' => false,
            'error' => 'File size exceeds maximum allowed (' . (UPLOAD_MAX_SIZE / 1024 / 1024) . 'MB)',
        ];
    }

    // Check file type
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!empty($allowedTypes) && !in_array($extension, $allowedTypes)) {
        return [
            'success' => false,
            'error' => 'File type not allowed. Allowed types: ' . implode(', ', $allowedTypes),
        ];
    }

    // Generate unique filename
    $filename = generateToken(16) . '.' . $extension;
    $filepath = rtrim($directory, '/') . '/' . $filename;

    // Create directory if not exists
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    // Move file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'filename' => $filename,
            'path' => $filepath,
            'url' => str_replace(__DIR__ . '/../../', ASSETS_URL . '/', $filepath),
        ];
    }

    return [
        'success' => false,
        'error' => 'Failed to save uploaded file',
    ];
}