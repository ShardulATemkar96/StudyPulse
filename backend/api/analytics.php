<?php
require_once __DIR__ . "/../config.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    jsonResponse(["detail" => "Method not allowed"], 405);
}

$currentUser = getCurrentUser();
$conn = getDB();
$uid = $currentUser["user_id"];

// Mastery by category
$stmt = $conn->prepare("SELECT category, AVG(progress) as avg_progress, COUNT(*) as count FROM learning_units WHERE user_id = ? GROUP BY category");
$stmt->bind_param("i", $uid);
$stmt->execute();
$result = $stmt->get_result();
$mastery = [];
while ($row = $result->fetch_assoc()) {
    $row["avg_progress"] = round((float)$row["avg_progress"], 1);
    $row["count"] = (int)$row["count"];
    $mastery[] = $row;
}
$stmt->close();

// Weakest areas
$stmt = $conn->prepare("SELECT * FROM learning_units WHERE user_id = ? ORDER BY progress ASC LIMIT 3");
$stmt->bind_param("i", $uid);
$stmt->execute();
$result = $stmt->get_result();
$weakest = [];
while ($row = $result->fetch_assoc()) {
    $row["id"] = (int)$row["id"];
    $row["user_id"] = (int)$row["user_id"];
    $row["progress"] = (int)$row["progress"];
    $weakest[] = $row;
}
$stmt->close();

// All units
$stmt = $conn->prepare("SELECT * FROM learning_units WHERE user_id = ? ORDER BY category, title");
$stmt->bind_param("i", $uid);
$stmt->execute();
$result = $stmt->get_result();
$all_units = [];
while ($row = $result->fetch_assoc()) {
    $row["id"] = (int)$row["id"];
    $row["user_id"] = (int)$row["user_id"];
    $row["progress"] = (int)$row["progress"];
    $all_units[] = $row;
}
$stmt->close();

// Study sessions
$stmt = $conn->prepare("SELECT date, SUM(duration_minutes) as total_minutes FROM study_sessions WHERE user_id = ? GROUP BY date ORDER BY date DESC LIMIT 14");
$stmt->bind_param("i", $uid);
$stmt->execute();
$result = $stmt->get_result();
$sessions = [];
while ($row = $result->fetch_assoc()) {
    $row["total_minutes"] = (int)$row["total_minutes"];
    $sessions[] = $row;
}
$stmt->close();

$conn->close();

jsonResponse([
    "mastery" => $mastery,
    "weakest_areas" => $weakest,
    "all_units" => $all_units,
    "study_sessions" => $sessions
]);
?>
