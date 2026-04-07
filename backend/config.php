<?php
// Database Configuration
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "Shardul@96";
$DB_NAME = "studypulse";

// JWT Secret Key
$JWT_SECRET = "studypulse_secret_key_2024";
$JWT_EXPIRY = 86400; // 24 hours in seconds

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
function getDB() {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(["detail" => "Database connection failed: " . $conn->connect_error]);
        echo"db connected";
        exit();
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// JSON response helper
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit();
}

// Get JSON body from request
function getJsonBody() {
    $json = file_get_contents("php://input");
    return json_decode($json, true) ?: [];
}

// Simple JWT encode
function jwtEncode($payload) {
    global $JWT_SECRET;
    $header = base64url_encode(json_encode(["alg" => "HS256", "typ" => "JWT"]));
    $payload["exp"] = time() + $GLOBALS["JWT_EXPIRY"];
    $payloadEncoded = base64url_encode(json_encode($payload));
    $signature = base64url_encode(hash_hmac("sha256", "$header.$payloadEncoded", $JWT_SECRET, true));
    return "$header.$payloadEncoded.$signature";
}

// Simple JWT decode
function jwtDecode($token) {
    global $JWT_SECRET;
    $parts = explode(".", $token);
    if (count($parts) !== 3) return null;

    $header = $parts[0];
    $payload = $parts[1];
    $signature = $parts[2];

    $validSignature = base64url_encode(hash_hmac("sha256", "$header.$payload", $JWT_SECRET, true));
    if ($signature !== $validSignature) return null;

    $data = json_decode(base64url_decode($payload), true);
    if (!$data || (isset($data["exp"]) && $data["exp"] < time())) return null;

    return $data;
}

// Base64 URL-safe encode/decode
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
}

function base64url_decode($data) {
    return base64_decode(strtr($data, "-_", "+/"));
}

// Get current user from JWT token
function getCurrentUser() {
    $headers = getallheaders();
    $authHeader = $headers["Authorization"] ?? $headers["authorization"] ?? "";

    if (!$authHeader || !str_starts_with($authHeader, "Bearer ")) {
        jsonResponse(["detail" => "Not authenticated"], 401);
    }

    $token = substr($authHeader, 7);
    $payload = jwtDecode($token);

    if (!$payload) {
        jsonResponse(["detail" => "Invalid or expired token"], 401);
    }

    return $payload;
}
?>
