<?php
/**
 * Carbonova World - Dedicated Admin Login API with Google Authenticator (TOTP) 2FA
 * Endpoint: /api/adminlogin.php
 * Authenticates administrators and staff against `adminusers` table
 * and issues unique dynamic session bearer tokens for every login.
 */

include_once "config.php";
require_once "totp_helper.php";

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

// Ensure 2FA temporary challenges table exists
function ensure2faTempTable() {
    try {
        PDO_Execute("CREATE TABLE IF NOT EXISTS `admin_2fa_temp` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `temp_token` VARCHAR(100) NOT NULL UNIQUE,
            `user_id` INT NOT NULL,
            `secret` VARCHAR(64) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (`temp_token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Exception $e) {}
}

// Parse JSON body or standard form POST parameters
$raw_input = file_get_contents('php://input');
$json_data = json_decode($raw_input, true);

$action = '';
$temp_token = '';
$totp_code = '';

if (is_array($json_data)) {
    $action = $json_data['action'] ?? '';
    $temp_token = $json_data['temp_token'] ?? '';
    $totp_code = $json_data['code'] ?? $json_data['totp_code'] ?? $json_data['otp'] ?? '';
}

if (empty($temp_token) && !empty($_POST)) {
    $action = $_POST['action'] ?? '';
    $temp_token = $_POST['temp_token'] ?? '';
    $totp_code = $_POST['code'] ?? $_POST['totp_code'] ?? $_POST['otp'] ?? '';
}

$response = new \stdClass();

// ==============================================================================
// STEP 2: VERIFY TWO-FACTOR AUTHENTICATION CODE (TOTP)
// ==============================================================================
if ($action === 'verify_2fa' || (!empty($temp_token) && !empty($totp_code))) {
    $temp_token = trim($temp_token);
    $totp_code = trim($totp_code);

    if (empty($temp_token) || empty($totp_code)) {
        $response->result = 0;
        $response->status = 0;
        $response->message = "Temporary session token and 6-digit Authenticator code are required.";
        echo json_encode($response);
        exit;
    }

    try {
        ensure2faTempTable();

        // 1. Fetch challenge
        $challenge = PDO_FetchRow("SELECT * FROM admin_2fa_temp WHERE temp_token = ? AND expires_at >= NOW()", [$temp_token]);

        if (!$challenge) {
            $response->result = 0;
            $response->status = 0;
            $response->message = "Verification session expired or invalid. Please login again.";
            echo json_encode($response);
            exit;
        }

        // 2. Validate TOTP 6-digit code against secret
        $isValidCode = CarbonovaTOTP::verifyCode($challenge['secret'], $totp_code, 1);

        if (!$isValidCode) {
            $response->result = 0;
            $response->status = 0;
            $response->message = "Invalid 6-digit Authenticator code. Please check your Google Authenticator app and try again.";
            echo json_encode($response);
            exit;
        }

        // 3. Delete used temporary challenge
        PDO_Execute("DELETE FROM admin_2fa_temp WHERE temp_token = ?", [$temp_token]);

        // 4. Fetch User Details
        $user = PDO_FetchRow("SELECT id, name, username, email, role, status FROM adminusers WHERE id = ?", [$challenge['user_id']]);

        if (!$user || $user['status'] !== 'Active') {
            $response->result = 0;
            $response->status = 0;
            $response->message = "Admin user account is invalid or inactive.";
            echo json_encode($response);
            exit;
        }

        // 5. Issue final bearer token
        $token = generateDynamicAdminToken();
        $expireson = date('Y-m-d H:i:s', strtotime('+24 hours'));

        try {
            PDO_Execute("INSERT INTO tokens (token, username, expireson) VALUES (?, ?, ?)", 
                [$token, $user['username'], $expireson]);
        } catch (\Exception $e) {
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
            } catch (\Exception $e2) {}
        }

        try {
            PDO_Execute("UPDATE adminusers SET token = ?, token_expires = ? WHERE id = ?", 
                [$token, $expireson, $user['id']]);
        } catch (\Exception $e3) {}

        $response->result = 1;
        $response->status = 1;
        $response->message = "Two-factor verification successful";
        $response->token = $token;
        $response->admin_token = $token;
        $response->expireson = $expireson;
        $response->user = [
            "id" => (int)$user['id'],
            "name" => $user['name'],
            "username" => $user['username'],
            "email" => $user['email'],
            "role" => $user['role'],
            "status" => $user['status'],
            "two_factor_verified" => true
        ];

        echo json_encode($response);
        exit;

    } catch (\Exception $e) {
        $response->result = 0;
        $response->status = 0;
        $response->message = "Server error during 2FA verification: " . $e->getMessage();
        echo json_encode($response);
        exit;
    }
}

// ==============================================================================
// STEP 1: INITIAL CREDENTIALS LOGIN (USERNAME & PASSWORD)
// ==============================================================================

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

if (empty($login) || empty($password)) {
    $response->result = 0;
    $response->status = 0;
    $response->message = "Username / Email and Password are required.";
    echo json_encode($response);
    exit;
}

try {
    // 1. Fetch user from `adminusers` table (including 2FA columns)
    $user = PDO_FetchRow("SELECT id, name, username, email, password, role, status, 
                                 IFNULL(two_factor_enabled, 0) as two_factor_enabled, 
                                 IFNULL(two_factor_confirmed, 0) as two_factor_confirmed, 
                                 two_factor_secret 
                          FROM adminusers 
                          WHERE (username = ? OR email = ?)", [$login, $login]);

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

    // 4. CHECK IF TWO-FACTOR AUTHENTICATION IS ENABLED
    $has2FA = ($user['two_factor_enabled'] == 1 && $user['two_factor_confirmed'] == 1 && !empty($user['two_factor_secret']));

    if ($has2FA) {
        ensure2faTempTable();

        // Create 5-minute temporary challenge token
        $temp_token = '2fa_' . bin2hex(random_bytes(16));
        $temp_expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        // Clean up older challenges for this user
        PDO_Execute("DELETE FROM admin_2fa_temp WHERE user_id = ? OR expires_at < NOW()", [$user['id']]);

        // Insert new challenge
        PDO_Execute("INSERT INTO admin_2fa_temp (temp_token, user_id, secret, expires_at) VALUES (?, ?, ?, ?)", 
            [$temp_token, $user['id'], $user['two_factor_secret'], $temp_expiry]);

        $response->result = 1;
        $response->status = 1;
        $response->requires_2fa = true;
        $response->two_factor_type = "TOTP";
        $response->temp_token = $temp_token;
        $response->message = "Please enter the 6-digit code from Google Authenticator.";
        $response->user = [
            "id" => (int)$user['id'],
            "name" => $user['name'],
            "username" => $user['username'],
            "role" => $user['role']
        ];

        echo json_encode($response);
        exit;
    }

    // 5. If 2FA not enabled: Issue regular dynamic session token
    $token = generateDynamicAdminToken();
    $expireson = date('Y-m-d H:i:s', strtotime('+24 hours'));

    try {
        PDO_Execute("INSERT INTO tokens (token, username, expireson) VALUES (?, ?, ?)", 
            [$token, $user['username'], $expireson]);
    } catch (\Exception $e) {
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

    try {
        PDO_Execute("UPDATE adminusers SET token = ?, token_expires = ? WHERE id = ?", 
            [$token, $expireson, $user['id']]);
    } catch (\Exception $e3) {}

    $response->result = 1;
    $response->status = 1;
    $response->requires_2fa = false;
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
