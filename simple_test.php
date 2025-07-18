<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    exit(0);
}

echo json_encode([
    "success" => true,
    "message" => "Simple test endpoint working!",
    "method" => $_SERVER["REQUEST_METHOD"],
    "timestamp" => date("Y-m-d H:i:s"),
    "url" => $_SERVER["REQUEST_URI"] ?? "unknown"
]);
?>