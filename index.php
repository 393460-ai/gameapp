<?php
// L1-MW-HTMLShell-2026-03-06
include 'functions.php'; // This lets us use welcomePlayer()
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>High Speed Lynx</title>
    </head>
<body>
    <h1><?php echo welcomePlayer(); ?></h1>

    <canvas id="gameCanvas" width="800" height="400" style="background-color: #000; border: 3px solid #ff0000;"></canvas>
  
    <script src="js/game.js"></script>
</body>
</html>