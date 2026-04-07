<?php
require_once __DIR__ . "/../config.php";

$currentUser = getCurrentUser();
$conn = getDB();
$method = $_SERVER["REQUEST_METHOD"];

// GET /api/units - List units
if ($method === "GET") {
    $uid = $currentUser["user_id"];
    $query = "SELECT * FROM learning_units WHERE user_id = ?";
    $params = [$uid];
    $types = "i";

    // Filter by category
    if (!empty($_GET["category"])) {
        $query .= " AND category = ?";
        $params[] = $_GET["category"];
        $types .= "s";
    }

    // Filter by status
    if (!empty($_GET["status"])) {
        $query .= " AND status = ?";
        $params[] = $_GET["status"];
        $types .= "s";
    }

    // Search by title
    if (!empty($_GET["search"])) {
        $query .= " AND title LIKE ?";
        $params[] = "%" . $_GET["search"] . "%";
        $types .= "s";
    }

    $query .= " ORDER BY updated_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $units = [];
    while ($row = $result->fetch_assoc()) {
        $row["id"] = (int)$row["id"];
        $row["user_id"] = (int)$row["user_id"];
        $row["progress"] = (int)$row["progress"];
        $units[] = $row;
    }

    $stmt->close();
    $conn->close();
    jsonResponse($units);
}

// POST /api/units - Create unit
if ($method === "POST") {
    $body = getJsonBody();
    $title = trim($body["title"] ?? "");
    $category = $body["category"] ?? "DSA";
    $status = $body["status"] ?? "In Progress";
    $notes = $body["notes"] ?? "";
    $progress = (int)($body["progress"] ?? 0);

    if (!$title) {
        jsonResponse(["detail" => "Title is required"], 400);
    }

    $uid = $currentUser["user_id"];
    $stmt = $conn->prepare("INSERT INTO learning_units (user_id, title, category, status, notes, progress) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssi", $uid, $title, $category, $status, $notes, $progress);
    $stmt->execute();
    $new_id = $conn->insert_id;
    $stmt->close();

    // Fetch the created unit
    $stmt = $conn->prepare("SELECT * FROM learning_units WHERE id = ?");
    $stmt->bind_param("i", $new_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $row["id"] = (int)$row["id"];
    $row["user_id"] = (int)$row["user_id"];
    $row["progress"] = (int)$row["progress"];
    $stmt->close();
    $conn->close();
    jsonResponse($row, 201);
}

jsonResponse(["detail" => "Method not allowed"], 405);
?>
