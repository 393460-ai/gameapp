// L1-MW-CreativeFeatures-2026-03-04
// Core Game Object
const game = {
    canvas: document.getElementById('gameCanvas'),
    ctx: document.getElementById('gameCanvas').getContext('2d'),
    score: 0,
    speed: 5,
    fearMeter: 0, // Scream-inspired mechanic
    isSpinCharging: false,
    
    init() {
        console.log("High Speed Lynx Initialized");
        // Start the game loop
        this.loop();
    },
    
    update() {
        // Breathing/Fear Meter Logic
        // If lynx is moving fast, fear/breathing increases
        if (this.speed > 5) {
            this.fearMeter += 0.2; // Increases faster during high speed
        } else {
            this.fearMeter = Math.max(0, this.fearMeter - 0.1); // Calms down when slow
        }

        // Cap the meter at 100
        if (this.fearMeter > 100) this.fearMeter = 100;

        // Screen shake or pulse effect if fear is high
        if (this.fearMeter > 80) {
            this.canvas.style.filter = `contrast(${100 + (this.fearMeter - 80)}%) brightness(${100 - (this.fearMeter - 80)/2}%)`;
        } else {
            this.canvas.style.filter = 'none';
        }
    },
    
    draw() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        
        // Draw Fear/Breathing Meter (Scream Theme)
        this.ctx.strokeStyle = "#ff0000";
        this.ctx.lineWidth = 2;
        this.ctx.strokeRect(10, 50, 200, 20);
        this.ctx.fillStyle = "#ff0000";
        this.ctx.fillRect(10, 50, this.fearMeter * 2, 20);
        this.ctx.fillStyle = "white";
        this.ctx.font = "14px Courier New";
        this.ctx.fillText("BREATHING/FEAR", 10, 85);

        // Draw Lynx (Placeholder)
        this.ctx.fillStyle = this.fearMeter > 50 ? "#00ff00" : "#ff0000"; // Carti Neon Green when "Overheated"
        this.ctx.fillRect(50, 200, 40, 40);
        
        // Draw Score
        this.ctx.fillStyle = "white";
        this.ctx.font = "20px Courier New";
        this.ctx.fillText(`Score: ${this.score}`, 10, 30);
    },
    
    loop() {
        this.update();
        this.draw();
        requestAnimationFrame(() => this.loop());
    }
};

game.init();
