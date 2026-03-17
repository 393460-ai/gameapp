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
// states: start | playing | bossfight | levelclear | win | gameover
let gameState    = "start";
let cameraX      = 0;
let frameCount   = 0;
const gravity    = 0.6;
let levelScores  = { 1:0, 2:0, 3:0 };

let keys = { ArrowLeft:false, ArrowRight:false, ArrowUp:false, ArrowDown:false, KeyZ:false };

// ─── LYNX ───────────────────────────────────────────────────
let lynx = {
    x:80, y:320, width:30, height:30, color:"#FF4500",
    dy:0, jumpPower:-13, ground:350, speed:6,
    hp:3, maxHp:5, isHit:false, isClimbing:false, climbTree:null, score:0,
    coins:0,
    // Dash ability
    dashUnlocked:false, dashActive:false, dashCooldown:0, dashTimer:0,
    dashSpeed:18, dashDuration:12,
    // Screen shake
    shakeX:0, shakeY:0,
};

// ─── COINS ARRAY (shared across all levels) ─────────────────
let coins = [];       // { x, y, type:'gold'|'chip'|'core', collected:false, bobOffset }
let particles = [];   // { x, y, vx, vy, life, color }

// ─── ABILITY UNLOCK DISPLAY ─────────────────────────────────
let abilityFlash = 0; // frames to show "DASH UNLOCKED!" banner

// ═══════════════════════════════════════════════════════════════
//  HELPERS
// ═══════════════════════════════════════════════════════════════
function saveScore(score) {
    fetch('save_score.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'playerName='+encodeURIComponent(PLAYER_NAME)+'&score='+encodeURIComponent(score)
    });
}

function spawnParticles(x, y, color, count) {
    for (let i = 0; i < count; i++) {
        particles.push({
            x, y,
            vx: (Math.random()-0.5)*5,
            vy: (Math.random()-0.5)*5 - 2,
            life: 30 + Math.random()*20,
            maxLife: 50,
            color
        });
    }
}

function updateParticles() {
    for (let i = particles.length-1; i >= 0; i--) {
        let p = particles[i];
        p.x += p.vx; p.y += p.vy; p.vy += 0.15;
        p.life--;
        if (p.life <= 0) { particles.splice(i,1); continue; }
        let alpha = p.life / p.maxLife;
        ctx.globalAlpha = alpha;
        ctx.fillStyle = p.color;
        ctx.fillRect(p.x - cameraX, p.y, 4, 4);
        ctx.globalAlpha = 1;
    }
}

function triggerShake(amount) {
    lynx.shakeX = (Math.random()-0.5)*amount;
    lynx.shakeY = (Math.random()-0.5)*amount;
}

function checkCoinCollect() {
    for (let c of coins) {
        if (c.collected) continue;
        if (lynx.x < c.x+14 && lynx.x+lynx.width > c.x &&
            lynx.y < c.y+14 && lynx.y+lynx.height > c.y) {
            c.collected = true;
            if (c.type === 'gold')  { lynx.score += 10;  spawnParticles(c.x,c.y,"#FFD700",8); }
            if (c.type === 'chip')  { lynx.score += 50;  spawnParticles(c.x,c.y,"#00BFFF",12); }
            if (c.type === 'core')  {
                if (lynx.hp < lynx.maxHp) lynx.hp++;
                spawnParticles(c.x,c.y,"#FF4444",12);
            }
            lynx.coins++;
            // Unlock dash at 10 coins
            if (!lynx.dashUnlocked && lynx.coins >= 10) {
                lynx.dashUnlocked = true;
                abilityFlash = 180; // 3 seconds
            }
        }
    }
}

function drawCoins() {
    for (let c of coins) {
        if (c.collected) continue;
        let sx = c.x - cameraX;
        if (sx < -20 || sx > W+20) continue;
        let bob = Math.sin(frameCount*0.08 + c.bobOffset)*4;
        let cy = c.y + bob;
        if (c.type === 'gold') {
            ctx.shadowColor = "#FFD700"; ctx.shadowBlur = 8;
            ctx.fillStyle = "#FFD700";
            ctx.beginPath(); ctx.arc(sx+7, cy+7, 7, 0, Math.PI*2); ctx.fill();
            ctx.fillStyle = "#FFA500";
            ctx.beginPath(); ctx.arc(sx+5, cy+5, 3, 0, Math.PI*2); ctx.fill();
        } else if (c.type === 'chip') {
            ctx.shadowColor = "#00BFFF"; ctx.shadowBlur = 10;
            ctx.fillStyle = "#00BFFF";
            ctx.fillRect(sx+1, cy+1, 12, 12);
            ctx.fillStyle = "#005080";
            ctx.fillRect(sx+4, cy+4, 6, 6);
        } else if (c.type === 'core') {
            ctx.shadowColor = "#FF4444"; ctx.shadowBlur = 12;
            ctx.fillStyle = "#FF4444";
            ctx.beginPath(); ctx.arc(sx+7, cy+7, 7, 0, Math.PI*2); ctx.fill();
            ctx.fillStyle = "#FF8888";
            ctx.beginPath(); ctx.arc(sx+5, cy+5, 3, 0, Math.PI*2); ctx.fill();
        }
        ctx.shadowBlur = 0;
    }
}

// Scatter coins across a level section
function placeLevelCoins(startX, endX, groundFn, density) {
    coins = [];
    let spacing = Math.floor((endX - startX) / density);
    for (let i = 0; i < density; i++) {
        let x = startX + i*spacing + Math.random()*spacing*0.5;
        let g = (groundFn ? groundFn(x) : 350);
        let r = Math.random();
        let type = r < 0.65 ? 'gold' : r < 0.88 ? 'chip' : 'core';
        // Place some on ground, some elevated
        let elev = (Math.random() < 0.4) ? 60 + Math.random()*80 : 20;
        coins.push({ x, y: g - elev, type, collected:false, bobOffset: Math.random()*Math.PI*2 });
    }
}

function drawAbilityHUD() {
    // Coin counter
    ctx.fillStyle="rgba(0,0,0,0.5)"; ctx.fillRect(W/2-55, H-32, 110, 24);
    ctx.fillStyle="#FFD700"; ctx.font="bold 13px Arial"; ctx.textAlign="center";
    ctx.fillText("🪙 "+lynx.coins+(lynx.dashUnlocked?"":" / 10 for DASH"), W/2, H-15);
    ctx.textAlign="left";

    // Dash ability icon
    if (lynx.dashUnlocked) {
        let coolPct = lynx.dashCooldown > 0 ? lynx.dashCooldown/60 : 0;
        ctx.fillStyle = coolPct>0 ? "rgba(0,0,0,0.5)" : "rgba(255,140,0,0.8)";
        ctx.fillRect(W-62, H-60, 50, 50);
        ctx.strokeStyle = lynx.dashActive ? "#FFFFFF" : "#FF8C00";
        ctx.lineWidth=2; ctx.strokeRect(W-62, H-60, 50, 50);
        // Cooldown overlay
        if (coolPct > 0) {
            ctx.fillStyle="rgba(0,0,0,0.6)";
            ctx.fillRect(W-62, H-60, 50, 50*coolPct);
        }
        ctx.fillStyle="white"; ctx.font="bold 11px Arial"; ctx.textAlign="center";
        ctx.fillText("DASH",W-37,H-38);
        ctx.fillText("[Z]",W-37,H-22);
        ctx.textAlign="left";
    }

    // Flash banner on unlock
    if (abilityFlash > 0) {
        let alpha = Math.min(1, abilityFlash/30);
        ctx.fillStyle=`rgba(255,140,0,${alpha*0.85})`;
        ctx.fillRect(W/2-160, H/2-30, 320, 50);
        ctx.fillStyle="white"; ctx.font="bold 22px Arial"; ctx.textAlign="center";
        ctx.fillText("⚡ DASH UNLOCKED! Press Z to dash!", W/2, H/2+2);
        ctx.textAlign="left";
        abilityFlash--;
    }
}

