<?php
/**
 * Carbonova World - Dedicated Admin Login API
 * Endpoint: /api/adminlogin.php
 * Authenticates administrators and staff against `adminusers` table.
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

    // 4. Issue admin session token
    // Uses standard active token recognized by admin.php
    $token = "1111-1111-1111-1111-1111";

    $response->result = 1;
    $response->status = 1;
    $response->message = "Login successful";
    $response->token = $token;
    $response->admin_token = $token;
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
