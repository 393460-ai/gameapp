<?php
// L1-MW-CombinedFunctions-2026-03-09

// 1. Your Leaderboard Function
function getScores() {
    // Pointed to the correct folder!
    $file = 'data/leaderboard.json'; 

    if (file_exists($file)) {
        $json_data = file_get_contents($file);
        return json_decode($json_data, true); 
    } else {
        return []; 
    }
}

// 2. Your Welcome Function (This fixes your error!)
function welcomePlayer() {
    return "are u ready to play high speed lynx game?...";
}
?>