<?php
// 1. Read the JSON file from the server
$leaderboard = [];
$file = "leaderboard.json";

if (file_exists($file)) {
    // Grab the raw JSON text
    $json = file_get_contents($file);
    // Convert it back into a PHP array
    $leaderboard = json_decode($json, true);

    // Sort the array so the highest scores are at the top!
    usort($leaderboard, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Leaderboard - High Speed Lynx</title>
    <style>
        /* --- VIBRANT ARCADE THEME CSS --- */
        body {
            background-color: #FFF2CC; 
            color: #333333; 
            font-family: 'Arial', sans-serif; 
            text-align: center;
            margin-top: 50px;
        }

        h1 { 
            font-size: 3em; 
            color: #FF8C00; 
            text-shadow: 2px 2px #FFDAB9; 
            letter-spacing: 2px;
        }

        /* The Leaderboard Table */
        table {
            margin: 0 auto; /* Centers the table on the screen */
            border-collapse: collapse;
            width: 60%;
            background-color: white; /* Clean white background for reading */
            border: 4px solid #FF8C00; /* Bright orange border */
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); /* Soft shadow */
            border-radius: 10px;
            overflow: hidden; /* Keeps the corners rounded */
        }

        th, td {
            padding: 15px;
            border-bottom: 2px solid #FFDAB9; /* Soft peach lines between rows */
            font-size: 1.2em;
        }

        th {
            background-color: #FF8C00;
            color: white;
            font-weight: bold;
            font-size: 1.4em;
        }

        /* Alternate row colors for easy reading */
        tr:nth-child(even) {
            background-color: #FFF8DC;
        }

        /* Back Button */
        .back-btn {
            background-color: #FF8C00;
            color: white;
            border: none;
            padding: 12px 25px;
            font-size: 18px;
            font-weight: bold;
            font-family: 'Arial', sans-serif;
            text-decoration: none; 
            cursor: pointer;
            display: inline-block;
            transition: 0.3s;
            margin-top: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .back-btn:hover {
            background-color: #E67E22;
            transform: scale(1.05); 
        }
    </style>
</head>
<body>
    <h1>LEADERBOARD</h1>

    <table>
        <tr>
            <th>Rank</th>
            <th>Player Name</th>
            <th>Score</th>
        </tr>

        <?php if (empty($leaderboard)): ?>
            <tr>
                <td colspan="3">No players yet... start running!</td>
            </tr>
        <?php else: ?>
            <?php $rank = 1; ?>
            <?php foreach ($leaderboard as $entry): ?>
                <tr>
                    <td>#<?php echo $rank++; ?></td>
                    <td><?php echo htmlspecialchars($entry['playerName']); ?></td>
                    <td><?php echo htmlspecialchars($entry['score']); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>

    <a href="index.php" class="back-btn">Return to Menu</a>
</body>
</html>