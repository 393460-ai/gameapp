<?php
session_start();
include 'functions.php';
$playerName = isset($_SESSION['playerName']) ? $_SESSION['playerName'] : 'Unknown';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Playing - High Speed Lynx</title>
    <style>
        body { background-color:#FFF2CC; color:#333; font-family:'Arial',sans-serif; text-align:center; margin-top:20px; }
        h1   { font-size:3em; color:#FF8C00; text-shadow:2px 2px #FFDAB9; letter-spacing:2px; margin-bottom:10px; }
        canvas {
            width:1000px; height:500px;
            box-shadow:0 5px 15px rgba(0,0,0,0.2);
            border:5px solid #FF8C00; border-radius:10px; image-rendering:pixelated;
        }
        .back-btn {
            background-color:#FF8C00; color:white; border:none; padding:12px 25px;
            font-size:18px; font-weight:bold; font-family:'Arial',sans-serif;
            text-decoration:none; cursor:pointer; display:inline-block;
            transition:0.3s; margin-top:20px; border-radius:8px;
        }
        .back-btn:hover { background-color:#E67E22; transform:scale(1.05); }
    </style>
</head>
<body>
<h1>High Speed Lynx</h1>
<canvas id="gameCanvas" width="800" height="400"></canvas>
<br>
<a href="index.php" class="back-btn">RETURN TO MENU</a>

<script>
// ═══════════════════════════════════════════════════════════════
//  CONSTANTS & CANVAS
// ═══════════════════════════════════════════════════════════════
const canvas = document.getElementById("gameCanvas");
const ctx    = canvas.getContext("2d");
const W = canvas.width, H = canvas.height;

const PLAYER_NAME = <?php echo json_encode($playerName); ?>;

// ═══════════════════════════════════════════════════════════════
//  GAME STATE
// ═══════════════════════════════════════════════════════════════
let currentLevel = 1;
// states: start | playing | levelclear | win | gameover
let gameState    = "start";
let cameraX      = 0;
let frameCount   = 0;
const gravity    = 0.6;

// Per-level score snapshots so leaderboard shows total + breakdown
let levelScores  = { 1:0, 2:0, 3:0 };

let keys = { ArrowLeft:false, ArrowRight:false, ArrowUp:false, ArrowDown:false };

let lynx = {
    x:80, y:320, width:30, height:30, color:"#FF4500",
    dy:0, jumpPower:-13, ground:350, speed:6,
    hp:3, isHit:false, isClimbing:false, climbTree:null, score:0,
};

// ═══════════════════════════════════════════════════════════════
//  SCORE SAVE  (calls save_score.php just like your existing flow)
// ═══════════════════════════════════════════════════════════════
function saveScore(score) {
    fetch('save_score.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'playerName=' + encodeURIComponent(PLAYER_NAME) + '&score=' + encodeURIComponent(score)
    });
}

// ═══════════════════════════════════════════════════════════════
//  ██  LEVEL 1 — DESERT  (6 000 px wide)
// ═══════════════════════════════════════════════════════════════
const DESERT_LEN = 6000;
let desertObjs   = [];
let desertTimer  = 0;

function spawnDesert() {
    let sx = cameraX + W + 60;
    let r  = Math.random();
    // difficulty ramps: more rats & snakes as you go further
    let progress = Math.min(lynx.x / DESERT_LEN, 1);
    if (r < 0.20)                desertObjs.push({type:'cactus', x:sx, y:lynx.ground-50, w:22, h:80,  color:'#228B22', speedX:0,           pts:null});
    else if (r < 0.20+0.25*1)   desertObjs.push({type:'rat',    x:sx, y:lynx.ground-16, w:32, h:16,  color:'#666',    speedX:-(3+progress*2)});
    else if (r < 0.20+0.25*2)   desertObjs.push({type:'snake',  x:sx, y:lynx.ground-11, w:44, h:11,  color:'#6B8E23', speedX:-(1+progress)});
    else if (r < 0.20+0.25*2+0.15) {
        // scorpion - new enemy, stays still but hard to jump
        desertObjs.push({type:'scorpion', x:sx, y:lynx.ground-14, w:28, h:14, color:'#B8860B', speedX:0});
    } else {
        desertObjs.push({type:'cloud', x:sx, y:Math.random()*90+15, w:90, h:35, color:'white', speedX:-0.6});
    }
}

function updateDrawDesert() {
    // BG
    let skyG = ctx.createLinearGradient(0,0,0,H);
    skyG.addColorStop(0,"#87CEEB"); skyG.addColorStop(1,"#FDE68A");
    ctx.fillStyle = skyG; ctx.fillRect(0,0,W,H);
    // Sand dunes (parallax)
    drawDune(0.15, "#E8C97A", 350);
    drawDune(0.30, "#D4A844", 360);
    // Ground
    ctx.fillStyle="#C8963E"; ctx.fillRect(0, lynx.ground+lynx.height, W, H-(lynx.ground+lynx.height));
    ctx.fillStyle="#DEB060"; ctx.fillRect(0, lynx.ground+lynx.height, W, 8);

    // Finish flag
    drawFlag(DESERT_LEN, lynx.ground-85, "#FFD700","LV2");

    // Progress bar
    drawProgressBar(lynx.x, DESERT_LEN);

    // Physics
    lynx.dy += gravity; lynx.y += lynx.dy;
    if (keys.ArrowLeft)  lynx.x -= lynx.speed;
    if (keys.ArrowRight) lynx.x += lynx.speed;
    if (lynx.x < 0) lynx.x = 0;
    if (lynx.y >= lynx.ground) { lynx.y = lynx.ground; lynx.dy = 0; }

    // Camera (smooth)
    let tc = lynx.x - W*0.35; cameraX += (tc-cameraX)*0.1;
    if (cameraX < 0) cameraX = 0;

    // Spawn
    desertTimer++;
    if (desertTimer % 55 === 0) spawnDesert();

    // Passive score: 1 pt per 10px travelled
    lynx.score += Math.floor(lynx.speed * 0.05);

    // Objects
    for (let i = desertObjs.length-1; i >= 0; i--) {
        let o = desertObjs[i];
        o.x += o.speedX;
        if (o.x + o.w < cameraX - 150) { desertObjs.splice(i,1); continue; }
        drawDesertObj(o);
        if (o.type!=='cloud' && !lynx.isHit) checkHit(o);
    }

    drawLynx();
    drawHUD("LEVEL 1 – DESERT", "#FF8C00");

    if (lynx.x >= DESERT_LEN - 50) {
        levelScores[1] = lynx.score;
        gameState = "levelclear";
    }
}

function drawDune(p, color, baseY) {
    let px = -cameraX * p;
    ctx.fillStyle = color; ctx.beginPath();
    ctx.moveTo(0, baseY);
    for (let x = 0; x <= W+200; x+=80) {
        ctx.quadraticCurveTo(px+x+40, baseY-30, px+x+80, baseY);
    }
    ctx.lineTo(W, H); ctx.lineTo(0, H); ctx.closePath(); ctx.fill();
}

function drawDesertObj(o) {
    let sx = o.x - cameraX;
    if (o.type==='cactus') {
        ctx.fillStyle="#2E8B57";
        ctx.fillRect(sx+8, o.y, 6, o.h);             // trunk
        ctx.fillRect(sx, o.y+20, o.w, 6);             // arm
        ctx.fillRect(sx, o.y+10, 6, 16);              // left branch
        ctx.fillRect(sx+16, o.y+26, 6, 14);           // right branch
    } else if (o.type==='rat') {
        ctx.fillStyle="#666"; ctx.fillRect(sx, o.y+4, 20, 10);
        ctx.beginPath(); ctx.arc(sx+22,o.y+8,8,0,Math.PI*2); ctx.fill();
        ctx.fillStyle="#999"; ctx.beginPath(); ctx.arc(sx+27,o.y+5,3,0,Math.PI*2); ctx.fill();
        ctx.strokeStyle="#555"; ctx.lineWidth=1.5;
        ctx.beginPath(); ctx.moveTo(sx,o.y+10); ctx.lineTo(sx-12,o.y+6); ctx.stroke();
    } else if (o.type==='snake') {
        ctx.fillStyle="#6B8E23";
        ctx.beginPath(); ctx.moveTo(sx,o.y+5);
        for (let i=0;i<o.w;i+=10) ctx.quadraticCurveTo(sx+i+5,o.y+(i%20<10?0:10),sx+i+10,o.y+5);
        ctx.lineWidth=8; ctx.strokeStyle="#6B8E23"; ctx.stroke();
        // head
        ctx.fillStyle="#556B2F"; ctx.beginPath(); ctx.ellipse(sx+o.w+4,o.y+5,7,5,0,0,Math.PI*2); ctx.fill();
        ctx.fillStyle="red"; ctx.fillRect(sx+o.w+8,o.y+3,6,2);
    } else if (o.type==='scorpion') {
        ctx.fillStyle="#B8860B";
        ctx.fillRect(sx+6, o.y+4, 16, 10); // body
        ctx.beginPath(); ctx.arc(sx+22,o.y+6,5,0,Math.PI*2); ctx.fill(); // head
        // claws
        ctx.strokeStyle="#B8860B"; ctx.lineWidth=2;
        ctx.beginPath(); ctx.moveTo(sx+6,o.y+6); ctx.lineTo(sx-4,o.y+2); ctx.lineTo(sx-8,o.y+5); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(sx+6,o.y+10); ctx.lineTo(sx-4,o.y+14); ctx.lineTo(sx-8,o.y+11); ctx.stroke();
        // tail arc
        ctx.beginPath(); ctx.moveTo(sx+6,o.y+4); ctx.quadraticCurveTo(sx,o.y-12,sx+10,o.y-14); ctx.lineWidth=2.5; ctx.stroke();
    } else if (o.type==='cloud') {
        ctx.fillStyle="rgba(255,255,255,0.85)";
        ctx.beginPath(); ctx.ellipse(sx+30,o.y+15,30,15,0,0,Math.PI*2); ctx.fill();
        ctx.beginPath(); ctx.ellipse(sx+55,o.y+18,22,12,0,0,Math.PI*2); ctx.fill();
        ctx.beginPath(); ctx.ellipse(sx+15,o.y+18,18,10,0,0,Math.PI*2); ctx.fill();
    }
}

// ═══════════════════════════════════════════════════════════════
//  ██  LEVEL 2 — MOUNTAIN  (8 000 px wide)
// ═══════════════════════════════════════════════════════════════
const MTN_LEN = 8000;
const TW=18, TH=80, TR=38, CS=3;

// Build long mountain terrain
const terrain = (function() {
    let t = [];
    let x=0, y=350;
    t.push({x:0,y:350,w:500});
    let segs = [
        [500,310,300],[800,270,250],[1050,230,350],[1400,260,200],[1600,300,300],
        [1900,350,500],[2400,290,300],[2700,250,250],[2950,280,200],[3150,350,600],
        [3750,310,250],[4000,260,300],[4300,220,400],[4700,250,250],[4950,290,300],
        [5250,350,500],[5750,300,300],[6050,260,250],[6300,230,300],[6600,260,200],
        [6800,300,300],[7100,350,900],
    ];
    for (let s of segs) t.push({x:s[0],y:s[1],w:s[2]});
    return t;
})();

function groundAt(x) {
    for (let i=terrain.length-1;i>=0;i--) {
        let t=terrain[i];
        if (x>=t.x && x<t.x+t.w) return t.y;
    }
    return 350;
}

// Build mountain objects (trees, rocks, bears) spread across 8000px
const mtnObjs = (function() {
    let arr = [];
    // Trees every ~500px
    let treeXs = [300,700,1200,1700,2200,2600,3000,3500,4100,4600,5100,5600,6000,6500,7000,7400];
    for (let x of treeXs) arr.push({type:'tree',x});
    // Rocks
    let rockData = [
        [450,35,28],[900,40,30],[1350,35,25],[1800,45,32],[2300,38,28],
        [2800,42,30],[3300,36,26],[3800,44,32],[4250,38,28],[4700,40,30],
        [5200,35,25],[5700,45,32],[6200,38,28],[6700,42,30],[7200,36,26],[7600,40,28],
    ];
    for (let r of rockData) arr.push({type:'rock',x:r[0],w:r[1],h:r[2]});
    // Bears (progressively faster)
    let bearData = [
        [850, 750,950,  1,1.5],[1400,1300,1550,-1,1.5],[1950,1850,2100,1,2],
        [2550,2450,2700,-1,2], [3200,3100,3350,1,2.5], [3800,3700,3950,-1,2.5],
        [4400,4300,4550,1,3],  [5000,4900,5150,-1,3],  [5600,5500,5750,1,3.5],
        [6200,6100,6350,-1,3.5],[6800,6700,6950,1,4],  [7400,7300,7550,-1,4],
    ];
    for (let b of bearData) arr.push({type:'bear',x:b[0],patrolMin:b[1],patrolMax:b[2],dir:b[3],speed:b[4],w:36,h:32});
    return arr;
})();

// Fix y for mountain objects
for (let o of mtnObjs) {
    let g = groundAt(o.x+(o.w||0)/2);
    if (o.type==='tree') { o.w=TW; o.h=TH; o.y=g-TH; }
    if (o.type==='rock') { o.y=g-o.h; }
    if (o.type==='bear') { o.y=g-o.h; }
}
const MTN_FLAG_X = MTN_LEN - 150;

function updateDrawMountain() {
    // Sky
    let sky=ctx.createLinearGradient(0,0,0,H);
    sky.addColorStop(0,"#4A90D9"); sky.addColorStop(1,"#A8D5F0");
    ctx.fillStyle=sky; ctx.fillRect(0,0,W,H);
    // Parallax ridges
    drawRidge(0.15,"#7BAFC8",[[0,310],[120,230],[280,260],[450,190],[650,240],[850,170],[1050,220],[1300,250],[1600,210],[1900,270],[2200,310]]);
    drawRidge(0.30,"#8EC9A8",[[0,330],[180,260],[400,230],[620,270],[880,210],[1100,265],[1380,280],[1650,245],[2000,330]]);
    // Terrain
    for (let t of terrain) {
        let sx=t.x-cameraX;
        ctx.fillStyle="#4E7A30"; ctx.fillRect(sx,t.y,t.w,14);
        ctx.fillStyle="#7A5230"; ctx.fillRect(sx,t.y+14,t.w,H-t.y);
    }
    // Snow caps on high peaks
    for (let t of terrain) {
        if (t.y < 260) {
            let sx=t.x-cameraX;
            ctx.fillStyle="rgba(255,255,255,0.7)";
            ctx.fillRect(sx,t.y,t.w,6);
        }
    }

    drawFlag(MTN_FLAG_X, groundAt(MTN_FLAG_X)-90, "#FFD700","LV3");
    drawProgressBar(lynx.x, MTN_LEN);

    // Climbing or normal
    if (lynx.isClimbing && lynx.climbTree) {
        let t=lynx.climbTree;
        if (keys.ArrowUp)   lynx.y-=CS;
        if (keys.ArrowDown) lynx.y+=CS;
        if (lynx.y < t.y-TR*2)           lynx.y=t.y-TR*2;
        if (lynx.y > t.y+t.h-lynx.height){ lynx.isClimbing=false; lynx.climbTree=null; lynx.dy=0; }
        lynx.dy=0;
    } else {
        lynx.dy+=gravity; lynx.y+=lynx.dy;
        if (keys.ArrowLeft)  lynx.x-=lynx.speed;
        if (keys.ArrowRight) lynx.x+=lynx.speed;
        lynx.x=Math.max(0,Math.min(MTN_LEN-lynx.width,lynx.x));
        let gnd=groundAt(lynx.x+lynx.width/2)-lynx.height;
        if (lynx.y>=gnd){ lynx.y=gnd; lynx.dy=0; }
        if (lynx.y>H+60){ lynx.hp=0; gameState="gameover"; }
        if (keys.ArrowUp) {
            for (let o of mtnObjs) {
                if (o.type!=='tree') continue;
                let d=Math.abs(lynx.x+lynx.width/2-(o.x+TW/2));
                if (d<25 && lynx.y+lynx.height>o.y && lynx.y<o.y+o.h) {
                    lynx.isClimbing=true; lynx.climbTree=o; lynx.dy=0; break;
                }
            }
        }
    }

    // Camera
    let tc=lynx.x-W*0.35; cameraX+=(tc-cameraX)*0.1;
    cameraX=Math.max(0,Math.min(MTN_LEN-W,cameraX));

    lynx.score += Math.floor(lynx.speed * 0.05);

    for (let o of mtnObjs) {
        if (o.x-cameraX>W+120||o.x+o.w-cameraX<-120) continue;
        if      (o.type==='tree') drawTree(o);
        else if (o.type==='rock') drawRock(o);
        else if (o.type==='bear') {
            o.x+=o.speed*o.dir;
            o.y=groundAt(o.x+o.w/2)-o.h;
            if (o.x>o.patrolMax) o.dir=-1;
            if (o.x<o.patrolMin) o.dir= 1;
            drawBear(o);
        }
        if (!lynx.isHit && o.type!=='tree') checkHit(o);
    }

    if (lynx.isClimbing) {
        ctx.fillStyle="rgba(0,0,0,0.5)"; ctx.fillRect(W/2-100,8,200,26);
        ctx.fillStyle="#FFD700"; ctx.font="bold 14px Arial"; ctx.textAlign="center";
        ctx.fillText("🌲 CLIMBING – ↑↓ move, SPACE leap off", W/2, 26); ctx.textAlign="left";
    }

    drawLynx();
    drawHUD("LEVEL 2 – MOUNTAIN","#7ECEF4");

    if (lynx.x >= MTN_FLAG_X-50) {
        levelScores[2] = lynx.score - levelScores[1];
        gameState="levelclear";
    }
}

function drawRidge(p,color,pts) {
    ctx.fillStyle=color; ctx.beginPath();
    ctx.moveTo(-cameraX*p+pts[0][0],pts[0][1]);
    for (let pt of pts) ctx.lineTo(-cameraX*p+pt[0],pt[1]);
    ctx.lineTo(-cameraX*p+pts[pts.length-1][0],H);
    ctx.lineTo(-cameraX*p+pts[0][0],H);
    ctx.closePath(); ctx.fill();
}

function drawTree(o) {
    let sx=o.x-cameraX,sy=o.y;
    ctx.fillStyle="#5C3A10"; ctx.fillRect(sx,sy,TW,TH);
    ctx.fillStyle="#1A5C1A"; ctx.beginPath(); ctx.arc(sx+TW/2,sy-12,TR,0,Math.PI*2); ctx.fill();
    ctx.fillStyle="#228B22"; ctx.beginPath(); ctx.arc(sx+TW/2,sy-30,TR*.75,0,Math.PI*2); ctx.fill();
    ctx.fillStyle="#32CD32"; ctx.beginPath(); ctx.arc(sx+TW/2,sy-46,TR*.5,0,Math.PI*2); ctx.fill();
    if (!lynx.isClimbing && Math.abs(lynx.x+lynx.width/2-(o.x+TW/2))<42) {
        ctx.fillStyle="rgba(255,255,255,0.9)"; ctx.font="bold 11px Arial";
        ctx.fillText("↑ CLIMB",sx-6,sy-62);
    }
}

function drawRock(o) {
    let sx=o.x-cameraX;
    ctx.fillStyle="rgba(0,0,0,0.12)";
    ctx.beginPath(); ctx.ellipse(sx+o.w/2,o.y+o.h+4,o.w/2+5,6,0,0,Math.PI*2); ctx.fill();
    ctx.fillStyle="#7A7A7A"; ctx.beginPath();
    ctx.moveTo(sx+9,o.y); ctx.lineTo(sx+o.w-6,o.y+5);
    ctx.lineTo(sx+o.w,o.y+o.h); ctx.lineTo(sx,o.y+o.h); ctx.lineTo(sx+3,o.y+9);
    ctx.closePath(); ctx.fill();
    ctx.fillStyle="#AAA"; ctx.beginPath(); ctx.ellipse(sx+11,o.y+9,8,5,-0.3,0,Math.PI*2); ctx.fill();
}

function drawBear(o) {
    let sx=o.x-cameraX,sy=o.y;
    ctx.save();
    if(o.dir<0){ctx.translate(sx+o.w,sy);ctx.scale(-1,1);ctx.translate(-o.w,0);}
    else ctx.translate(sx,sy);
    ctx.fillStyle="#5C3A1E"; ctx.fillRect(4,6,28,20);
    ctx.fillStyle="#6B4226"; ctx.beginPath(); ctx.arc(28,10,12,0,Math.PI*2); ctx.fill();
    ctx.fillStyle="#5C3A1E";
    ctx.beginPath();ctx.arc(22,2,5,0,Math.PI*2);ctx.fill();
    ctx.beginPath();ctx.arc(34,2,5,0,Math.PI*2);ctx.fill();
    ctx.fillStyle="white";  ctx.beginPath();ctx.arc(32,9,3,0,Math.PI*2);ctx.fill();
    ctx.fillStyle="#222";   ctx.beginPath();ctx.arc(33,9,1.5,0,Math.PI*2);ctx.fill();
    ctx.fillStyle="#A0624A";ctx.beginPath();ctx.ellipse(36,13,5,3.5,0,0,Math.PI*2);ctx.fill();
    ctx.fillStyle="#5C3A1E";
    let ls=Math.sin(frameCount*.18)*4;
    ctx.fillRect(6,24+ls,8,9); ctx.fillRect(18,24-ls,8,9);
    ctx.restore();
}

// ═══════════════════════════════════════════════════════════════
//  ██  LEVEL 3 — NEON CITY  (7 000 px wide)
// ═══════════════════════════════════════════════════════════════
const CITY_LEN = 7000;

// City platforms (rooftops / ground)
const cityTerrain = (function() {
    let arr = [];
    // Ground runs the full length
    arr.push({x:0, y:350, w:CITY_LEN, isGround:true});
    // Rooftop platforms scattered throughout
    let roofs = [
        [300,280,120],[500,240,100],[700,300,80],[950,260,110],[1150,220,90],
        [1400,290,130],[1600,250,100],[1850,270,120],[2050,230,90],[2300,260,110],
        [2500,210,100],[2750,280,130],[2950,240,90],[3200,300,110],[3450,260,120],
        [3700,220,100],[3950,270,130],[4200,240,90],[4450,290,120],[4650,250,100],
        [4900,210,110],[5150,270,130],[5400,230,90],[5650,280,120],[5900,250,100],
        [6150,300,110],[6400,260,130],[6650,220,90],
    ];
    for (let r of roofs) arr.push({x:r[0],y:r[1],w:r[2],isRoof:true});
    return arr;
})();

function cityGroundAt(x) {
    // Check rooftop platforms first
    for (let i=cityTerrain.length-1;i>=0;i--) {
        let t=cityTerrain[i];
        if (!t.isRoof) continue;
        if (x>=t.x && x<t.x+t.w) return t.y;
    }
    return 350;
}

// Neon city enemies
const cityObjs = (function() {
    let arr=[];
    // Drones (fly left-right at mid height)
    let droneData=[
        [400,200,300,450,  1,2],[800,200,700,900,  -1,2.5],[1300,180,1150,1450,1,2],
        [1700,190,1600,1850,-1,2.5],[2100,170,2000,2250,1,3],[2600,185,2500,2700,-1,3],
        [3100,175,3000,3200,1,3.5],[3600,180,3500,3750,-1,3.5],[4100,170,4000,4250,1,4],
        [4600,185,4500,4700,-1,4],[5100,175,5000,5250,1,4],[5600,180,5500,5750,-1,4],
        [6100,170,6000,6250,1,4.5],[6600,185,6500,6700,-1,4.5],
    ];
    for(let d of droneData) arr.push({type:'drone',x:d[0],y:d[1],patrolMin:d[2],patrolMax:d[3],dir:d[4],speed:d[5],w:36,h:18});
    // Security bots (ground patrol)
    let botData=[
        [600,500,700,1,2],[1100,1000,1200,-1,2.5],[1600,1500,1700,1,2.5],
        [2200,2100,2300,-1,3],[2800,2700,2900,1,3],[3400,3300,3500,-1,3.5],
        [4000,3900,4100,1,3.5],[4600,4500,4700,-1,4],[5200,5100,5300,1,4],
        [5800,5700,5900,-1,4],[6400,6300,6500,1,4.5],
    ];
    for(let b of botData) arr.push({type:'bot',x:b[0],patrolMin:b[1],patrolMax:b[2],dir:b[3],speed:b[4],w:28,h:36,y:314});
    // Barriers (static, low obstacles)
    let barrierXs=[450,950,1450,1950,2450,2950,3450,3950,4450,4950,5450,5950,6450];
    for(let bx of barrierXs) arr.push({type:'barrier',x:bx,y:330,w:20,h:20});
    return arr;
})();

let cityNeonPulse = 0; // for animated neon effects

function updateDrawCity() {
    cityNeonPulse = (cityNeonPulse + 0.04) % (Math.PI*2);
    let nAlpha = 0.55 + Math.sin(cityNeonPulse)*0.45;

    // Night sky
    let sky=ctx.createLinearGradient(0,0,0,H);
    sky.addColorStop(0,"#050518"); sky.addColorStop(0.6,"#0D0D2B"); sky.addColorStop(1,"#1A0A2E");
    ctx.fillStyle=sky; ctx.fillRect(0,0,W,H);

    // Stars
    ctx.fillStyle="white";
    let starSeed=42;
    for(let i=0;i<60;i++){
        let sx=((starSeed*37+i*173)%W);
        let sy=((starSeed*19+i*97)%120)+5;
        let br=0.4+Math.sin(cityNeonPulse*2+i)*0.6;
        ctx.globalAlpha=br; ctx.fillRect(sx,sy,1.5,1.5);
    }
    ctx.globalAlpha=1;

    // Far city silhouette (parallax)
    drawCitySilhouette(0.2, "rgba(20,10,50,0.9)", 80);
    drawCitySilhouette(0.45,"rgba(15,5,40,0.95)", 60);

    // Ground
    ctx.fillStyle="#1A1A2E"; ctx.fillRect(0,350,W,H-350);
    // Ground neon lines
    ctx.strokeStyle=`rgba(0,255,200,${nAlpha*0.5})`; ctx.lineWidth=2;
    for(let i=0;i<4;i++){
        ctx.beginPath(); ctx.moveTo(0,355+i*3); ctx.lineTo(W,355+i*3); ctx.stroke();
    }

    // Rooftop platforms
    for(let t of cityTerrain) {
        if(!t.isRoof) continue;
        let sx=t.x-cameraX;
        if(sx>W+100||sx+t.w<-100) continue;
        ctx.fillStyle="#2A1A4A";
        ctx.fillRect(sx,t.y,t.w,12);
        // Neon edge
        ctx.strokeStyle=`rgba(255,0,255,${nAlpha})`; ctx.lineWidth=2;
        ctx.beginPath(); ctx.moveTo(sx,t.y); ctx.lineTo(sx+t.w,t.y); ctx.stroke();
    }

    // Finish flag (neon style)
    drawNeonFlag(CITY_LEN-150, 260, nAlpha);
    drawProgressBar(lynx.x, CITY_LEN);

    // Normal physics (city is flat ground + platforms)
    lynx.dy+=gravity; lynx.y+=lynx.dy;
    if(keys.ArrowLeft)  lynx.x-=lynx.speed;
    if(keys.ArrowRight) lynx.x+=lynx.speed;
    lynx.x=Math.max(0,Math.min(CITY_LEN-lynx.width,lynx.x));

    // Land on platforms
    let onPlatform=false;
    for(let t of cityTerrain){
        if(!t.isRoof) continue;
        if(lynx.x+lynx.width>t.x && lynx.x<t.x+t.w){
            let top=t.y-lynx.height;
            if(lynx.y+lynx.height>=t.y && lynx.y+lynx.height<=t.y+20 && lynx.dy>=0){
                lynx.y=top; lynx.dy=0; onPlatform=true;
            }
        }
    }
    if(!onPlatform){
        if(lynx.y>=350-lynx.height){ lynx.y=350-lynx.height; lynx.dy=0; }
    }
    if(lynx.y>H+60){ lynx.hp=0; gameState="gameover"; }

    // Camera
    let tc=lynx.x-W*0.35; cameraX+=(tc-cameraX)*0.1;
    cameraX=Math.max(0,Math.min(CITY_LEN-W,cameraX));

    lynx.score+=Math.floor(lynx.speed*0.06);

    // Objects
    for(let o of cityObjs){
        let ex=o.x-(o.type==='drone'?0:0);
        if(ex-cameraX>W+120||ex+o.w-cameraX<-120) continue;
        if(o.type==='drone'){
            o.x+=o.speed*o.dir;
            if(o.x>o.patrolMax) o.dir=-1;
            if(o.x<o.patrolMin) o.dir= 1;
            drawDrone(o,nAlpha);
        } else if(o.type==='bot'){
            o.x+=o.speed*o.dir;
            if(o.x>o.patrolMax) o.dir=-1;
            if(o.x<o.patrolMin) o.dir= 1;
            drawBot(o,nAlpha);
        } else if(o.type==='barrier'){
            drawBarrier(o,nAlpha);
        }
        if(!lynx.isHit) checkHit(o);
    }

    drawLynx();
    drawHUD("LEVEL 3 – NEON CITY","#FF00FF");

    if(lynx.x>=CITY_LEN-100){
        levelScores[3]=lynx.score-levelScores[1]-levelScores[2];
        gameState="win";
        saveScore(lynx.score);
    }
}

function drawCitySilhouette(p, color, minH) {
    let px=-cameraX*p;
    ctx.fillStyle=color; ctx.beginPath(); ctx.moveTo(0,H);
    let bldW=60;
    for(let i=0;i<Math.ceil(W/bldW)+20;i++){
        let bx=px+i*bldW;
        let bh=minH+(((i*37)%80));
        ctx.lineTo(bx,H-bh);
        ctx.lineTo(bx+bldW-4,H-bh);
    }
    ctx.lineTo(W,H); ctx.closePath(); ctx.fill();
}

function drawDrone(o,na) {
    let sx=o.x-cameraX,sy=o.y;
    // Glow
    ctx.shadowColor="#00FFFF"; ctx.shadowBlur=12;
    ctx.fillStyle=`rgba(0,200,220,${na})`;
    ctx.fillRect(sx,sy+6,o.w,8);
    ctx.fillRect(sx+14,sy,8,o.h);
    // Rotors
    ctx.fillStyle=`rgba(0,255,255,${na})`;
    ctx.fillRect(sx-6,sy,10,4); ctx.fillRect(sx+o.w-4,sy,10,4);
    // Eye
    ctx.fillStyle="#FF0055"; ctx.beginPath(); ctx.arc(sx+o.w/2,sy+10,4,0,Math.PI*2); ctx.fill();
    ctx.shadowBlur=0;
}

function drawBot(o,na) {
    let sx=o.x-cameraX,sy=o.y;
    ctx.shadowColor="#FF00FF"; ctx.shadowBlur=10;
    // Body
    ctx.fillStyle="#2A2A4A"; ctx.fillRect(sx,sy,o.w,o.h);
    ctx.strokeStyle=`rgba(255,0,255,${na})`; ctx.lineWidth=2;
    ctx.strokeRect(sx,sy,o.w,o.h);
    // Visor
    ctx.fillStyle=`rgba(0,255,200,${na})`; ctx.fillRect(sx+4,sy+6,20,8);
    // Legs (animated)
    let ls=Math.sin(frameCount*.2)*3;
    ctx.fillStyle="#1A1A3A"; ctx.fillRect(sx+3,sy+o.h,8,8+ls); ctx.fillRect(sx+17,sy+o.h,8,8-ls);
    ctx.shadowBlur=0;
}

function drawBarrier(o,na) {
    let sx=o.x-cameraX;
    ctx.shadowColor="#FFFF00"; ctx.shadowBlur=8;
    ctx.fillStyle="#1A1A00";
    ctx.fillRect(sx,o.y,o.w,o.h);
    // Yellow hazard stripes
    for(let i=0;i<3;i++){
        ctx.fillStyle=(i%2===0)?`rgba(255,220,0,${na})`:"#333";
        ctx.fillRect(sx,o.y+i*(o.h/3),o.w,o.h/3);
    }
    ctx.shadowBlur=0;
}

function drawNeonFlag(wx,wy,na) {
    let sx=wx-cameraX;
    if(sx<-60||sx>W+60) return;
    ctx.shadowColor="#FF00FF"; ctx.shadowBlur=15;
    ctx.strokeStyle=`rgba(255,0,255,${na})`; ctx.lineWidth=3;
    ctx.beginPath(); ctx.moveTo(sx,wy+80); ctx.lineTo(sx,wy); ctx.stroke();
    ctx.fillStyle=`rgba(255,0,255,${na})`;
    ctx.beginPath(); ctx.moveTo(sx,wy); ctx.lineTo(sx+30,wy+14); ctx.lineTo(sx,wy+28); ctx.closePath(); ctx.fill();
    ctx.fillStyle="white"; ctx.font="bold 11px Arial"; ctx.fillText("WIN!",sx+4,wy+18);
    ctx.shadowBlur=0;
}

// ═══════════════════════════════════════════════════════════════
//  SHARED HELPERS
// ═══════════════════════════════════════════════════════════════
function checkHit(o) {
    if(lynx.isHit) return;
    if(lynx.x<o.x+o.w && lynx.x+lynx.width>o.x &&
       lynx.y<o.y+o.h && lynx.y+lynx.height>o.y){
        lynx.hp--; lynx.isHit=true;
        lynx.dy=-8; lynx.x+=(lynx.x<o.x+o.w/2)?-22:22;
        setTimeout(()=>{ lynx.isHit=false; },1200);
        if(lynx.hp<=0) gameState="gameover";
    }
}

function drawLynx() {
    let sx=lynx.x-cameraX, sy=lynx.y;
    let hit=lynx.isHit&&frameCount%10<5;

    // Neon glow in city
    if(currentLevel===3){
        ctx.shadowColor="#FF4500"; ctx.shadowBlur=10;
    }
    ctx.fillStyle=hit?"red":lynx.color; ctx.fillRect(sx+4,sy+10,22,18);
    ctx.fillStyle=hit?"red":"#E05000";  ctx.fillRect(sx+14,sy,16,16);
    ctx.fillStyle="#FF6A00";
    ctx.beginPath();ctx.moveTo(sx+15,sy);ctx.lineTo(sx+12,sy-8);ctx.lineTo(sx+19,sy-2);ctx.closePath();ctx.fill();
    ctx.beginPath();ctx.moveTo(sx+25,sy);ctx.lineTo(sx+29,sy-8);ctx.lineTo(sx+22,sy-2);ctx.closePath();ctx.fill();
    ctx.fillStyle="white"; ctx.fillRect(sx+17,sy+4,5,5);
    ctx.fillStyle="#222";  ctx.fillRect(sx+19,sy+5,3,3);
    ctx.beginPath();ctx.moveTo(sx+4,sy+15);ctx.quadraticCurveTo(sx-8,sy+5,sx-2,sy-2);
    ctx.lineWidth=5; ctx.strokeStyle="#FF4500"; ctx.stroke();
    ctx.fillStyle="#E05000";
    let run=Math.sin(frameCount*.2)*4;
    ctx.fillRect(sx+6,sy+26+run,7,10); ctx.fillRect(sx+17,sy+26-run,7,10);
    if(lynx.isClimbing){
        ctx.fillStyle="#FF8C00";
        ctx.fillRect(sx-4,sy+8,8,6); ctx.fillRect(sx+26,sy+20,8,6);
    }
    ctx.shadowBlur=0;
}

function drawFlag(wx,wy,color,label) {
    let sx=wx-cameraX;
    if(sx<-60||sx>W+60) return;
    ctx.strokeStyle="#555"; ctx.lineWidth=4;
    ctx.beginPath();ctx.moveTo(sx,wy+80);ctx.lineTo(sx,wy);ctx.stroke();
    ctx.fillStyle=color;
    ctx.beginPath();ctx.moveTo(sx,wy);ctx.lineTo(sx+30,wy+14);ctx.lineTo(sx,wy+28);ctx.closePath();ctx.fill();
    ctx.fillStyle="white"; ctx.font="bold 11px Arial"; ctx.fillText(label,sx+4,wy+18);
}

function drawProgressBar(pos, total) {
    let pct = Math.min(pos/total,1);
    ctx.fillStyle="rgba(0,0,0,0.4)"; ctx.fillRect(W/2-150,8,300,10);
    ctx.fillStyle="#FFD700"; ctx.fillRect(W/2-150,8,300*pct,10);
    // Lynx icon on bar
    ctx.fillStyle="#FF4500"; ctx.fillRect(W/2-150+300*pct-4,6,8,14);
}

function drawHUD(levelLabel, accentColor) {
    // HP
    ctx.fillStyle="rgba(0,0,0,0.5)"; ctx.fillRect(10,368,170,28);
    ctx.fillStyle="white"; ctx.font="bold 16px Arial";
    ctx.fillText("HP: "+"❤️".repeat(Math.max(0,lynx.hp)),16,387);
    // Score
    ctx.fillStyle="rgba(0,0,0,0.5)"; ctx.fillRect(W-165,368,155,28);
    ctx.fillStyle="white"; ctx.font="bold 16px Arial";
    ctx.fillText("Score: "+lynx.score,W-159,387);
    // Level badge
    let bw=ctx.measureText(levelLabel).width+24;
    ctx.fillStyle="rgba(0,0,0,0.5)"; ctx.fillRect(W/2-bw/2,22,bw,22);
    ctx.fillStyle=accentColor; ctx.font="bold 13px Arial"; ctx.textAlign="center";
    ctx.fillText(levelLabel,W/2,38); ctx.textAlign="left";
    // Per-level score breakdown (small, bottom left corner)
    ctx.fillStyle="rgba(0,0,0,0.4)"; ctx.fillRect(10,338,160,28);
    ctx.fillStyle="#FFD700"; ctx.font="12px Arial";
    ctx.fillText("L1:"+levelScores[1]+" L2:"+levelScores[2]+" L3:"+levelScores[3],15,355);
}

function drawOverlay(title,tColor,line2,line3) {
    ctx.fillStyle="rgba(0,0,0,0.72)"; ctx.fillRect(100,110,600,180);
    ctx.textAlign="center";
    ctx.fillStyle=tColor; ctx.font="bold 44px Arial"; ctx.fillText(title,W/2,170);
    ctx.fillStyle="white"; ctx.font="20px Arial";     ctx.fillText(line2, W/2,210);
    if(line3){ ctx.fillStyle="#FFD700"; ctx.font="16px Arial"; ctx.fillText(line3,W/2,245); }
    ctx.textAlign="left";
}

// ═══════════════════════════════════════════════════════════════
//  RESET HELPERS
// ═══════════════════════════════════════════════════════════════
function resetAll() {
    currentLevel=1; cameraX=0; frameCount=0;
    lynx.x=80; lynx.y=320; lynx.dy=0; lynx.hp=3; lynx.score=0;
    lynx.isHit=false; lynx.isClimbing=false; lynx.climbTree=null; lynx.ground=350;
    desertObjs=[]; desertTimer=0;
    levelScores={1:0,2:0,3:0};
}

function goLevel2() {
    currentLevel=2; cameraX=0; frameCount=0;
    lynx.x=80; lynx.y=320; lynx.dy=0;
    lynx.isHit=false; lynx.isClimbing=false; lynx.climbTree=null; lynx.ground=350;
    for(let o of mtnObjs){
        if(o.type==='bear'){o.x=o.patrolMin; o.y=groundAt(o.patrolMin)-o.h;}
    }
}

function goLevel3() {
    currentLevel=3; cameraX=0; frameCount=0;
    lynx.x=80; lynx.y=320; lynx.dy=0;
    lynx.isHit=false; lynx.isClimbing=false; lynx.climbTree=null; lynx.ground=350;
    for(let o of cityObjs){
        if(o.type==='bot'||o.type==='drone') o.x=o.patrolMin;
    }
}

// ═══════════════════════════════════════════════════════════════
//  MAIN LOOP
// ═══════════════════════════════════════════════════════════════
function drawGame() {
    ctx.clearRect(0,0,W,H);
    frameCount++;

    if(gameState==="start") {
        ctx.fillStyle="#87CEEB"; ctx.fillRect(0,0,W,H);
        ctx.fillStyle="#C8963E"; ctx.fillRect(0,355,W,H-355);
        drawLynx();
        ctx.fillStyle="rgba(0,0,0,0.6)"; ctx.fillRect(110,130,580,140);
        ctx.textAlign="center";
        ctx.fillStyle="#FF8C00"; ctx.font="bold 34px Arial";
        ctx.fillText("🌵 LEVEL 1: DESERT",W/2,178);
        ctx.fillStyle="white"; ctx.font="18px Arial";
        ctx.fillText("Dodge cacti, rats, snakes & scorpions",W/2,212);
        ctx.fillStyle="#FFD700"; ctx.font="17px Arial";
        ctx.fillText("→ Arrow keys to move  •  SPACE to jump / start",W/2,248);
        ctx.textAlign="left";

    } else if(gameState==="playing") {
        if     (currentLevel===1) updateDrawDesert();
        else if(currentLevel===2) updateDrawMountain();
        else                      updateDrawCity();

    } else if(gameState==="levelclear") {
        if(currentLevel===1){
            ctx.fillStyle="#87CEEB"; ctx.fillRect(0,0,W,H);
            ctx.fillStyle="#C8963E"; ctx.fillRect(0,355,W,H-355);
        } else {
            let sky=ctx.createLinearGradient(0,0,0,H);
            sky.addColorStop(0,"#4A90D9"); sky.addColorStop(1,"#A8D5F0");
            ctx.fillStyle=sky; ctx.fillRect(0,0,W,H);
        }
        drawLynx();
        let nextName = currentLevel===1 ? "⛰  Mountain Level 2" : "🌆  Neon City Level 3";
        drawOverlay(
            "✅  LEVEL "+currentLevel+" CLEAR!",
            "#00EE55",
            "Score so far: "+lynx.score+"  •  HP: "+"❤️".repeat(lynx.hp)+" carried over",
            "SPACE  →  Enter "+nextName
        );

    } else if(gameState==="win") {
        let sky=ctx.createLinearGradient(0,0,0,H);
        sky.addColorStop(0,"#050518"); sky.addColorStop(1,"#1A0A2E");
        ctx.fillStyle=sky; ctx.fillRect(0,0,W,H);
        drawLynx();
        drawOverlay(
            "🏆  YOU WIN!",
            "#FFD700",
            "L1: "+levelScores[1]+"  +  L2: "+levelScores[2]+"  +  L3: "+levelScores[3]+"  =  "+lynx.score,
            "Score saved to leaderboard!  •  SPACE to play again"
        );

    } else if(gameState==="gameover") {
        ctx.fillStyle=currentLevel===3?"#050518":"#87CEEB";
        ctx.fillRect(0,0,W,H);
        drawLynx();
        drawOverlay(
            "GAME OVER!",
            "#FF3333",
            "Score: "+lynx.score+"  (L1:"+levelScores[1]+" L2:"+levelScores[2]+" L3:"+levelScores[3]+")",
            "SPACE to retry from Level 1"
        );
    }

    requestAnimationFrame(drawGame);
}

drawGame();

// ═══════════════════════════════════════════════════════════════
//  INPUT
// ═══════════════════════════════════════════════════════════════
document.addEventListener("keydown", function(e) {
    if(e.code==="Space") {
        e.preventDefault();
        if(gameState==="start"||gameState==="gameover"){
            resetAll(); gameState="playing";
        } else if(gameState==="levelclear"){
            if(currentLevel===1){ goLevel2(); gameState="playing"; }
            else                 { goLevel3(); gameState="playing"; }
        } else if(gameState==="win"){
            resetAll(); gameState="start";
        } else if(gameState==="playing"){
            if(lynx.isClimbing){
                lynx.isClimbing=false; lynx.climbTree=null; lynx.dy=lynx.jumpPower*0.85;
            } else if(currentLevel===1){
                if(lynx.y>=lynx.ground) lynx.dy=lynx.jumpPower;
            } else if(currentLevel===2){
                let gnd=groundAt(lynx.x+lynx.width/2)-lynx.height;
                if(lynx.y>=gnd-2) lynx.dy=lynx.jumpPower;
            } else {
                // City: can jump from ground or platforms
                let onFloor=(lynx.y>=318);
                let onRoof=cityTerrain.some(t=>t.isRoof&&lynx.x+lynx.width>t.x&&lynx.x<t.x+t.w&&Math.abs(lynx.y-(t.y-lynx.height))<3);
                if(onFloor||onRoof) lynx.dy=lynx.jumpPower;
            }
        }
    }
    if(e.code==="ArrowLeft") { e.preventDefault(); keys.ArrowLeft =true; }
    if(e.code==="ArrowRight"){ e.preventDefault(); keys.ArrowRight=true; }
    if(e.code==="ArrowUp")   { e.preventDefault(); keys.ArrowUp   =true; }
    if(e.code==="ArrowDown") { e.preventDefault(); keys.ArrowDown =true; }
});

document.addEventListener("keyup", function(e) {
    if(e.code==="ArrowLeft")  keys.ArrowLeft =false;
    if(e.code==="ArrowRight") keys.ArrowRight=false;
    if(e.code==="ArrowUp")    keys.ArrowUp   =false;
    if(e.code==="ArrowDown")  keys.ArrowDown =false;
});
</script>
</body>
</html>