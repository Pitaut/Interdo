<?php
header("Content-Type: application/json");
require "config.php";

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["id"])) {
    echo json_encode(["error" => "Missing ID"]);
    exit;
}

$id = $data["id"];

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("DELETE FROM rendez_vous WHERE id=?");
    $stmt->execute([$id]);

    echo json_encode(["status" => "deleted"]);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
    http_response_code(500);
}
?>
