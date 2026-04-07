<?php
require_once __DIR__ . "/../config.php";

$currentUser = getCurrentUser();
$conn = getDB();
$method = $_SERVER["REQUEST_METHOD"];

// POST /api/test/save-answer - Auto-save a single answer
if ($method === "POST") {
    $body = getJsonBody();
    $attemptId = (int)($body["attempt_id"] ?? 0);
    $questionId = (int)($body["question_id"] ?? 0);
    $selectedOption = strtoupper(trim($body["selected_option"] ?? ""));

    // Validate input
    if (!$attemptId || !$questionId || !$selectedOption) {
        jsonResponse(["detail" => "attempt_id, question_id, and selected_option are required"], 400);
    }

    if (!in_array($selectedOption, ["A", "B", "C", "D"])) {
        jsonResponse(["detail" => "selected_option must be A, B, C, or D"], 400);
    }

    $userId = (int)$currentUser["user_id"];

    // Verify attempt belongs to user and is in progress
    $stmt = $conn->prepare("SELECT ta.attempt_id, ta.start_time, t.duration_minutes FROM test_attempts ta JOIN tests t ON ta.test_id = t.test_id WHERE ta.attempt_id = ? AND ta.user_id = ? AND ta.status = 'IN_PROGRESS'");
    $stmt->bind_param("ii", $attemptId, $userId);
    $stmt->execute();
    $attempt = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$attempt) {
        jsonResponse(["detail" => "Invalid attempt or test already submitted"], 403);
    }

    // Check if time has expired (server-side timer validation)
    $startTime = strtotime($attempt["start_time"]);
    $endTime = $startTime + ((int)$attempt["duration_minutes"] * 60);
    $now = time();

    if ($now >= $endTime) {
        jsonResponse(["detail" => "Time has expired. Test will be auto-submitted."], 403);
    }

    // Verify question exists
    $stmt = $conn->prepare("SELECT question_id FROM questions WHERE question_id = ?");
    $stmt->bind_param("i", $questionId);
    $stmt->execute();
    $question = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$question) {
        jsonResponse(["detail" => "Question not found"], 404);
    }

    // Insert or update answer (upsert)
    $stmt = $conn->prepare("INSERT INTO user_answers (attempt_id, question_id, selected_option) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE selected_option = VALUES(selected_option)");
    $stmt->bind_param("iis", $attemptId, $questionId, $selectedOption);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    jsonResponse(["message" => "Answer saved successfully"]);
}

jsonResponse(["detail" => "Method not allowed"], 405);
?>
