<?php
// L2-MW-GamePage-2026-03-09
include 'functions.php'; // Pull in the backstage logic
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Playing - High Speed Lynx</title>
</head>
<body>
    <h1><?php echo welcomePlayer(); ?></h1>

    <canvas id="gameCanvas" width="800" height="400" style="background-color: #000; border: 3px solid #ff0000;"></canvas>
  
    <br><br>
    
    <a href="index.php">Back to Main Menu</a>

    <script>
        // Grab the HTML canvas
        const canvas = document.getElementById("gameCanvas");
        const ctx = canvas.getContext("2d");

        let gameState = "start"; 

        // Define our Lynx stats (With Physics!)
        let lynx = {
            x: 50,
            y: 350,
            width: 30,
            height: 30,
            color: "orange",
            dy: 0,           // speed going up/down
            jumpPower: -12,  // Negative moves us UP the canvas
            ground: 350      // The floor level
        };

        // Gravity pulls us back down constantly
        let gravity = 0.6; 

        // The Game Loop (This runs continuously)
        function drawGame() {
            ctx.clearRect(0, 0, canvas.width, canvas.height); // Clear old frames

            if (gameState === "start") {
                ctx.fillStyle = "white";
                ctx.font = "30px Arial";
                ctx.fillText("Press SPACE to escape Woodsboro", 150, 200);
            } else if (gameState === "playing") {
                // --- PHYSICS ENGINE ---
                lynx.dy += gravity; // Gravity constantly pulls us down
                lynx.y += lynx.dy;  // Move the Lynx based on its speed

                // Stop falling if we hit the ground
                if (lynx.y >= lynx.ground) {
                    lynx.y = lynx.ground;
                    lynx.dy = 0; // Stop moving down
                }

                // --- DRAW THE LYNX ---
                ctx.fillStyle = lynx.color; 
                ctx.fillRect(lynx.x, lynx.y, lynx.width, lynx.height); 

                // --- LOOP THE ANIMATION ---
                requestAnimationFrame(drawGame); 
            }
        }

        // Draw the very first frame
        drawGame();

        // Listen for the spacebar (Start game AND Jump!)
        document.addEventListener("keydown", function(event) {
            if (event.code === "Space") {
                event.preventDefault(); // Stops the page from scrolling down!

                if (gameState === "start") {
                    // If on the menu, START the game!
                    gameState = "playing"; 
                    drawGame(); 
                } else if (gameState === "playing" && lynx.y >= lynx.ground) {
                    // If playing AND on the ground, JUMP!
                    lynx.dy = lynx.jumpPower;
                }
            }
        });
    </script>
</body>
</html>