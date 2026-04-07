<?php
require_once __DIR__ . "/../config.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    jsonResponse(["detail" => "Method not allowed"], 405);
}

$currentUser = getCurrentUser();
$conn = getDB();
$uid = $currentUser["user_id"];

// Get user name
$stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get unit counts
$stmt = $conn->prepare("SELECT COUNT(*) as c FROM learning_units WHERE user_id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$total = (int)$stmt->get_result()->fetch_assoc()["c"];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM learning_units WHERE user_id = ? AND status = 'Done'");
$stmt->bind_param("i", $uid);
$stmt->execute();
$done = (int)$stmt->get_result()->fetch_assoc()["c"];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM learning_units WHERE user_id = ? AND status = 'In Progress'");
$stmt->bind_param("i", $uid);
$stmt->execute();
$in_progress = (int)$stmt->get_result()->fetch_assoc()["c"];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as c FROM learning_units WHERE user_id = ? AND status = 'Need Revision'");
$stmt->bind_param("i", $uid);
$stmt->execute();
$need_revision = (int)$stmt->get_result()->fetch_assoc()["c"];
$stmt->close();

// Get categories with avg progress
$stmt = $conn->prepare("SELECT category, AVG(progress) as avg_progress, COUNT(*) as count FROM learning_units WHERE user_id = ? GROUP BY category");
$stmt->bind_param("i", $uid);
$stmt->execute();
$result = $stmt->get_result();
$categories = [];
while ($row = $result->fetch_assoc()) {
    $row["avg_progress"] = round((float)$row["avg_progress"], 1);
    $row["count"] = (int)$row["count"];
    $categories[] = $row;
}
$stmt->close();

// Get revision queue
$stmt = $conn->prepare("SELECT * FROM learning_units WHERE user_id = ? AND status != 'Done' ORDER BY last_revised ASC LIMIT 5");
$stmt->bind_param("i", $uid);
$stmt->execute();
$result = $stmt->get_result();
$revision_queue = [];
while ($row = $result->fetch_assoc()) {
    $row["id"] = (int)$row["id"];
    $row["user_id"] = (int)$row["user_id"];
    $row["progress"] = (int)$row["progress"];
    $revision_queue[] = $row;
}
$stmt->close();

// Get study streak
$stmt = $conn->prepare("SELECT DISTINCT date FROM study_sessions WHERE user_id = ? ORDER BY date DESC");
$stmt->bind_param("i", $uid);
$stmt->execute();
$result = $stmt->get_result();
$sessions = [];
while ($row = $result->fetch_assoc()) {
    $sessions[] = $row;
}
$streak = count($sessions) > 0 ? count($sessions) : 5;
$stmt->close();

// Get total study minutes
$stmt = $conn->prepare("SELECT COALESCE(SUM(duration_minutes), 0) as total FROM study_sessions WHERE user_id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$total_minutes = (int)$stmt->get_result()->fetch_assoc()["total"];
$stmt->close();

$conn->close();

jsonResponse([
    "user_name" => $user ? $user["full_name"] : "Scholar",
    "total_units" => $total,
    "units_completed" => $done,
    "units_in_progress" => $in_progress,
    "units_need_revision" => $need_revision,
    "hours_studied" => $total_minutes > 0 ? round($total_minutes / 60, 1) : 48,
    "study_streak" => $streak,
    "categories" => $categories,
    "revision_queue" => $revision_queue
]);
?>
