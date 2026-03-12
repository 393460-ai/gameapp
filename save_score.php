<?php
// 1. Start the session to grab the player's name!
session_start();

// 2. Check if we have a player name AND if data was sent via POST
if (isset($_SESSION["playerName"]) && $_SERVER["REQUEST_METHOD"] == "POST") {

    // 3. Grab the JSON data sent over from the JavaScript game
    $incomingData = json_decode(file_get_contents("php://input"), true);

    // 4. Build the stat block
    $playerName = $_SESSION["playerName"];
    $score = $incomingData["score"] ?? 0;
    $date = date("Y-m-d H:i:s"); // This grabs the exact date and time!

    $newEntry = [
        "playerName" => $playerName,
        "score" => $score,
        "dateTime" => $date
    ];

    // 5. Open the existing leaderboard (or create a blank one)
    $file = "leaderboard.json";
    $leaderboard = [];
    if (file_exists($file)) {
        $json = file_get_contents($file);
        $leaderboard = json_decode($json, true);
    }

    // 6. Add the new score to the list and save it back to the file
    $leaderboard[] = $newEntry;
    file_put_contents($file, json_encode($leaderboard, JSON_PRETTY_PRINT));

    // 7. Tell the JavaScript game it was a success!
    echo json_encode(["status" => "success"]);

} else {
    // If they aren't logged in, block them!
    echo json_encode(["status" => "error", "message" => "No player logged in!"]);
}
?>