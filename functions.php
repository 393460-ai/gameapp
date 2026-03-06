<?php
// This function opens the JSON file and reads the saved scores
function getScores() {
    // 1. Tell PHP exactly where our data is saved
    $file = 'data.json';

    // 2. Check if the file actually exists before we try to open it
    if (file_exists($file)) {
        // 3. Read the file and turn the JSON text back into a PHP array
        $json_data = file_get_contents($file);
        return json_decode($json_data, true); 
    } else {
        // 4. If no file exists yet (no one has played), return an empty array
        return []; 
    }
}
// L1-MW-leaderboard_read-2026-03-04
?>
