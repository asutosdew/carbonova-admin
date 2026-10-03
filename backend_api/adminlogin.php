<?php
/**
 * Carbonova World - Dedicated Admin Login API
 * Endpoint: /api/adminlogin.php
 * Authenticates administrators and staff against `adminusers` table
 * and issues unique dynamic session bearer tokens for every login.
 */

include_once "config.php";

// Handle CORS & Preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("HTTP/1.1 200 OK");
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    exit;
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Cryptographically secure token generator (UUID v4 format)
function generateDynamicAdminToken() {
    try {
        $bytes = random_bytes(16);
    } catch (\Exception $e) {
        if (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes(16);
        } else {
            $bytes = md5(uniqid(mt_rand(), true), true);
        }
    }
    // Set UUID v4 version (0100) and variant (10)
    $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
    $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

// Parse JSON body or standard form POST parameters
$raw_input = file_get_contents('php://input');
$json_data = json_decode($raw_input, true);

$login = '';
$password = '';

if (is_array($json_data)) {
    $login = $json_data['login'] ?? $json_data['username'] ?? $json_data['userid'] ?? $json_data['email'] ?? '';
    $password = $json_data['password'] ?? '';
}

if (empty($login) && !empty($_POST)) {
    $login = $_POST['login'] ?? $_POST['username'] ?? $_POST['userid'] ?? $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
}

$login = trim($login);
$password = trim($password);

$response = new \stdClass();

if (empty($login) || empty($password)) {
    $response->result = 0;
    $response->status = 0;
    $response->message = "Username / Email and Password are required.";
    echo json_encode($response);
    exit;
}

try {
    // 1. Fetch user from `adminusers` table by username or email
    $user = PDO_FetchRow("SELECT id, name, username, email, password, role, status FROM adminusers WHERE (username = ? OR email = ?)", [$login, $login]);

    if (!$user) {
        $response->result = 0;
        $response->status = 0;
        $response->message = "Invalid Administrator credentials.";
        echo json_encode($response);
        exit;
    }

    // 2. Check if user status is Active
    if ($user['status'] !== 'Active') {
        $response->result = 0;
        $response->status = 0;
        $response->message = "Your admin account is currently inactive. Please contact Super Admin.";
        echo json_encode($response);
        exit;
    }

    // 3. Password Verification (supports plaintext, bcrypt, or md5)
    $password_matched = false;
    if ($password === $user['password']) {
        $password_matched = true;
    } elseif (function_exists('password_verify') && password_verify($password, $user['password'])) {
        $password_matched = true;
    } elseif (md5($password) === $user['password']) {
        $password_matched = true;
    }

    if (!$password_matched) {
        $response->result = 0;
        $response->status = 0;
        $response->message = "Invalid Administrator password.";
        echo json_encode($response);
        exit;
    }

    // 4. Issue a fresh, unique, cryptographically secure dynamic session token
    $token = generateDynamicAdminToken();
    $expireson = date('Y-m-d H:i:s', strtotime('+24 hours')); // 24-hour expiry

    // Save token in `tokens` table
    try {
        PDO_Execute("INSERT INTO tokens (token, username, expireson) VALUES (?, ?, ?)", 
            [$token, $user['username'], $expireson]);
    } catch (\Exception $e) {
        // If table doesn't exist or columns vary, auto-create table & retry
        try {
            PDO_Execute("CREATE TABLE IF NOT EXISTS `tokens` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `token` VARCHAR(100) NOT NULL UNIQUE,
                `username` VARCHAR(50) NOT NULL,
                `expireson` DATETIME DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (`token`),
                INDEX (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            PDO_Execute("INSERT INTO tokens (token, username, expireson) VALUES (?, ?, ?)", 
                [$token, $user['username'], $expireson]);
        } catch (\Exception $e2) {
            error_log("Failed to insert into tokens table: " . $e2->getMessage());
        }
    }

    // Also update `adminusers` table with current token and expiry
    try {
        PDO_Execute("UPDATE adminusers SET token = ?, token_expires = ? WHERE id = ?", 
            [$token, $expireson, $user['id']]);
    } catch (\Exception $e3) {
        // Ignored if column not added yet
    }

    $response->result = 1;
    $response->status = 1;
    $response->message = "Login successful";
    $response->token = $token;
    $response->admin_token = $token;
    $response->expireson = $expireson;
    $response->user = [
        "id" => (int)$user['id'],
        "name" => $user['name'],
        "username" => $user['username'],
        "email" => $user['email'],
        "role" => $user['role'],
        "status" => $user['status']
    ];

    echo json_encode($response);
    exit;

} catch (\Exception $e) {
    $response->result = 0;
    $response->status = 0;
    $response->message = "Server error occurred: " . $e->getMessage();
    echo json_encode($response);
    exit;
}
