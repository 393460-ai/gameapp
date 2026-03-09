// 1. Grab the HTML canvas
const canvas = document.getElementById("gameCanvas");
const ctx = canvas.getContext("2d");

let gameState = "start"; 

// 2. Define our Lynx stats (Now with Physics!)
let lynx = {
    x: 50,
    y: 350,
    width: 30,
    height: 30,
    color: "orange",
    dy: 0,           // 'delta y' - speed going up/down
    jumpPower: -12,  // Negative moves us UP the canvas
    ground: 350      // The floor level so we don't fall forever
};

// Gravity pulls us back down constantly
let gravity = 0.6; 

// 3. The Game Loop (This runs continuously to create animation)
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
        requestAnimationFrame(drawGame); // Tells the browser to draw the next frame
    }
}

// Draw the very first frame
drawGame();

// 4. Listen for the spacebar (Start game AND Jump!)
document.addEventListener("keydown", function(event) {
    if (event.code === "Space") {
        if (gameState === "start") {
            // If on the menu, START the game!
            gameState = "playing"; 
            drawGame(); // Kick off the animation loop
        } else if (gameState === "playing" && lynx.y >= lynx.ground) {
            // If playing AND on the ground, JUMP!
            lynx.dy = lynx.jumpPower;
        }
    }
});