function handleDash() {
    if (!lynx.dashUnlocked) return;
    if (lynx.dashCooldown > 0) { lynx.dashCooldown--; return; }
    if (lynx.dashActive) {
        lynx.dashTimer--;
        let dir = keys.ArrowLeft ? -1 : 1;
        lynx.x += lynx.dashSpeed * dir;
        spawnParticles(lynx.x, lynx.y+15, "#FF8C00", 2);
        if (lynx.dashTimer <= 0) { lynx.dashActive=false; lynx.dashCooldown=60; }
    }
    if (keys.KeyZ && !lynx.dashActive && lynx.dashCooldown===0) {
        lynx.dashActive=true; lynx.dashTimer=lynx.dashDuration;
    }
}

// ═══════════════════════════════════════════════════════════════
//  CLOUDS  (universal, styled per level)
// ═══════════════════════════════════════════════════════════════
let cloudObjs = [];

function initClouds(style) {
    cloudObjs = [];
    let count = 18;
    for (let i = 0; i < count; i++) {
        cloudObjs.push({
            x: Math.random() * 8000,
            y: 20 + Math.random() * 120,
            w: 60 + Math.random() * 80,
            h: 25 + Math.random() * 20,
            speed: 0.3 + Math.random() * 0.4,
            style
        });
    }
}

function drawClouds(levelLen) {
    for (let c of cloudObjs) {
        c.x -= c.speed;
        if (c.x + c.w < cameraX - 200) c.x += levelLen + 400;
        let sx = c.x - cameraX;
        if (sx > W + 200 || sx + c.w < -200) continue;

        if (c.style === 'desert') {
            ctx.fillStyle="rgba(255,255,255,0.78)";
            ctx.beginPath(); ctx.ellipse(sx+c.w/2, c.y+c.h/2, c.w/2, c.h/2, 0,0,Math.PI*2); ctx.fill();
            ctx.beginPath(); ctx.ellipse(sx+c.w*0.3, c.y+c.h*0.6, c.w*0.3, c.h*0.4, 0,0,Math.PI*2); ctx.fill();
            ctx.beginPath(); ctx.ellipse(sx+c.w*0.72, c.y+c.h*0.6, c.w*0.28, c.h*0.38,0,0,Math.PI*2); ctx.fill();
        } else if (c.style === 'mountain') {
            // Wispy mist
            let g = ctx.createRadialGradient(sx+c.w/2, c.y+c.h/2, 0, sx+c.w/2, c.y+c.h/2, c.w/2);
            g.addColorStop(0,"rgba(220,235,255,0.55)");
            g.addColorStop(1,"rgba(220,235,255,0)");
            ctx.fillStyle=g;
            ctx.beginPath(); ctx.ellipse(sx+c.w/2,c.y+c.h/2,c.w/2,c.h/2,0,0,Math.PI*2); ctx.fill();
        } else if (c.style === 'city') {
            // Dark smog with neon tint
            let pulse = 0.3 + Math.sin(frameCount*0.03+c.x)*0.2;
            ctx.fillStyle=`rgba(30,10,50,${pulse})`;
            ctx.beginPath(); ctx.ellipse(sx+c.w/2,c.y+c.h/2,c.w/2,c.h/2,0,0,Math.PI*2); ctx.fill();
            ctx.fillStyle=`rgba(180,0,255,${pulse*0.4})`;
            ctx.beginPath(); ctx.ellipse(sx+c.w/2,c.y+c.h/2+4,c.w/2,c.h/3,0,0,Math.PI*2); ctx.fill();
        }
    }
}

// ═══════════════════════════════════════════════════════════════
//  ██  LEVEL 1 — DESERT  (10 000 px wide + Sand Golem boss)
// ═══════════════════════════════════════════════════════════════
const DESERT_LEN    = 10000;
const DESERT_BOSS_X = 9200; // boss arena starts here
let desertObjs   = [];
let desertTimer  = 0;

// ─── SAND GOLEM BOSS ────────────────────────────────────────
let boss = {
    active:false, defeated:false,
    x: DESERT_BOSS_X + 300, y: 280, w:70, h:70,
    hp:8, maxHp:8,
    phase:1,         // phase 1: walk+throw; phase 2 (hp<=4): faster+more boulders
    dir:1, speed:1.8,
    patrolMin: DESERT_BOSS_X+80, patrolMax: DESERT_BOSS_X+700,
    throwTimer:0, throwInterval:90,
    isHit:false, hitFlash:0,
    boulders:[],     // { x,y,vx,vy }
    shakeActive:false,
};

function activateBoss() {
    if (!boss.active && !boss.defeated && lynx.x > DESERT_BOSS_X - 100) {
        boss.active = true;
        // Remove normal desert objects in boss arena
        desertObjs = desertObjs.filter(o => o.x < DESERT_BOSS_X);
    }
}

function spawnBoulder() {
    let bx = boss.x + boss.w/2;
    let by = boss.y + 10;
    // Aim roughly at lynx
    let dx = (lynx.x + lynx.width/2) - bx;
    let dy = (lynx.y + lynx.height/2) - by;
    let dist = Math.sqrt(dx*dx+dy*dy) || 1;
    let spd = boss.phase===1 ? 5 : 7;
    boss.boulders.push({ x:bx, y:by, vx:(dx/dist)*spd, vy:(dy/dist)*spd - 2 });
}

function updateBoss() {
    if (!boss.active || boss.defeated) return;

    // Phase switch
    if (boss.hp <= 4) boss.phase = 2;

    // Walk patrol
    boss.x += boss.speed * boss.dir * (boss.phase===2?1.5:1);
    boss.y = 350 - boss.h; // stay on ground
    if (boss.x > boss.patrolMax) boss.dir=-1;
    if (boss.x < boss.patrolMin) boss.dir= 1;

    // Throw boulders
    boss.throwTimer++;
    let interval = boss.phase===1 ? boss.throwInterval : 55;
    if (boss.throwTimer >= interval) {
        boss.throwTimer=0;
        spawnBoulder();
        if (boss.phase===2) setTimeout(spawnBoulder,200);
    }

    // Hit flash
    if (boss.hitFlash > 0) boss.hitFlash--;

    // Update boulders
    for (let i = boss.boulders.length-1; i>=0; i--) {
        let b = boss.boulders[i];
        b.x += b.vx; b.y += b.vy; b.vy += 0.3;
        // Boulder hits ground
        if (b.y > 345) { boss.boulders.splice(i,1); continue; }
        // Boulder hits lynx
        if (!lynx.isHit &&
            lynx.x < b.x+14 && lynx.x+lynx.width > b.x &&
            lynx.y < b.y+14 && lynx.y+lynx.height > b.y) {
            lynx.hp--; lynx.isHit=true; triggerShake(8);
            setTimeout(()=>{ lynx.isHit=false; },1200);
            if (lynx.hp<=0) gameState="gameover";
            boss.boulders.splice(i,1); continue;
        }
    }

    // Lynx hits boss body
    if (!lynx.isHit &&
        lynx.x < boss.x+boss.w && lynx.x+lynx.width > boss.x &&
        lynx.y < boss.y+boss.h && lynx.y+lynx.height > boss.y) {
        lynx.hp--; lynx.isHit=true; triggerShake(10);
        lynx.dy=-9; lynx.x += lynx.x<boss.x+boss.w/2 ? -25:25;
        setTimeout(()=>{ lynx.isHit=false; },1200);
        if (lynx.hp<=0) gameState="gameover";
    }

    // Player jumps on top of boss = damage
    if (lynx.dy > 0 &&
        lynx.x+lynx.width > boss.x+8 && lynx.x < boss.x+boss.w-8 &&
        lynx.y+lynx.height >= boss.y && lynx.y+lynx.height <= boss.y+18) {
        if (!boss.isHit) {
            boss.hp--; boss.isHit=true; boss.hitFlash=20;
            lynx.dy=-10; triggerShake(6);
            spawnParticles(boss.x+boss.w/2, boss.y, "#C8963E", 16);
            setTimeout(()=>{ boss.isHit=false; },500);
            if (boss.hp<=0) {
                boss.defeated=true; boss.active=false;
                lynx.score += 500;
                spawnParticles(boss.x+boss.w/2, boss.y+boss.h/2, "#FFD700",30);
                spawnParticles(boss.x+boss.w/2, boss.y+boss.h/2, "#FF8C00",20);
            }
        }
    }
}

