<?php
// We MUST start the session here too, so this page can read the player's name!
session_start(); 

include 'functions.php'; // Pull in the backstage logic
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Playing - High Speed Lynx</title>
    <style>
        body {
            background-color: #FFF2CC; 
            color: #333333; 
            font-family: 'Arial', sans-serif; 
            text-align: center;
            margin-top: 20px;
        }
        
        h1 { 
            font-size: 3em; 
            color: #FF8C00; 
            text-shadow: 2px 2px #FFDAB9; 
            letter-spacing: 2px;
            margin-bottom: 10px;
        }

        canvas {
            width: 1000px;
            height: 500px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2); 
            border: 5px solid #FF8C00; 
            border-radius: 10px; 
            image-rendering: pixelated; 
        }

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
            margin-top: 20px;
            border-radius: 8px;
        }

        .back-btn:hover {
            background-color: #E67E22;
            transform: scale(1.05); 
        }
    </style>
</head>
<body>
    <h1>High Speed Lynx</h1>

    <canvas id="gameCanvas" width="800" height="400" style="background-color: #87CEEB;"></canvas>
  
    <br>
    <a href="index.php" class="back-btn">RETURN TO MENU</a>

    <script>
        const canvas = document.getElementById("gameCanvas");
        const ctx = canvas.getContext("2d");

        let gameState = "start"; 
        let cameraX = 0;
        let frameCount = 0; 
        let score = 0; // NEW: The Score Variable!

        let lynx = {
            x: 50,
            y: 350,
            width: 30,
            height: 30,
            color: "#FF4500", 
            dy: 0,           
            jumpPower: -12,  
            ground: 350,     
            speed: 6,
            hp: 3,           
            isHit: false,
            canJump: false   
        };

        let keys = { ArrowLeft: false, ArrowRight: false };
        let gravity = 0.6; 
        let gameObjects = [];

        function spawnObject() {
            let spawnX = cameraX + canvas.width + 50; 
            let rng = Math.random();

            if (rng < 0.20) {
                gameObjects.push({ type: 'cactus', x: spawnX, y: lynx.ground - 40, w: 20, h: 70, color: 'green', speedX: 0 });
            } else if (rng < 0.40) {
                gameObjects.push({ type: 'rat', x: spawnX, y: lynx.ground - 15, w: 30, h: 15, color: '#666666', speedX: -3 });
            } else if (rng < 0.60) {
                gameObjects.push({ type: 'snake', x: spawnX, y: lynx.ground - 10, w: 40, h: 10, color: '#556B2F', speedX: -1 });
            } else if (rng < 0.80) {
                let cloudY = Math.random() * 100 + 20; 
                gameObjects.push({ type: 'cloud', x: spawnX, y: cloudY, w: 80, h: 30, color: 'white', speedX: -0.5 });
            } else {
                let platformY = lynx.ground - 60 - (Math.random() * 40); 
                gameObjects.push({ type: 'platform', x: spawnX, y: platformY, w: 100, h: 15, color: '#8B4513', speedX: 0 });
            }
        }

        function drawGame() {
            // Background
            ctx.fillStyle = "#87CEEB";
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = "#EEDD82";
            ctx.fillRect(0, lynx.ground + lynx.height, canvas.width, canvas.height - lynx.ground);

            if (gameState === "start") {
                ctx.fillStyle = "white";
                ctx.font = "bold 30px Arial";
                ctx.shadowColor = "black";
                ctx.shadowBlur = 4;
                ctx.fillText("Press SPACE to start running!", 180, 200);
                ctx.shadowBlur = 0; 
            } else if (gameState === "playing") {
                
                // Physics
                lynx.dy += gravity; 
                lynx.y += lynx.dy;  
                lynx.canJump = false; 

                if (keys.ArrowLeft) lynx.x -= lynx.speed; 
                if (keys.ArrowRight) lynx.x += lynx.speed; 
                if (lynx.x < 0) lynx.x = 0;

                if (lynx.y >= lynx.ground) {
                    lynx.y = lynx.ground;
                    lynx.dy = 0; 
                    lynx.canJump = true; 
                }

                // Camera & Score Calculation
                if (lynx.x > cameraX + (canvas.width / 2)) {
                    cameraX = lynx.x - (canvas.width / 2);
                }
                if (cameraX < 0) cameraX = 0;
                
                // Calculate score by dividing camera travel by 10
                score = Math.floor(cameraX / 10);

                // Spawn Objects
                frameCount++;
                if (frameCount % 60 === 0) spawnObject();

                // Draw & Move Objects
                for (let i = 0; i < gameObjects.length; i++) {
                    let obj = gameObjects[i];
                    obj.x += obj.speedX; 
                    let drawX = obj.x - cameraX; 

                    ctx.fillStyle = obj.color;
                    ctx.fillRect(drawX, obj.y, obj.w, obj.h);

                    // Textures
                    if (obj.type === 'cactus') {
                        ctx.fillStyle = "black";
                        ctx.fillRect(drawX + 4, obj.y + 10, 4, 4);
                        ctx.fillRect(drawX + 12, obj.y + 10, 4, 4);
                    } else if (obj.type === 'rat') {
                        ctx.fillStyle = "red";
                        ctx.fillRect(drawX + 4, obj.y + 4, 4, 4);
                        ctx.fillStyle = "pink";
                        ctx.fillRect(drawX + obj.w, obj.y + 8, 10, 4); 
                    } else if (obj.type === 'snake') {
                        ctx.fillStyle = "yellow";
                        ctx.fillRect(drawX + 6, obj.y + 2, 4, 4);
                        ctx.fillStyle = "red";
                        ctx.fillRect(drawX - 6, obj.y + 6, 6, 2); 
                    } else if (obj.type === 'platform') {
                        ctx.fillStyle = "#A0522D"; 
                        ctx.fillRect(drawX, obj.y, obj.w, 4); 
                    }

                    // Collisions
                    if (obj.type === 'platform') {
                        if (lynx.dy >= 0 && lynx.y + lynx.height - lynx.dy <= obj.y && 
                            lynx.y + lynx.height >= obj.y && lynx.x + lynx.width > obj.x && lynx.x < obj.x + obj.w) {
                            lynx.y = obj.y - lynx.height; 
                            lynx.dy = 0; 
                            lynx.canJump = true; 
                        }
                    } else if (obj.type !== 'cloud' && !lynx.isHit) {
                        if (lynx.x < obj.x + obj.w && lynx.x + lynx.width > obj.x &&
                            lynx.y < obj.y + obj.h && lynx.y + lynx.height > obj.y) {
                            
                            lynx.hp -= 1;
                            lynx.isHit = true;
                            setTimeout(() => { lynx.isHit = false; }, 1000);

                            // GAME OVER TRIGGER
                            if (lynx.hp <= 0) {
                                gameState = "gameover";
                                savePlayerStats(score); // FIRE THE SCORE TO THE SERVER!
                            }
                        }
                    }
                }

                // Draw Lynx
                if (lynx.isHit && frameCount % 10 < 5) ctx.fillStyle = "red"; 
                else ctx.fillStyle = lynx.color; 
                ctx.fillRect(lynx.x - cameraX, lynx.y, lynx.width, lynx.height); 
                
                ctx.fillStyle = "black";
                ctx.fillRect((lynx.x - cameraX) + 20, lynx.y + 6, 6, 6);

                // --- DRAW THE HUD (HP and Score) ---
                ctx.fillStyle = "black";
                ctx.font = "bold 24px Arial";
                ctx.fillText("Score: " + score, 20, 355);
                ctx.fillText("HP: " + "❤️".repeat(lynx.hp), 20, 385);

                requestAnimationFrame(drawGame); 
            } else if (gameState === "gameover") {
                ctx.fillStyle = "black";
                ctx.font = "bold 40px Arial";
                ctx.fillText("GAME OVER!", 280, 200);
                ctx.font = "20px Arial";
                ctx.fillText("Final Score: " + score, 330, 240);
                ctx.fillText("Press SPACE to restart", 300, 280);
            }
        }

        drawGame();

        // Controls
        document.addEventListener("keydown", function(event) {
            if (event.code === "Space") {
                event.preventDefault(); 
                if (gameState === "start" || gameState === "gameover") {
                    if(gameState === "gameover") {
                        lynx.x = 50;
                        lynx.hp = 3;
                        cameraX = 0;
                        score = 0;
                        gameObjects = [];
                    }
                    gameState = "playing"; 
                    drawGame(); 
                } else if (gameState === "playing" && lynx.canJump) { 
                    lynx.dy = lynx.jumpPower; 
                    lynx.canJump = false;
                }
            }
            if (event.code === "ArrowLeft") keys.ArrowLeft = true;
            if (event.code === "ArrowRight") keys.ArrowRight = true;
        });

        document.addEventListener("keyup", function(event) {
            if (event.code === "ArrowLeft") keys.ArrowLeft = false;
            if (event.code === "ArrowRight") keys.ArrowRight = false;
        });

        // --- SERVER SAVE FUNCTION ---
        function savePlayerStats(finalScore) {
            // This sends a hidden message to your PHP server with the score
            fetch("save_score.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ score: finalScore }) 
            })
            .then(response => response.json())
            .then(data => {
                console.log("Server responded:", data);
            })
            .catch(error => {
                console.error("Error saving score:", error);
            });
        }
    </script>
</body>
</html>