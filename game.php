<?php
session_start();
include 'functions.php';
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
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
        .back-btn:hover { background-color: #E67E22; transform: scale(1.05); }
    </style>
</head>
<body>
    <h1>High Speed Lynx</h1>
    <canvas id="gameCanvas" width="800" height="400"></canvas>
    <br>
    <a href="index.php" class="back-btn">RETURN TO MENU</a>

    <script>
        const canvas = document.getElementById("gameCanvas");
        const ctx    = canvas.getContext("2d");

        // ═══════════════════════════════════════════════════════
        //  GAME-WIDE STATE
        // ═══════════════════════════════════════════════════════
        let currentLevel = 1;
        let gameState    = "start";  // start | playing | levelclear | win | gameover
        let cameraX      = 0;
        let frameCount   = 0;
        const gravity    = 0.6;
        let keys = { ArrowLeft:false, ArrowRight:false, ArrowUp:false, ArrowDown:false };

        let lynx = {
            x:60, y:320, width:30, height:30,
            color:"#FF4500", dy:0, jumpPower:-12,
            ground:350, speed:6, hp:3,
            isHit:false, isClimbing:false, climbTree:null, score:0,
        };

        // ═══════════════════════════════════════════════════════
        //  LEVEL 1 — DESERT  (your original game, now with a
        //  finish flag at x = 3200)
        // ═══════════════════════════════════════════════════════
        const DESERT_LENGTH  = 3200;
        let desertObjects    = [];
        let desertSpawnTimer = 0;

        function spawnDesertObject() {
            let spawnX = cameraX + canvas.width + 50;
            let rng = Math.random();
            if      (rng < 0.25) desertObjects.push({ type:'cactus', x:spawnX, y:lynx.ground-40, w:20, h:70,  color:'green',   speedX:0   });
            else if (rng < 0.50) desertObjects.push({ type:'rat',    x:spawnX, y:lynx.ground-15, w:30, h:15,  color:'#555555', speedX:-3  });
            else if (rng < 0.75) desertObjects.push({ type:'snake',  x:spawnX, y:lynx.ground-10, w:40, h:10,  color:'olive',   speedX:-1  });
            else                 desertObjects.push({ type:'cloud',  x:spawnX, y:Math.random()*100+20, w:80, h:30, color:'white', speedX:-0.5 });
        }

        function updateDrawDesert() {
            // Background
            ctx.fillStyle = "#87CEEB"; ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = "#EEDD82"; ctx.fillRect(0, lynx.ground+lynx.height, canvas.width, canvas.height-lynx.ground);

            // Finish flag
            drawSimpleFlag(DESERT_LENGTH, lynx.ground - 80, "#FFD700", "LV2");

            // Physics
            lynx.dy += gravity; lynx.y += lynx.dy;
            if (keys.ArrowLeft)  lynx.x -= lynx.speed;
            if (keys.ArrowRight) lynx.x += lynx.speed;
            if (lynx.x < 0) lynx.x = 0;
            if (lynx.y >= lynx.ground) { lynx.y = lynx.ground; lynx.dy = 0; }

            // Camera
            if (lynx.x > cameraX + canvas.width/2) cameraX = lynx.x - canvas.width/2;
            if (cameraX < 0) cameraX = 0;

            // Spawn
            desertSpawnTimer++;
            if (desertSpawnTimer % 60 === 0) spawnDesertObject();

            // Objects
            for (let i = desertObjects.length - 1; i >= 0; i--) {
                let obj = desertObjects[i];
                obj.x += obj.speedX;
                if (obj.x + obj.w < cameraX - 100) { desertObjects.splice(i, 1); continue; }
                ctx.fillStyle = obj.color;
                ctx.fillRect(obj.x - cameraX, obj.y, obj.w, obj.h);
                if (obj.type !== 'cloud' && !lynx.isHit) {
                    if (lynx.x < obj.x+obj.w && lynx.x+lynx.width > obj.x &&
                        lynx.y < obj.y+obj.h && lynx.y+lynx.height > obj.y) {
                        lynx.hp--; lynx.isHit = true;
                        setTimeout(() => { lynx.isHit = false; }, 1000);
                        if (lynx.hp <= 0) gameState = "gameover";
                    }
                }
            }

            drawLynxSprite();
            drawHUD();

            // Reach flag?
            if (lynx.x >= DESERT_LENGTH - 40) {
                lynx.score += 200;
                gameState = "levelclear";
            }
        }

        // ═══════════════════════════════════════════════════════
        //  LEVEL 2 — MOUNTAIN
        // ═══════════════════════════════════════════════════════
        const MOUNTAIN_LENGTH = 4800;
        const TREE_W = 18, TREE_H = 80, TREE_R = 38, CLIMB_SPD = 3;

        const terrain = [
            {x:0,    y:350, w:600 }, {x:600,  y:310, w:200}, {x:800,  y:270, w:200},
            {x:1000, y:230, w:300 }, {x:1300, y:260, w:200}, {x:1500, y:300, w:300},
            {x:1800, y:350, w:400 }, {x:2200, y:290, w:300}, {x:2500, y:250, w:200},
            {x:2700, y:280, w:200 }, {x:2900, y:350, w:500}, {x:3400, y:350, w:1400},
        ];

        function groundAt(x) {
            for (let i = terrain.length-1; i >= 0; i--) {
                let t = terrain[i];
                if (x >= t.x && x < t.x+t.w) return t.y;
            }
            return 350;
        }

        const mtnObjs = [
            // Trees
            {type:'tree', x:300 }, {type:'tree', x:700 }, {type:'tree', x:1900},
            {type:'tree', x:2100}, {type:'tree', x:3100}, {type:'tree', x:3600}, {type:'tree', x:4200},
            // Rocks
            {type:'rock', x:500,  w:35, h:28}, {type:'rock', x:1100, w:40, h:30},
            {type:'rock', x:1700, w:35, h:25}, {type:'rock', x:2800, w:45, h:32},
            {type:'rock', x:3700, w:38, h:28}, {type:'rock', x:4300, w:35, h:26},
            // Bears
            {type:'bear', x:900,  w:36, h:32, patrolMin:800,  patrolMax:1050, dir:1,  speed:1.5},
            {type:'bear', x:1500, w:36, h:32, patrolMin:1400, patrolMax:1600, dir:-1, speed:1.5},
            {type:'bear', x:2000, w:36, h:32, patrolMin:1900, patrolMax:2150, dir:1,  speed:2  },
            {type:'bear', x:2600, w:36, h:32, patrolMin:2500, patrolMax:2750, dir:-1, speed:2  },
            {type:'bear', x:3300, w:36, h:32, patrolMin:3200, patrolMax:3450, dir:1,  speed:2.5},
            {type:'bear', x:3900, w:36, h:32, patrolMin:3800, patrolMax:4050, dir:-1, speed:2.5},
            {type:'bear', x:4400, w:36, h:32, patrolMin:4300, patrolMax:4550, dir:1,  speed:3  },
        ];

        // Fix y positions once
        for (let o of mtnObjs) {
            let g = groundAt(o.x + (o.w||0)/2);
            if (o.type==='tree') { o.w=TREE_W; o.h=TREE_H; o.y=g-TREE_H; }
            if (o.type==='rock') { o.y=g-o.h; }
            if (o.type==='bear') { o.y=g-o.h; }
        }
        const MTN_FLAG_X = MOUNTAIN_LENGTH - 120;

        function updateDrawMountain() {
            // Sky
            let sky = ctx.createLinearGradient(0,0,0,canvas.height);
            sky.addColorStop(0,"#5BA3D9"); sky.addColorStop(1,"#B8DCEF");
            ctx.fillStyle=sky; ctx.fillRect(0,0,canvas.width,canvas.height);
            // Parallax hills
            drawPRange(0.2,"#8EB4CC",[[0,300],[150,200],[300,250],[500,180],[700,230],[900,160],[1100,210],[1300,240],[1700,260],[2000,300]]);
            drawPRange(0.4,"#9FCFBB",[[0,320],[200,240],[450,210],[650,260],[900,200],[1150,255],[1700,235],[2000,320]]);
            // Terrain
            for (let t of terrain) {
                let sx=t.x-cameraX;
                ctx.fillStyle="#5A8A3C"; ctx.fillRect(sx,t.y,t.w,12);
                ctx.fillStyle="#8B6040"; ctx.fillRect(sx,t.y+12,t.w,canvas.height-t.y);
            }
            // Win flag
            drawSimpleFlag(MTN_FLAG_X, groundAt(MTN_FLAG_X)-90, "#FFD700","WIN!");

            // Climbing physics
            if (lynx.isClimbing && lynx.climbTree) {
                let t = lynx.climbTree;
                if (keys.ArrowUp)   lynx.y -= CLIMB_SPD;
                if (keys.ArrowDown) lynx.y += CLIMB_SPD;
                if (lynx.y < t.y - TREE_R*2)   lynx.y = t.y - TREE_R*2;
                if (lynx.y > t.y + t.h - lynx.height) { lynx.isClimbing=false; lynx.climbTree=null; lynx.dy=0; }
                lynx.dy = 0;
            } else {
                lynx.dy += gravity; lynx.y += lynx.dy;
                if (keys.ArrowLeft)  lynx.x -= lynx.speed;
                if (keys.ArrowRight) lynx.x += lynx.speed;
                lynx.x = Math.max(0, Math.min(MOUNTAIN_LENGTH - lynx.width, lynx.x));
                let gnd = groundAt(lynx.x+lynx.width/2) - lynx.height;
                if (lynx.y >= gnd) { lynx.y=gnd; lynx.dy=0; }
                if (lynx.y > canvas.height+50) { lynx.hp=0; gameState="gameover"; }

                if (keys.ArrowUp) {
                    for (let o of mtnObjs) {
                        if (o.type!=='tree') continue;
                        let dist = Math.abs(lynx.x+lynx.width/2-(o.x+TREE_W/2));
                        if (dist<24 && lynx.y+lynx.height>o.y && lynx.y<o.y+o.h) {
                            lynx.isClimbing=true; lynx.climbTree=o; lynx.dy=0; break;
                        }
                    }
                }
            }

            // Smooth camera
            let tc = lynx.x - canvas.width*0.35;
            cameraX += (tc-cameraX)*0.12;
            cameraX = Math.max(0, Math.min(MOUNTAIN_LENGTH-canvas.width, cameraX));

            // Draw & update objects
            for (let o of mtnObjs) {
                if (o.x-cameraX > canvas.width+100 || o.x+o.w-cameraX < -100) continue;
                if      (o.type==='tree') drawTree(o);
                else if (o.type==='rock') drawRock(o);
                else if (o.type==='bear') {
                    o.x += o.speed * o.dir;
                    o.y  = groundAt(o.x+o.w/2) - o.h;
                    if (o.x > o.patrolMax) o.dir=-1;
                    if (o.x < o.patrolMin) o.dir= 1;
                    drawBear(o);
                }
                if (!lynx.isHit && o.type!=='tree') {
                    if (lynx.x<o.x+o.w && lynx.x+lynx.width>o.x && lynx.y<o.y+o.h && lynx.y+lynx.height>o.y) {
                        lynx.hp--; lynx.isHit=true; lynx.dy=-7;
                        lynx.x += (lynx.x < o.x+o.w/2) ? -20 : 20;
                        setTimeout(()=>{ lynx.isHit=false; },1200);
                        if (lynx.hp<=0) gameState="gameover";
                    }
                }
            }

            drawLynxSprite();
            drawHUD();
            if (lynx.isClimbing) {
                ctx.fillStyle="rgba(0,0,0,0.5)"; ctx.fillRect(300,10,200,28);
                ctx.fillStyle="#FFD700"; ctx.font="bold 16px Arial";
                ctx.fillText("🌲 CLIMBING TREE", 310, 29);
            }
            if (lynx.x+lynx.width > MTN_FLAG_X && lynx.x < MTN_FLAG_X+20) {
                lynx.score += 500; gameState = "win";
            }
        }

        // ═══════════════════════════════════════════════════════
        //  DRAW HELPERS
        // ═══════════════════════════════════════════════════════
        function drawPRange(p, color, pts) {
            ctx.fillStyle=color; ctx.beginPath();
            ctx.moveTo(-cameraX*p+pts[0][0], pts[0][1]);
            for (let pt of pts) ctx.lineTo(-cameraX*p+pt[0], pt[1]);
            ctx.lineTo(-cameraX*p+pts[pts.length-1][0], canvas.height);
            ctx.lineTo(-cameraX*p+pts[0][0], canvas.height);
            ctx.closePath(); ctx.fill();
        }

        function drawSimpleFlag(wx, wy, color, label) {
            let sx = wx - cameraX;
            if (sx < -50 || sx > canvas.width+50) return;
            ctx.strokeStyle="#555"; ctx.lineWidth=4;
            ctx.beginPath(); ctx.moveTo(sx, wy+80); ctx.lineTo(sx, wy); ctx.stroke();
            ctx.fillStyle=color;
            ctx.beginPath(); ctx.moveTo(sx,wy); ctx.lineTo(sx+28,wy+12); ctx.lineTo(sx,wy+24); ctx.closePath(); ctx.fill();
            ctx.fillStyle="white"; ctx.font="bold 11px Arial"; ctx.fillText(label, sx+3, wy+16);
        }

        function drawTree(o) {
            let sx=o.x-cameraX, sy=o.y;
            ctx.fillStyle="#7B4F2A"; ctx.fillRect(sx,sy,TREE_W,TREE_H);
            ctx.fillStyle="#2D6A2D"; ctx.beginPath(); ctx.arc(sx+TREE_W/2,sy-10, TREE_R,    0,Math.PI*2); ctx.fill();
            ctx.fillStyle="#3A8A3A"; ctx.beginPath(); ctx.arc(sx+TREE_W/2,sy-28, TREE_R*.75,0,Math.PI*2); ctx.fill();
            ctx.fillStyle="#48AA48"; ctx.beginPath(); ctx.arc(sx+TREE_W/2,sy-44, TREE_R*.5, 0,Math.PI*2); ctx.fill();
            if (!lynx.isClimbing && Math.abs(lynx.x+lynx.width/2-(o.x+TREE_W/2))<40) {
                ctx.fillStyle="rgba(255,255,255,0.85)"; ctx.font="bold 11px Arial";
                ctx.fillText("↑ CLIMB", sx-5, sy-55);
            }
        }

        function drawRock(o) {
            let sx=o.x-cameraX;
            ctx.fillStyle="rgba(0,0,0,0.15)"; ctx.beginPath();
            ctx.ellipse(sx+o.w/2,o.y+o.h+3,o.w/2+4,5,0,0,Math.PI*2); ctx.fill();
            ctx.fillStyle="#888"; ctx.beginPath();
            ctx.moveTo(sx+8,o.y); ctx.lineTo(sx+o.w-5,o.y+5);
            ctx.lineTo(sx+o.w,o.y+o.h); ctx.lineTo(sx,o.y+o.h); ctx.lineTo(sx+2,o.y+8);
            ctx.closePath(); ctx.fill();
            ctx.fillStyle="#aaa"; ctx.beginPath(); ctx.ellipse(sx+10,o.y+8,7,5,-0.4,0,Math.PI*2); ctx.fill();
        }

        function drawBear(o) {
            let sx=o.x-cameraX, sy=o.y;
            ctx.save();
            if (o.dir<0) { ctx.translate(sx+o.w,sy); ctx.scale(-1,1); ctx.translate(-o.w,0); }
            else          { ctx.translate(sx,sy); }
            ctx.fillStyle="#5C3A1E"; ctx.fillRect(4,6,28,20);
            ctx.fillStyle="#6B4226"; ctx.beginPath(); ctx.arc(28,10,12,0,Math.PI*2); ctx.fill();
            ctx.fillStyle="#5C3A1E";
            ctx.beginPath(); ctx.arc(22,2,5,0,Math.PI*2); ctx.fill();
            ctx.beginPath(); ctx.arc(34,2,5,0,Math.PI*2); ctx.fill();
            ctx.fillStyle="white";   ctx.beginPath(); ctx.arc(32,9,3,0,Math.PI*2); ctx.fill();
            ctx.fillStyle="#222";    ctx.beginPath(); ctx.arc(33,9,1.5,0,Math.PI*2); ctx.fill();
            ctx.fillStyle="#A0624A"; ctx.beginPath(); ctx.ellipse(36,13,5,3.5,0,0,Math.PI*2); ctx.fill();
            ctx.fillStyle="#222";    ctx.beginPath(); ctx.arc(36,12,1.2,0,Math.PI*2); ctx.fill();
            ctx.fillStyle="#5C3A1E";
            let ls=Math.sin(frameCount*0.18)*4;
            ctx.fillRect(6,24+ls,8,9); ctx.fillRect(18,24-ls,8,9);
            ctx.restore();
        }

        function drawLynxSprite() {
            let sx=lynx.x-cameraX, sy=lynx.y;
            let hit = lynx.isHit && frameCount%10<5;
            ctx.fillStyle = hit ? "red" : lynx.color; ctx.fillRect(sx+4,sy+10,22,18);
            ctx.fillStyle = hit ? "red" : "#E05000";  ctx.fillRect(sx+14,sy,16,16);
            ctx.fillStyle="#FF6A00";
            ctx.beginPath(); ctx.moveTo(sx+15,sy); ctx.lineTo(sx+12,sy-8); ctx.lineTo(sx+19,sy-2); ctx.closePath(); ctx.fill();
            ctx.beginPath(); ctx.moveTo(sx+25,sy); ctx.lineTo(sx+29,sy-8); ctx.lineTo(sx+22,sy-2); ctx.closePath(); ctx.fill();
            ctx.fillStyle="white"; ctx.fillRect(sx+17,sy+4,5,5);
            ctx.fillStyle="#222";  ctx.fillRect(sx+19,sy+5,3,3);
            ctx.beginPath(); ctx.moveTo(sx+4,sy+15); ctx.quadraticCurveTo(sx-8,sy+5,sx-2,sy-2);
            ctx.lineWidth=5; ctx.strokeStyle="#FF4500"; ctx.stroke();
            ctx.fillStyle="#E05000";
            let run = lynx.isClimbing ? 0 : Math.sin(frameCount*0.2)*4;
            ctx.fillRect(sx+6, sy+26+run,7,10); ctx.fillRect(sx+17,sy+26-run,7,10);
            if (lynx.isClimbing) {
                ctx.fillStyle="#FF8C00";
                ctx.fillRect(sx-4,sy+8,8,6); ctx.fillRect(sx+26,sy+20,8,6);
            }
        }

        function drawHUD() {
            ctx.fillStyle="rgba(0,0,0,0.45)"; ctx.fillRect(10,368,160,28);
            ctx.fillStyle="white"; ctx.font="bold 16px Arial";
            ctx.fillText("HP: "+"❤️".repeat(Math.max(0,lynx.hp)), 16, 387);
            ctx.fillStyle="rgba(0,0,0,0.45)"; ctx.fillRect(canvas.width-150,368,140,28);
            ctx.fillStyle="white"; ctx.font="bold 16px Arial";
            ctx.fillText("Score: "+lynx.score, canvas.width-144, 387);
            let badge = currentLevel===1 ? "LEVEL 1 – DESERT" : "LEVEL 2 – MOUNTAIN";
            let bw = ctx.measureText(badge).width + 20;
            ctx.fillStyle="rgba(0,0,0,0.45)"; ctx.fillRect(canvas.width/2-bw/2,8,bw,24);
            ctx.fillStyle="#FFD700"; ctx.font="bold 14px Arial";
            ctx.textAlign="center"; ctx.fillText(badge, canvas.width/2, 25); ctx.textAlign="left";
        }

        function drawOverlay(title, tColor, sub) {
            ctx.fillStyle="rgba(0,0,0,0.62)"; ctx.fillRect(130,130,540,140);
            ctx.textAlign="center";
            ctx.fillStyle=tColor;  ctx.font="bold 42px Arial"; ctx.fillText(title, canvas.width/2, 190);
            ctx.fillStyle="white"; ctx.font="20px Arial";      ctx.fillText(sub,   canvas.width/2, 235);
            ctx.textAlign="left";
        }

        // ═══════════════════════════════════════════════════════
        //  RESET HELPERS
        // ═══════════════════════════════════════════════════════
        function resetToLevel1() {
            currentLevel=1; cameraX=0; frameCount=0;
            lynx.x=60; lynx.y=320; lynx.dy=0; lynx.hp=3; lynx.score=0;
            lynx.isHit=false; lynx.isClimbing=false; lynx.climbTree=null; lynx.ground=350;
            desertObjects=[]; desertSpawnTimer=0;
        }

        function startLevel2() {
            currentLevel=2; cameraX=0; frameCount=0;
            lynx.x=60; lynx.y=320; lynx.dy=0;
            lynx.isHit=false; lynx.isClimbing=false; lynx.climbTree=null; lynx.ground=350;
            // HP & score carry over!
            for (let o of mtnObjs) {
                if (o.type==='bear') { o.x=o.patrolMin; o.y=groundAt(o.patrolMin)-o.h; }
            }
        }

        // ═══════════════════════════════════════════════════════
        //  MAIN LOOP
        // ═══════════════════════════════════════════════════════
        function drawGame() {
            ctx.clearRect(0,0,canvas.width,canvas.height);
            frameCount++;

            if (gameState==="start") {
                ctx.fillStyle="#87CEEB"; ctx.fillRect(0,0,canvas.width,canvas.height);
                ctx.fillStyle="#EEDD82"; ctx.fillRect(0,380,canvas.width,20);
                drawLynxSprite();
                ctx.fillStyle="rgba(0,0,0,0.55)"; ctx.fillRect(130,140,540,115);
                ctx.textAlign="center";
                ctx.fillStyle="#FF8C00"; ctx.font="bold 32px Arial"; ctx.fillText("🌵  LEVEL 1: DESERT", canvas.width/2, 185);
                ctx.fillStyle="white";   ctx.font="18px Arial";      ctx.fillText("Dodge cacti, rats & snakes — reach the flag!", canvas.width/2, 218);
                ctx.fillStyle="#FFD700"; ctx.font="16px Arial";      ctx.fillText("Press SPACE to start", canvas.width/2, 244);
                ctx.textAlign="left";

            } else if (gameState==="playing") {
                if (currentLevel===1) updateDrawDesert();
                else                  updateDrawMountain();

            } else if (gameState==="levelclear") {
                ctx.fillStyle="#87CEEB"; ctx.fillRect(0,0,canvas.width,canvas.height);
                ctx.fillStyle="#EEDD82"; ctx.fillRect(0,380,canvas.width,20);
                drawLynxSprite();
                ctx.fillStyle="rgba(0,0,0,0.62)"; ctx.fillRect(110,120,580,160);
                ctx.textAlign="center";
                ctx.fillStyle="#00DD44"; ctx.font="bold 38px Arial"; ctx.fillText("✅  LEVEL 1 CLEAR!", canvas.width/2, 172);
                ctx.fillStyle="white";   ctx.font="20px Arial";      ctx.fillText("Score: "+lynx.score+"  •  HP: "+"❤️".repeat(lynx.hp)+" carried over!", canvas.width/2, 208);
                ctx.fillStyle="#FFD700"; ctx.font="18px Arial";      ctx.fillText("Press SPACE to enter ⛰  Mountain Level 2", canvas.width/2, 248);
                ctx.textAlign="left";

            } else if (gameState==="win") {
                ctx.fillStyle="#5BA3D9"; ctx.fillRect(0,0,canvas.width,canvas.height);
                drawLynxSprite();
                drawOverlay("🏆  YOU WIN!", "#FFD700", "Final Score: "+lynx.score+"  •  SPACE to play again");

            } else if (gameState==="gameover") {
                ctx.fillStyle = currentLevel===1 ? "#87CEEB" : "#5BA3D9";
                ctx.fillRect(0,0,canvas.width,canvas.height);
                drawLynxSprite();
                drawOverlay("GAME OVER!", "#FF4444", "Score: "+lynx.score+"  •  SPACE to retry from Level 1");
            }

            requestAnimationFrame(drawGame);
        }

        drawGame();

        // ═══════════════════════════════════════════════════════
        //  INPUT
        // ═══════════════════════════════════════════════════════
        document.addEventListener("keydown", function(e) {
            if (e.code==="Space") {
                e.preventDefault();
                if (gameState==="start" || gameState==="gameover") {
                    resetToLevel1(); gameState="playing";
                } else if (gameState==="levelclear") {
                    startLevel2(); gameState="playing";
                } else if (gameState==="win") {
                    resetToLevel1(); gameState="start";
                } else if (gameState==="playing") {
                    if (lynx.isClimbing) {
                        lynx.isClimbing=false; lynx.climbTree=null; lynx.dy=lynx.jumpPower*0.8;
                    } else if (currentLevel===1) {
                        if (lynx.y >= lynx.ground) lynx.dy = lynx.jumpPower;
                    } else {
                        let gnd = groundAt(lynx.x+lynx.width/2)-lynx.height;
                        if (lynx.y >= gnd-2) lynx.dy = lynx.jumpPower;
                    }
                }
            }
            if (e.code==="ArrowLeft")  { e.preventDefault(); keys.ArrowLeft =true; }
            if (e.code==="ArrowRight") { e.preventDefault(); keys.ArrowRight=true; }
            if (e.code==="ArrowUp")    { e.preventDefault(); keys.ArrowUp   =true; }
            if (e.code==="ArrowDown")  { e.preventDefault(); keys.ArrowDown =true; }
        });

        document.addEventListener("keyup", function(e) {
            if (e.code==="ArrowLeft")  keys.ArrowLeft =false;
            if (e.code==="ArrowRight") keys.ArrowRight=false;
            if (e.code==="ArrowUp")    keys.ArrowUp   =false;
            if (e.code==="ArrowDown")  keys.ArrowDown =false;
        });
    </script>
</body>
</html>