function drawBossArena() {
    if (DESERT_BOSS_X - cameraX > W || DESERT_BOSS_X + 800 - cameraX < 0) return;
    // Arena floor highlight
    let ax = DESERT_BOSS_X - cameraX;
    ctx.fillStyle="rgba(180,80,0,0.18)"; ctx.fillRect(ax,350,800,20);
    ctx.strokeStyle="#FF4500"; ctx.lineWidth=2; ctx.setLineDash([10,8]);
    ctx.beginPath(); ctx.moveTo(ax,351); ctx.lineTo(ax+800,351); ctx.stroke();
    ctx.setLineDash([]);

    // Boss HP bar
    if (boss.active) {
        let bw=200, bh=18;
        let bx=W/2-bw/2, by=50;
        ctx.fillStyle="rgba(0,0,0,0.6)"; ctx.fillRect(bx-4,by-4,bw+8,bh+8);
        ctx.fillStyle="#333"; ctx.fillRect(bx,by,bw,bh);
        ctx.fillStyle = boss.phase===2 ? "#FF2200" : "#FF8C00";
        ctx.fillRect(bx,by,bw*(boss.hp/boss.maxHp),bh);
        ctx.strokeStyle="#FFD700"; ctx.lineWidth=2; ctx.strokeRect(bx,by,bw,bh);
        ctx.fillStyle="white"; ctx.font="bold 12px Arial"; ctx.textAlign="center";
        ctx.fillText("🏜️ SAND GOLEM  "+boss.hp+"/"+boss.maxHp, W/2, by+13);
        ctx.textAlign="left";
    }
}

function drawSandGolem() {
    if (!boss.active && !(!boss.defeated)) return;
    if (boss.defeated) return;
    let sx=boss.x-cameraX, sy=boss.y;
    let flash = boss.hitFlash>0 && frameCount%4<2;

    ctx.save();
    // Body
    ctx.fillStyle = flash ? "#FFFFFF" : "#C8963E";
    ctx.fillRect(sx+5,sy+20,60,50);
    // Head
    ctx.fillStyle = flash ? "#FFFFFF" : "#DEB060";
    ctx.fillRect(sx+10,sy,50,30);
    // Eyes (glowing red in phase 2)
    ctx.fillStyle = boss.phase===2 ? "#FF0000" : "#FF6600";
    ctx.beginPath(); ctx.arc(sx+28,sy+12,6,0,Math.PI*2); ctx.fill();
    ctx.beginPath(); ctx.arc(sx+48,sy+12,6,0,Math.PI*2); ctx.fill();
    ctx.fillStyle="#222";
    ctx.beginPath(); ctx.arc(sx+29,sy+12,3,0,Math.PI*2); ctx.fill();
    ctx.beginPath(); ctx.arc(sx+49,sy+12,3,0,Math.PI*2); ctx.fill();
    // Mouth (snarl)
    ctx.strokeStyle="#333"; ctx.lineWidth=2;
    ctx.beginPath(); ctx.moveTo(sx+22,sy+22); ctx.lineTo(sx+54,sy+22); ctx.stroke();
    // Arms
    ctx.fillStyle = flash ? "#FFFFFF" : "#C8963E";
    let armSwing = Math.sin(frameCount*0.12)*8;
    ctx.fillRect(sx-12,sy+20+armSwing,16,35);
    ctx.fillRect(sx+boss.w-4,sy+20-armSwing,16,35);
    // Legs
    let legSwing = Math.sin(frameCount*0.15)*5;
    ctx.fillRect(sx+10,sy+65+legSwing,20,16);
    ctx.fillRect(sx+36,sy+65-legSwing,20,16);
    // Cracks in phase 2
    if (boss.phase===2) {
        ctx.strokeStyle="rgba(0,0,0,0.4)"; ctx.lineWidth=1.5;
        ctx.beginPath(); ctx.moveTo(sx+20,sy+25); ctx.lineTo(sx+30,sy+45); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(sx+45,sy+30); ctx.lineTo(sx+38,sy+55); ctx.stroke();
    }
    ctx.restore();

    // Boulders
    for (let b of boss.boulders) {
        let bsx = b.x - cameraX;
        ctx.fillStyle="#A0824A"; ctx.shadowColor="#6B5A30"; ctx.shadowBlur=4;
        ctx.beginPath(); ctx.arc(bsx+7,b.y+7,7,0,Math.PI*2); ctx.fill();
        ctx.fillStyle="#C8A870";
        ctx.beginPath(); ctx.arc(bsx+5,b.y+5,3,0,Math.PI*2); ctx.fill();
        ctx.shadowBlur=0;
    }
}

// ─── DESERT SPAWN & UPDATE ──────────────────────────────────
function spawnDesert() {
    if (boss.active) return;
    let sx = cameraX + W + 60;
    if (sx > DESERT_BOSS_X - 200) return; // stop spawning near boss
    let r = Math.random();
    let progress = Math.min(lynx.x / DESERT_LEN, 1);
    if      (r < 0.18) desertObjs.push({type:'cactus',  x:sx, y:lynx.ground-54, w:22, h:84,  color:'#228B22', speedX:0});
    else if (r < 0.38) desertObjs.push({type:'rat',     x:sx, y:lynx.ground-16, w:32, h:16,  color:'#666',    speedX:-(3+progress*2.5)});
    else if (r < 0.55) desertObjs.push({type:'snake',   x:sx, y:lynx.ground-11, w:44, h:11,  color:'#6B8E23', speedX:-(1.2+progress)});
    else if (r < 0.70) desertObjs.push({type:'scorpion',x:sx, y:lynx.ground-14, w:28, h:14,  color:'#B8860B', speedX:0});
    else               desertObjs.push({type:'tumbleweed',x:sx,y:lynx.ground-18,w:20, h:20,  color:'#8B7355', speedX:-(2+Math.random()*2)});
}

