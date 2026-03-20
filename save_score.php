<?php
session_start();

if (!isset($_SESSION["playerName"]) || $_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "error", "message" => "No player logged in!"]);
    exit;
}

$incomingData = json_decode(file_get_contents("php://input"), true);
$score = isset($incomingData["score"]) ? intval($incomingData["score"]) : 0;

if ($score <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid score"]);
    exit;
}

$playerName = htmlspecialchars($_SESSION["playerName"]);
$dateTime   = date("Y-m-d H:i:s");

$newEntry = [
    "playerName" => $playerName,
    "score"      => $score,
    "dateTime"   => $dateTime,
    "timestamp"  => $dateTime
];

// Use data/leaderboard.json to match functions.php path
$dir  = "data";
$file = $dir . "/leaderboard.json";

// Create data/ folder if it doesn't exist
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$leaderboard = [];
if (file_exists($file)) {
    $json        = file_get_contents($file);
    $leaderboard = json_decode($json, true) ?? [];
}

$leaderboard[] = $newEntry;
usort($leaderboard, fn($a, $b) => $b["score"] - $a["score"]);
$leaderboard = array_slice($leaderboard, 0, 100);

file_put_contents($file, json_encode($leaderboard, JSON_PRETTY_PRINT));
echo json_encode(["status" => "success", "score" => $score, "player" => $playerName]);
?>