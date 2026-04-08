<?php
require_once __DIR__ . "/../config.php";

$currentUser = getCurrentUser();
$conn = getDB();
$method = $_SERVER["REQUEST_METHOD"];

// GET /api/test/list - List all available tests
if ($method === "GET") {
    $stmt = $conn->prepare("SELECT test_id, title, total_questions, total_marks, duration_minutes, created_at FROM tests ORDER BY created_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();

    $tests = [];
    while ($row = $result->fetch_assoc()) {
        $row["test_id"] = (int)$row["test_id"];
        $row["total_questions"] = (int)$row["total_questions"];
        $row["total_marks"] = (int)$row["total_marks"];
        $row["duration_minutes"] = (int)$row["duration_minutes"];

        // Check if user has an in-progress attempt
        $uid = $currentUser["user_id"];
        $tid = $row["test_id"];
        $attemptStmt = $conn->prepare("SELECT attempt_id, start_time, status FROM test_attempts WHERE user_id = ? AND test_id = ? ORDER BY start_time DESC LIMIT 1");
        $attemptStmt->bind_param("ii", $uid, $tid);
        $attemptStmt->execute();
        $attemptResult = $attemptStmt->get_result();
        $attempt = $attemptResult->fetch_assoc();
        $attemptStmt->close();

        if ($attempt) {
            $row["last_attempt"] = [
                "attempt_id" => (int)$attempt["attempt_id"],
                "status" => $attempt["status"],
                "start_time" => $attempt["start_time"]
            ];
        } else {
            $row["last_attempt"] = null;
        }

        $tests[] = $row;
    }

    $stmt->close();
    $conn->close();
    jsonResponse($tests);
}

jsonResponse(["detail" => "Method not allowed"], 405);
?>