function updateDrawDesert() {
    // Screen shake
    ctx.save();
    ctx.translate(lynx.shakeX, lynx.shakeY);
    lynx.shakeX *= 0.8; lynx.shakeY *= 0.8;

    // BG
    let skyG = ctx.createLinearGradient(0,0,0,H);
    skyG.addColorStop(0,"#87CEEB"); skyG.addColorStop(1,"#FDE68A");
    ctx.fillStyle=skyG; ctx.fillRect(0,0,W,H);

    // Sun
    ctx.shadowColor="#FFD700"; ctx.shadowBlur=30;
    ctx.fillStyle="#FFE44D";
    ctx.beginPath(); ctx.arc(W-80-cameraX*0.02, 55, 32, 0, Math.PI*2); ctx.fill();
    ctx.shadowBlur=0;

    // Clouds
    drawClouds(DESERT_LEN);

    // Dunes (parallax)
    drawDune(0.12,"#E8C97A",355); drawDune(0.28,"#D4A844",362);

    // Ground
    ctx.fillStyle="#C8963E"; ctx.fillRect(0,lynx.ground+lynx.height,W,H-(lynx.ground+lynx.height));
    ctx.fillStyle="#DEB060"; ctx.fillRect(0,lynx.ground+lynx.height,W,8);

    // Mid-level warning sign
    if (Math.abs(DESERT_BOSS_X - 400 - cameraX) < W) {
        let sx = DESERT_BOSS_X - 400 - cameraX;
        ctx.fillStyle="#FF4500"; ctx.font="bold 14px Arial";
        ctx.fillText("⚠ BOSS ARENA AHEAD ⚠", sx, lynx.ground-10);
    }

    // Finish flag
    drawFlag(DESERT_LEN-80, lynx.ground-85, "#FFD700","LV2");
    drawProgressBar(lynx.x, DESERT_LEN);

    // Physics
    lynx.dy += gravity; lynx.y += lynx.dy;
    if (!lynx.dashActive) {
        if (keys.ArrowLeft)  lynx.x -= lynx.speed;
        if (keys.ArrowRight) lynx.x += lynx.speed;
    }
    handleDash();
    if (lynx.x < 0) lynx.x = 0;
    if (lynx.y >= lynx.ground) { lynx.y = lynx.ground; lynx.dy = 0; }

    // Camera
    let tc = lynx.x - W*0.35; cameraX += (tc-cameraX)*0.1;
    if (cameraX < 0) cameraX = 0;

    // Spawn enemies
    desertTimer++;
    if (desertTimer % 52 === 0) spawnDesert();

    // Passive score
    lynx.score += Math.floor(lynx.speed*0.04);

    // Desert objects
    for (let i = desertObjs.length-1; i>=0; i--) {
        let o = desertObjs[i];
        o.x += o.speedX;
        if (o.x + o.w < cameraX-150) { desertObjs.splice(i,1); continue; }
        drawDesertObj(o);
        if (o.type!=='cloud' && !lynx.isHit && !lynx.dashActive) checkHit(o);
    }

    // Coins
    checkCoinCollect(); drawCoins(); updateParticles();

    // Boss
    activateBoss();
    updateBoss();
    drawBossArena();
    drawSandGolem();

    drawLynx();
    drawHUD("LEVEL 1 – DESERT","#FF8C00");
    drawAbilityHUD();

    ctx.restore(); // end shake

    // Reach flag (only after boss defeated)
    if (lynx.x >= DESERT_LEN-100) {
        if (!boss.defeated && boss.hp > 0) {
            // Don't advance until boss is beaten (flag is past boss arena)
        } else {
            levelScores[1]=lynx.score; gameState="levelclear";
        }
    }
}

function drawDune(p,color,baseY) {
    let px=-cameraX*p;
    ctx.fillStyle=color; ctx.beginPath(); ctx.moveTo(0,baseY);
    for (let x=0;x<=W+200;x+=80) ctx.quadraticCurveTo(px+x+40,baseY-28,px+x+80,baseY);
    ctx.lineTo(W,H); ctx.lineTo(0,H); ctx.closePath(); ctx.fill();
}

function drawDesertObj(o) {
    let sx=o.x-cameraX;
    if (sx<-60||sx>W+60) return;
    if (o.type==='cactus') {
        ctx.fillStyle="#2E8B57";
        ctx.fillRect(sx+8,o.y,6,o.h);
        ctx.fillRect(sx,o.y+22,o.w,6);
        ctx.fillRect(sx,o.y+10,6,18);
        ctx.fillRect(sx+16,o.y+28,6,16);
    } else if (o.type==='rat') {
        ctx.fillStyle="#666"; ctx.fillRect(sx,o.y+4,20,10);
        ctx.beginPath(); ctx.arc(sx+22,o.y+8,8,0,Math.PI*2); ctx.fill();
        ctx.fillStyle="#999"; ctx.beginPath(); ctx.arc(sx+27,o.y+5,3,0,Math.PI*2); ctx.fill();
        ctx.strokeStyle="#555"; ctx.lineWidth=1.5;
        ctx.beginPath(); ctx.moveTo(sx,o.y+10); ctx.lineTo(sx-12,o.y+6); ctx.stroke();
    } else if (o.type==='snake') {
        ctx.strokeStyle="#6B8E23"; ctx.lineWidth=8;
        ctx.beginPath(); ctx.moveTo(sx,o.y+5);
        for (let i=0;i<o.w;i+=10) ctx.quadraticCurveTo(sx+i+5,o.y+(i%20<10?0:10),sx+i+10,o.y+5);
        ctx.stroke();
        ctx.fillStyle="#556B2F"; ctx.beginPath(); ctx.ellipse(sx+o.w+4,o.y+5,7,5,0,0,Math.PI*2); ctx.fill();
        ctx.fillStyle="red"; ctx.fillRect(sx+o.w+8,o.y+3,6,2);
    } else if (o.type==='scorpion') {
        ctx.fillStyle="#B8860B";
        ctx.fillRect(sx+6,o.y+4,16,10);
        ctx.beginPath(); ctx.arc(sx+22,o.y+6,5,0,Math.PI*2); ctx.fill();
        ctx.strokeStyle="#B8860B"; ctx.lineWidth=2;
        ctx.beginPath(); ctx.moveTo(sx+6,o.y+6); ctx.lineTo(sx-4,o.y+2); ctx.lineTo(sx-8,o.y+5); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(sx+6,o.y+10); ctx.lineTo(sx-4,o.y+14); ctx.lineTo(sx-8,o.y+11); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(sx+6,o.y+4); ctx.quadraticCurveTo(sx,o.y-12,sx+10,o.y-14); ctx.lineWidth=2.5; ctx.stroke();
    } else if (o.type==='tumbleweed') {
        let bounce = Math.abs(Math.sin(frameCount*0.18))*8;
        ctx.strokeStyle="#8B7355"; ctx.lineWidth=2;
        ctx.beginPath(); ctx.arc(sx+10,o.y+10-bounce,10,0,Math.PI*2); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(sx,o.y+10-bounce); ctx.lineTo(sx+20,o.y+10-bounce); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(sx+10,o.y-bounce); ctx.lineTo(sx+10,o.y+20-bounce); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(sx+3,o.y+3-bounce); ctx.lineTo(sx+17,o.y+17-bounce); ctx.stroke();
    }
}

// ═══════════════════════════════════════════════════════════════
//  ██  LEVEL 2 — MOUNTAIN  (8 000 px wide)
// ═══════════════════════════════════════════════════════════════
const MTN_LEN=8000;
const TW=18,TH=80,TR=38,CS=3;

const terrain=(function(){
    let t=[];
    t.push({x:0,y:350,w:500});
    let segs=[
        [500,310,300],[800,270,250],[1050,230,350],[1400,260,200],[1600,300,300],
        [1900,350,500],[2400,290,300],[2700,250,250],[2950,280,200],[3150,350,600],
        [3750,310,250],[4000,260,300],[4300,220,400],[4700,250,250],[4950,290,300],
        [5250,350,500],[5750,300,300],[6050,260,250],[6300,230,300],[6600,260,200],
        [6800,300,300],[7100,350,900],
    ];
    for(let s of segs) t.push({x:s[0],y:s[1],w:s[2]});
    return t;
})();

function groundAt(x){
    for(let i=terrain.length-1;i>=0;i--){
        let t=terrain[i];
        if(x>=t.x&&x<t.x+t.w) return t.y;
    }
    return 350;
}

