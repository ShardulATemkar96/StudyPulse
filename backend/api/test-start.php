<?php
require_once __DIR__ . "/../config.php";

$currentUser = getCurrentUser();
$conn = getDB();
$method = $_SERVER["REQUEST_METHOD"];

// POST /api/test/start - Start a new test attempt
if ($method === "POST") {
    $body = getJsonBody();
    $testId = (int)($body["test_id"] ?? 0);

    if (!$testId) {
        jsonResponse(["detail" => "test_id is required"], 400);
    }

    $userId = (int)$currentUser["user_id"];

    // Verify test exists
    $stmt = $conn->prepare("SELECT test_id, duration_minutes, total_questions FROM tests WHERE test_id = ?");
    $stmt->bind_param("i", $testId);
    $stmt->execute();
    $test = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$test) {
        jsonResponse(["detail" => "Test not found"], 404);
    }

    // Check if there's already an in-progress attempt
    $stmt = $conn->prepare("SELECT attempt_id, start_time FROM test_attempts WHERE user_id = ? AND test_id = ? AND status = 'IN_PROGRESS' LIMIT 1");
    $stmt->bind_param("ii", $userId, $testId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        // Check if time has expired
        $startTime = strtotime($existing["start_time"]);
        $endTime = $startTime + ((int)$test["duration_minutes"] * 60);
        $now = time();

        if ($now >= $endTime) {
            // Auto-submit the expired attempt
            $expiredId = (int)$existing["attempt_id"];
            autoSubmitAttempt($conn, $expiredId);
        } else {
            // Return existing attempt
            $conn->close();
            jsonResponse([
                "attempt_id" => (int)$existing["attempt_id"],
                "duration_minutes" => (int)$test["duration_minutes"],
                "start_time" => $existing["start_time"],
                "resumed" => true
            ]);
        }
    }

    // Create new attempt
    $now = date("Y-m-d H:i:s");
    $stmt = $conn->prepare("INSERT INTO test_attempts (user_id, test_id, start_time, status) VALUES (?, ?, ?, 'IN_PROGRESS')");
    $stmt->bind_param("iis", $userId, $testId, $now);
    $stmt->execute();
    $attemptId = $conn->insert_id;
    $stmt->close();
    $conn->close();

    jsonResponse([
        "attempt_id" => $attemptId,
        "duration_minutes" => (int)$test["duration_minutes"],
        "start_time" => $now,
        "resumed" => false
    ], 201);
}

jsonResponse(["detail" => "Method not allowed"], 405);

// Helper: Auto-submit an expired attempt
function autoSubmitAttempt($conn, $attemptId) {
    // Get attempt details
    $stmt = $conn->prepare("SELECT ta.test_id FROM test_attempts ta WHERE ta.attempt_id = ? AND ta.status = 'IN_PROGRESS'");
    $stmt->bind_param("i", $attemptId);
    $stmt->execute();
    $attempt = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$attempt) return;

    $testId = (int)$attempt["test_id"];

    // Evaluate answers
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

    // Update attempt
    $now = date("Y-m-d H:i:s");
    $stmt = $conn->prepare("UPDATE test_attempts SET status = 'AUTO_SUBMITTED', end_time = ?, score = ? WHERE attempt_id = ?");
    $stmt->bind_param("sii", $now, $score, $attemptId);
    $stmt->execute();
    $stmt->close();
}
?>
