<?php
require_once __DIR__ . "/../config.php";

$currentUser = getCurrentUser();
$conn = getDB();
$method = $_SERVER["REQUEST_METHOD"];

// GET /api/test/{test_id}/questions - Fetch questions WITHOUT correct_option
if ($method === "GET") {
    $testId = (int)($_GET["test_id"] ?? 0);

    if (!$testId) {
        jsonResponse(["detail" => "test_id is required"], 400);
    }

    // Verify test exists
    $stmt = $conn->prepare("SELECT test_id FROM tests WHERE test_id = ?");
    $stmt->bind_param("i", $testId);
    $stmt->execute();
    $test = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$test) {
        jsonResponse(["detail" => "Test not found"], 404);
    }

    // Fetch questions - IMPORTANT: DO NOT include correct_option
    $stmt = $conn->prepare("SELECT question_id, question_text, option_a, option_b, option_c, option_d, difficulty_level FROM questions WHERE test_id = ? ORDER BY question_id ASC");
    $stmt->bind_param("i", $testId);
    $stmt->execute();
    $result = $stmt->get_result();

    $questions = [];
    while ($row = $result->fetch_assoc()) {
        $row["question_id"] = (int)$row["question_id"];
        $questions[] = $row;
    }

    $stmt->close();
    $conn->close();
    jsonResponse($questions);
}

jsonResponse(["detail" => "Method not allowed"], 405);
?>
