<?php
require_once __DIR__ . "/../config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    jsonResponse(["detail" => "Method not allowed"], 405);
}

$body = getJsonBody();
$email = trim($body["email"] ?? "");
$password = $body["password"] ?? "";

if (!$email || !$password) {
    jsonResponse(["detail" => "Email and password are required"], 400);
}

$conn = getDB();

$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($password, $user["password_hash"])) {
    jsonResponse(["detail" => "Invalid email or password"], 401);
}

$token = jwtEncode(["user_id" => $user["id"], "email" => $user["email"]]);

$conn->close();

jsonResponse([
    "token" => $token,
    "user" => [
        "id" => $user["id"],
        "full_name" => $user["full_name"],
        "email" => $user["email"]
    ]
]);
?>