const mtnObjs=(function(){
    let arr=[];
    let treeXs=[300,700,1200,1700,2200,2600,3000,3500,4100,4600,5100,5600,6000,6500,7000,7400];
    for(let x of treeXs) arr.push({type:'tree',x});
    let rockData=[
        [450,35,28],[900,40,30],[1350,35,25],[1800,45,32],[2300,38,28],
        [2800,42,30],[3300,36,26],[3800,44,32],[4250,38,28],[4700,40,30],
        [5200,35,25],[5700,45,32],[6200,38,28],[6700,42,30],[7200,36,26],[7600,40,28],
    ];
    for(let r of rockData) arr.push({type:'rock',x:r[0],w:r[1],h:r[2]});
    let bearData=[
        [850,750,950,1,1.5],[1400,1300,1550,-1,1.5],[1950,1850,2100,1,2],
        [2550,2450,2700,-1,2],[3200,3100,3350,1,2.5],[3800,3700,3950,-1,2.5],
        [4400,4300,4550,1,3],[5000,4900,5150,-1,3],[5600,5500,5750,1,3.5],
        [6200,6100,6350,-1,3.5],[6800,6700,6950,1,4],[7400,7300,7550,-1,4],
    ];
    for(let b of bearData) arr.push({type:'bear',x:b[0],patrolMin:b[1],patrolMax:b[2],dir:b[3],speed:b[4],w:36,h:32});
    return arr;
})();

for(let o of mtnObjs){
    let g=groundAt(o.x+(o.w||0)/2);
    if(o.type==='tree'){o.w=TW;o.h=TH;o.y=g-TH;}
    if(o.type==='rock'){o.y=g-o.h;}
    if(o.type==='bear'){o.y=g-o.h;}
}
const MTN_FLAG_X=MTN_LEN-150;

function updateDrawMountain(){
    ctx.save();
    ctx.translate(lynx.shakeX,lynx.shakeY);
    lynx.shakeX*=0.8; lynx.shakeY*=0.8;

    let sky=ctx.createLinearGradient(0,0,0,H);
    sky.addColorStop(0,"#4A90D9"); sky.addColorStop(1,"#A8D5F0");
    ctx.fillStyle=sky; ctx.fillRect(0,0,W,H);

    drawClouds(MTN_LEN);
    drawRidge(0.15,"#7BAFC8",[[0,310],[120,230],[280,260],[450,190],[650,240],[850,170],[1050,220],[1300,250],[1600,210],[1900,270],[2200,310]]);
    drawRidge(0.30,"#8EC9A8",[[0,330],[180,260],[400,230],[620,270],[880,210],[1100,265],[1380,280],[1650,245],[2000,330]]);

    for(let t of terrain){
        let sx=t.x-cameraX;
        ctx.fillStyle="#4E7A30"; ctx.fillRect(sx,t.y,t.w,14);
        ctx.fillStyle="#7A5230"; ctx.fillRect(sx,t.y+14,t.w,H-t.y);
    }
    for(let t of terrain){
        if(t.y<260){
            let sx=t.x-cameraX;
            ctx.fillStyle="rgba(255,255,255,0.7)";
            ctx.fillRect(sx,t.y,t.w,6);
        }
    }

    drawFlag(MTN_FLAG_X,groundAt(MTN_FLAG_X)-90,"#FFD700","LV3");
    drawProgressBar(lynx.x,MTN_LEN);

    if(lynx.isClimbing&&lynx.climbTree){
        let t=lynx.climbTree;
        if(keys.ArrowUp)   lynx.y-=CS;
        if(keys.ArrowDown) lynx.y+=CS;
        if(lynx.y<t.y-TR*2)            lynx.y=t.y-TR*2;
        if(lynx.y>t.y+t.h-lynx.height){lynx.isClimbing=false;lynx.climbTree=null;lynx.dy=0;}
        lynx.dy=0;
    } else {
        lynx.dy+=gravity; lynx.y+=lynx.dy;
        if(!lynx.dashActive){
            if(keys.ArrowLeft)  lynx.x-=lynx.speed;
            if(keys.ArrowRight) lynx.x+=lynx.speed;
        }
        handleDash();
        lynx.x=Math.max(0,Math.min(MTN_LEN-lynx.width,lynx.x));
        let gnd=groundAt(lynx.x+lynx.width/2)-lynx.height;
        if(lynx.y>=gnd){lynx.y=gnd;lynx.dy=0;}
        if(lynx.y>H+60){lynx.hp=0;gameState="gameover";}
        if(keys.ArrowUp&&!lynx.dashActive){
            for(let o of mtnObjs){
                if(o.type!=='tree') continue;
                let d=Math.abs(lynx.x+lynx.width/2-(o.x+TW/2));
                if(d<25&&lynx.y+lynx.height>o.y&&lynx.y<o.y+o.h){
                    lynx.isClimbing=true;lynx.climbTree=o;lynx.dy=0;break;
                }
            }
        }
    }

    let tc=lynx.x-W*0.35; cameraX+=(tc-cameraX)*0.1;
    cameraX=Math.max(0,Math.min(MTN_LEN-W,cameraX));
    lynx.score+=Math.floor(lynx.speed*0.05);

    for(let o of mtnObjs){
        if(o.x-cameraX>W+120||o.x+o.w-cameraX<-120) continue;
        if     (o.type==='tree') drawTree(o);
        else if(o.type==='rock') drawRock(o);
        else if(o.type==='bear'){
            o.x+=o.speed*o.dir;
            o.y=groundAt(o.x+o.w/2)-o.h;
            if(o.x>o.patrolMax) o.dir=-1;
            if(o.x<o.patrolMin) o.dir= 1;
            drawBear(o);
        }
        if(!lynx.isHit&&o.type!=='tree'&&!lynx.dashActive) checkHit(o);
    }

    if(lynx.isClimbing){
        ctx.fillStyle="rgba(0,0,0,0.5)"; ctx.fillRect(W/2-100,8,200,26);
        ctx.fillStyle="#FFD700"; ctx.font="bold 14px Arial"; ctx.textAlign="center";
        ctx.fillText("🌲 CLIMBING – ↑↓ move, SPACE leap",W/2,26); ctx.textAlign="left";
    }

    checkCoinCollect(); drawCoins(); updateParticles();
    drawLynx();
    drawHUD("LEVEL 2 – MOUNTAIN","#7ECEF4");
    drawAbilityHUD();
    ctx.restore();

    if(lynx.x>=MTN_FLAG_X-50){
        levelScores[2]=lynx.score-levelScores[1];
        gameState="levelclear";
    }
}

function drawRidge(p,color,pts){
    ctx.fillStyle=color; ctx.beginPath();
    ctx.moveTo(-cameraX*p+pts[0][0],pts[0][1]);
    for(let pt of pts) ctx.lineTo(-cameraX*p+pt[0],pt[1]);
    ctx.lineTo(-cameraX*p+pts[pts.length-1][0],H);
    ctx.lineTo(-cameraX*p+pts[0][0],H);
    ctx.closePath(); ctx.fill();
}

function drawTree(o){
    let sx=o.x-cameraX,sy=o.y;
    ctx.fillStyle="#5C3A10"; ctx.fillRect(sx,sy,TW,TH);
    ctx.fillStyle="#1A5C1A"; ctx.beginPath(); ctx.arc(sx+TW/2,sy-12,TR,0,Math.PI*2); ctx.fill();
    ctx.fillStyle="#228B22"; ctx.beginPath(); ctx.arc(sx+TW/2,sy-30,TR*.75,0,Math.PI*2); ctx.fill();
    ctx.fillStyle="#32CD32"; ctx.beginPath(); ctx.arc(sx+TW/2,sy-46,TR*.5,0,Math.PI*2); ctx.fill();
    if(!lynx.isClimbing&&Math.abs(lynx.x+lynx.width/2-(o.x+TW/2))<42){
        ctx.fillStyle="rgba(255,255,255,0.9)"; ctx.font="bold 11px Arial";
        ctx.fillText("↑ CLIMB",sx-6,sy-62);
    }
}

