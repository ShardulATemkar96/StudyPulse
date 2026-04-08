<?php
require_once __DIR__ . "/../config.php";

$currentUser = getCurrentUser();
$conn = getDB();
$method = $_SERVER["REQUEST_METHOD"];

// GET /api/test/resume/{attempt_id} - Resume an in-progress attempt (get saved answers)
if ($method === "GET") {
    $attemptId = (int)($_GET["attempt_id"] ?? 0);

    if (!$attemptId) {
        jsonResponse(["detail" => "attempt_id is required"], 400);
    }

    $userId = (int)$currentUser["user_id"];

    // Verify attempt belongs to user
    $stmt = $conn->prepare("
        SELECT ta.attempt_id, ta.test_id, ta.start_time, ta.status, t.duration_minutes
        FROM test_attempts ta
        JOIN tests t ON ta.test_id = t.test_id
        WHERE ta.attempt_id = ? AND ta.user_id = ?
    ");
    $stmt->bind_param("ii", $attemptId, $userId);
    $stmt->execute();
    $attempt = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$attempt) {
        jsonResponse(["detail" => "Attempt not found"], 404);
    }

    // Check if time has expired
    if ($attempt["status"] === "IN_PROGRESS") {
        $startTime = strtotime($attempt["start_time"]);
        $endTime = $startTime + ((int)$attempt["duration_minutes"] * 60);
        $now = time();

        if ($now >= $endTime) {
            // Auto-submit expired attempt
            autoSubmitAttempt($conn, $attemptId);
            jsonResponse([
                "detail" => "Time expired. Test has been auto-submitted.",
                "auto_submitted" => true,
                "attempt_id" => $attemptId
            ]);
        }
    }

    // If already submitted, tell frontend
    if ($attempt["status"] !== "IN_PROGRESS") {
        jsonResponse([
            "detail" => "Test already submitted",
            "status" => $attempt["status"],
            "attempt_id" => $attemptId
        ]);
    }

    // Fetch saved answers
    $stmt = $conn->prepare("SELECT question_id, selected_option FROM user_answers WHERE attempt_id = ?");
    $stmt->bind_param("i", $attemptId);
    $stmt->execute();
    $result = $stmt->get_result();

    $savedAnswers = [];
    while ($row = $result->fetch_assoc()) {
        $savedAnswers[(int)$row["question_id"]] = $row["selected_option"];
    }
    $stmt->close();
    $conn->close();

    jsonResponse([
        "attempt_id" => (int)$attempt["attempt_id"],
        "test_id" => (int)$attempt["test_id"],
        "start_time" => $attempt["start_time"],
        "duration_minutes" => (int)$attempt["duration_minutes"],
        "status" => $attempt["status"],
        "saved_answers" => $savedAnswers
    ]);
}

jsonResponse(["detail" => "Method not allowed"], 405);

// Helper: Auto-submit an expired attempt
function autoSubmitAttempt($conn, $attemptId) {
    $score = 0;
    $stmt = $conn->prepare("
        SELECT ua.answer_id, ua.selected_option, q.correct_option
        FROM user_answers ua
        JOIN questions q ON ua.question_id = q.question_id
        WHERE ua.attempt_id = ?
    ");
    $stmt->bind_param("i", $attemptId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $isCorrect = ($row["selected_option"] === $row["correct_option"]) ? 1 : 0;
        if ($isCorrect) $score++;

        $updateStmt = $conn->prepare("UPDATE user_answers SET is_correct = ? WHERE answer_id = ?");
        $updateStmt->bind_param("ii", $isCorrect, $row["answer_id"]);
        $updateStmt->execute();
        $updateStmt->close();
    }
    $stmt->close();

    $now = date("Y-m-d H:i:s");
    $stmt = $conn->prepare("UPDATE test_attempts SET status = 'AUTO_SUBMITTED', end_time = ?, score = ? WHERE attempt_id = ?");
    $stmt->bind_param("sii", $now, $score, $attemptId);
    $stmt->execute();
    $stmt->close();
}
?>
