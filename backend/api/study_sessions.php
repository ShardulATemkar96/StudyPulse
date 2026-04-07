<?php
require_once __DIR__ . "/../config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    jsonResponse(["detail" => "Method not allowed"], 405);
}

$currentUser = getCurrentUser();
$conn = getDB();

$body = getJsonBody();
$unit_id = isset($body["unit_id"]) ? (int)$body["unit_id"] : null;
$duration = (int)($body["duration_minutes"] ?? 25);

$uid = $currentUser["user_id"];

if ($unit_id) {
    $stmt = $conn->prepare("INSERT INTO study_sessions (user_id, unit_id, duration_minutes) VALUES (?, ?, ?)");
    $stmt->bind_param("iii", $uid, $unit_id, $duration);
} else {
    $stmt = $conn->prepare("INSERT INTO study_sessions (user_id, duration_minutes) VALUES (?, ?)");
    $stmt->bind_param("ii", $uid, $duration);
}

$stmt->execute();
$new_id = $conn->insert_id;
$stmt->close();
$conn->close();

jsonResponse(["message" => "Study session logged", "id" => $new_id], 201);
?>