function drawRock(o){
    let sx=o.x-cameraX;
    ctx.fillStyle="rgba(0,0,0,0.12)";
    ctx.beginPath(); ctx.ellipse(sx+o.w/2,o.y+o.h+4,o.w/2+5,6,0,0,Math.PI*2); ctx.fill();
    ctx.fillStyle="#7A7A7A"; ctx.beginPath();
    ctx.moveTo(sx+9,o.y); ctx.lineTo(sx+o.w-6,o.y+5);
    ctx.lineTo(sx+o.w,o.y+o.h); ctx.lineTo(sx,o.y+o.h); ctx.lineTo(sx+3,o.y+9);
    ctx.closePath(); ctx.fill();
    ctx.fillStyle="#AAA"; ctx.beginPath(); ctx.ellipse(sx+11,o.y+9,8,5,-0.3,0,Math.PI*2); ctx.fill();
}

function drawBear(o){
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
const CITY_LEN=7000;

const cityTerrain=(function(){
    let arr=[];
    arr.push({x:0,y:350,w:CITY_LEN,isGround:true});
    let roofs=[
        [300,280,120],[500,240,100],[700,300,80],[950,260,110],[1150,220,90],
        [1400,290,130],[1600,250,100],[1850,270,120],[2050,230,90],[2300,260,110],
        [2500,210,100],[2750,280,130],[2950,240,90],[3200,300,110],[3450,260,120],
        [3700,220,100],[3950,270,130],[4200,240,90],[4450,290,120],[4650,250,100],
        [4900,210,110],[5150,270,130],[5400,230,90],[5650,280,120],[5900,250,100],
        [6150,300,110],[6400,260,130],[6650,220,90],
    ];
    for(let r of roofs) arr.push({x:r[0],y:r[1],w:r[2],isRoof:true});
    return arr;
})();

const cityObjs=(function(){
    let arr=[];
    let droneData=[
        [400,200,300,450,1,2],[800,200,700,900,-1,2.5],[1300,180,1150,1450,1,2],
        [1700,190,1600,1850,-1,2.5],[2100,170,2000,2250,1,3],[2600,185,2500,2700,-1,3],
        [3100,175,3000,3200,1,3.5],[3600,180,3500,3750,-1,3.5],[4100,170,4000,4250,1,4],
        [4600,185,4500,4700,-1,4],[5100,175,5000,5250,1,4],[5600,180,5500,5750,-1,4],
        [6100,170,6000,6250,1,4.5],[6600,185,6500,6700,-1,4.5],
    ];
    for(let d of droneData) arr.push({type:'drone',x:d[0],y:d[1],patrolMin:d[2],patrolMax:d[3],dir:d[4],speed:d[5],w:36,h:18});
    let botData=[
        [600,500,700,1,2],[1100,1000,1200,-1,2.5],[1600,1500,1700,1,2.5],
        [2200,2100,2300,-1,3],[2800,2700,2900,1,3],[3400,3300,3500,-1,3.5],
        [4000,3900,4100,1,3.5],[4600,4500,4700,-1,4],[5200,5100,5300,1,4],
        [5800,5700,5900,-1,4],[6400,6300,6500,1,4.5],
    ];
    for(let b of botData) arr.push({type:'bot',x:b[0],patrolMin:b[1],patrolMax:b[2],dir:b[3],speed:b[4],w:28,h:36,y:314});
    let barrierXs=[450,950,1450,1950,2450,2950,3450,3950,4450,4950,5450,5950,6450];
    for(let bx of barrierXs) arr.push({type:'barrier',x:bx,y:330,w:20,h:20});
    return arr;
})();

let cityNeonPulse=0;

function updateDrawCity(){
    cityNeonPulse=(cityNeonPulse+0.04)%(Math.PI*2);
    let nAlpha=0.55+Math.sin(cityNeonPulse)*0.45;

    ctx.save();
    ctx.translate(lynx.shakeX,lynx.shakeY);
    lynx.shakeX*=0.8; lynx.shakeY*=0.8;

    let sky=ctx.createLinearGradient(0,0,0,H);
    sky.addColorStop(0,"#050518"); sky.addColorStop(0.6,"#0D0D2B"); sky.addColorStop(1,"#1A0A2E");
    ctx.fillStyle=sky; ctx.fillRect(0,0,W,H);

    // Stars
    ctx.fillStyle="white";
    for(let i=0;i<60;i++){
        let sx=((42*37+i*173)%W);
        let sy=((42*19+i*97)%120)+5;
        let br=0.4+Math.sin(cityNeonPulse*2+i)*0.6;
        ctx.globalAlpha=br; ctx.fillRect(sx,sy,1.5,1.5);
    }
    ctx.globalAlpha=1;

    drawClouds(CITY_LEN);
    drawCitySilhouette(0.2,"rgba(20,10,50,0.9)",80);
    drawCitySilhouette(0.45,"rgba(15,5,40,0.95)",60);

    ctx.fillStyle="#1A1A2E"; ctx.fillRect(0,350,W,H-350);
    ctx.strokeStyle=`rgba(0,255,200,${nAlpha*0.5})`; ctx.lineWidth=2;
    for(let i=0;i<4;i++){
        ctx.beginPath(); ctx.moveTo(0,355+i*3); ctx.lineTo(W,355+i*3); ctx.stroke();
    }

    for(let t of cityTerrain){
        if(!t.isRoof) continue;
        let sx=t.x-cameraX;
        if(sx>W+100||sx+t.w<-100) continue;
        ctx.fillStyle="#2A1A4A"; ctx.fillRect(sx,t.y,t.w,12);
        ctx.strokeStyle=`rgba(255,0,255,${nAlpha})`; ctx.lineWidth=2;
        ctx.beginPath(); ctx.moveTo(sx,t.y); ctx.lineTo(sx+t.w,t.y); ctx.stroke();
    }

    drawNeonFlag(CITY_LEN-150,260,nAlpha);
    drawProgressBar(lynx.x,CITY_LEN);

    lynx.dy+=gravity; lynx.y+=lynx.dy;
    if(!lynx.dashActive){
        if(keys.ArrowLeft)  lynx.x-=lynx.speed;
        if(keys.ArrowRight) lynx.x+=lynx.speed;
    }
    handleDash();
    lynx.x=Math.max(0,Math.min(CITY_LEN-lynx.width,lynx.x));

    let onPlatform=false;
    for(let t of cityTerrain){
        if(!t.isRoof) continue;
        if(lynx.x+lynx.width>t.x&&lynx.x<t.x+t.w){
            let top=t.y-lynx.height;
            if(lynx.y+lynx.height>=t.y&&lynx.y+lynx.height<=t.y+20&&lynx.dy>=0){
                lynx.y=top; lynx.dy=0; onPlatform=true;
            }
        }
    }
    if(!onPlatform){
        if(lynx.y>=350-lynx.height){lynx.y=350-lynx.height;lynx.dy=0;}
    }
    if(lynx.y>H+60){lynx.hp=0;gameState="gameover";}

    let tc=lynx.x-W*0.35; cameraX+=(tc-cameraX)*0.1;
    cameraX=Math.max(0,Math.min(CITY_LEN-W,cameraX));
    lynx.score+=Math.floor(lynx.speed*0.06);

    for(let o of cityObjs){
        if(o.x-cameraX>W+120||o.x+o.w-cameraX<-120) continue;
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
        if(!lynx.isHit&&!lynx.dashActive) checkHit(o);
    }

    checkCoinCollect(); drawCoins(); updateParticles();
    drawLynx();
    drawHUD("LEVEL 3 – NEON CITY","#FF00FF");
    drawAbilityHUD();
    ctx.restore();

    if(lynx.x>=CITY_LEN-100){
        levelScores[3]=lynx.score-levelScores[1]-levelScores[2];
        gameState="win";
        saveScore(lynx.score);
    }
}

function drawCitySilhouette(p,color,minH){
    let px=-cameraX*p;
    ctx.fillStyle=color; ctx.beginPath(); ctx.moveTo(0,H);
    let bldW=60;
    for(let i=0;i<Math.ceil(W/bldW)+20;i++){
        let bx=px+i*bldW;
        let bh=minH+(((i*37)%80));
        ctx.lineTo(bx,H-bh); ctx.lineTo(bx+bldW-4,H-bh);
    }
    ctx.lineTo(W,H); ctx.closePath(); ctx.fill();
}

function drawDrone(o,na){
    let sx=o.x-cameraX,sy=o.y;
    ctx.shadowColor="#00FFFF"; ctx.shadowBlur=12;
    ctx.fillStyle=`rgba(0,200,220,${na})`;
    ctx.fillRect(sx,sy+6,o.w,8); ctx.fillRect(sx+14,sy,8,o.h);
    ctx.fillStyle=`rgba(0,255,255,${na})`;
    ctx.fillRect(sx-6,sy,10,4); ctx.fillRect(sx+o.w-4,sy,10,4);
    ctx.fillStyle="#FF0055"; ctx.beginPath(); ctx.arc(sx+o.w/2,sy+10,4,0,Math.PI*2); ctx.fill();
    ctx.shadowBlur=0;
}

function drawBot(o,na){
    let sx=o.x-cameraX,sy=o.y;
    ctx.shadowColor="#FF00FF"; ctx.shadowBlur=10;
    ctx.fillStyle="#2A2A4A"; ctx.fillRect(sx,sy,o.w,o.h);
    ctx.strokeStyle=`rgba(255,0,255,${na})`; ctx.lineWidth=2; ctx.strokeRect(sx,sy,o.w,o.h);
    ctx.fillStyle=`rgba(0,255,200,${na})`; ctx.fillRect(sx+4,sy+6,20,8);
    let ls=Math.sin(frameCount*.2)*3;
    ctx.fillStyle="#1A1A3A"; ctx.fillRect(sx+3,sy+o.h,8,8+ls); ctx.fillRect(sx+17,sy+o.h,8,8-ls);
    ctx.shadowBlur=0;
}

function drawBarrier(o,na){
    let sx=o.x-cameraX;
    ctx.shadowColor="#FFFF00"; ctx.shadowBlur=8;
    ctx.fillStyle="#1A1A00"; ctx.fillRect(sx,o.y,o.w,o.h);
    for(let i=0;i<3;i++){
        ctx.fillStyle=(i%2===0)?`rgba(255,220,0,${na})`:"#333";
        ctx.fillRect(sx,o.y+i*(o.h/3),o.w,o.h/3);
    }
    ctx.shadowBlur=0;
}

function drawNeonFlag(wx,wy,na){
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
//  SHARED DRAW HELPERS
// ═══════════════════════════════════════════════════════════════
function checkHit(o){
    if(lynx.isHit) return;
    if(lynx.x<o.x+o.w&&lynx.x+lynx.width>o.x&&lynx.y<o.y+o.h&&lynx.y+lynx.height>o.y){
        lynx.hp--; lynx.isHit=true; triggerShake(7);
        lynx.dy=-8; lynx.x+=(lynx.x<o.x+o.w/2)?-22:22;
        setTimeout(()=>{lynx.isHit=false;},1200);
        if(lynx.hp<=0) gameState="gameover";
    }
}

function drawLynx(){
    let sx=lynx.x-cameraX, sy=lynx.y;
    let hit=lynx.isHit&&frameCount%10<5;
    if(currentLevel===3){ ctx.shadowColor="#FF4500"; ctx.shadowBlur=10; }
    if(lynx.dashActive){ ctx.shadowColor="#FF8C00"; ctx.shadowBlur=20; }
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
    if(lynx.isClimbing){ ctx.fillStyle="#FF8C00"; ctx.fillRect(sx-4,sy+8,8,6); ctx.fillRect(sx+26,sy+20,8,6); }
    ctx.shadowBlur=0;
}

function drawFlag(wx,wy,color,label){
    let sx=wx-cameraX;
    if(sx<-60||sx>W+60) return;
    ctx.strokeStyle="#555"; ctx.lineWidth=4;
    ctx.beginPath();ctx.moveTo(sx,wy+80);ctx.lineTo(sx,wy);ctx.stroke();
    ctx.fillStyle=color;
    ctx.beginPath();ctx.moveTo(sx,wy);ctx.lineTo(sx+30,wy+14);ctx.lineTo(sx,wy+28);ctx.closePath();ctx.fill();
    ctx.fillStyle="white"; ctx.font="bold 11px Arial"; ctx.fillText(label,sx+4,wy+18);
}

function drawProgressBar(pos,total){
    let pct=Math.min(pos/total,1);
    ctx.fillStyle="rgba(0,0,0,0.4)"; ctx.fillRect(W/2-150,8,300,10);
    ctx.fillStyle="#FFD700"; ctx.fillRect(W/2-150,8,300*pct,10);
    ctx.fillStyle="#FF4500"; ctx.fillRect(W/2-150+300*pct-4,6,8,14);
}

function drawHUD(levelLabel,accentColor){
    ctx.fillStyle="rgba(0,0,0,0.5)"; ctx.fillRect(10,H-32,175,24);
    ctx.fillStyle="white"; ctx.font="bold 15px Arial";
    ctx.fillText("HP: "+"❤️".repeat(Math.max(0,lynx.hp)),16,H-13);
    ctx.fillStyle="rgba(0,0,0,0.5)"; ctx.fillRect(W-170,H-32,160,24);
    ctx.fillStyle="white"; ctx.font="bold 15px Arial";
    ctx.fillText("Score: "+lynx.score,W-165,H-13);
    let bw=ctx.measureText(levelLabel).width+24;
    ctx.fillStyle="rgba(0,0,0,0.5)"; ctx.fillRect(W/2-bw/2,22,bw,22);
    ctx.fillStyle=accentColor; ctx.font="bold 13px Arial"; ctx.textAlign="center";
    ctx.fillText(levelLabel,W/2,38); ctx.textAlign="left";
    ctx.fillStyle="rgba(0,0,0,0.4)"; ctx.fillRect(10,H-60,165,24);
    ctx.fillStyle="#FFD700"; ctx.font="12px Arial";
    ctx.fillText("L1:"+levelScores[1]+" L2:"+levelScores[2]+" L3:"+levelScores[3],15,H-42);
}

function drawOverlay(title,tColor,line2,line3){
    ctx.fillStyle="rgba(0,0,0,0.72)"; ctx.fillRect(100,110,600,180);
    ctx.textAlign="center";
    ctx.fillStyle=tColor; ctx.font="bold 44px Arial"; ctx.fillText(title,W/2,170);
    ctx.fillStyle="white"; ctx.font="20px Arial";     ctx.fillText(line2,W/2,210);
    if(line3){ctx.fillStyle="#FFD700";ctx.font="16px Arial";ctx.fillText(line3,W/2,245);}
    ctx.textAlign="left";
}

// ═══════════════════════════════════════════════════════════════
//  RESET HELPERS
// ═══════════════════════════════════════════════════════════════
function resetAll(){
    currentLevel=1; cameraX=0; frameCount=0;
    lynx.x=80; lynx.y=320; lynx.dy=0; lynx.hp=3; lynx.score=0;
    lynx.isHit=false; lynx.isClimbing=false; lynx.climbTree=null; lynx.ground=350;
    lynx.coins=0; lynx.dashUnlocked=false; lynx.dashActive=false;
    lynx.dashCooldown=0; lynx.dashTimer=0;
    desertObjs=[]; desertTimer=0;
    levelScores={1:0,2:0,3:0};
    // Reset boss
    boss.active=false; boss.defeated=false; boss.hp=boss.maxHp;
    boss.x=DESERT_BOSS_X+300; boss.dir=1; boss.phase=1;
    boss.throwTimer=0; boss.boulders=[];
    abilityFlash=0; particles=[];
    initClouds('desert');
    placeLevelCoins(200, DESERT_BOSS_X-200, ()=>350, 55);
}

function goLevel2(){
    currentLevel=2; cameraX=0; frameCount=0;
    lynx.x=80; lynx.y=320; lynx.dy=0;
    lynx.isHit=false; lynx.isClimbing=false; lynx.climbTree=null; lynx.ground=350;
    for(let o of mtnObjs){
        if(o.type==='bear'){o.x=o.patrolMin;o.y=groundAt(o.patrolMin)-o.h;}
    }
    initClouds('mountain');
    placeLevelCoins(200, MTN_LEN-200, groundAt, 50);
    particles=[];
}

function goLevel3(){
    currentLevel=3; cameraX=0; frameCount=0;
    lynx.x=80; lynx.y=320; lynx.dy=0;
    lynx.isHit=false; lynx.isClimbing=false; lynx.climbTree=null; lynx.ground=350;
    for(let o of cityObjs){
        if(o.type==='bot'||o.type==='drone') o.x=o.patrolMin;
    }
    initClouds('city');
    placeLevelCoins(200, CITY_LEN-200, ()=>310, 45);
    particles=[];
}

// ═══════════════════════════════════════════════════════════════
//  MAIN LOOP
// ═══════════════════════════════════════════════════════════════
function drawGame(){
    ctx.clearRect(0,0,W,H);
    frameCount++;

    if(gameState==="start"){
        ctx.fillStyle="#87CEEB"; ctx.fillRect(0,0,W,H);
        ctx.fillStyle="#C8963E"; ctx.fillRect(0,355,W,H-355);
        drawLynx();
        ctx.fillStyle="rgba(0,0,0,0.6)"; ctx.fillRect(110,120,580,155);
        ctx.textAlign="center";
        ctx.fillStyle="#FF8C00"; ctx.font="bold 34px Arial";
        ctx.fillText("🌵 LEVEL 1: DESERT",W/2,168);
        ctx.fillStyle="white"; ctx.font="17px Arial";
        ctx.fillText("Dodge enemies • Collect 🪙 coins • Beat the boss!",W/2,202);
        ctx.fillStyle="#FFD700"; ctx.font="16px Arial";
        ctx.fillText("→ / ← Move   •   SPACE Jump   •   Z Dash (unlock with 10 coins)",W/2,234);
        ctx.fillStyle="#88FF88"; ctx.font="14px Arial";
        ctx.fillText("🪙 Gold +10  •  🔵 Chip +50  •  ❤️ Core restores HP",W/2,258);
        ctx.textAlign="left";

    } else if(gameState==="playing"){
        if     (currentLevel===1) updateDrawDesert();
        else if(currentLevel===2) updateDrawMountain();
        else                      updateDrawCity();

    } else if(gameState==="levelclear"){
        if(currentLevel===1){
            ctx.fillStyle="#87CEEB"; ctx.fillRect(0,0,W,H);
            ctx.fillStyle="#C8963E"; ctx.fillRect(0,355,W,H-355);
        } else {
            let sky=ctx.createLinearGradient(0,0,0,H);
            sky.addColorStop(0,"#4A90D9"); sky.addColorStop(1,"#A8D5F0");
            ctx.fillStyle=sky; ctx.fillRect(0,0,W,H);
        }
        drawLynx();
        let nextName=currentLevel===1?"⛰ Mountain Level 2":"🌆 Neon City Level 3";
        drawOverlay(
            "✅  LEVEL "+currentLevel+" CLEAR!",
            "#00EE55",
            "Score: "+lynx.score+"  •  HP: "+"❤️".repeat(lynx.hp)+"  •  Coins: 🪙"+lynx.coins,
            "SPACE  →  "+nextName
        );

    } else if(gameState==="win"){
        let sky=ctx.createLinearGradient(0,0,0,H);
        sky.addColorStop(0,"#050518"); sky.addColorStop(1,"#1A0A2E");
        ctx.fillStyle=sky; ctx.fillRect(0,0,W,H);
        drawLynx();
        drawOverlay(
            "🏆  YOU WIN!",
            "#FFD700",
            "L1:"+levelScores[1]+" + L2:"+levelScores[2]+" + L3:"+levelScores[3]+" = "+lynx.score,
            "Score saved!  •  SPACE to play again"
        );

    } else if(gameState==="gameover"){
        ctx.fillStyle=currentLevel===3?"#050518":"#87CEEB";
        ctx.fillRect(0,0,W,H);
        drawLynx();
        drawOverlay(
            "GAME OVER!",
            "#FF3333",
            "Score: "+lynx.score+"  •  Coins collected: 🪙"+lynx.coins,
            "SPACE to retry from Level 1"
        );
    }

    requestAnimationFrame(drawGame);
}

// Init and start
resetAll();
drawGame();

// ═══════════════════════════════════════════════════════════════
//  INPUT
// ═══════════════════════════════════════════════════════════════
document.addEventListener("keydown",function(e){
    if(e.code==="Space"){
        e.preventDefault();
        if(gameState==="start"||gameState==="gameover"){
            resetAll(); gameState="playing";
        } else if(gameState==="levelclear"){
            if(currentLevel===1){goLevel2();gameState="playing";}
            else                {goLevel3();gameState="playing";}
        } else if(gameState==="win"){
            resetAll(); gameState="start";
        } else if(gameState==="playing"){
            if(lynx.isClimbing){
                lynx.isClimbing=false;lynx.climbTree=null;lynx.dy=lynx.jumpPower*0.85;
            } else if(currentLevel===1){
                if(lynx.y>=lynx.ground) lynx.dy=lynx.jumpPower;
            } else if(currentLevel===2){
                let gnd=groundAt(lynx.x+lynx.width/2)-lynx.height;
                if(lynx.y>=gnd-2) lynx.dy=lynx.jumpPower;
            } else {
                let onFloor=(lynx.y>=318);
                let onRoof=cityTerrain.some(t=>t.isRoof&&lynx.x+lynx.width>t.x&&lynx.x<t.x+t.w&&Math.abs(lynx.y-(t.y-lynx.height))<3);
                if(onFloor||onRoof) lynx.dy=lynx.jumpPower;
            }
        }
    }
    if(e.code==="KeyZ")      { e.preventDefault(); keys.KeyZ=true; }
    if(e.code==="ArrowLeft") { e.preventDefault(); keys.ArrowLeft =true; }
    if(e.code==="ArrowRight"){ e.preventDefault(); keys.ArrowRight=true; }
    if(e.code==="ArrowUp")   { e.preventDefault(); keys.ArrowUp   =true; }
    if(e.code==="ArrowDown") { e.preventDefault(); keys.ArrowDown =true; }
});

document.addEventListener("keyup",function(e){
    if(e.code==="KeyZ")      keys.KeyZ=false;
    if(e.code==="ArrowLeft") keys.ArrowLeft =false;
    if(e.code==="ArrowRight")keys.ArrowRight=false;
    if(e.code==="ArrowUp")   keys.ArrowUp   =false;
    if(e.code==="ArrowDown") keys.ArrowDown =false;
});
</script>
</body>
</html>