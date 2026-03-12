<?php
// Start the session so we can remember who is playing!
session_start();

// If the player submitted the form, save their name and send them to the game!
if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST["playerName"])) {
    // Save the name securely
    $_SESSION["playerName"] = htmlspecialchars($_POST["playerName"]);
    
    // Teleport them to the game screen
    header("Location: game.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Main Menu - High Speed Lynx</title>
    <style>
        /* --- VIBRANT ARCADE THEME CSS --- */
        body {
            background-color: #FFF2CC; 
            color: #333333; 
            font-family: 'Arial', sans-serif; 
            text-align: center;
            margin-top: 80px;
        }

        h1 { 
            font-size: 4em; 
            color: #FF8C00; 
            text-shadow: 3px 3px #FFDAB9; 
            letter-spacing: 2px;
            margin-bottom: 10px;
        }

        h2 {
            color: #E67E22;
            margin-bottom: 40px;
        }

        /* The Menu Box */
        .menu-container {
            background-color: white;
            border: 5px solid #FF8C00;
            border-radius: 15px;
            width: 400px;
            margin: 0 auto; /* Centers the box */
            padding: 40px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }

        input[type="text"] {
            width: 80%;
            padding: 12px;
            font-size: 18px;
            border: 2px solid #FFDAB9;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: #FF8C00;
        }

        /* Buttons */
        .btn {
            background-color: #FF8C00;
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 20px;
            font-weight: bold;
            font-family: 'Arial', sans-serif;
            text-decoration: none; 
            cursor: pointer;
            display: block;
            width: 100%;
            transition: 0.3s;
            border-radius: 8px;
            margin-bottom: 15px;
            box-sizing: border-box;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .btn:hover {
            background-color: #E67E22;
            transform: scale(1.05); 
        }

        .btn-secondary {
            background-color: #87CEEB; /* Sky blue for the leaderboard button */
            color: #333;
        }

        .btn-secondary:hover {
            background-color: #5DADE2;
        }
    </style>
</head>
<body>

    <h1>High Speed Lynx</h1>
    <h2>Ready to run? The Desert awaits...</h2>

    <div class="menu-container">
        <form method="POST" action="index.php">
            <input type="text" name="playerName" placeholder="Enter Player Name..." required maxlength="15">
            <button type="submit" class="btn">START RUNNING</button>
        </form>

        <a href="leaderboard.php" class="btn btn-secondary">VIEW LEADERBOARD</a>
    </div>

</body>
</html>