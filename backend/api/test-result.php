<?php
require_once __DIR__ . "/../config.php";

$currentUser = getCurrentUser();
$conn = getDB();
$method = $_SERVER["REQUEST_METHOD"];

// GET /api/test/result/{attempt_id} - Fetch test result
if ($method === "GET") {
    $attemptId = (int)($_GET["attempt_id"] ?? 0);

    if (!$attemptId) {
        jsonResponse(["detail" => "attempt_id is required"], 400);
    }

    $userId = (int)$currentUser["user_id"];

    // Fetch attempt with test details
    $stmt = $conn->prepare("
        SELECT ta.attempt_id, ta.test_id, ta.start_time, ta.end_time, ta.status, ta.score,
               t.title, t.total_questions, t.total_marks, t.duration_minutes
        FROM test_attempts ta
        JOIN tests t ON ta.test_id = t.test_id
        WHERE ta.attempt_id = ? AND ta.user_id = ?
    ");
    $stmt->bind_param("ii", $attemptId, $userId);
    $stmt->execute();
    $attempt = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$attempt) {
        jsonResponse(["detail" => "Result not found"], 404);
    }

    // Only show results for submitted tests
    if ($attempt["status"] === "IN_PROGRESS") {
        jsonResponse(["detail" => "Test is still in progress"], 403);
    }

    // Count correct and wrong answers
    $stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_count, SUM(CASE WHEN is_correct = 0 THEN 1 ELSE 0 END) as wrong_count FROM user_answers WHERE attempt_id = ?");
    $stmt->bind_param("i", $attemptId);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $totalQuestions = (int)$attempt["total_questions"];
    $correctAnswers = (int)($counts["correct_count"] ?? 0);
    $wrongAnswers = (int)($counts["wrong_count"] ?? 0);
    $answered = (int)($counts["total"] ?? 0);
    $unanswered = $totalQuestions - $answered;
    $score = (int)($attempt["score"] ?? 0);
    $percentage = $totalQuestions > 0 ? round(($score / $totalQuestions) * 100, 2) : 0;

    $conn->close();

    jsonResponse([
        "attempt_id" => (int)$attempt["attempt_id"],
        "test_id" => (int)$attempt["test_id"],
        "test_title" => $attempt["title"],
        "status" => $attempt["status"],
        "start_time" => $attempt["start_time"],
        "end_time" => $attempt["end_time"],
        "total_questions" => $totalQuestions,
        "answered" => $answered,
        "correct_answers" => $correctAnswers,
        "wrong_answers" => $wrongAnswers,
        "unanswered" => $unanswered,
        "score" => $score,
        "total_marks" => (int)$attempt["total_marks"],
        "percentage" => $percentage,
        "duration_minutes" => (int)$attempt["duration_minutes"]
    ]);
}

jsonResponse(["detail" => "Method not allowed"], 405);
?>
