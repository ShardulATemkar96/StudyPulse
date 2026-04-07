<?php
require_once __DIR__ . "/../config.php";

$currentUser = getCurrentUser();
$conn = getDB();
$method = $_SERVER["REQUEST_METHOD"];

// POST /api/test/submit - Submit test and evaluate
if ($method === "POST") {
    $body = getJsonBody();
    $attemptId = (int)($body["attempt_id"] ?? 0);

    if (!$attemptId) {
        jsonResponse(["detail" => "attempt_id is required"], 400);
    }

    $userId = (int)$currentUser["user_id"];

    // Verify attempt belongs to user
    $stmt = $conn->prepare("
        SELECT ta.attempt_id, ta.test_id, ta.start_time, ta.status, t.duration_minutes, t.total_questions, t.title
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

    // Prevent re-submission
    if ($attempt["status"] !== "IN_PROGRESS") {
        jsonResponse(["detail" => "Test has already been submitted"], 403);
    }

    // Determine submission status based on time
    $startTime = strtotime($attempt["start_time"]);
    $endTime = $startTime + ((int)$attempt["duration_minutes"] * 60);
    $now = time();
    $status = ($now >= $endTime) ? "AUTO_SUBMITTED" : "SUBMITTED";

    // Evaluate all answers
    $score = 0;
    $correctCount = 0;
    $wrongCount = 0;
    $answeredCount = 0;

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
        $answeredCount++;
        $isCorrect = ($row["selected_option"] === $row["correct_option"]) ? 1 : 0;
        if ($isCorrect) {
            $score++;
            $correctCount++;
        } else {
            $wrongCount++;
        }

        // Update is_correct for each answer
        $updateStmt = $conn->prepare("UPDATE user_answers SET is_correct = ? WHERE answer_id = ?");
        $updateStmt->bind_param("ii", $isCorrect, $row["answer_id"]);
        $updateStmt->execute();
        $updateStmt->close();
    }
    $stmt->close();

    // Update attempt with score and status
    $nowStr = date("Y-m-d H:i:s");
    $stmt = $conn->prepare("UPDATE test_attempts SET status = ?, end_time = ?, score = ? WHERE attempt_id = ?");
    $stmt->bind_param("ssii", $status, $nowStr, $score, $attemptId);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    $totalQuestions = (int)$attempt["total_questions"];
    $percentage = $totalQuestions > 0 ? round(($score / $totalQuestions) * 100, 2) : 0;

    jsonResponse([
        "attempt_id" => $attemptId,
        "test_title" => $attempt["title"],
        "status" => $status,
        "total_questions" => $totalQuestions,
        "answered" => $answeredCount,
        "correct_answers" => $correctCount,
        "wrong_answers" => $wrongCount,
        "unanswered" => $totalQuestions - $answeredCount,
        "score" => $score,
        "percentage" => $percentage
    ]);
}

jsonResponse(["detail" => "Method not allowed"], 405);
?>
