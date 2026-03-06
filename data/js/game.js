/// 1. Grab the HTML canvas so we have a stage to draw on
const canvas = document.getElementById("gameCanvas");
const ctx = canvas.getContext("2d"); // 'ctx' is our magic paintbrush

// 2. Define our Lynx character's stats
let lynx = {
    x: 50,          // Starting position from the left wall
    y: 350,         // Starting position from the ceiling (close to the floor)
    width: 30,      // How wide the Lynx is
    height: 30,     // How tall the Lynx is
    color: "orange" // The color of our Lynx
};

// 3. Create a function to draw the Lynx on the screen
function drawGame() {
    ctx.fillStyle = lynx.color; // Dip the brush in the lynx color
    ctx.fillRect(lynx.x, lynx.y, lynx.width, lynx.height); // Draw the box
}

// 4. Run the function to actually show it
drawGame();

// L1-MW-draw_lynx-2026-03-04
// L1-MW-SpacebarStart-2026-03-06

// 4. Listen for the player to press a key anywhere on the page
document.addEventListener("keydown", function(event) {
    // Check if we are at the start screen AND the player pressed Space
    if (gameState === "start" && event.code === "Space") {
        gameState = "playing"; // Change the game's mode!
        drawGame(); // Redraw the screen so the Lynx finally appears
    }
});