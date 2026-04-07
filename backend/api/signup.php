<?php
require_once __DIR__ . "/../config.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    jsonResponse(["detail" => "Method not allowed"], 405);
}

$body = getJsonBody();
$full_name = trim($body["full_name"] ?? "");
$email = trim($body["email"] ?? "");
$password = $body["password"] ?? "";

if (!$full_name || !$email || !$password) {
    jsonResponse(["detail" => "Full name, email, and password are required"], 400);
}

$conn = getDB();

// Check if email already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    jsonResponse(["detail" => "Email already registered"], 400);
}
$stmt->close();

// Hash password and insert user
$password_hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $full_name, $email, $password_hash);
$stmt->execute();
$user_id = $conn->insert_id;
$stmt->close();

// Seed 5 learning units for new user
$seed_units = [
    ["Binary Search Tree", "DSA", "In Progress", "Core tree operations, traversal methods", 60],
    ["React Composition API", "Web", "Done", "Component patterns, hooks composition", 100],
    ["JVM Memory Management", "Java", "Need Revision", "Heap, stack, garbage collection", 32],
    ["Graph Traversal (DFS/BFS)", "DSA", "In Progress", "Depth-first and breadth-first search algorithms", 15],
    ["Next.js Server Components", "Web", "In Progress", "Server-side rendering, streaming, RSC patterns", 85],
];

$stmt = $conn->prepare("INSERT INTO learning_units (user_id, title, category, status, notes, progress) VALUES (?, ?, ?, ?, ?, ?)");
foreach ($seed_units as $unit) {
    $stmt->bind_param("issssi", $user_id, $unit[0], $unit[1], $unit[2], $unit[3], $unit[4]);
    $stmt->execute();
}
$stmt->close();

// Generate JWT token
$token = jwtEncode(["user_id" => $user_id, "email" => $email]);

$conn->close();

jsonResponse([
    "token" => $token,
    "user" => [
        "id" => $user_id,
        "full_name" => $full_name,
        "email" => $email
    ]
]);
?>
