// 1. Grab the HTML canvas
const canvas = document.getElementById("gameCanvas");
const ctx = canvas.getContext("2d");

let gameState = "start"; 
// L2-MW-ScoreTracking-2026-03-10


// --- NEW: HUD TRACKERS ---
let score = 0;
let frames = 0; // We use frames to calculate how many seconds have passed!
let coinsCollected = 0; // Gotta secure the bag!
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
        ctx.fillText("Press SPACE to escape the enemies", 150, 200);
    } else if (gameState === "playing") {

        // --- NEW: HUD / SCORE TRACKER ---
        frames += 1; // Add 1 frame every time the loop runs (60 times a second)
        let timeSurvived = Math.floor(frames / 60); // Math.floor rounds down to a clean second!
        score += 1; // Score goes up just by surviving!

        ctx.fillStyle = "white";
        ctx.font = "20px Arial";

        // Draw the stats stacked on top of each other in the top left corner
        ctx.fillText("Score: " + score, 20, 30); 
        ctx.fillText("Time: " + timeSurvived + "s", 20, 60); 
        ctx.fillText("Coins: " + coinsCollected, 20, 90); 

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