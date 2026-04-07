<?php
require_once __DIR__ . "/../config.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    jsonResponse(["detail" => "Method not allowed"], 405);
}

$currentUser = getCurrentUser();
$conn = getDB();

$stmt = $conn->prepare("SELECT id, full_name, email, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $currentUser["user_id"]);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$user) {
    jsonResponse(["detail" => "User not found"], 404);
}

jsonResponse($user);
?>
