<?php
require_once __DIR__ . "/../config.php";

$currentUser = getCurrentUser();
$conn = getDB();
$method = $_SERVER["REQUEST_METHOD"];

// Get unit_id from query parameter
$unit_id = (int)($_GET["id"] ?? 0);
if (!$unit_id) {
    jsonResponse(["detail" => "Unit ID is required"], 400);
}

$uid = $currentUser["user_id"];

// Verify unit belongs to user
$stmt = $conn->prepare("SELECT * FROM learning_units WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $unit_id, $uid);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$existing) {
    jsonResponse(["detail" => "Unit not found"], 404);
}

// PUT - Update unit
if ($method === "PUT") {
    $body = getJsonBody();
    $updates = [];
    $params = [];
    $types = "";

    if (isset($body["title"])) { $updates[] = "title = ?"; $params[] = $body["title"]; $types .= "s"; }
    if (isset($body["category"])) { $updates[] = "category = ?"; $params[] = $body["category"]; $types .= "s"; }
    if (isset($body["status"])) { $updates[] = "status = ?"; $params[] = $body["status"]; $types .= "s"; }
    if (isset($body["notes"])) { $updates[] = "notes = ?"; $params[] = $body["notes"]; $types .= "s"; }
    if (isset($body["progress"])) { $updates[] = "progress = ?"; $params[] = (int)$body["progress"]; $types .= "i"; }

    if (!empty($updates)) {
        $updates[] = "updated_at = NOW()";
        $updates[] = "last_revised = NOW()";
        $set_clause = implode(", ", $updates);
        $params[] = $unit_id;
        $types .= "i";

        $stmt = $conn->prepare("UPDATE learning_units SET $set_clause WHERE id = ?");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    }

    // Fetch updated unit
    $stmt = $conn->prepare("SELECT * FROM learning_units WHERE id = ?");
    $stmt->bind_param("i", $unit_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $row["id"] = (int)$row["id"];
    $row["user_id"] = (int)$row["user_id"];
    $row["progress"] = (int)$row["progress"];
    $stmt->close();
    $conn->close();
    jsonResponse($row);
}

// DELETE - Delete unit
if ($method === "DELETE") {
    $stmt = $conn->prepare("DELETE FROM learning_units WHERE id = ?");
    $stmt->bind_param("i", $unit_id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    jsonResponse(["message" => "Unit deleted"]);
}

jsonResponse(["detail" => "Method not allowed"], 405);
?>
