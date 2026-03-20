<?php
session_start();
// Safely include functions.php — won't crash the game if file is missing
if (file_exists('functions.php')) {
    @include 'functions.php';
}
$playerName = isset($_SESSION['playerName']) ? $_SESSION['playerName'] : 'Player';
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
            background:#87CEEB; /* sky blue — visible even if JS hasn't drawn yet */
        }
        .back-btn {
            background-color:#FF8C00; color:white; border:none; padding:12px 25px;
            font-size:18px; font-weight:bold; font-family:'Arial',sans-serif;
            text-decoration:none; cursor:pointer; display:inline-block;
            transition:0.3s; margin-top:20px; border-radius:8px;
        }
        .back-btn:hover { background-color:#E67E22; transform:scale(1.05); }
        /* Touch controls */
        #touchControls {
            display:none; position:fixed; bottom:10px; left:0; right:0;
            justify-content:space-between; padding:0 10px; pointer-events:none; z-index:999;
        }
        .tc-group { display:flex; gap:8px; pointer-events:all; }
        .tc-btn {
            width:60px; height:60px; background:rgba(255,140,0,0.55);
            border:3px solid rgba(255,200,0,0.8); border-radius:12px;
            color:white; font-size:22px; display:flex; align-items:center;
            justify-content:center; user-select:none; touch-action:none;
            font-weight:bold;
        }
    </style>
</head>
<body>
<h1>High Speed Lynx</h1>
<canvas id="gameCanvas" width="800" height="400"></canvas>
<br>
<a href="index.php" class="back-btn">RETURN TO MENU</a>

<!-- Touch Controls (shown only on touch devices) -->
<div id="touchControls">
    <div class="tc-group">
        <div class="tc-btn" id="tc-left">◀</div>
        <div class="tc-btn" id="tc-right">▶</div>
    </div>
    <div class="tc-group">
        <div class="tc-btn" id="tc-up">▲</div>
        <div class="tc-btn" id="tc-jump">⬆</div>
        <div class="tc-btn" id="tc-dash">⚡</div>
    </div>
</div>


<script>
// ── IMMEDIATE DRAW TEST ──────────────────────────────────────
(function(){
    try {
        var c = document.getElementById("gameCanvas");
        var x = c.getContext("2d");
        x.fillStyle = "#87CEEB"; x.fillRect(0,0,c.width,c.height);
        x.fillStyle = "#C8963E"; x.fillRect(0,355,c.width,100);
        x.fillStyle = "#FF8C00"; x.font = "bold 20px Arial"; x.textAlign="center";
        x.fillText("Loading High Speed Lynx...", c.width/2, 200);
        x.textAlign="left";
    } catch(e) {}
})();

// Global error catcher
window.onerror = function(msg, src, line, col, err) {
    try {
        var c = document.getElementById("gameCanvas");
        var x = c.getContext("2d");
        x.fillStyle = "#FFF2CC"; x.fillRect(0,0,c.width,c.height);
        x.fillStyle = "#CC0000"; x.font = "bold 16px Arial";
        x.fillText("JS ERROR (line " + line + "):", 20, 40);
        x.fillStyle = "#333"; x.font = "14px Arial";
        var words = String(msg).split(' '), line2 = '', y = 70;
        for(var w of words){
            var test = line2 + w + ' ';
            if(x.measureText(test).width > 760){ x.fillText(line2, 20, y); y+=22; line2=w+' '; }
            else line2=test;
        }
        if(line2) x.fillText(line2, 20, y);
        x.fillStyle="#888"; x.font="12px Arial";
        x.fillText("File: "+src+"  Col: "+col, 20, y+30);
        x.fillText("Open F12 > Console for full details", 20, y+50);
    } catch(e2){}
    return false;
};

// ═══════════════════════════════════════════════════════════════
//  CONSTANTS & CANVAS
// ═══════════════════════════════════════════════════════════════
const canvas = document.getElementById("gameCanvas");
const ctx    = canvas.getContext("2d");
const W = canvas.width, H = canvas.height;
const PLAYER_NAME = <?php echo json_encode($playerName); ?>;

// ═══════════════════════════════════════════════════════════════
//  WEB AUDIO API — No files needed, synthesised in JS
// ═══════════════════════════════════════════════════════════════
let _ac = null; // AudioContext — created on first user interaction
let musicVol = 0.35;
let sfxVol   = 0.5;
let _musicNodes = []; // currently playing music oscillators
let _musicKey = null;

function _getAC() {
    if (!_ac) {
        try { _ac = new (window.AudioContext || window.webkitAudioContext)(); } catch(e){}
    }
    if (_ac && _ac.state === 'suspended') { _ac.resume(); }
    return _ac;
}

// ── Low-level tone helpers ──────────────────────────────────
function _tone(freq, type, startT, dur, vol, ac, dest) {
    try {
        let o = ac.createOscillator();
        let g = ac.createGain();
        o.type = type;
        o.frequency.setValueAtTime(freq, startT);
        g.gain.setValueAtTime(vol, startT);
        g.gain.exponentialRampToValueAtTime(0.0001, startT + dur);
        o.connect(g); g.connect(dest);
        o.start(startT); o.stop(startT + dur + 0.01);
        return {o, g};
    } catch(e) { return null; }
}

function _noise(startT, dur, vol, ac, dest) {
    try {
        let bufSize = Math.floor(ac.sampleRate * dur);
        let buf = ac.createBuffer(1, bufSize, ac.sampleRate);
        let d = buf.getChannelData(0);
        for (let i = 0; i < bufSize; i++) d[i] = Math.random() * 2 - 1;
        let src = ac.createBufferSource();
        src.buffer = buf;
        let g = ac.createGain();
        g.gain.setValueAtTime(vol, startT);
        g.gain.exponentialRampToValueAtTime(0.0001, startT + dur);
        src.connect(g); g.connect(dest);
        src.start(startT); src.stop(startT + dur + 0.01);
    } catch(e) {}
}

// ── SFX definitions ─────────────────────────────────────────
const SFX = {
    jump:    (ac, m, t) => {
        _tone(220, 'square', t,    0.04, 0.3*m, ac, ac.destination);
        _tone(440, 'square', t+0.04, 0.1, 0.2*m, ac, ac.destination);
    },
    coin:    (ac, m, t) => {
        _tone(880,  'sine', t,      0.06, 0.3*m, ac, ac.destination);
        _tone(1320, 'sine', t+0.06, 0.08, 0.25*m, ac, ac.destination);
    },
    hit:     (ac, m, t) => {
        _noise(t, 0.15, 0.5*m, ac, ac.destination);
        _tone(120, 'sawtooth', t, 0.15, 0.3*m, ac, ac.destination);
    },
    die:     (ac, m, t) => {
        [440,330,220,110].forEach((f,i) => _tone(f,'sawtooth',t+i*0.08,0.1,0.35*m,ac,ac.destination));
    },
    boss:    (ac, m, t) => {
        _tone(80,  'sawtooth', t,      0.1,  0.4*m, ac, ac.destination);
        _tone(160, 'square',   t+0.05, 0.1,  0.3*m, ac, ac.destination);
        _noise(t, 0.2, 0.2*m, ac, ac.destination);
    },
    powerup: (ac, m, t) => {
        [440,550,660,880].forEach((f,i) => _tone(f,'sine',t+i*0.07,0.1,0.3*m,ac,ac.destination));
    },
    win:     (ac, m, t) => {
        let melody=[523,659,784,1047,784,1047];
        melody.forEach((f,i) => _tone(f,'sine',t+i*0.1,0.15,0.35*m,ac,ac.destination));
    },
    combo:   (ac, m, t) => {
        _tone(660, 'square', t,      0.05, 0.25*m, ac, ac.destination);
        _tone(880, 'square', t+0.05, 0.05, 0.25*m, ac, ac.destination);
        _tone(1100,'square', t+0.1,  0.08, 0.3*m,  ac, ac.destination);
    },
};

function playSfx(id, relVol) {
    try {
        let ac = _getAC(); if (!ac) return;
        let vol = (relVol !== undefined ? relVol : 0.5) * sfxVol;
        let fn = SFX[id]; if (!fn) return;
        fn(ac, vol, ac.currentTime);
    } catch(e) {}
}

// ── Music engine — chiptune loop per level ──────────────────
// Each track is a sequence of [freq, duration] notes
const MUSIC_TRACKS = {
    desert: {
        bpm: 128, loop: true,
        // Upbeat desert theme — pentatonic with syncopation
        notes: [
            [330,0.25],[392,0.25],[440,0.5],[392,0.25],[330,0.25],
            [294,0.5], [0,0.25],  [330,0.25],[392,0.25],[440,0.25],
            [392,0.25],[330,0.5], [294,0.25],[330,0.25],[262,0.5],
            [0,0.25],  [294,0.25],[330,0.25],[392,0.5], [440,0.25],
            [494,0.25],[440,0.5], [392,0.25],[330,0.5], [0,0.5],
        ],
        bass: [
            [110,0.5],[110,0.5],[147,0.5],[147,0.5],
            [110,0.5],[110,0.5],[98,0.5], [98,0.5],
        ],
        type:'square', bassType:'triangle'
    },
    mountain: {
        bpm: 100, loop: true,
        // Slower, more majestic mountain feel
        notes: [
            [262,0.5],[294,0.5],[330,1.0],[294,0.5],[262,0.5],
            [220,1.0],[0,0.5],  [262,0.5],[330,0.5],[392,0.5],
            [440,1.0],[392,0.5],[330,0.5],[294,1.0],[0,0.5],
            [330,0.5],[294,0.5],[262,0.5],[220,0.5],[196,1.0],[0,0.5],
        ],
        bass: [
            [65,1.0],[65,1.0],[82,1.0],[82,1.0],
            [73,1.0],[73,1.0],[55,1.0],[55,1.0],
        ],
        type:'triangle', bassType:'sine'
    },
    city: {
        bpm: 150, loop: true,
        // Fast neon city techno feel
        notes: [
            [880,0.125],[0,0.125],[880,0.125],[0,0.125],[1047,0.25],[880,0.125],[0,0.125],
            [784,0.25],[0,0.25],[784,0.125],[0,0.125],[880,0.25],[0,0.25],
            [1047,0.125],[0,0.125],[1047,0.125],[880,0.125],[784,0.125],[0,0.125],[880,0.5],
            [0,0.25],[784,0.125],[0,0.125],[698,0.25],[784,0.25],[0,0.25],[880,0.25],
        ],
        bass: [
            [110,0.25],[110,0.25],[0,0.25],[110,0.25],
            [147,0.25],[147,0.25],[0,0.25],[147,0.25],
            [110,0.25],[110,0.25],[0,0.25],[98,0.25],
            [110,0.5], [0,0.5],
        ],
        type:'square', bassType:'sawtooth'
    }
};

let _musicTimer = null;
let _musicScheduled = false;

function playMusic(key) {
    stopMusic();
    _musicKey = key;
    _scheduleMusicLoop(key);
}

function stopMusic() {
    _musicKey = null;
    _musicNodes.forEach(n => { try { n.stop(); } catch(e){} });
    _musicNodes = [];
    if (_musicTimer) { clearTimeout(_musicTimer); _musicTimer = null; }
}

function applyMusicVol() {
    // Volume changes take effect on next loop iteration
}

function _scheduleMusicLoop(key) {
    if (_musicKey !== key) return;
    let ac = _getAC(); if (!ac) return;
    let track = MUSIC_TRACKS[key]; if (!track) return;

    let now = ac.currentTime;
    let t = now + 0.05; // small lookahead
    let vol = musicVol * 0.4;
    let bassVol = musicVol * 0.25;
    let totalDur = 0;

    // Schedule melody
    for (let [freq, dur] of track.notes) {
        if (freq > 0) {
            let n = _tone(freq, track.type, t, dur * 0.85, vol, ac, ac.destination);
            if (n) _musicNodes.push(n.o);
        }
        t += dur;
    }
    totalDur = t - now - 0.05;

    // Schedule bass (loops to fill melody length)
    let bt = now + 0.05;
    let bassLen = track.bass.reduce((s, [, d]) => s + d, 0);
    while (bt < now + 0.05 + totalDur) {
        for (let [freq, dur] of track.bass) {
            if (bt >= now + 0.05 + totalDur) break;
            if (freq > 0) {
                let n = _tone(freq, track.bassType, bt, dur * 0.7, bassVol, ac, ac.destination);
                if (n) _musicNodes.push(n.o);
            }
            bt += dur;
        }
    }

    // Add a simple percussion beat (for city/desert)
    if (key === 'city' || key === 'desert') {
        let pt = now + 0.05;
        let beatDur = 60 / track.bpm;
        while (pt < now + 0.05 + totalDur) {
            _noise(pt, 0.05, musicVol * 0.15, ac, ac.destination);
            _tone(60, 'sine', pt, 0.08, musicVol * 0.2, ac, ac.destination);
            pt += beatDur;
        }
    }

    // Schedule next loop
    if (track.loop) {
        _musicTimer = setTimeout(() => _scheduleMusicLoop(key), totalDur * 1000 - 100);
    }
}

// ─── VOLUME SLIDER DRAG STATE ────────────────────────────────
let volUI = {
    dragging: null,
    musicRect: {x:0,y:0,w:0,h:0},
    sfxRect:   {x:0,y:0,w:0,h:0},
};

function drawVolumeSliders() {
    let sx=W-62, sy=H-130, sw=50, sh=8, gap=22;
    let mr={x:sx-sw,y:sy,w:sw,h:sh};
    volUI.musicRect=mr;
    ctx.fillStyle="rgba(0,0,0,0.45)"; ctx.fillRect(mr.x-2,mr.y-12,sw+4,sh+16);
    ctx.fillStyle="#333"; ctx.fillRect(mr.x,mr.y,sw,sh);
    ctx.fillStyle="#FF8C00"; ctx.fillRect(mr.x,mr.y,sw*musicVol,sh);
    ctx.strokeStyle="rgba(255,255,255,0.4)"; ctx.lineWidth=1; ctx.strokeRect(mr.x,mr.y,sw,sh);
    ctx.fillStyle="white"; ctx.font="9px Arial"; ctx.textAlign="center";
    ctx.fillText("♪",mr.x+sw/2,mr.y-2);
    let sr={x:sx-sw,y:sy+gap,w:sw,h:sh};
    volUI.sfxRect=sr;
    ctx.fillStyle="rgba(0,0,0,0.45)"; ctx.fillRect(sr.x-2,sr.y-12,sw+4,sh+16);
    ctx.fillStyle="#333"; ctx.fillRect(sr.x,sr.y,sw,sh);
    ctx.fillStyle="#00BFFF"; ctx.fillRect(sr.x,sr.y,sw*sfxVol,sh);
    ctx.strokeStyle="rgba(255,255,255,0.4)"; ctx.lineWidth=1; ctx.strokeRect(sr.x,sr.y,sw,sh);
    ctx.fillStyle="white"; ctx.font="9px Arial";
    ctx.fillText("SFX",sr.x+sw/2,sr.y-2);
    ctx.textAlign="left";
}

function handleVolSliderInput(clientX, clientY) {
    let rect=canvas.getBoundingClientRect();
    let scaleX=800/rect.width, scaleY=400/rect.height;
    let mx=(clientX-rect.left)*scaleX, my=(clientY-rect.top)*scaleY;
    function hitSlider(r){ return mx>=r.x&&mx<=r.x+r.w&&my>=r.y-6&&my<=r.y+r.h+6; }
    function valFromX(r){ return Math.max(0,Math.min(1,(mx-r.x)/r.w)); }
    if(volUI.dragging==='music'||hitSlider(volUI.musicRect)){
        volUI.dragging='music'; musicVol=valFromX(volUI.musicRect); return true;
    }
    if(volUI.dragging==='sfx'||hitSlider(volUI.sfxRect)){
        volUI.dragging='sfx'; sfxVol=valFromX(volUI.sfxRect); return true;
    }
    return false;
}
canvas.addEventListener('mousedown', e=>{ if(!handleVolSliderInput(e.clientX,e.clientY)) _getAC(); });
canvas.addEventListener('mousemove', e=>{ if(volUI.dragging) handleVolSliderInput(e.clientX,e.clientY); });
canvas.addEventListener('mouseup',   ()=>{ volUI.dragging=null; });
canvas.addEventListener('touchstart', e=>{ if(e.touches[0]) handleVolSliderInput(e.touches[0].clientX,e.touches[0].clientY); },{passive:true});
canvas.addEventListener('touchmove',  e=>{ if(volUI.dragging&&e.touches[0]) handleVolSliderInput(e.touches[0].clientX,e.touches[0].clientY); },{passive:true});
canvas.addEventListener('touchend',   ()=>{ volUI.dragging=null; });

// ═══════════════════════════════════════════════════════════════
//  DIFFICULTY
// ═══════════════════════════════════════════════════════════════
let difficulty = "normal"; // "easy" | "normal" | "hard"

// Returns a scaling object based on current difficulty
function D() {
    if (difficulty === "easy") return {
        enemySpeed: 0.5,   // multiplier on all enemy speeds
        bossHp: 5,         // sand golem / troll / cyber boss HP
        bossThrowInterval: 130, // frames between throws (bigger = slower)
        bossSpeed: 0.9,    // boss patrol speed multiplier
        playerHp: 5,       // starting HP
        scorpionTail: false,
        boulderSpeed: 3,
        hitInvincTime: 1800 // ms of invincibility after hit
    };
    if (difficulty === "hard") return {
        enemySpeed: 1.5,
        bossHp: 10,
        bossThrowInterval: 60,
        bossSpeed: 1.4,
        playerHp: 3,
        scorpionTail: true,
        boulderSpeed: 8,
        hitInvincTime: 800
    };
    // normal
    return {
        enemySpeed: 1.0,
        bossHp: 8,
        bossThrowInterval: 90,
        bossSpeed: 1.0,
        playerHp: 3,
        scorpionTail: true,
        boulderSpeed: 5,
        hitInvincTime: 1200
    };
}

// ═══════════════════════════════════════════════════════════════
//  GAME STATE
// ═══════════════════════════════════════════════════════════════
let currentLevel = 1;
// states: start | intro | playing | bossfight | levelclear | win | gameover
let gameState    = "start";
let cameraX      = 0;
let frameCount   = 0;
const gravity    = 0.6;
let levelScores  = { 1:0, 2:0, 3:0 };

// Intro cutscene
const INTRO_DUR = 130;
let introFrame  = 0;
let introText   = "";
let introBg     = "#FF8C00";
let introLabel  = "";

// Leaderboard
let leaderboard = [];
let personalBest = 0;
function fetchLeaderboard() {
    fetch('data/leaderboard.json?_='+Date.now())
        .then(r=>r.json())
        .then(data=>{
            leaderboard = data.sort((a,b)=>b.score-a.score).slice(0,10);
            // Find personal best
            let mine = leaderboard.filter(e=>e.playerName===PLAYER_NAME);
            if(mine.length) personalBest = Math.max(...mine.map(e=>e.score));
        }).catch(()=>{});
}
fetchLeaderboard();

let keys = { ArrowLeft:false, ArrowRight:false, ArrowUp:false, ArrowDown:false, KeyZ:false };

// ─── CHECKPOINT ─────────────────────────────────────────────
let checkpoint = {
    x:0, y:0,           // world position
    active:false,        // has player touched it?
    flash:0,             // animation frames on activation
    respawnHp:3,         // HP saved at checkpoint
};
function resetCheckpoint(levelLen, groundFn) {
    checkpoint.x = Math.floor(levelLen * 0.5);
    checkpoint.y = (groundFn ? groundFn(checkpoint.x) : 350) - 70;
    checkpoint.active = false;
    checkpoint.flash  = 0;
    checkpoint.respawnHp = 3;
}
function checkCheckpointTouch() {
    if (checkpoint.active) return;
    let cx = checkpoint.x, cy = checkpoint.y;
    if (lynx.x < cx+20 && lynx.x+lynx.width > cx &&
        lynx.y < cy+60  && lynx.y+lynx.height > cy) {
        checkpoint.active   = true;
        checkpoint.flash    = 90;
        checkpoint.respawnHp = lynx.hp;
        playSfx('powerup', 0.6);
        spawnParticles(cx+10, cy, "#00FF88", 20);
    }
}
function drawCheckpoint() {
    let sx = checkpoint.x - cameraX;
    if (sx < -40 || sx > W+40) return;
    let pulse = Math.sin(frameCount * 0.1) * 0.3 + 0.7;
    let col   = checkpoint.active ? "#00FF88" : "#FFFFFF";
    // Pole
    ctx.strokeStyle = checkpoint.active ? "#00CC66" : "#AAAAAA";
    ctx.lineWidth = 4;
    ctx.beginPath(); ctx.moveTo(sx+10, checkpoint.y+60); ctx.lineTo(sx+10, checkpoint.y); ctx.stroke();
    // Flag
    ctx.fillStyle = checkpoint.active
        ? `rgba(0,255,136,${pulse})`
        : "rgba(200,200,200,0.85)";
    ctx.beginPath();
    ctx.moveTo(sx+10, checkpoint.y);
    ctx.lineTo(sx+32, checkpoint.y+12);
    ctx.lineTo(sx+10, checkpoint.y+24);
    ctx.closePath(); ctx.fill();
    // Glow on activation
    if (checkpoint.flash > 0) {
        let a = checkpoint.flash / 90;
        ctx.fillStyle = `rgba(0,255,136,${a * 0.35})`;
        ctx.beginPath(); ctx.arc(sx+10, checkpoint.y+30, 50*a, 0, Math.PI*2); ctx.fill();
        checkpoint.flash--;
    }
    // Label
    if (!checkpoint.active) {
        ctx.fillStyle = "rgba(255,255,255,0.8)";
        ctx.font = "bold 10px Arial"; ctx.textAlign = "center";
        ctx.fillText("CHECKPOINT", sx+10, checkpoint.y-6);
        ctx.textAlign = "left";
    }
}

// ─── SAVE / LOAD GAME ────────────────────────────────────────
const SAVE_KEY = 'hsl_savegame';

function saveGame() {
    try {
        let data = {
            currentLevel,
            score: lynx.score,
            hp: lynx.hp,
            coins: lynx.coins,
            dashUnlocked: lynx.dashUnlocked,
            levelScores: {...levelScores},
            difficulty,
            savedAt: new Date().toISOString()
        };
        localStorage.setItem(SAVE_KEY, JSON.stringify(data));
    } catch(e){}
}

function loadGame() {
    try {
        let raw = localStorage.getItem(SAVE_KEY);
        if (!raw) return null;
        return JSON.parse(raw);
    } catch(e){ return null; }
}

function deleteSave() {
    try { localStorage.removeItem(SAVE_KEY); } catch(e){}
}

// Auto-save on level clear and win
function onLevelComplete() {
    saveGame();
}

// Saved game state for start screen display
let savedGame = loadGame();

// ─── WEATHER ─────────────────────────────────────────────────
let weatherParticles = [];
function initWeather(type, count) {
    weatherParticles = [];
    for (let i = 0; i < count; i++) {
        weatherParticles.push(makeWeatherParticle(type, true));
    }
}
function makeWeatherParticle(type, randomY) {
    if (type === 'sand') return {
        type, x: Math.random()*W, y: randomY ? Math.random()*H : -5,
        vx: 3+Math.random()*4, vy: 0.5+Math.random()*0.5,
        size: 1+Math.random()*2, alpha: 0.1+Math.random()*0.3
    };
    if (type === 'snow') return {
        type, x: Math.random()*W, y: randomY ? Math.random()*H : -5,
        vx: (Math.random()-0.5)*0.8, vy: 0.6+Math.random()*1.2,
        size: 2+Math.random()*3, alpha: 0.5+Math.random()*0.5
    };
    if (type === 'rain') return {
        type, x: Math.random()*W, y: randomY ? Math.random()*H : -10,
        vx: -1, vy: 8+Math.random()*5,
        size: 1, alpha: 0.25+Math.random()*0.25
    };
    return {};
}
function updateDrawWeather(type) {
    for (let p of weatherParticles) {
        p.x += p.vx; p.y += p.vy;
        if (p.y > H+10 || p.x > W+10 || p.x < -10) {
            Object.assign(p, makeWeatherParticle(type, false));
            p.x = type === 'sand' ? -5 : Math.random()*W;
        }
        ctx.globalAlpha = p.alpha;
        if (type === 'sand') {
            ctx.fillStyle = "#DEB060";
            ctx.fillRect(p.x, p.y, p.size*3, p.size*0.7);
        } else if (type === 'snow') {
            ctx.fillStyle = "white";
            ctx.beginPath(); ctx.arc(p.x, p.y, p.size, 0, Math.PI*2); ctx.fill();
        } else if (type === 'rain') {
            ctx.strokeStyle = "#88BBFF";
            ctx.lineWidth = 1;
            ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(p.x+p.vx*2, p.y+p.vy*0.4); ctx.stroke();
        }
        ctx.globalAlpha = 1;
    }
}

// ─── LYNX ───────────────────────────────────────────────────
let lynx = {
    x:80, y:320, width:30, height:30, color:"#FF4500",
    dy:0, jumpPower:-13, ground:350, speed:6,
    hp:3, maxHp:5, isHit:false, isClimbing:false, climbTree:null, score:0,
    coins:0,
    dashUnlocked:false, dashActive:false, dashCooldown:0, dashTimer:0,
    dashSpeed:18, dashDuration:12,
    shakeX:0, shakeY:0,
    // Power-ups
    starTimer:0,    // invincibility star (frames remaining)
    magnetTimer:0,  // coin magnet (frames remaining)
};

let coins      = [];
let particles  = [];
let deathAnims = [];
let powerUps   = [];

let abilityFlash = 0;

// ─── COMBO ──────────────────────────────────────────────────
let combo = { count:0, timer:0, multiplier:1, floats:[] };

function registerComboHit(x, y) {
    combo.count++;
    combo.timer = 120;
    combo.multiplier = Math.min(4, 1 + Math.floor(combo.count/3));
    if (combo.count >= 3) {
        combo.floats.push({ x, y, label: combo.multiplier+"x COMBO!", life:60, vy:-1.2 });
        if (combo.count % 3 === 0) playSfx('combo', 0.5);
    }
}

function updateCombo() {
    if (combo.timer > 0) { combo.timer--; }
    else { combo.count=0; combo.multiplier=1; }
    // Float labels
    for (let i=combo.floats.length-1;i>=0;i--) {
        let f=combo.floats[i];
        f.y+=f.vy; f.life--;
        if (f.life<=0) { combo.floats.splice(i,1); continue; }
        let alpha=f.life/60;
        let hue = combo.multiplier<=1?60:combo.multiplier<=2?40:combo.multiplier<=3?20:0;
        ctx.globalAlpha=alpha;
        ctx.fillStyle=`hsl(${hue},100%,60%)`;
        ctx.font="bold 16px Arial"; ctx.textAlign="center";
        ctx.fillText(f.label, f.x-cameraX, f.y);
        ctx.textAlign="left"; ctx.globalAlpha=1;
    }
    // Combo bar
    if (combo.count>=2) {
        let barW=200, barH=10, bx=W/2-barW/2, by=H-50;
        ctx.fillStyle="rgba(0,0,0,0.5)"; ctx.fillRect(bx,by,barW,barH);
        let pct=Math.min(combo.count/12,1);
        let hue=combo.multiplier<=1?60:combo.multiplier<=2?40:combo.multiplier<=3?20:0;
        ctx.fillStyle=`hsl(${hue},100%,55%)`; ctx.fillRect(bx,by,barW*pct,barH);
        ctx.strokeStyle="#fff"; ctx.lineWidth=1; ctx.strokeRect(bx,by,barW,barH);
        ctx.fillStyle="white"; ctx.font="bold 11px Arial"; ctx.textAlign="center";
        ctx.fillText(combo.multiplier+"x MULT", W/2, by-2);
        ctx.textAlign="left";
    }
}

// ─── MINI-MAP ───────────────────────────────────────────────
function drawMiniMap(totalLen, enemyObjs) {
    let mw=190, mh=14, mx=W-mw-8, my=8;
    ctx.fillStyle="rgba(0,0,0,0.55)"; ctx.fillRect(mx,my,mw,mh);
    ctx.strokeStyle="rgba(255,255,255,0.3)"; ctx.lineWidth=1; ctx.strokeRect(mx,my,mw,mh);
    // Enemies
    if (enemyObjs) for (let o of enemyObjs) {
        if (o.defeated || o.collected) continue;
        let ex = mx + (o.x/totalLen)*mw;
        ctx.fillStyle="#FF4444"; ctx.fillRect(ex-1,my+4,2,6);
    }
    // Coins
    for (let c of coins) {
        if (c.collected) continue;
        let cx2 = mx + (c.x/totalLen)*mw;
        ctx.fillStyle="#FFD700"; ctx.fillRect(cx2-1,my+5,2,4);
    }
    // Power-ups
    for (let p of powerUps) {
        if (p.collected) continue;
        let px2 = mx + (p.x/totalLen)*mw;
        ctx.fillStyle="#CC44FF"; ctx.fillRect(px2-1,my+4,2,6);
    }
    // Checkpoint
    let cpx = mx + (checkpoint.x/totalLen)*mw;
    ctx.fillStyle = checkpoint.active ? "#00FF88" : "rgba(255,255,255,0.5)";
    ctx.fillRect(cpx-1, my+2, 2, mh-4);
    // Player
    let plx = mx + (lynx.x/totalLen)*mw;
    ctx.shadowColor="#FF8C00"; ctx.shadowBlur=6;
    ctx.fillStyle="#FF8C00"; ctx.fillRect(plx-2,my+2,4,mh-4);
    ctx.shadowBlur=0;
}

// ═══════════════════════════════════════════════════════════════
//  DEATH ANIMATIONS
// ═══════════════════════════════════════════════════════════════
function spawnDeath(type, x, y, extra) {
    deathAnims.push({ type, x, y, life:0, extra: extra||{} });
}

function updateDeathAnims() {
    for (let i=deathAnims.length-1;i>=0;i--) {
        let d=deathAnims[i];
        d.life++;
        let sx=d.x-cameraX;
        ctx.save();
        if (d.type==='rat_squash') {
            // Flatten oval + star burst
            if (d.life>40) { deathAnims.splice(i,1); continue; }
            let s=Math.min(d.life/5,1);
            ctx.globalAlpha=1-d.life/40;
            ctx.fillStyle="#666";
            ctx.beginPath(); ctx.ellipse(sx,d.y,18*s,5*(1-s*0.5),0,0,Math.PI*2); ctx.fill();
            // Stars
            for (let k=0;k<5;k++) {
                let ang=k/5*Math.PI*2, r=14*s;
                ctx.strokeStyle="#FFD700"; ctx.lineWidth=2;
                ctx.beginPath(); ctx.moveTo(sx,d.y); ctx.lineTo(sx+Math.cos(ang)*r,d.y+Math.sin(ang)*r*0.5); ctx.stroke();
            }
        } else if (d.type==='snake_curl') {
            if (d.life>50) { deathAnims.splice(i,1); continue; }
            ctx.globalAlpha=1-d.life/50;
            ctx.strokeStyle="#6B8E23"; ctx.lineWidth=6;
            ctx.beginPath();
            let r2=d.life*0.6;
            ctx.arc(sx,d.y,r2,0,Math.PI*2*(1-d.life/50));
            ctx.stroke();
        } else if (d.type==='cactus_break') {
            if (d.life>45) { deathAnims.splice(i,1); continue; }
            let chunks=[{ox:-10,oy:0,vx:-2,vy:-3},{ox:10,oy:0,vx:2,vy:-3},{ox:-5,oy:-20,vx:-1,vy:-5},{ox:5,oy:-20,vx:1,vy:-5}];
            ctx.fillStyle="#2E8B57";
            for (let c of chunks) {
                let cx2=sx+c.ox+c.vx*d.life, cy=d.y+c.oy+c.vy*d.life+0.3*d.life*d.life;
                ctx.globalAlpha=1-d.life/45;
                ctx.fillRect(cx2-4,cy-6,8,14);
            }
        } else if (d.type==='bear_spin') {
            if (d.life>55) { deathAnims.splice(i,1); continue; }
            ctx.globalAlpha=1-d.life/55;
            ctx.translate(sx+18,d.y+16+d.life*2.5);
            ctx.rotate(d.life*0.3);
            ctx.fillStyle="#5C3A1E"; ctx.fillRect(-18,-16,36,32);
            ctx.fillStyle="#6B4226"; ctx.beginPath(); ctx.arc(9,-6,12,0,Math.PI*2); ctx.fill();
        } else if (d.type==='drone_spark') {
            if (d.life>40) { deathAnims.splice(i,1); continue; }
            for (let k=0;k<6;k++) {
                let ang=k/6*Math.PI*2+d.life*0.1, r3=d.life*1.8;
                ctx.strokeStyle=k%2===0?"#00FFFF":"#FF0055"; ctx.lineWidth=2;
                ctx.globalAlpha=1-d.life/40;
                ctx.beginPath(); ctx.moveTo(sx,d.y); ctx.lineTo(sx+Math.cos(ang)*r3,d.y+Math.sin(ang)*r3); ctx.stroke();
            }
        } else if (d.type==='bot_fizzle') {
            if (d.life>50) { deathAnims.splice(i,1); continue; }
            for (let k=0;k<8;k++) {
                let ang=k/8*Math.PI*2, r4=d.life*1.4+Math.sin(d.life+k)*5;
                ctx.fillStyle=k%2===0?"#FF00FF":"#00FF88";
                ctx.globalAlpha=1-d.life/50;
                ctx.fillRect(sx+Math.cos(ang)*r4-2,d.y+Math.sin(ang)*r4-2,4,4);
            }
        } else if (d.type==='golem_explode') {
            if (d.life>70) { deathAnims.splice(i,1); continue; }
            // Expanding rings
            for (let ring=0;ring<3;ring++) {
                let r5=(d.life-ring*8)*4; if(r5<0) continue;
                ctx.strokeStyle=`rgba(200,150,60,${1-d.life/70})`;
                ctx.lineWidth=4; ctx.beginPath(); ctx.arc(sx,d.y,r5,0,Math.PI*2); ctx.stroke();
            }
            // Sand chunks
            for (let k=0;k<8;k++) {
                let ang=k/8*Math.PI*2, spd=d.life*2.2;
                let cx3=sx+Math.cos(ang)*spd, cy=d.y+Math.sin(ang)*spd+0.18*d.life*d.life;
                ctx.globalAlpha=Math.max(0,1-d.life/70);
                ctx.fillStyle="#C8963E"; ctx.fillRect(cx3-5,cy-5,10,10);
            }
        }
        ctx.restore();
        ctx.globalAlpha=1;
    }
}

// ═══════════════════════════════════════════════════════════════
//  POWER-UPS
// ═══════════════════════════════════════════════════════════════
function placePowerUps(startX, endX, groundFn, count) {
    powerUps = [];
    let spacing = Math.floor((endX-startX)/count);
    for (let i=0;i<count;i++) {
        let x=startX+i*spacing+spacing*0.4+Math.random()*spacing*0.2;
        let g=groundFn ? groundFn(x) : 350;
        let type=Math.random()<0.5?'star':'magnet';
        powerUps.push({x, y:g-80-Math.random()*40, type, collected:false, bobOffset:Math.random()*Math.PI*2});
    }
}

function checkPowerUpCollect() {
    for (let p of powerUps) {
        if (p.collected) continue;
        if (lynx.x<p.x+20&&lynx.x+lynx.width>p.x&&lynx.y<p.y+20&&lynx.y+lynx.height>p.y) {
            p.collected=true;
            playSfx('powerup',0.7);
            registerComboHit(p.x,p.y);
            if (p.type==='star')   { lynx.starTimer=180; }
            if (p.type==='magnet') { lynx.magnetTimer=300; }
        }
    }
    // Tick timers
    if (lynx.starTimer>0)   lynx.starTimer--;
    if (lynx.magnetTimer>0) {
        lynx.magnetTimer--;
        // Pull coins
        for (let c of coins) {
            if (c.collected) continue;
            let dx=lynx.x-c.x, dy=lynx.y-c.y;
            let dist=Math.sqrt(dx*dx+dy*dy);
            if (dist<120) { c.x+=dx/dist*3; c.y+=dy/dist*3; }
        }
    }
}

function drawPowerUps() {
    for (let p of powerUps) {
        if (p.collected) continue;
        let sx=p.x-cameraX;
        if (sx<-30||sx>W+30) continue;
        let bob=Math.sin(frameCount*0.07+p.bobOffset)*5;
        let py=p.y+bob;
        if (p.type==='star') {
            // 5-point star
            ctx.shadowColor="#FFD700"; ctx.shadowBlur=16;
            ctx.fillStyle="#FFD700";
            ctx.save(); ctx.translate(sx+10,py+10);
            ctx.rotate(frameCount*0.03);
            ctx.beginPath();
            for (let k=0;k<5;k++) {
                let a=k/5*Math.PI*2-Math.PI/2;
                let a2=a+Math.PI/5;
                if(k===0) ctx.moveTo(Math.cos(a)*10,Math.sin(a)*10);
                else ctx.lineTo(Math.cos(a)*10,Math.sin(a)*10);
                ctx.lineTo(Math.cos(a2)*5,Math.sin(a2)*5);
            }
            ctx.closePath(); ctx.fill();
            ctx.restore();
        } else if (p.type==='magnet') {
            // U-shape magnet
            ctx.shadowColor="#FF44FF"; ctx.shadowBlur=14;
            ctx.strokeStyle="#CC44FF"; ctx.lineWidth=5;
            ctx.beginPath();
            ctx.arc(sx+10,py+14,8,Math.PI,0);
            ctx.stroke();
            // Tips
            ctx.strokeStyle="#FF0000"; ctx.lineWidth=4;
            ctx.beginPath(); ctx.moveTo(sx+2,py+14); ctx.lineTo(sx+2,py+6); ctx.stroke();
            ctx.strokeStyle="#0088FF";
            ctx.beginPath(); ctx.moveTo(sx+18,py+14); ctx.lineTo(sx+18,py+6); ctx.stroke();
            // Orbiting dot
            let oa=frameCount*0.12;
            ctx.fillStyle="#FFD700"; ctx.shadowColor="#FFD700"; ctx.shadowBlur=8;
            ctx.beginPath(); ctx.arc(sx+10+Math.cos(oa)*14,py+14+Math.sin(oa)*7,3,0,Math.PI*2); ctx.fill();
        }
        ctx.shadowBlur=0;
    }
}

function drawPowerUpHUD() {
    let hx=W-62, hy=H-60;
    // Star timer bar
    if (lynx.starTimer>0) {
        let pct=lynx.starTimer/180;
        let rainbow=`hsl(${frameCount*8%360},100%,55%)`;
        ctx.fillStyle="rgba(0,0,0,0.5)"; ctx.fillRect(hx-70,hy,64,12);
        ctx.fillStyle=rainbow; ctx.fillRect(hx-70,hy,64*pct,12);
        ctx.fillStyle="white"; ctx.font="bold 10px Arial"; ctx.textAlign="center";
        ctx.fillText("⭐ STAR",hx-38,hy+10); ctx.textAlign="left";
    }
    // Magnet timer bar
    if (lynx.magnetTimer>0) {
        let pct=lynx.magnetTimer/300;
        ctx.fillStyle="rgba(0,0,0,0.5)"; ctx.fillRect(hx-70,hy+16,64,12);
        ctx.fillStyle="#CC44FF"; ctx.fillRect(hx-70,hy+16,64*pct,12);
        ctx.fillStyle="white"; ctx.font="bold 10px Arial"; ctx.textAlign="center";
        ctx.fillText("🧲 MAGNET",hx-38,hy+26); ctx.textAlign="left";
    }
}

// ═══════════════════════════════════════════════════════════════
//  HELPERS
// ═══════════════════════════════════════════════════════════════
function saveScore(score) {
    let ts = new Date().toISOString();
    // Primary: server — send JSON to match save_score.php's json_decode expectation
    fetch('save_score.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ score: score })
    }).catch(()=>{});
    // Backup: localStorage
    try {
        let saved = JSON.parse(localStorage.getItem('hsl_scores')||'[]');
        saved.push({playerName:PLAYER_NAME, score, timestamp:ts});
        saved.sort((a,b)=>b.score-a.score);
        saved = saved.slice(0,20);
        localStorage.setItem('hsl_scores', JSON.stringify(saved));
        if(score > personalBest) personalBest = score;
    } catch(e){}
}

function spawnParticles(x, y, color, count) {
    for (let i=0;i<count;i++) {
        particles.push({ x, y, vx:(Math.random()-0.5)*5, vy:(Math.random()-0.5)*5-2,
            life:30+Math.random()*20, maxLife:50, color });
    }
}

function updateParticles() {
    for (let i=particles.length-1;i>=0;i--) {
        let p=particles[i];
        p.x+=p.vx; p.y+=p.vy; p.vy+=0.15; p.life--;
        if (p.life<=0) { particles.splice(i,1); continue; }
        ctx.globalAlpha=p.life/p.maxLife;
        ctx.fillStyle=p.color; ctx.fillRect(p.x-cameraX,p.y,4,4);
        ctx.globalAlpha=1;
    }
}

function triggerShake(amount) {
    lynx.shakeX=(Math.random()-0.5)*amount;
    lynx.shakeY=(Math.random()-0.5)*amount;
}

// ─── SPINNING COIN DRAW ─────────────────────────────────────
function drawSpinCoin(sx, cy, type, frame, bobOffset) {
    let bob=Math.sin(frame*0.08+bobOffset)*4;
    let y=cy+bob;
    let spin=Math.abs(Math.cos(frame*0.1+bobOffset));
    let w=14*spin, h=14;
    let cx2=sx+7;
    let colors = type==='gold' ? ['#FFD700','#FFA500','#FFE87C'] :
                 type==='chip' ? ['#00BFFF','#005080','#66DDFF'] :
                                 ['#FF4444','#AA0000','#FF8888'];
    ctx.shadowColor=colors[0]; ctx.shadowBlur=10;
    ctx.fillStyle=colors[0];
    ctx.beginPath(); ctx.ellipse(cx2,y+7,Math.max(1,w/2),h/2,0,0,Math.PI*2); ctx.fill();
    if (spin>0.3) {
        ctx.fillStyle=colors[2];
        ctx.beginPath(); ctx.ellipse(cx2-w*0.15,y+5,Math.max(0.5,w*0.15),h*0.25,0,0,Math.PI*2); ctx.fill();
    }
    // $ symbol
    if (spin>0.5) {
        ctx.fillStyle=colors[1]; ctx.font="bold 8px Arial"; ctx.textAlign="center";
        ctx.fillText(type==='gold'?'$':type==='chip'?'⬡':'♥', cx2,y+10);
        ctx.textAlign="left";
    }
    ctx.shadowBlur=0;
}

function checkCoinCollect() {
    for (let c of coins) {
        if (c.collected) continue;
        if (lynx.x<c.x+14&&lynx.x+lynx.width>c.x&&lynx.y<c.y+14&&lynx.y+lynx.height>c.y) {
            c.collected=true;
            playSfx('coin',0.4);
            let pts = c.type==='gold'?10:c.type==='chip'?50:0;
            if (c.type==='core') { if (lynx.hp<lynx.maxHp) lynx.hp++; spawnParticles(c.x,c.y,"#FF4444",12); }
            else { lynx.score+=pts*combo.multiplier; spawnParticles(c.x,c.y,c.type==='gold'?"#FFD700":"#00BFFF",8); }
            lynx.coins++;
            registerComboHit(c.x,c.y);
            if (!lynx.dashUnlocked&&lynx.coins>=10) { lynx.dashUnlocked=true; abilityFlash=180; }
        }
    }
}

function drawCoins() {
    for (let c of coins) {
        if (c.collected) continue;
        let sx=c.x-cameraX;
        if (sx<-20||sx>W+20) continue;
        drawSpinCoin(sx,c.y,c.type,frameCount,c.bobOffset);
    }
}

function placeLevelCoins(startX,endX,groundFn,density) {
    coins=[];
    let spacing=Math.floor((endX-startX)/density);
    for (let i=0;i<density;i++) {
        let x=startX+i*spacing+Math.random()*spacing*0.5;
        let g=groundFn?groundFn(x):350;
        let r=Math.random();
        let type=r<0.65?'gold':r<0.88?'chip':'core';
        let elev=(Math.random()<0.4)?60+Math.random()*80:20;
        coins.push({x,y:g-elev,type,collected:false,bobOffset:Math.random()*Math.PI*2});
    }
}

// ─── MINI-LYNX HP BAR ───────────────────────────────────────
function drawMiniLynx(ox,oy,scale,full) {
    ctx.save(); ctx.translate(ox,oy); ctx.scale(scale,scale);
    if (full) { ctx.shadowColor="#FF8C00"; ctx.shadowBlur=6; }
    let col=full?"#FF4500":"rgba(150,150,150,0.4)";
    let col2=full?"#E05000":"rgba(120,120,120,0.3)";
    ctx.fillStyle=col2; ctx.fillRect(7,3,10,10);
    ctx.fillStyle=col;  ctx.fillRect(2,7,14,12);
    ctx.fillStyle=col2;
    ctx.beginPath();ctx.moveTo(8,3);ctx.lineTo(6,0);ctx.lineTo(10,1);ctx.closePath();ctx.fill();
    ctx.beginPath();ctx.moveTo(14,3);ctx.lineTo(17,0);ctx.lineTo(13,1);ctx.closePath();ctx.fill();
    if (full) { ctx.fillStyle="white"; ctx.fillRect(10,4,3,3); ctx.fillStyle="#222"; ctx.fillRect(11,5,2,2); }
    let legC=full?"#E05000":"rgba(120,120,120,0.3)";
    ctx.fillStyle=legC; ctx.fillRect(3,18,5,6); ctx.fillRect(11,18,5,6);
    if (full) {
        ctx.strokeStyle="#FF6A00"; ctx.lineWidth=2;
        ctx.beginPath(); ctx.moveTo(2,10); ctx.quadraticCurveTo(-5,3,-1,-1); ctx.stroke();
    }
    ctx.shadowBlur=0; ctx.restore();
}

function drawHpBar() {
    let panW=lynx.maxHp*26+16, panH=38;
    let px=10, py=H-panH-4;
    ctx.fillStyle="rgba(0,0,0,0.55)"; ctx.beginPath();
    ctx.roundRect(px,py,panW,panH,8); ctx.fill();
    ctx.fillStyle="#FFD700"; ctx.font="bold 10px Arial"; ctx.fillText("HP",px+5,py+12);
    for (let i=0;i<lynx.maxHp;i++) {
        drawMiniLynx(px+5+i*26,py+14,1.1,i<lynx.hp);
    }
}

function drawAbilityHUD() {
    // Coin counter
    ctx.fillStyle="rgba(0,0,0,0.5)"; ctx.fillRect(W/2-55,H-32,110,24);
    ctx.fillStyle="#FFD700"; ctx.font="bold 13px Arial"; ctx.textAlign="center";
    ctx.fillText("🪙 "+lynx.coins+(lynx.dashUnlocked?"":" / 10 for DASH"),W/2,H-15);
    ctx.textAlign="left";
    // Dash icon
    if (lynx.dashUnlocked) {
        let coolPct=lynx.dashCooldown>0?lynx.dashCooldown/60:0;
        ctx.fillStyle=coolPct>0?"rgba(0,0,0,0.5)":"rgba(255,140,0,0.8)";
        ctx.fillRect(W-62,H-60,50,50);
        ctx.strokeStyle=lynx.dashActive?"#FFFFFF":"#FF8C00";
        ctx.lineWidth=2; ctx.strokeRect(W-62,H-60,50,50);
        if (coolPct>0) { ctx.fillStyle="rgba(0,0,0,0.6)"; ctx.fillRect(W-62,H-60,50,50*coolPct); }
        ctx.fillStyle="white"; ctx.font="bold 11px Arial"; ctx.textAlign="center";
        ctx.fillText("DASH",W-37,H-38); ctx.fillText("[Z]",W-37,H-22);
        ctx.textAlign="left";
    }
    if (abilityFlash>0) {
        let alpha=Math.min(1,abilityFlash/30);
        ctx.fillStyle=`rgba(255,140,0,${alpha*0.85})`; ctx.fillRect(W/2-160,H/2-30,320,50);
        ctx.fillStyle="white"; ctx.font="bold 22px Arial"; ctx.textAlign="center";
        ctx.fillText("⚡ DASH UNLOCKED! Press Z to dash!",W/2,H/2+2);
        ctx.textAlign="left"; abilityFlash--;
    }
    drawPowerUpHUD();
    drawVolumeSliders();
}

function handleDash() {
    if (!lynx.dashUnlocked) return;
    if (lynx.dashCooldown>0) { lynx.dashCooldown--; return; }
    if (lynx.dashActive) {
        lynx.dashTimer--;
        let dir=keys.ArrowLeft?-1:1;
        lynx.x+=lynx.dashSpeed*dir;
        // Fiery dash trail — more dramatic during attack
        spawnParticles(lynx.x,lynx.y+10,"#FF8C00",3);
        spawnParticles(lynx.x+lynx.width/2,lynx.y+15,"#FFD700",2);
        if (lynx.dashTimer<=0) { lynx.dashActive=false; lynx.dashCooldown=60; }
    }
    if (keys.KeyZ&&!lynx.dashActive&&lynx.dashCooldown===0) {
        lynx.dashActive=true; lynx.dashTimer=lynx.dashDuration;
        // Flash effect on dash start
        spawnParticles(lynx.x+lynx.width/2,lynx.y+lynx.height/2,"#FF8C00",12);
    }
}

// Check if lynx dash-attacks an enemy object (call per enemy during update)
function checkDashAttack(o) {
    if (!lynx.dashActive) return false;
    if (lynx.x<o.x+o.w && lynx.x+lynx.width>o.x && lynx.y<o.y+o.h && lynx.y+lynx.height>o.y) {
        lynx.score += 60*combo.multiplier;
        registerComboHit(o.x, o.y);
        spawnParticles(o.x+o.w/2, o.y+o.h/2, "#FF8C00", 16);
        spawnParticles(o.x+o.w/2, o.y+o.h/2, "#FFD700", 8);
        playSfx('coin', 0.4);
        return true; // caller should remove / mark dead
    }
    return false;
}

// ═══════════════════════════════════════════════════════════════
//  LEVEL INTRO CUTSCENE
// ═══════════════════════════════════════════════════════════════
function startIntro(label, bgColor) {
    gameState="intro"; introFrame=0; introText=""; introLabel=label; introBg=bgColor;
}

function drawIntro() {
    introFrame++;
    // Slide in panel
    let slideTarget=W*0.12;
    let slideX=W-(W*0.76)*Math.min(1,introFrame/30);
    ctx.fillStyle="rgba(0,0,0,0.7)"; ctx.fillRect(0,0,W,H);
    // Panel
    ctx.fillStyle=introBg;
    ctx.beginPath(); ctx.roundRect(slideX,H/2-55,W*0.76,110,14); ctx.fill();
    // Scanline sweep
    let sweep=(introFrame/INTRO_DUR)*W;
    ctx.fillStyle="rgba(255,255,255,0.08)";
    for (let y=H/2-55;y<H/2+55;y+=4) ctx.fillRect(slideX,y,sweep-slideX,2);
    // Type out text
    let targetLen=Math.floor((introFrame-10)/2);
    if (targetLen>introLabel.length) targetLen=introLabel.length;
    introText=introLabel.slice(0,targetLen);
    ctx.fillStyle="white"; ctx.font="bold 32px Arial"; ctx.textAlign="center";
    ctx.fillText(introText,W/2,H/2+4);
    ctx.font="16px Arial"; ctx.fillStyle="rgba(255,255,255,0.7)";
    ctx.fillText("GET READY!",W/2,H/2+32);
    ctx.textAlign="left";
    // Fade to black at end
    if (introFrame>INTRO_DUR-25) {
        ctx.fillStyle=`rgba(0,0,0,${(introFrame-(INTRO_DUR-25))/25})`;
        ctx.fillRect(0,0,W,H);
    }
    if (introFrame>=INTRO_DUR) gameState="playing";
}

// ═══════════════════════════════════════════════════════════════
//  ANIMATED GROUND TILES
// ═══════════════════════════════════════════════════════════════
function drawAnimDesertGround() {
    ctx.fillStyle="#C8963E"; ctx.fillRect(0,lynx.ground+lynx.height,W,H-(lynx.ground+lynx.height));
    ctx.fillStyle="#DEB060"; ctx.fillRect(0,lynx.ground+lynx.height,W,8);
    // Heat shimmer bands
    for (let b=0;b<3;b++) {
        let yy=lynx.ground+lynx.height+6+b*4;
        let alpha=0.12-b*0.03;
        let off=Math.sin(frameCount*0.07+b*2)*8;
        ctx.fillStyle=`rgba(255,220,100,${alpha})`;
        ctx.fillRect(off,yy,W,3);
    }
}

function drawAnimMtnGround(terrain) {
    for (let t of terrain) {
        let sx=t.x-cameraX;
        if (sx>W+100||sx+t.w<-100) continue;
        ctx.fillStyle="#4E7A30"; ctx.fillRect(sx,t.y,t.w,14);
        ctx.fillStyle="#7A5230"; ctx.fillRect(sx,t.y+14,t.w,H-t.y);
        // Swaying grass
        let blades=Math.floor(t.w/8);
        for (let b=0;b<blades;b++) {
            let bx=sx+b*8+4;
            let sway=Math.sin(frameCount*0.05+bx*0.1)*3;
            ctx.strokeStyle="#5DBB3A"; ctx.lineWidth=1.5;
            ctx.beginPath(); ctx.moveTo(bx,t.y); ctx.quadraticCurveTo(bx+sway,t.y-5,bx+sway*1.5,t.y-9); ctx.stroke();
        }
        if (t.y<260) { ctx.fillStyle="rgba(255,255,255,0.7)"; ctx.fillRect(sx,t.y,t.w,6); }
    }
}

function drawAnimCityGround(nAlpha) {
    ctx.fillStyle="#1A1A2E"; ctx.fillRect(0,350,W,H-350);
    let gridOff=(frameCount*1.5)%40;
    ctx.strokeStyle=`rgba(0,255,200,${nAlpha*0.18})`; ctx.lineWidth=1;
    for (let gx=(-gridOff);gx<W;gx+=40) { ctx.beginPath(); ctx.moveTo(gx,350); ctx.lineTo(gx,H); ctx.stroke(); }
    ctx.strokeStyle=`rgba(0,255,200,${nAlpha*0.5})`; ctx.lineWidth=2;
    for (let nl=0;nl<4;nl++) { ctx.beginPath(); ctx.moveTo(0,355+nl*3); ctx.lineTo(W,355+nl*3); ctx.stroke(); }
}

// ═══════════════════════════════════════════════════════════════
//  LEADERBOARD
// ═══════════════════════════════════════════════════════════════
function drawLeaderboard() {
    // Merge server + localStorage scores
    let local = [];
    try { local = JSON.parse(localStorage.getItem('hsl_scores')||'[]'); } catch(e){}
    let combined = [...leaderboard];
    for(let ls of local){
        if(!combined.find(e=>e.playerName===ls.playerName&&e.score===ls.score))
            combined.push(ls);
    }
    combined.sort((a,b)=>b.score-a.score);
    combined = combined.slice(0,10);

    let medals=['🥇','🥈','🥉'];
    let rowH=19, topPad=52, panW=420, panH=topPad+combined.length*rowH+28;
    let lx=W/2-panW/2, ly=105;

    // Panel background
    ctx.fillStyle="rgba(0,0,30,0.88)";
    ctx.beginPath(); ctx.roundRect(lx,ly,panW,panH,12); ctx.fill();
    ctx.strokeStyle="rgba(255,215,0,0.5)"; ctx.lineWidth=2;
    ctx.beginPath(); ctx.roundRect(lx,ly,panW,panH,12); ctx.stroke();

    // Title
    ctx.textAlign="center";
    ctx.fillStyle="#FFD700"; ctx.font="bold 16px Arial";
    ctx.fillText("🏆  HIGH SCORES",W/2,ly+22);

    // Personal best banner
    if(personalBest>0){
        ctx.fillStyle="rgba(255,140,0,0.25)";
        ctx.fillRect(lx+6,ly+28,panW-12,18);
        ctx.fillStyle="#FF8C00"; ctx.font="bold 11px Arial";
        ctx.fillText("Your best: "+personalBest+" — "+PLAYER_NAME,W/2,ly+40);
    }

    // Column headers
    ctx.fillStyle="rgba(255,255,255,0.4)"; ctx.font="10px Arial";
    ctx.fillText("#   Player",lx+18,ly+topPad-4);
    ctx.fillText("Score",lx+panW*0.52,ly+topPad-4);
    ctx.fillText("Date",lx+panW*0.74,ly+topPad-4);

    // Rows
    for(let i=0;i<combined.length;i++){
        let e=combined[i];
        let isMe=e.playerName===PLAYER_NAME;
        let ry=ly+topPad+i*rowH;

        // Row highlight
        if(isMe){
            ctx.fillStyle="rgba(255,140,0,0.22)";
            ctx.fillRect(lx+4,ry-13,panW-8,rowH);
        } else if(i%2===0){
            ctx.fillStyle="rgba(255,255,255,0.04)";
            ctx.fillRect(lx+4,ry-13,panW-8,rowH);
        }

        // Rank / medal
        let rankStr = i<3 ? medals[i] : (i+1)+".";
        ctx.fillStyle=isMe?"#FF8C00":i<3?"#FFD700":"rgba(255,255,255,0.7)";
        ctx.font=(isMe||i<3)?"bold 12px Arial":"12px Arial";
        ctx.textAlign="left";
        ctx.fillText(rankStr,lx+10,ry);

        // Name
        ctx.fillStyle=isMe?"#FF8C00":"white";
        ctx.font=(isMe?"bold ":"")+"12px Arial";
        let name=e.playerName.length>14?e.playerName.slice(0,13)+"…":e.playerName;
        ctx.fillText(name,lx+38,ry);

        // Score
        ctx.fillStyle=isMe?"#FFD700":"#AAFFAA";
        ctx.textAlign="center";
        ctx.fillText(e.score,lx+panW*0.62,ry);

        // Date/time
        let dateStr="—";
        if(e.dateTime||e.timestamp){
            try{
                let d=new Date(e.dateTime||e.timestamp);
                dateStr=d.toLocaleDateString('en-US',{month:'short',day:'numeric'})
                    +' '+d.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'});
            }catch(ex){}
        }
        ctx.fillStyle="rgba(180,180,200,0.75)"; ctx.font="10px Arial";
        ctx.fillText(dateStr,lx+panW*0.86,ry);
    }

    if(combined.length===0){
        ctx.fillStyle="rgba(255,255,255,0.5)"; ctx.font="13px Arial";
        ctx.fillText("No scores yet — be the first!",W/2,ly+topPad+20);
    }
    ctx.textAlign="left";
}

// ═══════════════════════════════════════════════════════════════
//  CLOUDS
// ═══════════════════════════════════════════════════════════════
let cloudObjs=[];
function initClouds(style) {
    cloudObjs=[];
    for (let i=0;i<18;i++) {
        cloudObjs.push({x:Math.random()*8000,y:20+Math.random()*120,w:60+Math.random()*80,
            h:25+Math.random()*20,speed:0.3+Math.random()*0.4,style});
    }
}
function drawClouds(levelLen) {
    for (let c of cloudObjs) {
        c.x-=c.speed;
        if (c.x+c.w<cameraX-200) c.x+=levelLen+400;
        let sx=c.x-cameraX;
        if (sx>W+200||sx+c.w<-200) continue;
        if (c.style==='desert') {
            ctx.fillStyle="rgba(255,255,255,0.78)";
            ctx.beginPath(); ctx.ellipse(sx+c.w/2,c.y+c.h/2,c.w/2,c.h/2,0,0,Math.PI*2); ctx.fill();
            ctx.beginPath(); ctx.ellipse(sx+c.w*0.3,c.y+c.h*0.6,c.w*0.3,c.h*0.4,0,0,Math.PI*2); ctx.fill();
            ctx.beginPath(); ctx.ellipse(sx+c.w*0.72,c.y+c.h*0.6,c.w*0.28,c.h*0.38,0,0,Math.PI*2); ctx.fill();
        } else if (c.style==='mountain') {
            let g=ctx.createRadialGradient(sx+c.w/2,c.y+c.h/2,0,sx+c.w/2,c.y+c.h/2,c.w/2);
            g.addColorStop(0,"rgba(220,235,255,0.55)"); g.addColorStop(1,"rgba(220,235,255,0)");
            ctx.fillStyle=g; ctx.beginPath(); ctx.ellipse(sx+c.w/2,c.y+c.h/2,c.w/2,c.h/2,0,0,Math.PI*2); ctx.fill();
        } else if (c.style==='city') {
            let pulse=0.3+Math.sin(frameCount*0.03+c.x)*0.2;
            ctx.fillStyle=`rgba(30,10,50,${pulse})`; ctx.beginPath(); ctx.ellipse(sx+c.w/2,c.y+c.h/2,c.w/2,c.h/2,0,0,Math.PI*2); ctx.fill();
            ctx.fillStyle=`rgba(180,0,255,${pulse*0.4})`; ctx.beginPath(); ctx.ellipse(sx+c.w/2,c.y+c.h/2+4,c.w/2,c.h/3,0,0,Math.PI*2); ctx.fill();
        }
    }
}

// ═══════════════════════════════════════════════════════════════
//  ██  LEVEL 1 — DESERT
// ═══════════════════════════════════════════════════════════════
const DESERT_LEN=10000;
let desertObjs=[], desertTimer=0;

function spawnDesert() {
    let sx=cameraX+W+60;
    if (sx>DESERT_LEN-200) return;
    let d=D(), r=Math.random(), progress=Math.min(lynx.x/DESERT_LEN,1);
    let spd=d.enemySpeed;
    if      (r<0.18) desertObjs.push({type:'cactus',  x:sx,y:lynx.ground-54,w:22,h:84,color:'#228B22',speedX:0});
    else if (r<0.38) desertObjs.push({type:'rat',     x:sx,y:lynx.ground-16,w:32,h:16,color:'#666',   speedX:-(3+progress*2.5)*spd});
    else if (r<0.55) desertObjs.push({type:'snake',   x:sx,y:lynx.ground-11,w:44,h:11,color:'#6B8E23',speedX:-(1.2+progress)*spd});
    else if (r<0.70) desertObjs.push({type:'scorpion',x:sx,y:lynx.ground-14,w:28,h:14,color:'#B8860B',speedX:0,noTail:!d.scorpionTail});
    else             desertObjs.push({type:'tumbleweed',x:sx,y:lynx.ground-18,w:20,h:20,color:'#8B7355',speedX:-(2+Math.random()*2)*spd});
}

function checkStompDesert(o) {
    // Returns true if stomped from above
    if (lynx.dy>0 && lynx.x+lynx.width>o.x+4 && lynx.x<o.x+o.w-4 &&
        lynx.y+lynx.height>=o.y && lynx.y+lynx.height<=o.y+14) {
        return true;
    }
    return false;
}

function updateDrawDesert() {
    ctx.save(); ctx.translate(lynx.shakeX,lynx.shakeY);
    lynx.shakeX*=0.8; lynx.shakeY*=0.8;
    let skyG=ctx.createLinearGradient(0,0,0,H);
    skyG.addColorStop(0,"#87CEEB"); skyG.addColorStop(1,"#FDE68A");
    ctx.fillStyle=skyG; ctx.fillRect(0,0,W,H);
    // Animated sun with rotating rays
    let sunX=W-80-cameraX*0.02, sunY=55;
    for(let r=0;r<8;r++){
        let ang=r/8*Math.PI*2+frameCount*0.005;
        let len=44+Math.sin(frameCount*0.05+r)*6;
        ctx.strokeStyle="rgba(255,220,50,0.25)";ctx.lineWidth=6;
        ctx.beginPath();ctx.moveTo(sunX+Math.cos(ang)*34,sunY+Math.sin(ang)*34);
        ctx.lineTo(sunX+Math.cos(ang)*len,sunY+Math.sin(ang)*len);ctx.stroke();
    }
    ctx.shadowColor="#FFD700"; ctx.shadowBlur=30;
    ctx.fillStyle="#FFE44D"; ctx.beginPath(); ctx.arc(sunX,sunY,32,0,Math.PI*2); ctx.fill();
    ctx.fillStyle="#FFF176"; ctx.beginPath(); ctx.arc(sunX-6,sunY-6,12,0,Math.PI*2); ctx.fill();
    ctx.shadowBlur=0;
    // Far parallax dune layer
    drawDune(0.06,"#F0D898",360);
    drawClouds(DESERT_LEN);
    drawDune(0.12,"#E8C97A",355); drawDune(0.28,"#D4A844",362);
    drawAnimDesertGround();
    // Sandstorm weather
    updateDrawWeather('sand');
    drawFlag(DESERT_LEN-80,lynx.ground-85,"#FFD700","LV2");
    drawCheckpoint();
    drawProgressBar(lynx.x,DESERT_LEN);
    lynx.dy+=gravity; lynx.y+=lynx.dy;
    if (!lynx.dashActive) { if(keys.ArrowLeft)lynx.x-=lynx.speed; if(keys.ArrowRight)lynx.x+=lynx.speed; }
    handleDash();
    if (lynx.x<0) lynx.x=0;
    if (lynx.y>=lynx.ground) { lynx.y=lynx.ground; lynx.dy=0; }
    let tc=lynx.x-W*0.35; cameraX+=(tc-cameraX)*0.1; if(cameraX<0)cameraX=0;
    desertTimer++; if(desertTimer%52===0)spawnDesert();
    lynx.score+=Math.floor(lynx.speed*0.04);
    for (let i=desertObjs.length-1;i>=0;i--) {
        let o=desertObjs[i];
        o.x+=o.speedX;
        if (o.x+o.w<cameraX-150) { desertObjs.splice(i,1); continue; }
        drawDesertObj(o);
        let invincible=lynx.starTimer>0||lynx.dashActive;
        // Stomp check (non-static enemies)
        if (!invincible && (o.type==='rat'||o.type==='snake'||o.type==='tumbleweed')) {
            if (checkStompDesert(o)) {
                playSfx('coin',0.3); lynx.dy=-9;
                lynx.score+=50*combo.multiplier;
                registerComboHit(o.x,o.y);
                if (o.type==='rat') spawnDeath('rat_squash',o.x+o.w/2,o.y+o.h/2);
                if (o.type==='snake') spawnDeath('snake_curl',o.x+o.w/2,o.y+o.h/2);
                if (o.type==='cactus') spawnDeath('cactus_break',o.x+o.w/2,o.y);
                spawnParticles(o.x+o.w/2,o.y,"#FFD700",10);
                desertObjs.splice(i,1); continue;
            }
        }
        // Dash attack kills non-cactus enemies
        if (lynx.dashActive && o.type!=='cactus') {
            if (checkDashAttack(o)) {
                if (o.type==='rat')   spawnDeath('rat_squash',o.x+o.w/2,o.y+o.h/2);
                if (o.type==='snake') spawnDeath('snake_curl',o.x+o.w/2,o.y+o.h/2);
                desertObjs.splice(i,1); continue;
            }
        }
        if (o.type==='cactus'&&!invincible&&!lynx.isHit) checkHit(o);
        else if (o.type!=='cloud'&&!invincible&&!lynx.isHit&&o.type!=='cactus') checkHit(o);
    }
    checkCoinCollect(); checkPowerUpCollect(); checkCheckpointTouch();
    drawCoins(); drawPowerUps(); updateParticles(); updateDeathAnims(); updateCombo();
    // Star rainbow flicker
    if (lynx.starTimer>0) {
        ctx.fillStyle=`hsla(${frameCount*15%360},100%,55%,0.35)`;
        ctx.fillRect(lynx.x-cameraX-4,lynx.y-4,lynx.width+8,lynx.height+8);
    }
    drawLynx(); drawHUD("LEVEL 1 – DESERT","#FF8C00"); drawAbilityHUD(); drawHpBar();
    drawMiniMap(DESERT_LEN, desertObjs);
    ctx.restore();
    if (lynx.x>=DESERT_LEN-100) { levelScores[1]=lynx.score; gameState="levelclear"; saveGame(); }
}

function drawDune(p,color,baseY) {
    let px=-cameraX*p;
    ctx.fillStyle=color; ctx.beginPath(); ctx.moveTo(0,baseY);
    for (let x=0;x<=W+200;x+=80) ctx.quadraticCurveTo(px+x+40,baseY-28,px+x+80,baseY);
    ctx.lineTo(W,H); ctx.lineTo(0,H); ctx.closePath(); ctx.fill();
}

function drawDesertObj(o) {
    let sx=o.x-cameraX; if(sx<-60||sx>W+60)return;
    if (o.type==='cactus') {
        ctx.fillStyle="#2E8B57"; ctx.fillRect(sx+8,o.y,6,o.h); ctx.fillRect(sx,o.y+22,o.w,6);
        ctx.fillRect(sx,o.y+10,6,18); ctx.fillRect(sx+16,o.y+28,6,16);
    } else if (o.type==='rat') {
        ctx.fillStyle="#666"; ctx.fillRect(sx,o.y+4,20,10);
        ctx.beginPath();ctx.arc(sx+22,o.y+8,8,0,Math.PI*2);ctx.fill();
        ctx.fillStyle="#999"; ctx.beginPath();ctx.arc(sx+27,o.y+5,3,0,Math.PI*2);ctx.fill();
        ctx.strokeStyle="#555"; ctx.lineWidth=1.5;
        ctx.beginPath();ctx.moveTo(sx,o.y+10);ctx.lineTo(sx-12,o.y+6);ctx.stroke();
    } else if (o.type==='snake') {
        ctx.strokeStyle="#6B8E23"; ctx.lineWidth=8;
        ctx.beginPath(); ctx.moveTo(sx,o.y+5);
        for (let i=0;i<o.w;i+=10) ctx.quadraticCurveTo(sx+i+5,o.y+(i%20<10?0:10),sx+i+10,o.y+5);
        ctx.stroke();
        ctx.fillStyle="#556B2F"; ctx.beginPath(); ctx.ellipse(sx+o.w+4,o.y+5,7,5,0,0,Math.PI*2); ctx.fill();
        ctx.fillStyle="red"; ctx.fillRect(sx+o.w+8,o.y+3,6,2);
    } else if (o.type==='scorpion') {
        ctx.fillStyle="#B8860B"; ctx.fillRect(sx+6,o.y+4,16,10);
        ctx.beginPath();ctx.arc(sx+22,o.y+6,5,0,Math.PI*2);ctx.fill();
        ctx.strokeStyle="#B8860B"; ctx.lineWidth=2;
        ctx.beginPath();ctx.moveTo(sx+6,o.y+6);ctx.lineTo(sx-4,o.y+2);ctx.lineTo(sx-8,o.y+5);ctx.stroke();
        ctx.beginPath();ctx.moveTo(sx+6,o.y+10);ctx.lineTo(sx-4,o.y+14);ctx.lineTo(sx-8,o.y+11);ctx.stroke();
        if (!o.noTail) { ctx.beginPath();ctx.moveTo(sx+6,o.y+4);ctx.quadraticCurveTo(sx,o.y-12,sx+10,o.y-14);ctx.lineWidth=2.5;ctx.stroke(); }
    } else if (o.type==='tumbleweed') {
        let bounce=Math.abs(Math.sin(frameCount*0.18))*8;
        ctx.strokeStyle="#8B7355"; ctx.lineWidth=2;
        ctx.beginPath();ctx.arc(sx+10,o.y+10-bounce,10,0,Math.PI*2);ctx.stroke();
        ctx.beginPath();ctx.moveTo(sx,o.y+10-bounce);ctx.lineTo(sx+20,o.y+10-bounce);ctx.stroke();
        ctx.beginPath();ctx.moveTo(sx+10,o.y-bounce);ctx.lineTo(sx+10,o.y+20-bounce);ctx.stroke();
        ctx.beginPath();ctx.moveTo(sx+3,o.y+3-bounce);ctx.lineTo(sx+17,o.y+17-bounce);ctx.stroke();
    }
}

// ═══════════════════════════════════════════════════════════════
//  ██  LEVEL 2 — MOUNTAIN
// ═══════════════════════════════════════════════════════════════
const MTN_LEN=8000, TW=18, TH=80, TR=38, CS=3;
const terrain=(function(){
    let t=[]; t.push({x:0,y:350,w:500});
    let segs=[[500,310,300],[800,270,250],[1050,230,350],[1400,260,200],[1600,300,300],[1900,350,500],[2400,290,300],[2700,250,250],[2950,280,200],[3150,350,600],[3750,310,250],[4000,260,300],[4300,220,400],[4700,250,250],[4950,290,300],[5250,350,500],[5750,300,300],[6050,260,250],[6300,230,300],[6600,260,200],[6800,300,300],[7100,350,900]];
    for(let s of segs)t.push({x:s[0],y:s[1],w:s[2]});
    return t;
})();
function groundAt(x){for(let i=terrain.length-1;i>=0;i--){let t=terrain[i];if(x>=t.x&&x<t.x+t.w)return t.y;}return 350;}
const mtnObjs=(function(){
    let arr=[];
    for(let x of[300,700,1200,1700,2200,2600,3000,3500,4100,4600,5100,5600,6000,6500,7000,7400])arr.push({type:'tree',x});
    for(let r of[[450,35,28],[900,40,30],[1350,35,25],[1800,45,32],[2300,38,28],[2800,42,30],[3300,36,26],[3800,44,32],[4250,38,28],[4700,40,30],[5200,35,25],[5700,45,32],[6200,38,28],[6700,42,30],[7200,36,26],[7600,40,28]])arr.push({type:'rock',x:r[0],w:r[1],h:r[2]});
    for(let b of[[850,750,950,1,1.5],[1400,1300,1550,-1,1.5],[1950,1850,2100,1,2],[2550,2450,2700,-1,2],[3200,3100,3350,1,2.5],[3800,3700,3950,-1,2.5],[4400,4300,4550,1,3],[5000,4900,5150,-1,3],[5600,5500,5750,1,3.5],[6200,6100,6350,-1,3.5],[6800,6700,6950,1,4],[7400,7300,7550,-1,4]])arr.push({type:'bear',x:b[0],patrolMin:b[1],patrolMax:b[2],dir:b[3],speed:b[4],w:36,h:32});
    // Eagles — patrol high in sky, dive when lynx is close
    for(let ex of[600,1500,2400,3300,4200,5100,6000,6900])
        arr.push({type:'eagle',x:ex,y:60,w:32,h:22,baseY:50+Math.random()*40,
            state:'circle', // 'circle' | 'dive' | 'return'
            vx:1.8,vy:0,dir:1,diveVx:0,diveVy:0,dead:false});
    // Goats — patrol slowly, charge when lynx enters range
    for(let g of[[550,450,700,1],[1200,1100,1350,-1],[2000,1900,2150,1],[2900,2800,3050,-1],[3700,3600,3850,1],[4500,4400,4650,-1],[5300,5200,5450,1],[6100,6000,6250,-1]])
        arr.push({type:'goat',x:g[0],patrolMin:g[1],patrolMax:g[2],dir:g[3],
            speed:1.2,chargeSpeed:7,state:'patrol',w:34,h:28,dead:false,
            chargeDir:1,chargeCooldown:0});
    return arr;
})();
for(let o of mtnObjs){
    let g=groundAt(o.x+(o.w||0)/2);
    if(o.type==='tree'){o.w=TW;o.h=TH;o.y=g-TH;}
    if(o.type==='rock')o.y=g-o.h;
    if(o.type==='bear')o.y=g-o.h;
    if(o.type==='goat')o.y=g-o.h;
}
const MTN_FLAG_X=MTN_LEN-150;

function updateDrawMountain(){
    ctx.save(); ctx.translate(lynx.shakeX,lynx.shakeY);
    lynx.shakeX*=0.8; lynx.shakeY*=0.8;
    let sky=ctx.createLinearGradient(0,0,0,H);
    sky.addColorStop(0,"#4A90D9"); sky.addColorStop(1,"#A8D5F0");
    ctx.fillStyle=sky; ctx.fillRect(0,0,W,H);
    // Distant snow-capped peaks parallax layer
    let pkOff=-cameraX*0.05;
    ctx.fillStyle="rgba(180,210,240,0.5)";
    for(let i=0;i<8;i++){
        let px=pkOff+i*140, ph=60+((i*53)%50);
        ctx.beginPath();ctx.moveTo(px,H/2);ctx.lineTo(px+70,H/2-ph);ctx.lineTo(px+140,H/2);ctx.fill();
        // Snow cap
        ctx.fillStyle="rgba(255,255,255,0.7)";
        ctx.beginPath();ctx.moveTo(px+50,H/2-ph+12);ctx.lineTo(px+70,H/2-ph);ctx.lineTo(px+90,H/2-ph+12);ctx.fill();
        ctx.fillStyle="rgba(180,210,240,0.5)";
    }
    drawClouds(MTN_LEN);
    drawRidge(0.15,"#7BAFC8",[[0,310],[120,230],[280,260],[450,190],[650,240],[850,170],[1050,220],[1300,250],[1600,210],[1900,270],[2200,310]]);
    drawRidge(0.30,"#8EC9A8",[[0,330],[180,260],[400,230],[620,270],[880,210],[1100,265],[1380,280],[1650,245],[2000,330]]);
    drawAnimMtnGround(terrain);
    drawFlag(MTN_FLAG_X,groundAt(MTN_FLAG_X)-90,"#FFD700","LV3");
    drawCheckpoint();
    drawProgressBar(lynx.x,MTN_LEN);
    if(lynx.isClimbing&&lynx.climbTree){
        let t=lynx.climbTree;
        if(keys.ArrowUp)lynx.y-=CS; if(keys.ArrowDown)lynx.y+=CS;
        if(lynx.y<t.y-TR*2)lynx.y=t.y-TR*2;
        if(lynx.y>t.y+t.h-lynx.height){lynx.isClimbing=false;lynx.climbTree=null;lynx.dy=0;}
        lynx.dy=0;
    } else {
        lynx.dy+=gravity; lynx.y+=lynx.dy;
        if(!lynx.dashActive){if(keys.ArrowLeft)lynx.x-=lynx.speed;if(keys.ArrowRight)lynx.x+=lynx.speed;}
        handleDash();
        lynx.x=Math.max(0,Math.min(MTN_LEN-lynx.width,lynx.x));
        let gnd=groundAt(lynx.x+lynx.width/2)-lynx.height;
        if(lynx.y>=gnd){lynx.y=gnd;lynx.dy=0;}
        if(lynx.y>H+60){lynx.hp=0;gameState="gameover";}
        if(keys.ArrowUp&&!lynx.dashActive){
            for(let o of mtnObjs){
                if(o.type!=='tree')continue;
                let d=Math.abs(lynx.x+lynx.width/2-(o.x+TW/2));
                if(d<25&&lynx.y+lynx.height>o.y&&lynx.y<o.y+o.h){lynx.isClimbing=true;lynx.climbTree=o;lynx.dy=0;break;}
            }
        }
    }
    let tc=lynx.x-W*0.35; cameraX+=(tc-cameraX)*0.1;
    cameraX=Math.max(0,Math.min(MTN_LEN-W,cameraX));
    lynx.score+=Math.floor(lynx.speed*0.05);
    for(let o of mtnObjs){
        if(o.x-cameraX>W+120||o.x+o.w-cameraX<-120)continue;
        if(o.type==='tree')drawTree(o);
        else if(o.type==='rock')drawRock(o);
        else if(o.type==='bear'){
            o.x+=o.speed*o.dir; o.y=groundAt(o.x+o.w/2)-o.h;
            if(o.x>o.patrolMax)o.dir=-1; if(o.x<o.patrolMin)o.dir=1;
            drawBear(o);
            let invincible=lynx.starTimer>0||lynx.dashActive;
            // Dash attack kills bear
            if(lynx.dashActive&&checkDashAttack(o)){
                spawnDeath('bear_spin',o.x,o.y); o.x=-9999; continue;
            }
            // Stomp bear
            if(!invincible&&lynx.dy>0&&lynx.x+lynx.width>o.x+4&&lynx.x<o.x+o.w-4&&lynx.y+lynx.height>=o.y&&lynx.y+lynx.height<=o.y+14){
                lynx.dy=-9; lynx.score+=80*combo.multiplier; registerComboHit(o.x,o.y);
                spawnDeath('bear_spin',o.x,o.y); spawnParticles(o.x+o.w/2,o.y,"#5C3A1E",10);
                playSfx('coin',0.3); o.x=-9999; continue;
            }
        } else if(o.type==='goat'&&!o.dead){
            let d=D();
            let invincible=lynx.starTimer>0||lynx.dashActive;
            // Init base speed once
            if(!o._baseSpeed) o._baseSpeed=o.speed;
            let distToLynx=lynx.x-(o.x+o.w/2);
            // Charge cooldown countdown
            if(o.chargeCooldown>0) o.chargeCooldown--;
            // Detect lynx in range → charge
            if(o.state==='patrol'&&o.chargeCooldown===0&&Math.abs(distToLynx)<280){
                o.state='charge';
                o.chargeDir=distToLynx>0?1:-1;
                o.dir=o.chargeDir;
                o.speed=o.chargeSpeed*d.enemySpeed;
            }
            // Charging — reset when past patrol zone
            if(o.state==='charge'){
                if(o.x>o.patrolMax+120||o.x<o.patrolMin-120){
                    o.state='patrol'; o.speed=o._baseSpeed*d.enemySpeed;
                    o.chargeCooldown=90; // brief pause before charging again
                    o.x=Math.max(o.patrolMin,Math.min(o.patrolMax,o.x));
                }
            }
            if(o.state==='patrol'){if(o.x>o.patrolMax)o.dir=-1;if(o.x<o.patrolMin)o.dir=1;}
            o.x+=o.speed*(o.state==='charge'?o.chargeDir:o.dir);
            o.y=groundAt(o.x+o.w/2)-o.h;
            drawGoat(o);
            // Dash attack kills goat
            if(lynx.dashActive&&checkDashAttack(o)){
                spawnDeath('rat_squash',o.x+o.w/2,o.y+o.h/2);
                o.dead=true; continue;
            }
            // Stomp goat
            if(!invincible&&lynx.dy>0&&lynx.x+lynx.width>o.x+4&&lynx.x<o.x+o.w-4&&lynx.y+lynx.height>=o.y&&lynx.y+lynx.height<=o.y+14){
                lynx.dy=-10; lynx.score+=100*combo.multiplier; registerComboHit(o.x,o.y);
                spawnParticles(o.x+o.w/2,o.y,"#DDCCAA",12);
                spawnDeath('rat_squash',o.x+o.w/2,o.y+o.h/2);
                playSfx('coin',0.35); o.dead=true; continue;
            }
            if(!lynx.isHit&&!invincible)checkHit(o);
        } else if(o.type==='eagle'&&!o.dead){
            let invincible=lynx.starTimer>0||lynx.dashActive;
            if(o.state==='circle'){
                o.x+=o.vx; o.y=o.baseY+Math.sin(frameCount*0.04+o.x*0.002)*18;
                if(o.x<-100) o.x=MTN_LEN+100;
                if(o.x>MTN_LEN+100) o.x=-100;
                let dx=Math.abs(lynx.x-(o.x+o.w/2));
                if(dx<200&&lynx.y>o.y+30){
                    o.state='dive'; o.diveTimer=0;
                    o.vx=(lynx.x-o.x)*0.045;
                    o.vy=4;
                }
            } else if(o.state==='dive'){
                o.x+=o.vx; o.y+=o.vy; o.vy+=0.3;
                o.diveTimer=(o.diveTimer||0)+1;
                let gnd=groundAt(o.x+o.w/2);
                if(o.y>gnd-10||o.diveTimer>90){
                    o.state='pullup'; o.vy=-5;
                }
            } else if(o.state==='pullup'){
                o.x+=o.vx*1.4; o.y+=o.vy; o.vy+=0.2;
                if(o.y<=o.baseY){o.y=o.baseY;o.vy=0;o.state='circle';o.vx=o.vx>0?1.8:-1.8;}
            }
            drawEagle(o);
            // Dash attack kills eagle (any state)
            if(lynx.dashActive&&checkDashAttack(o)){
                spawnParticles(o.x+o.w/2,o.y,"#8B4513",14);
                spawnParticles(o.x+o.w/2,o.y,"#FFFFFF",8);
                spawnDeath('drone_spark',o.x+o.w/2,o.y);
                o.dead=true; continue;
            }
            // Stomp eagle from above
            if(!invincible&&lynx.dy>0&&lynx.x+lynx.width>o.x+4&&lynx.x<o.x+o.w-4&&lynx.y+lynx.height>=o.y&&lynx.y+lynx.height<=o.y+14){
                lynx.dy=-10; lynx.score+=120*combo.multiplier; registerComboHit(o.x,o.y);
                spawnParticles(o.x+o.w/2,o.y,"#8B4513",14);
                spawnParticles(o.x+o.w/2,o.y,"#FFFFFF",8);
                spawnDeath('drone_spark',o.x+o.w/2,o.y);
                playSfx('coin',0.4); o.dead=true; continue;
            }
            // Eagle body hits lynx (only during dive)
            if(!lynx.isHit&&!invincible&&o.state==='dive'){
                if(lynx.x<o.x+o.w&&lynx.x+lynx.width>o.x&&lynx.y<o.y+o.h&&lynx.y+lynx.height>o.y){
                    lynx.hp--;lynx.isHit=true;triggerShake(7);playSfx('hit',0.6);
                    combo.count=0;combo.multiplier=1;combo.timer=0;
                    lynx.dy=-8;lynx.x+=lynx.x<o.x+o.w/2?-22:22;
                    setTimeout(()=>{lynx.isHit=false;},D().hitInvincTime);
                    if(lynx.hp<=0){playSfx('die',0.8);gameState="gameover";}
                }
            }
        }
        if(!lynx.isHit&&o.type!=='tree'&&o.type!=='goat'&&o.type!=='eagle'&&!(lynx.starTimer>0||lynx.dashActive))checkHit(o);
    }
    if(lynx.isClimbing){
        ctx.fillStyle="rgba(0,0,0,0.5)";ctx.fillRect(W/2-100,8,200,26);
        ctx.fillStyle="#FFD700";ctx.font="bold 14px Arial";ctx.textAlign="center";
        ctx.fillText("🌲 CLIMBING – ↑↓ move, SPACE leap",W/2,26);ctx.textAlign="left";
    }
    checkCoinCollect(); checkPowerUpCollect(); checkCheckpointTouch();
    drawCoins(); drawPowerUps(); updateParticles(); updateDeathAnims(); updateCombo();
    updateDrawWeather('snow');
    if(lynx.starTimer>0){ctx.fillStyle=`hsla(${frameCount*15%360},100%,55%,0.35)`;ctx.fillRect(lynx.x-cameraX-4,lynx.y-4,lynx.width+8,lynx.height+8);}
    drawLynx(); drawHUD("LEVEL 2 – MOUNTAIN","#7ECEF4"); drawAbilityHUD(); drawHpBar();
    drawMiniMap(MTN_LEN, mtnObjs);
    ctx.restore();
    if(lynx.x>=MTN_FLAG_X-50){levelScores[2]=lynx.score-levelScores[1];gameState="levelclear";saveGame();}
}

function drawRidge(p,color,pts){
    ctx.fillStyle=color; ctx.beginPath();
    ctx.moveTo(-cameraX*p+pts[0][0],pts[0][1]);
    for(let pt of pts)ctx.lineTo(-cameraX*p+pt[0],pt[1]);
    ctx.lineTo(-cameraX*p+pts[pts.length-1][0],H); ctx.lineTo(-cameraX*p+pts[0][0],H);
    ctx.closePath(); ctx.fill();
}
function drawTree(o){
    let sx=o.x-cameraX,sy=o.y;
    ctx.fillStyle="#5C3A10";ctx.fillRect(sx,sy,TW,TH);
    ctx.fillStyle="#1A5C1A";ctx.beginPath();ctx.arc(sx+TW/2,sy-12,TR,0,Math.PI*2);ctx.fill();
    ctx.fillStyle="#228B22";ctx.beginPath();ctx.arc(sx+TW/2,sy-30,TR*.75,0,Math.PI*2);ctx.fill();
    ctx.fillStyle="#32CD32";ctx.beginPath();ctx.arc(sx+TW/2,sy-46,TR*.5,0,Math.PI*2);ctx.fill();
    if(!lynx.isClimbing&&Math.abs(lynx.x+lynx.width/2-(o.x+TW/2))<42){
        ctx.fillStyle="rgba(255,255,255,0.9)";ctx.font="bold 11px Arial";ctx.fillText("↑ CLIMB",sx-6,sy-62);
    }
}
function drawRock(o){
    let sx=o.x-cameraX;
    ctx.fillStyle="rgba(0,0,0,0.12)";ctx.beginPath();ctx.ellipse(sx+o.w/2,o.y+o.h+4,o.w/2+5,6,0,0,Math.PI*2);ctx.fill();
    ctx.fillStyle="#7A7A7A";ctx.beginPath();ctx.moveTo(sx+9,o.y);ctx.lineTo(sx+o.w-6,o.y+5);ctx.lineTo(sx+o.w,o.y+o.h);ctx.lineTo(sx,o.y+o.h);ctx.lineTo(sx+3,o.y+9);ctx.closePath();ctx.fill();
    ctx.fillStyle="#AAA";ctx.beginPath();ctx.ellipse(sx+11,o.y+9,8,5,-0.3,0,Math.PI*2);ctx.fill();
}
function drawBear(o){
    let sx=o.x-cameraX,sy=o.y;
    ctx.save();
    if(o.dir<0){ctx.translate(sx+o.w,sy);ctx.scale(-1,1);ctx.translate(-o.w,0);}else ctx.translate(sx,sy);
    ctx.fillStyle="#5C3A1E";ctx.fillRect(4,6,28,20);
    ctx.fillStyle="#6B4226";ctx.beginPath();ctx.arc(28,10,12,0,Math.PI*2);ctx.fill();
    ctx.fillStyle="#5C3A1E";ctx.beginPath();ctx.arc(22,2,5,0,Math.PI*2);ctx.fill();ctx.beginPath();ctx.arc(34,2,5,0,Math.PI*2);ctx.fill();
    ctx.fillStyle="white";ctx.beginPath();ctx.arc(32,9,3,0,Math.PI*2);ctx.fill();
    ctx.fillStyle="#222";ctx.beginPath();ctx.arc(33,9,1.5,0,Math.PI*2);ctx.fill();
    ctx.fillStyle="#A0624A";ctx.beginPath();ctx.ellipse(36,13,5,3.5,0,0,Math.PI*2);ctx.fill();
    ctx.fillStyle="#5C3A1E";
    let ls=Math.sin(frameCount*.18)*4;
    ctx.fillRect(6,24+ls,8,9);ctx.fillRect(18,24-ls,8,9);
    ctx.restore();
}

function drawEagle(o){
    if(o.dead) return;
    let sx=o.x-cameraX, sy=o.y;
    if(sx<-60||sx>W+60) return;
    ctx.save();
    let facing=o.vx>=0?1:-1;
    ctx.translate(sx+16, sy+11);
    if(facing<0){ctx.scale(-1,1);}
    // Body
    ctx.fillStyle="#5C3A00"; ctx.beginPath(); ctx.ellipse(0,0,14,7,0,0,Math.PI*2); ctx.fill();
    // Head
    ctx.fillStyle="#7A5000"; ctx.beginPath(); ctx.arc(12,0,7,0,Math.PI*2); ctx.fill();
    // Yellow beak
    ctx.fillStyle="#FFB300"; ctx.beginPath(); ctx.moveTo(18,-2); ctx.lineTo(24,0); ctx.lineTo(18,2); ctx.closePath(); ctx.fill();
    // Eye
    ctx.fillStyle="white"; ctx.beginPath(); ctx.arc(14,-1,2.5,0,Math.PI*2); ctx.fill();
    ctx.fillStyle="#111";  ctx.beginPath(); ctx.arc(15,-1,1.2,0,Math.PI*2); ctx.fill();
    // Wings — flap on circle state
    let flap = o.state==='circle' ? Math.sin(frameCount*0.25)*12 : -4;
    ctx.fillStyle="#3A2200";
    ctx.beginPath(); ctx.moveTo(-4,-2); ctx.lineTo(-18,-12+flap); ctx.lineTo(-6,2); ctx.closePath(); ctx.fill();
    ctx.beginPath(); ctx.moveTo(-4,2);  ctx.lineTo(-18, 12-flap); ctx.lineTo(-6,-2); ctx.closePath(); ctx.fill();
    // Talons
    ctx.fillStyle="#FFB300";
    ctx.fillRect(-4,6,3,5); ctx.fillRect(2,6,3,5);
    ctx.restore();
}

function drawGoat(o){
    if(o.dead) return;
    let sx=o.x-cameraX, sy=o.y;
    if(sx<-60||sx>W+60) return;
    ctx.save();
    if(o.dir<0){ctx.translate(sx+o.w,sy);ctx.scale(-1,1);ctx.translate(-o.w,0);}
    else ctx.translate(sx,sy);
    // Body
    ctx.fillStyle="#E8E8DC"; ctx.fillRect(2,8,26,16);
    // Head
    ctx.fillStyle="#F0F0E0"; ctx.fillRect(22,2,12,14);
    // Horns (curved)
    ctx.strokeStyle="#C8B88A"; ctx.lineWidth=3;
    ctx.beginPath(); ctx.moveTo(26,2); ctx.quadraticCurveTo(24,-8,20,-6); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(30,2); ctx.quadraticCurveTo(32,-8,36,-6); ctx.stroke();
    // Eye
    ctx.fillStyle="#222"; ctx.beginPath(); ctx.arc(31,7,2,0,Math.PI*2); ctx.fill();
    ctx.fillStyle="white"; ctx.beginPath(); ctx.arc(32,6,0.8,0,Math.PI*2); ctx.fill();
    // Nose
    ctx.fillStyle="#FFAAAA"; ctx.beginPath(); ctx.ellipse(34,10,3,2,0,0,Math.PI*2); ctx.fill();
    // Legs — animate when charging
    let ls = o.state==='charge' ? Math.sin(frameCount*0.35)*5 : Math.sin(frameCount*0.12)*3;
    ctx.fillStyle="#D0D0C0";
    ctx.fillRect(4,22+ls,6,8); ctx.fillRect(12,22-ls,6,8);
    ctx.fillRect(20,22+ls,6,8);
    // Red eyes when charging
    if(o.state==='charge'){
        ctx.fillStyle="red"; ctx.beginPath(); ctx.arc(31,7,2.5,0,Math.PI*2); ctx.fill();
        ctx.shadowColor="#FF0000"; ctx.shadowBlur=8;
        ctx.strokeStyle="#FF4400"; ctx.lineWidth=2;
        ctx.beginPath(); ctx.moveTo(22,2); ctx.quadraticCurveTo(20,-9,16,-7); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(30,2); ctx.quadraticCurveTo(32,-9,36,-7); ctx.stroke();
        ctx.shadowBlur=0;
    }
    ctx.restore();
}

// ═══════════════════════════════════════════════════════════════
//  ██  LEVEL 3 — NEON CITY
// ═══════════════════════════════════════════════════════════════
const CITY_LEN=7000;
const CITY_BOSS_X=6000; // Cyber Guardian arena
const cityTerrain=(function(){
    let arr=[]; arr.push({x:0,y:350,w:CITY_LEN,isGround:true});
    for(let r of[[300,280,120],[500,240,100],[700,300,80],[950,260,110],[1150,220,90],[1400,290,130],[1600,250,100],[1850,270,120],[2050,230,90],[2300,260,110],[2500,210,100],[2750,280,130],[2950,240,90],[3200,300,110],[3450,260,120],[3700,220,100],[3950,270,130],[4200,240,90],[4450,290,120],[4650,250,100],[4900,210,110],[5150,270,130],[5400,230,90],[5650,280,120],[5900,250,100],[6150,300,110],[6400,260,130],[6650,220,90]])arr.push({x:r[0],y:r[1],w:r[2],isRoof:true});
    return arr;
})();
const cityObjs=(function(){
    let arr=[];
    for(let d of[[400,200,300,450,1,2],[800,200,700,900,-1,2.5],[1300,180,1150,1450,1,2],[1700,190,1600,1850,-1,2.5],[2100,170,2000,2250,1,3],[2600,185,2500,2700,-1,3],[3100,175,3000,3200,1,3.5],[3600,180,3500,3750,-1,3.5],[4100,170,4000,4250,1,4],[4600,185,4500,4700,-1,4],[5100,175,5000,5250,1,4],[5600,180,5500,5750,-1,4]])arr.push({type:'drone',x:d[0],y:d[1],patrolMin:d[2],patrolMax:d[3],dir:d[4],speed:d[5],w:36,h:18});
    for(let b of[[600,500,700,1,2],[1100,1000,1200,-1,2.5],[1600,1500,1700,1,2.5],[2200,2100,2300,-1,3],[2800,2700,2900,1,3],[3400,3300,3500,-1,3.5],[4000,3900,4100,1,3.5],[4600,4500,4700,-1,4],[5200,5100,5300,1,4],[5800,5700,5900,-1,4]])arr.push({type:'bot',x:b[0],patrolMin:b[1],patrolMax:b[2],dir:b[3],speed:b[4],w:28,h:36,y:314});
    for(let bx of[450,950,1450,1950,2450,2950,3450,3950,4450,4950,5450,5950])arr.push({type:'barrier',x:bx,y:330,w:20,h:20});
    // Laser turrets — stationary, fire horizontally every ~120 frames
    for(let t of[[700,314],[1600,314],[2500,314],[3500,314],[4400,314],[5300,314]])
        arr.push({type:'turret',x:t[0],y:t[1],w:24,h:36,fireTimer:Math.floor(Math.random()*120),fireInterval:120,laser:null,laserLife:0,dir:1});
    return arr;
})();

// ─── CYBER GUARDIAN BOSS (City Level 3) ─────────────────────
let cityBoss={
    active:false,defeated:false,x:CITY_BOSS_X+150,y:280,w:68,h:90,
    hp:10,maxHp:10,phase:1,dir:1,speed:2.2,
    patrolMin:CITY_BOSS_X+40,patrolMax:CITY_BOSS_X+680,
    laserTimer:0,laserInterval:100,laser:null,  // laser: {startX,endX,y,life}
    spawnTimer:0,spawnInterval:220,             // spawns mini-drones in phase 2
    miniDrones:[],
    isHit:false,hitFlash:0
};

function activateCityBoss() {
    if (!cityBoss.active&&!cityBoss.defeated&&lynx.x>CITY_BOSS_X-120) {
        let d=D();
        cityBoss.active=true;
        cityBoss.hp=d.bossHp+2; cityBoss.maxHp=d.bossHp+2;
        cityBoss.laserInterval=d.bossThrowInterval+10;
        cityBoss.speed=2.2*d.bossSpeed;
    }
}

function updateCityBoss(nAlpha) {
    if (!cityBoss.active||cityBoss.defeated) return;
    if (cityBoss.hp<=Math.floor(cityBoss.maxHp/2)) cityBoss.phase=2;

    // Move
    let spd=cityBoss.speed*(cityBoss.phase===2?1.5:1);
    cityBoss.x+=spd*cityBoss.dir; cityBoss.y=320-cityBoss.h;
    if (cityBoss.x>cityBoss.patrolMax) cityBoss.dir=-1;
    if (cityBoss.x<cityBoss.patrolMin) cityBoss.dir=1;

    if (cityBoss.hitFlash>0) cityBoss.hitFlash--;

    // Laser sweep
    cityBoss.laserTimer++;
    let li=cityBoss.phase===2?Math.floor(cityBoss.laserInterval*0.55):cityBoss.laserInterval;
    if (cityBoss.laserTimer>=li) {
        cityBoss.laserTimer=0;
        cityBoss.laser={x:cityBoss.x+cityBoss.w/2, y:cityBoss.y+cityBoss.h*0.4, life:40, dir:cityBoss.dir};
        triggerShake(4);
    }

    // Update laser
    if (cityBoss.laser) {
        cityBoss.laser.life--;
        let laserReach=240;
        let lx1=cityBoss.laser.x, lx2=lx1+cityBoss.laser.dir*laserReach;
        // Laser hits lynx
        let invincible=lynx.starTimer>0||lynx.dashActive;
        if (!lynx.isHit&&!invincible) {
            let ly=cityBoss.laser.y;
            if (lynx.x<Math.max(lx1,lx2)&&lynx.x+lynx.width>Math.min(lx1,lx2)&&lynx.y<ly+10&&lynx.y+lynx.height>ly-10) {
                lynx.hp--;lynx.isHit=true;triggerShake(9);playSfx('hit',0.6);
                combo.count=0;combo.multiplier=1;combo.timer=0;
                lynx.dy=-8;
                setTimeout(()=>{lynx.isHit=false;},D().hitInvincTime);
                if(lynx.hp<=0){playSfx('die',0.8);gameState="gameover";}
            }
        }
        if (cityBoss.laser.life<=0) cityBoss.laser=null;
    }

    // Phase 2: spawn mini drones
    if (cityBoss.phase===2) {
        cityBoss.spawnTimer++;
        if (cityBoss.spawnTimer>=cityBoss.spawnInterval) {
            cityBoss.spawnTimer=0;
            cityBoss.miniDrones.push({x:cityBoss.x+cityBoss.w/2,y:cityBoss.y-20,vx:(Math.random()-0.5)*3,vy:-2,life:180});
        }
    }

    // Update mini drones
    for (let i=cityBoss.miniDrones.length-1;i>=0;i--) {
        let md=cityBoss.miniDrones[i];
        // Home toward lynx
        let dx=lynx.x-md.x, dy=lynx.y-md.y;
        let dist=Math.sqrt(dx*dx+dy*dy)||1;
        md.vx+=dx/dist*0.15; md.vy+=dy/dist*0.15;
        let spd2=Math.sqrt(md.vx*md.vx+md.vy*md.vy);
        if(spd2>3){md.vx=md.vx/spd2*3;md.vy=md.vy/spd2*3;}
        md.x+=md.vx; md.y+=md.vy; md.life--;
        if (md.life<=0){cityBoss.miniDrones.splice(i,1);continue;}
        // Draw mini drone
        let msx=md.x-cameraX;
        ctx.fillStyle=`rgba(255,80,200,0.9)`;ctx.shadowColor="#FF00FF";ctx.shadowBlur=8;
        ctx.fillRect(msx-8,md.y-4,16,8);ctx.shadowBlur=0;
        // Hit lynx
        if (!lynx.isHit&&!(lynx.starTimer>0||lynx.dashActive)&&lynx.x<md.x+12&&lynx.x+lynx.width>md.x-12&&lynx.y<md.y+8&&lynx.y+lynx.height>md.y-8) {
            lynx.hp--;lynx.isHit=true;triggerShake(7);playSfx('hit',0.6);
            combo.count=0;combo.multiplier=1;combo.timer=0;
            lynx.dy=-8;
            setTimeout(()=>{lynx.isHit=false;},D().hitInvincTime);
            if(lynx.hp<=0){playSfx('die',0.8);gameState="gameover";}
            cityBoss.miniDrones.splice(i,1);continue;
        }
    }

    // Body contact
    if (!lynx.isHit&&!(lynx.starTimer>0||lynx.dashActive)&&lynx.x<cityBoss.x+cityBoss.w&&lynx.x+lynx.width>cityBoss.x&&lynx.y<cityBoss.y+cityBoss.h&&lynx.y+lynx.height>cityBoss.y) {
        lynx.hp--;lynx.isHit=true;triggerShake(10);playSfx('hit',0.6);
        combo.count=0;combo.multiplier=1;combo.timer=0;
        lynx.dy=-9;lynx.x+=lynx.x<cityBoss.x+cityBoss.w/2?-25:25;
        setTimeout(()=>{lynx.isHit=false;},D().hitInvincTime);
        if(lynx.hp<=0){playSfx('die',0.8);gameState="gameover";}
    }

    // Stomp head
    if (lynx.dy>0&&lynx.x+lynx.width>cityBoss.x+8&&lynx.x<cityBoss.x+cityBoss.w-8&&lynx.y+lynx.height>=cityBoss.y&&lynx.y+lynx.height<=cityBoss.y+18) {
        if (!cityBoss.isHit) {
            cityBoss.hp--;cityBoss.isHit=true;cityBoss.hitFlash=20;lynx.dy=-10;triggerShake(6);playSfx('boss',0.7);
            spawnParticles(cityBoss.x+cityBoss.w/2,cityBoss.y,"#00FFFF",16);
            registerComboHit(cityBoss.x+cityBoss.w/2,cityBoss.y);
            setTimeout(()=>{cityBoss.isHit=false;},500);
            if (cityBoss.hp<=0) {
                cityBoss.defeated=true;cityBoss.active=false;lynx.score+=800*combo.multiplier;
                spawnDeath('bot_fizzle',cityBoss.x+cityBoss.w/2,cityBoss.y+cityBoss.h/2);
                spawnParticles(cityBoss.x+cityBoss.w/2,cityBoss.y+cityBoss.h/2,"#FF00FF",40);
                spawnParticles(cityBoss.x+cityBoss.w/2,cityBoss.y+cityBoss.h/2,"#00FFFF",40);
                playSfx('win',0.6);
            }
        }
    }
}

function drawCityBossHP() {
    if (!cityBoss.active) return;
    let bw=200,bh=18,bx=W/2-bw/2,by=50;
    ctx.fillStyle="rgba(0,0,0,0.6)";ctx.fillRect(bx-4,by-4,bw+8,bh+8);
    ctx.fillStyle="#333";ctx.fillRect(bx,by,bw,bh);
    ctx.fillStyle=cityBoss.phase===2?"#FF0088":"#00AAFF";
    ctx.fillRect(bx,by,bw*(cityBoss.hp/cityBoss.maxHp),bh);
    ctx.strokeStyle="#FF00FF";ctx.lineWidth=2;ctx.shadowColor="#FF00FF";ctx.shadowBlur=8;ctx.strokeRect(bx,by,bw,bh);ctx.shadowBlur=0;
    ctx.fillStyle="white";ctx.font="bold 12px Arial";ctx.textAlign="center";
    ctx.fillText("⚡ CYBER GUARDIAN  "+cityBoss.hp+"/"+cityBoss.maxHp,W/2,by+13);
    ctx.textAlign="left";
}

function drawCyberGuardian(nAlpha) {
    if (cityBoss.defeated||!cityBoss.active) return;
    let sx=cityBoss.x-cameraX, sy=cityBoss.y;
    if (sx>W+100||sx+cityBoss.w<-100) return;
    let flash=cityBoss.hitFlash>0&&frameCount%4<2;
    let pulse=0.6+Math.sin(frameCount*0.15)*0.4;
    ctx.save();
    // Main body — armoured mech
    ctx.fillStyle=flash?"#FFFFFF":cityBoss.phase===2?"#1A0033":"#0A1A33";
    ctx.fillRect(sx+4,sy+28,60,62);
    // Armour plates
    ctx.strokeStyle=flash?"#FFFFFF":`rgba(0,255,255,${pulse})`;ctx.lineWidth=2;
    ctx.strokeRect(sx+6,sy+30,56,58);
    // Head visor
    ctx.fillStyle=flash?"#FFFFFF":"#0A0A2A";ctx.fillRect(sx+10,sy,48,30);
    ctx.fillStyle=flash?"#FF0000":cityBoss.phase===2?`rgba(255,0,100,${pulse})`:`rgba(0,200,255,${pulse})`;
    ctx.fillRect(sx+14,sy+8,40,14); // visor slit
    ctx.fillStyle="rgba(255,255,255,0.9)";ctx.fillRect(sx+14,sy+8,12,14); // glint
    // Shoulder cannons
    ctx.fillStyle=flash?"#FFFFFF":"#223344";
    ctx.fillRect(sx-12,sy+28,16,28);ctx.fillRect(sx+cityBoss.w-4,sy+28,16,28);
    ctx.fillStyle=flash?"#FFFFFF":`rgba(0,255,255,${pulse})`;
    ctx.fillRect(sx-14,sy+32,5,8);ctx.fillRect(sx+cityBoss.w+9,sy+32,5,8);
    // Legs animated
    let leg=Math.sin(frameCount*0.12)*5;
    ctx.fillStyle=flash?"#FFFFFF":"#112233";
    ctx.fillRect(sx+8,sy+86+leg,20,16);ctx.fillRect(sx+40,sy+86-leg,20,16);
    // Laser beam
    if (cityBoss.laser) {
        let la=cityBoss.laser.life/40;
        let lx1=cityBoss.laser.x-cameraX, lx2=lx1+cityBoss.laser.dir*240;
        ctx.strokeStyle=`rgba(255,0,200,${la})`;ctx.lineWidth=5;ctx.shadowColor="#FF00FF";ctx.shadowBlur=20;
        ctx.beginPath();ctx.moveTo(lx1,cityBoss.laser.y);ctx.lineTo(lx2,cityBoss.laser.y);ctx.stroke();
        ctx.lineWidth=2;ctx.strokeStyle=`rgba(255,200,255,${la})`;
        ctx.beginPath();ctx.moveTo(lx1,cityBoss.laser.y);ctx.lineTo(lx2,cityBoss.laser.y);ctx.stroke();
        ctx.shadowBlur=0;
    }
    ctx.restore();
}

let cityNeonPulse=0;

function updateDrawCity(){
    cityNeonPulse=(cityNeonPulse+0.04)%(Math.PI*2);
    let nAlpha=0.55+Math.sin(cityNeonPulse)*0.45;
    ctx.save(); ctx.translate(lynx.shakeX,lynx.shakeY);
    lynx.shakeX*=0.8; lynx.shakeY*=0.8;
    let sky=ctx.createLinearGradient(0,0,0,H);
    sky.addColorStop(0,"#050518");sky.addColorStop(0.6,"#0D0D2B");sky.addColorStop(1,"#1A0A2E");
    ctx.fillStyle=sky; ctx.fillRect(0,0,W,H);
    // Twinkling stars — varied sizes, random flicker phase per star
    for(let i=0;i<80;i++){
        let sx2=((42*37+i*173)%W);
        let sy2=((42*19+i*97)%130)+3;
        let br=0.3+Math.sin(frameCount*0.04+i*1.3)*0.7;
        let sz=i%7===0?2.5:i%3===0?1.5:1;
        ctx.globalAlpha=Math.max(0,br);
        ctx.fillStyle=i%5===0?"#AADDFF":i%4===0?"#FFCCFF":"white";
        ctx.fillRect(sx2,sy2,sz,sz);
    }
    ctx.globalAlpha=1;
    // Neon moon
    let moonX=120-cameraX*0.015, moonY=38;
    ctx.shadowColor="#AA88FF";ctx.shadowBlur=20;
    ctx.fillStyle="#EEE8FF";ctx.beginPath();ctx.arc(moonX,moonY,18,0,Math.PI*2);ctx.fill();
    ctx.fillStyle="#0D0D2B";ctx.beginPath();ctx.arc(moonX+7,moonY-3,14,0,Math.PI*2);ctx.fill();
    ctx.shadowBlur=0;
    drawClouds(CITY_LEN);
    drawCitySilhouette(0.2,"rgba(20,10,50,0.9)",80); drawCitySilhouette(0.45,"rgba(15,5,40,0.95)",60);
    drawAnimCityGround(nAlpha);
    for(let t of cityTerrain){
        if(!t.isRoof)continue;
        let sx2=t.x-cameraX; if(sx2>W+100||sx2+t.w<-100)continue;
        ctx.fillStyle="#2A1A4A";ctx.fillRect(sx2,t.y,t.w,12);
        ctx.strokeStyle=`rgba(255,0,255,${nAlpha})`;ctx.lineWidth=2;
        ctx.beginPath();ctx.moveTo(sx2,t.y);ctx.lineTo(sx2+t.w,t.y);ctx.stroke();
    }
    drawNeonFlag(CITY_LEN-150,260,nAlpha);
    drawCheckpoint();
    drawProgressBar(lynx.x,CITY_LEN);
    lynx.dy+=gravity; lynx.y+=lynx.dy;
    if(!lynx.dashActive){if(keys.ArrowLeft)lynx.x-=lynx.speed;if(keys.ArrowRight)lynx.x+=lynx.speed;}
    handleDash();
    lynx.x=Math.max(0,Math.min(CITY_LEN-lynx.width,lynx.x));
    let onPlatform=false;
    for(let t of cityTerrain){
        if(!t.isRoof)continue;
        if(lynx.x+lynx.width>t.x&&lynx.x<t.x+t.w){
            let top=t.y-lynx.height;
            if(lynx.y+lynx.height>=t.y&&lynx.y+lynx.height<=t.y+20&&lynx.dy>=0){lynx.y=top;lynx.dy=0;onPlatform=true;}
        }
    }
    if(!onPlatform){if(lynx.y>=350-lynx.height){lynx.y=350-lynx.height;lynx.dy=0;}}
    if(lynx.y>H+60){lynx.hp=0;gameState="gameover";}
    let tc=lynx.x-W*0.35; cameraX+=(tc-cameraX)*0.1;
    cameraX=Math.max(0,Math.min(CITY_LEN-W,cameraX));
    lynx.score+=Math.floor(lynx.speed*0.06);
    for(let o of cityObjs){
        if(o.x-cameraX>W+120||o.x+o.w-cameraX<-120)continue;
        if(o.type==='drone'){o.x+=o.speed*o.dir;if(o.x>o.patrolMax)o.dir=-1;if(o.x<o.patrolMin)o.dir=1;drawDrone(o,nAlpha);
            let inv=lynx.starTimer>0||lynx.dashActive;
            // Dash attack kills drone
            if(lynx.dashActive&&checkDashAttack(o)){
                spawnDeath('drone_spark',o.x+o.w/2,o.y+o.h/2);o.x=-9999;continue;
            }
            // Stomp drone
            if(!inv&&lynx.dy>0&&lynx.x+lynx.width>o.x+4&&lynx.x<o.x+o.w-4&&lynx.y+lynx.height>=o.y&&lynx.y+lynx.height<=o.y+12){
                lynx.dy=-9;lynx.score+=60*combo.multiplier;registerComboHit(o.x,o.y);
                spawnDeath('drone_spark',o.x+o.w/2,o.y+o.h/2);spawnParticles(o.x+o.w/2,o.y,"#00FFFF",10);
                playSfx('coin',0.3);o.x=-9999;continue;
            }
            if(!lynx.isHit&&!inv)checkHit(o);
        } else if(o.type==='bot'){o.x+=o.speed*o.dir;if(o.x>o.patrolMax)o.dir=-1;if(o.x<o.patrolMin)o.dir=1;drawBot(o,nAlpha);
            let inv=lynx.starTimer>0||lynx.dashActive;
            // Dash attack kills bot
            if(lynx.dashActive&&checkDashAttack(o)){
                spawnDeath('bot_fizzle',o.x+o.w/2,o.y+o.h/2);o.x=-9999;continue;
            }
            // Stomp bot
            if(!inv&&lynx.dy>0&&lynx.x+lynx.width>o.x+4&&lynx.x<o.x+o.w-4&&lynx.y+lynx.height>=o.y&&lynx.y+lynx.height<=o.y+14){
                lynx.dy=-9;lynx.score+=70*combo.multiplier;registerComboHit(o.x,o.y);
                spawnDeath('bot_fizzle',o.x+o.w/2,o.y+o.h/2);spawnParticles(o.x+o.w/2,o.y,"#FF00FF",10);
                playSfx('coin',0.3);o.x=-9999;continue;
            }
            if(!lynx.isHit&&!inv)checkHit(o);
        } else if(o.type==='barrier'){drawBarrier(o,nAlpha);if(!lynx.isHit&&!lynx.dashActive&&!lynx.starTimer)checkHit(o);}
        else if(o.type==='turret'){
            o.fireTimer++;
            if(o.fireTimer>=o.fireInterval){o.fireTimer=0;o.laserLife=30;}
            drawTurret(o,nAlpha);
            if(!lynx.isHit&&!(lynx.starTimer>0||lynx.dashActive))checkHit(o);
        }
    }
    checkCoinCollect(); checkPowerUpCollect(); checkCheckpointTouch();
    drawCoins(); drawPowerUps(); updateParticles(); updateDeathAnims(); updateCombo();
    updateDrawWeather('rain');
    if (Math.abs(CITY_BOSS_X-300-cameraX)<W) {
        let wx=CITY_BOSS_X-300-cameraX;
        ctx.shadowColor="#FF00FF";ctx.shadowBlur=8;
        ctx.fillStyle="#FF00FF";ctx.font="bold 14px Arial";
        ctx.fillText("⚡ CYBER GUARDIAN AHEAD ⚡",wx,310);
        ctx.shadowBlur=0;
    }
    activateCityBoss(); updateCityBoss(nAlpha); drawCityBossHP(); drawCyberGuardian(nAlpha);
    if(lynx.starTimer>0){ctx.fillStyle=`hsla(${frameCount*15%360},100%,55%,0.35)`;ctx.fillRect(lynx.x-cameraX-4,lynx.y-4,lynx.width+8,lynx.height+8);}
    drawLynx(); drawHUD("LEVEL 3 – NEON CITY","#FF00FF"); drawAbilityHUD(); drawHpBar();
    drawMiniMap(CITY_LEN, cityObjs);
    ctx.restore();
    if(lynx.x>=CITY_LEN-100){
        if(!cityBoss.defeated&&cityBoss.hp>0){}
        else{levelScores[3]=lynx.score-levelScores[1]-levelScores[2];gameState="win";saveScore(lynx.score);fetchLeaderboard();playSfx('win',0.8);saveGame();}
    }
}

function drawCitySilhouette(p,color,minH){
    let px=-cameraX*p; ctx.fillStyle=color; ctx.beginPath(); ctx.moveTo(0,H);
    for(let i=0;i<Math.ceil(W/60)+20;i++){let bx=px+i*60;let bh=minH+(((i*37)%80));ctx.lineTo(bx,H-bh);ctx.lineTo(bx+56,H-bh);}
    ctx.lineTo(W,H);ctx.closePath();ctx.fill();
}
function drawDrone(o,na){
    let sx=o.x-cameraX,sy=o.y;
    ctx.shadowColor="#00FFFF";ctx.shadowBlur=12;
    ctx.fillStyle=`rgba(0,200,220,${na})`;ctx.fillRect(sx,sy+6,o.w,8);ctx.fillRect(sx+14,sy,8,o.h);
    ctx.fillStyle=`rgba(0,255,255,${na})`;ctx.fillRect(sx-6,sy,10,4);ctx.fillRect(sx+o.w-4,sy,10,4);
    ctx.fillStyle="#FF0055";ctx.beginPath();ctx.arc(sx+o.w/2,sy+10,4,0,Math.PI*2);ctx.fill();
    ctx.shadowBlur=0;
}
function drawBot(o,na){
    let sx=o.x-cameraX,sy=o.y;
    ctx.shadowColor="#FF00FF";ctx.shadowBlur=10;
    ctx.fillStyle="#2A2A4A";ctx.fillRect(sx,sy,o.w,o.h);
    ctx.strokeStyle=`rgba(255,0,255,${na})`;ctx.lineWidth=2;ctx.strokeRect(sx,sy,o.w,o.h);
    ctx.fillStyle=`rgba(0,255,200,${na})`;ctx.fillRect(sx+4,sy+6,20,8);
    let ls=Math.sin(frameCount*.2)*3;
    ctx.fillStyle="#1A1A3A";ctx.fillRect(sx+3,sy+o.h,8,8+ls);ctx.fillRect(sx+17,sy+o.h,8,8-ls);
    ctx.shadowBlur=0;
}
function drawBarrier(o,na){
    let sx=o.x-cameraX;
    ctx.shadowColor="#FFFF00";ctx.shadowBlur=8;
    ctx.fillStyle="#1A1A00";ctx.fillRect(sx,o.y,o.w,o.h);
    for(let i=0;i<3;i++){ctx.fillStyle=(i%2===0)?`rgba(255,220,0,${na})`:"#333";ctx.fillRect(sx,o.y+i*(o.h/3),o.w,o.h/3);}
    ctx.shadowBlur=0;
}
function drawTurret(o,na){
    let sx=o.x-cameraX,sy=o.y;
    // Base
    ctx.fillStyle="#1A0A2A";ctx.fillRect(sx,sy+16,o.w,20);
    // Body
    ctx.fillStyle="#2A0A4A";ctx.fillRect(sx+2,sy+6,20,16);
    ctx.strokeStyle=`rgba(255,0,100,${na})`;ctx.lineWidth=1.5;ctx.strokeRect(sx+2,sy+6,20,16);
    // Barrel — points right
    ctx.fillStyle="#FF0055";ctx.shadowColor="#FF0055";ctx.shadowBlur=8;
    ctx.fillRect(sx+22,sy+12,14,5);
    ctx.shadowBlur=0;
    // Eye
    let eyePulse=0.5+Math.sin(frameCount*0.15)*0.5;
    ctx.fillStyle=`rgba(255,0,80,${eyePulse})`;
    ctx.beginPath();ctx.arc(sx+12,sy+14,4,0,Math.PI*2);ctx.fill();
    // Active laser beam
    if (o.laserLife>0) {
        let la=o.laserLife/30;
        let lx1=sx+o.w, lx2=lx1+320;
        ctx.strokeStyle=`rgba(255,0,80,${la})`;ctx.lineWidth=4;ctx.shadowColor="#FF0055";ctx.shadowBlur=16;
        ctx.beginPath();ctx.moveTo(lx1,sy+14);ctx.lineTo(lx2,sy+14);ctx.stroke();
        ctx.strokeStyle=`rgba(255,180,200,${la*0.6})`;ctx.lineWidth=1.5;
        ctx.beginPath();ctx.moveTo(lx1,sy+14);ctx.lineTo(lx2,sy+14);ctx.stroke();
        ctx.shadowBlur=0;
        o.laserLife--;
        // Hit lynx
        let invincible=lynx.starTimer>0||lynx.dashActive;
        if(!lynx.isHit&&!invincible){
            let worldLx1=o.x+o.w, worldLx2=worldLx1+320;
            let laserY=o.y+14;
            if(lynx.x<worldLx2&&lynx.x+lynx.width>worldLx1&&lynx.y<laserY+6&&lynx.y+lynx.height>laserY-6){
                lynx.hp--;lynx.isHit=true;triggerShake(8);playSfx('hit',0.6);
                combo.count=0;combo.multiplier=1;combo.timer=0;
                lynx.dy=-8;
                setTimeout(()=>{lynx.isHit=false;},D().hitInvincTime);
                if(lynx.hp<=0){playSfx('die',0.8);gameState="gameover";}
            }
        }
    }
    // Charge-up indicator
    let chargeRatio=o.fireTimer/o.fireInterval;
    ctx.fillStyle=`rgba(255,0,80,${chargeRatio*0.8})`;
    ctx.fillRect(sx,sy+36,o.w*chargeRatio,3);
}
function drawNeonFlag(wx,wy,na){
    let sx=wx-cameraX; if(sx<-60||sx>W+60)return;
    ctx.shadowColor="#FF00FF";ctx.shadowBlur=15;
    ctx.strokeStyle=`rgba(255,0,255,${na})`;ctx.lineWidth=3;
    ctx.beginPath();ctx.moveTo(sx,wy+80);ctx.lineTo(sx,wy);ctx.stroke();
    ctx.fillStyle=`rgba(255,0,255,${na})`;
    ctx.beginPath();ctx.moveTo(sx,wy);ctx.lineTo(sx+30,wy+14);ctx.lineTo(sx,wy+28);ctx.closePath();ctx.fill();
    ctx.fillStyle="white";ctx.font="bold 11px Arial";ctx.fillText("WIN!",sx+4,wy+18);ctx.shadowBlur=0;
}

// ═══════════════════════════════════════════════════════════════
//  SHARED HELPERS
// ═══════════════════════════════════════════════════════════════
function checkHit(o){
    if(lynx.isHit)return false;
    if(lynx.x<o.x+o.w&&lynx.x+lynx.width>o.x&&lynx.y<o.y+o.h&&lynx.y+lynx.height>o.y){
        // Dash attack — kill enemy instead of taking damage
        if(lynx.dashActive){
            lynx.score+=60*combo.multiplier;
            registerComboHit(o.x+o.w/2,o.y);
            spawnParticles(o.x+o.w/2,o.y+o.h/2,"#FF8C00",12);
            spawnParticles(o.x+o.w/2,o.y+o.h/2,"#FFD700",8);
            playSfx('coin',0.4);
            return true; // signal caller to remove enemy
        }
        lynx.hp--;lynx.isHit=true;triggerShake(7);playSfx('hit',0.6);
        combo.count=0;combo.multiplier=1;combo.timer=0;
        lynx.dy=-8;lynx.x+=(lynx.x<o.x+o.w/2)?-22:22;
        setTimeout(()=>{lynx.isHit=false;},D().hitInvincTime);
        if(lynx.hp<=0){playSfx('die',0.8);gameState="gameover";}
    }
    return false;
}

function drawLynx(){
    let sx=lynx.x-cameraX,sy=lynx.y;
    let hit=lynx.isHit&&frameCount%10<5;
    if(currentLevel===3){ctx.shadowColor="#FF4500";ctx.shadowBlur=10;}
    if(lynx.dashActive){ctx.shadowColor="#FF8C00";ctx.shadowBlur=20;}
    ctx.fillStyle=hit?"red":lynx.color;ctx.fillRect(sx+4,sy+10,22,18);
    ctx.fillStyle=hit?"red":"#E05000";ctx.fillRect(sx+14,sy,16,16);
    ctx.fillStyle="#FF6A00";
    ctx.beginPath();ctx.moveTo(sx+15,sy);ctx.lineTo(sx+12,sy-8);ctx.lineTo(sx+19,sy-2);ctx.closePath();ctx.fill();
    ctx.beginPath();ctx.moveTo(sx+25,sy);ctx.lineTo(sx+29,sy-8);ctx.lineTo(sx+22,sy-2);ctx.closePath();ctx.fill();
    ctx.fillStyle="white";ctx.fillRect(sx+17,sy+4,5,5);
    ctx.fillStyle="#222";ctx.fillRect(sx+19,sy+5,3,3);
    ctx.beginPath();ctx.moveTo(sx+4,sy+15);ctx.quadraticCurveTo(sx-8,sy+5,sx-2,sy-2);
    ctx.lineWidth=5;ctx.strokeStyle="#FF4500";ctx.stroke();
    ctx.fillStyle="#E05000";
    let run=Math.sin(frameCount*.2)*4;
    ctx.fillRect(sx+6,sy+26+run,7,10);ctx.fillRect(sx+17,sy+26-run,7,10);
    if(lynx.isClimbing){ctx.fillStyle="#FF8C00";ctx.fillRect(sx-4,sy+8,8,6);ctx.fillRect(sx+26,sy+20,8,6);}
    ctx.shadowBlur=0;
}

function drawFlag(wx,wy,color,label){
    let sx=wx-cameraX; if(sx<-60||sx>W+60)return;
    ctx.strokeStyle="#555";ctx.lineWidth=4;
    ctx.beginPath();ctx.moveTo(sx,wy+80);ctx.lineTo(sx,wy);ctx.stroke();
    ctx.fillStyle=color;ctx.beginPath();ctx.moveTo(sx,wy);ctx.lineTo(sx+30,wy+14);ctx.lineTo(sx,wy+28);ctx.closePath();ctx.fill();
    ctx.fillStyle="white";ctx.font="bold 11px Arial";ctx.fillText(label,sx+4,wy+18);
}

function drawProgressBar(pos,total){
    let pct=Math.min(pos/total,1);
    ctx.fillStyle="rgba(0,0,0,0.4)";ctx.fillRect(W/2-150,8,300,10);
    ctx.fillStyle="#FFD700";ctx.fillRect(W/2-150,8,300*pct,10);
    ctx.fillStyle="#FF4500";ctx.fillRect(W/2-150+300*pct-4,6,8,14);
}

function drawHUD(levelLabel,accentColor){
    // Score panel
    ctx.fillStyle="rgba(0,0,0,0.5)";ctx.fillRect(W-170,H-32,160,24);
    ctx.fillStyle="white";ctx.font="bold 15px Arial";ctx.fillText("Score: "+lynx.score,W-165,H-13);
    // Level badge
    let bw=ctx.measureText(levelLabel).width+24;
    ctx.fillStyle="rgba(0,0,0,0.5)";ctx.fillRect(W/2-bw/2,22,bw,22);
    ctx.fillStyle=accentColor;ctx.font="bold 13px Arial";ctx.textAlign="center";
    ctx.fillText(levelLabel,W/2,38);ctx.textAlign="left";
    // Level score breakdown
    ctx.fillStyle="rgba(0,0,0,0.4)";ctx.fillRect(10,H-60,165,24);
    ctx.fillStyle="#FFD700";ctx.font="12px Arial";
    ctx.fillText("L1:"+levelScores[1]+" L2:"+levelScores[2]+" L3:"+levelScores[3],15,H-42);
    // Difficulty badge top-left
    let dcol=difficulty==="easy"?"#33FF66":difficulty==="hard"?"#FF4400":"#FFDD00";
    ctx.fillStyle="rgba(0,0,0,0.45)";ctx.fillRect(10,8,72,18);
    ctx.fillStyle=dcol;ctx.font="bold 11px Arial";
    ctx.fillText((difficulty==="easy"?"🟢 ":difficulty==="hard"?"🔴 ":"🟡 ")+difficulty.toUpperCase(),14,21);
}

function drawOverlay(title,tColor,line2,line3){
    ctx.fillStyle="rgba(0,0,0,0.72)";ctx.fillRect(100,110,600,180);
    ctx.textAlign="center";
    ctx.fillStyle=tColor;ctx.font="bold 44px Arial";ctx.fillText(title,W/2,170);
    ctx.fillStyle="white";ctx.font="20px Arial";ctx.fillText(line2,W/2,210);
    if(line3){ctx.fillStyle="#FFD700";ctx.font="16px Arial";ctx.fillText(line3,W/2,245);}
    ctx.textAlign="left";
}

// ═══════════════════════════════════════════════════════════════
//  RESET HELPERS
// ═══════════════════════════════════════════════════════════════
function resetAll(){
    let d=D();
    currentLevel=1;cameraX=0;frameCount=0;
    lynx.x=80;lynx.y=320;lynx.dy=0;lynx.hp=d.playerHp;lynx.maxHp=d.playerHp+2;lynx.score=0;
    lynx.isHit=false;lynx.isClimbing=false;lynx.climbTree=null;lynx.ground=350;
    lynx.coins=0;lynx.dashUnlocked=false;lynx.dashActive=false;lynx.dashCooldown=0;lynx.dashTimer=0;
    lynx.starTimer=0;lynx.magnetTimer=0;
    desertObjs=[];desertTimer=0;levelScores={1:0,2:0,3:0};
    // reset city boss
    cityBoss.active=false;cityBoss.defeated=false;cityBoss.hp=d.bossHp+2;cityBoss.maxHp=d.bossHp+2;
    cityBoss.x=CITY_BOSS_X+150;cityBoss.dir=1;cityBoss.phase=1;cityBoss.laserTimer=0;cityBoss.laser=null;cityBoss.spawnTimer=0;
    abilityFlash=0;particles=[];deathAnims=[];powerUps=[];
    combo.count=0;combo.multiplier=1;combo.timer=0;combo.floats=[];
    initClouds('desert');
    initWeather('sand', 80);
    resetCheckpoint(DESERT_LEN, ()=>350);
    placeLevelCoins(200,DESERT_LEN-200,()=>350,55);
    placePowerUps(400,DESERT_LEN-400,()=>350,6);
}

function goLevel2(){
    let d=D();
    currentLevel=2;cameraX=0;frameCount=0;
    lynx.x=80;lynx.y=320;lynx.dy=0;
    lynx.isHit=false;lynx.isClimbing=false;lynx.climbTree=null;lynx.ground=350;
    lynx.starTimer=0;lynx.magnetTimer=0;
    for(let o of mtnObjs){
        if(o.type==='bear'){
            o.x=o.patrolMin;o.y=groundAt(o.patrolMin)-o.h;
            if(!o._baseSpeed)o._baseSpeed=o.speed;
            o.speed=o._baseSpeed*d.enemySpeed;
        }
        if(o.type==='goat'){
            o.dead=false; o.state='patrol'; o.chargeCooldown=0;
            o.x=o.patrolMin; o.y=groundAt(o.patrolMin)-o.h;
            if(!o._baseSpeed)o._baseSpeed=o.speed;
            o.speed=o._baseSpeed*d.enemySpeed;
        }
        if(o.type==='eagle'){
            o.dead=false; o.state='circle'; o.y=o.baseY; o.vx=1.8; o.vy=0;
        }
    }
    initClouds('mountain');
    initWeather('snow', 60);
    resetCheckpoint(MTN_LEN, groundAt);
    placeLevelCoins(200,MTN_LEN-200,groundAt,50);
    placePowerUps(400,MTN_LEN-400,groundAt,5);
    particles=[];deathAnims=[];
    combo.count=0;combo.multiplier=1;combo.timer=0;combo.floats=[];
    playMusic('mountain');
}

function goLevel3(){
    let d=D();
    currentLevel=3;cameraX=0;frameCount=0;
    lynx.x=80;lynx.y=320;lynx.dy=0;
    lynx.isHit=false;lynx.isClimbing=false;lynx.climbTree=null;lynx.ground=350;
    lynx.starTimer=0;lynx.magnetTimer=0;
    for(let o of cityObjs){
        if(o.type==='bot'||o.type==='drone'){
            o.x=o.patrolMin;
            if(!o._baseSpeed) o._baseSpeed=o.speed;
            o.speed=o._baseSpeed*d.enemySpeed;
        }
    }
    // reset city boss
    cityBoss.active=false;cityBoss.defeated=false;cityBoss.hp=d.bossHp+2;cityBoss.maxHp=d.bossHp+2;
    cityBoss.x=CITY_BOSS_X+150;cityBoss.dir=1;cityBoss.phase=1;cityBoss.laserTimer=0;cityBoss.laser=null;cityBoss.spawnTimer=0;
    initClouds('city');
    initWeather('rain', 100);
    resetCheckpoint(CITY_LEN, ()=>310);
    placeLevelCoins(200,CITY_LEN-200,()=>310,45);
    placePowerUps(400,CITY_LEN-400,()=>310,5);
    particles=[];deathAnims=[];
    combo.count=0;combo.multiplier=1;combo.timer=0;combo.floats=[];
    playMusic('city');
}

// ═══════════════════════════════════════════════════════════════
//  MAIN LOOP
// ═══════════════════════════════════════════════════════════════
// ─── FIXED TIMESTEP LOOP ────────────────────────────────────
// Locks game logic to 60 updates/sec regardless of monitor refresh rate.
// This prevents the game running faster on 120/144Hz Windows displays.
const TARGET_FPS   = 60;
const STEP_MS      = 1000 / TARGET_FPS;  // 16.667 ms per logic tick
const MAX_CATCH_UP = 5;                  // never run more than 5 ticks at once
let   lastTime     = null;
let   accumulator  = 0;

function drawGame(timestamp) {
    if (lastTime === null) lastTime = timestamp;
    let elapsed = timestamp - lastTime;
    lastTime = timestamp;

    if (elapsed > STEP_MS * MAX_CATCH_UP) elapsed = STEP_MS * MAX_CATCH_UP;
    accumulator += elapsed;

    ctx.clearRect(0, 0, W, H);

    if (gameState === "playing") {
        // Fixed-timestep: run exactly as many 16.67ms ticks as elapsed time allows
        let ticked = false;
        while (accumulator >= STEP_MS) {
            accumulator -= STEP_MS;
            frameCount++;
            if (currentLevel===1) updateDrawDesert();
            else if (currentLevel===2) updateDrawMountain();
            else updateDrawCity();
            ticked = true;
        }
        // Safety: if no tick ran this frame, force one draw so canvas never goes blank
        if (!ticked) {
            if (currentLevel===1) updateDrawDesert();
            else if (currentLevel===2) updateDrawMountain();
            else updateDrawCity();
        }

    } else {
        // Non-playing states: advance frameCount at fixed rate for animations, then draw
        while (accumulator >= STEP_MS) {
            accumulator -= STEP_MS;
            frameCount++;
        }

        if (gameState === "start") {
            ctx.fillStyle="#87CEEB";ctx.fillRect(0,0,W,H);
            ctx.fillStyle="#C8963E";ctx.fillRect(0,355,W,H-355);
            drawLynx();
            ctx.fillStyle="rgba(0,0,0,0.65)";ctx.fillRect(80,28,640,88);
            ctx.textAlign="center";
            ctx.fillStyle="#FF8C00";ctx.font="bold 32px Arial";ctx.fillText("🐱 HIGH SPEED LYNX",W/2,64);
            ctx.fillStyle="white";ctx.font="15px Arial";ctx.fillText("Dodge enemies • Collect coins • Defeat 3 Bosses!",W/2,90);
            ctx.fillStyle="#88DDFF";ctx.font="13px Arial";ctx.fillText("→/← Move  •  SPACE Jump  •  Z Dash  •  Stomp enemies for bonus!",W/2,110);
            let diffs=[
                {key:"easy",  label:"🟢 EASY",   desc:"More HP • Slower enemies • No scorpion stings", col:"#22AA44", hi:"#33FF66"},
                {key:"normal",label:"🟡 NORMAL", desc:"Balanced fun", col:"#BB8800", hi:"#FFDD00"},
                {key:"hard",  label:"🔴 HARD",   desc:"Fast enemies • Less HP • Aggressive bosses", col:"#AA2200", hi:"#FF4400"},
            ];
            ctx.font="bold 14px Arial";
            diffs.forEach((d,i)=>{
                let bx=130+i*190, by=140, bw=170, bh=54;
                let selected=difficulty===d.key;
                ctx.fillStyle=selected?d.col:"rgba(0,0,0,0.55)";
                ctx.beginPath();ctx.roundRect(bx,by,bw,bh,10);ctx.fill();
                ctx.strokeStyle=selected?d.hi:"rgba(255,255,255,0.3)";
                ctx.lineWidth=selected?3:1.5;ctx.stroke();
                ctx.fillStyle=selected?d.hi:"#CCCCCC";ctx.font="bold 16px Arial";
                ctx.fillText(d.label,bx+bw/2,by+22);
                ctx.fillStyle="rgba(255,255,255,0.75)";ctx.font="11px Arial";
                ctx.fillText(d.desc,bx+bw/2,by+40);
            });
            ctx.fillStyle="rgba(0,0,0,0.55)";ctx.fillRect(160,206,480,60);
            ctx.fillStyle="#88FF88";ctx.font="13px Arial";ctx.fillText("🪙 Gold +10  •  🔵 Chip +50  •  ❤️ Core restores HP",W/2,226);
            ctx.fillStyle="#FF88FF";ctx.fillText("⭐ Star = Invincible 3s   •   🧲 Magnet = Auto-coins 5s",W/2,248);
            ctx.fillStyle="rgba(0,0,0,0.55)";ctx.fillRect(110,274,580,50);
            ctx.fillStyle="#FFDD66";ctx.font="bold 13px Arial";
            ctx.fillText("BOSS: ⚡ Cyber Guardian (L3 only)",W/2,294);
            let dcol=difficulty==="easy"?"#33FF66":difficulty==="hard"?"#FF4400":"#FFDD00";
            ctx.fillStyle=dcol;ctx.font="bold 14px Arial";
            ctx.fillText("Difficulty: "+difficulty.toUpperCase()+"  —  Click a button above to change  •  SPACE to start",W/2,316);
            let sg=loadGame();
            if(sg){
                let lvlName=sg.currentLevel===1?"Desert":sg.currentLevel===2?"Mountain":"Neon City";
                let savedDate="";
                try{let d=new Date(sg.savedAt);savedDate=" ("+d.toLocaleDateString('en-US',{month:'short',day:'numeric'})+")";}catch(ex){}
                ctx.fillStyle="rgba(0,180,100,0.22)";ctx.fillRect(195,322,410,28);
                ctx.strokeStyle="rgba(0,255,136,0.5)";ctx.lineWidth=1.5;ctx.strokeRect(195,322,410,28);
                ctx.fillStyle="#00FF88";ctx.font="bold 12px Arial";
                ctx.fillText("💾 CONTINUE:  Level "+sg.currentLevel+" ("+lvlName+")  •  Score: "+sg.score+"  •  Press C"+savedDate,W/2,340);
            } else {
                ctx.fillStyle="rgba(255,255,255,0.3)";ctx.font="11px Arial";
                ctx.fillText("No saved game — complete a level to auto-save",W/2,337);
            }
            ctx.textAlign="left";

        } else if(gameState==="intro"){
            if(currentLevel===1){ctx.fillStyle="#87CEEB";ctx.fillRect(0,0,W,H);ctx.fillStyle="#C8963E";ctx.fillRect(0,355,W,H-355);}
            else if(currentLevel===2){let sky=ctx.createLinearGradient(0,0,0,H);sky.addColorStop(0,"#4A90D9");sky.addColorStop(1,"#A8D5F0");ctx.fillStyle=sky;ctx.fillRect(0,0,W,H);}
            else{ctx.fillStyle="#050518";ctx.fillRect(0,0,W,H);}
            drawIntro();

        } else if(gameState==="levelclear"){
            if(currentLevel===1){ctx.fillStyle="#87CEEB";ctx.fillRect(0,0,W,H);ctx.fillStyle="#C8963E";ctx.fillRect(0,355,W,H-355);}
            else{let sky=ctx.createLinearGradient(0,0,0,H);sky.addColorStop(0,"#4A90D9");sky.addColorStop(1,"#A8D5F0");ctx.fillStyle=sky;ctx.fillRect(0,0,W,H);}
            drawLynx();
            let nextName=currentLevel===1?"⛰ Mountain Level 2":"🌆 Neon City Level 3";
            drawOverlay("✅  LEVEL "+currentLevel+" CLEAR!","#00EE55","Score: "+lynx.score+"  •  HP: "+"❤️".repeat(lynx.hp)+"  •  Coins: 🪙"+lynx.coins,"SPACE  →  "+nextName);

        } else if(gameState==="win"){
            let sky=ctx.createLinearGradient(0,0,0,H);sky.addColorStop(0,"#050518");sky.addColorStop(1,"#1A0A2E");
            ctx.fillStyle=sky;ctx.fillRect(0,0,W,H);
            drawLynx();
            ctx.fillStyle="rgba(0,0,0,0.72)";ctx.fillRect(80,80,640,310);
            ctx.textAlign="center";
            ctx.fillStyle="#FFD700";ctx.font="bold 44px Arial";ctx.fillText("🏆  YOU WIN!",W/2,130);
            ctx.fillStyle="white";ctx.font="18px Arial";ctx.fillText("L1:"+levelScores[1]+" + L2:"+levelScores[2]+" + L3:"+levelScores[3]+" = "+lynx.score,W/2,160);
            ctx.fillStyle="#FFD700";ctx.font="14px Arial";ctx.fillText("Score saved!  •  SPACE to play again",W/2,185);
            drawLeaderboard();
            ctx.textAlign="left";

        } else if(gameState==="gameover"){
            ctx.fillStyle=currentLevel===3?"#050518":"#87CEEB";ctx.fillRect(0,0,W,H);
            drawLynx();
            let line3=checkpoint.active
                ? "SPACE = restart from Level 1  •  R = respawn at checkpoint 🚩"
                : "SPACE to retry from Level 1";
            drawOverlay("GAME OVER!","#FF3333","Score: "+lynx.score+"  •  Coins collected: 🪙"+lynx.coins,line3);
            if(checkpoint.active){
                ctx.textAlign="center";
                ctx.fillStyle="rgba(0,255,136,0.9)";ctx.font="bold 13px Arial";
                ctx.fillText("Checkpoint saved at midpoint — press R to resume from there!",W/2,310);
                ctx.textAlign="left";
            }
        }
    }

    requestAnimationFrame(drawGame);
}

// Checkpoint respawn helper
function respawnAtCheckpoint(){
    lynx.x=checkpoint.x;
    lynx.y=checkpoint.y+20;
    lynx.dy=0;lynx.isHit=false;lynx.isClimbing=false;lynx.climbTree=null;
    lynx.hp=Math.max(1,checkpoint.respawnHp);
    lynx.starTimer=0;lynx.magnetTimer=0;
    cameraX=Math.max(0,checkpoint.x-W*0.4);
    particles=[];deathAnims=[];
    combo.count=0;combo.multiplier=1;combo.timer=0;combo.floats=[];
    gameState="playing";
}

// Continue from saved game
function continueFromSave(){
    let sg=loadGame();
    if(!sg) return;
    difficulty=sg.difficulty||"normal";
    levelScores=sg.levelScores||{1:0,2:0,3:0};
    // Jump to the saved level
    if(sg.currentLevel===1){
        resetAll();
        lynx.score=sg.score||0;
        lynx.hp=sg.hp||3;
        lynx.coins=sg.coins||0;
        lynx.dashUnlocked=sg.dashUnlocked||false;
        startIntro("LEVEL 1 – DESERT","#C8963E");
    } else if(sg.currentLevel===2){
        resetAll();
        goLevel2();
        lynx.score=sg.score||0;
        lynx.hp=sg.hp||3;
        lynx.coins=sg.coins||0;
        lynx.dashUnlocked=sg.dashUnlocked||false;
        startIntro("LEVEL 2 – MOUNTAIN","#4A90D9");
    } else if(sg.currentLevel>=3){
        resetAll();
        goLevel2();
        goLevel3();
        lynx.score=sg.score||0;
        lynx.hp=sg.hp||3;
        lynx.coins=sg.coins||0;
        lynx.dashUnlocked=sg.dashUnlocked||false;
        startIntro("LEVEL 3 – NEON CITY","#1A0A2E");
    }
}

// Init — wrapped in try/catch so any startup error shows on canvas
try {
    resetAll();
    requestAnimationFrame(drawGame);
} catch(startErr) {
    ctx.fillStyle="#FFF2CC"; ctx.fillRect(0,0,W,H);
    ctx.fillStyle="#CC0000"; ctx.font="bold 18px Arial"; ctx.textAlign="center";
    ctx.fillText("STARTUP ERROR:", W/2, 60);
    ctx.fillStyle="#333"; ctx.font="14px Arial";
    ctx.fillText(String(startErr), W/2, 100);
    ctx.fillText("Please screenshot this and share it.", W/2, 130);
    ctx.textAlign="left";
}

// ═══════════════════════════════════════════════════════════════
//  INPUT
// ═══════════════════════════════════════════════════════════════
document.addEventListener("keydown",function(e){
    if(e.code==="Space"){
        e.preventDefault();
        if(gameState==="start"||gameState==="gameover"){resetAll();startIntro("LEVEL 1 – DESERT","#C8963E");}
        else if(gameState==="levelclear"){
            if(currentLevel===1){goLevel2();startIntro("LEVEL 2 – MOUNTAIN","#4A90D9");}
            else{goLevel3();startIntro("LEVEL 3 – NEON CITY","#1A0A2E");}
        }
        else if(gameState==="win"){deleteSave();resetAll();gameState="start";}
        else if(gameState==="playing"){
            if(lynx.isClimbing){lynx.isClimbing=false;lynx.climbTree=null;lynx.dy=lynx.jumpPower*0.85;playSfx('jump',0.5);}
            else if(currentLevel===1){if(lynx.y>=lynx.ground){lynx.dy=lynx.jumpPower;playSfx('jump',0.5);}}
            else if(currentLevel===2){let gnd=groundAt(lynx.x+lynx.width/2)-lynx.height;if(lynx.y>=gnd-2){lynx.dy=lynx.jumpPower;playSfx('jump',0.5);}}
            else{let onFloor=(lynx.y>=318);let onRoof=cityTerrain.some(t=>t.isRoof&&lynx.x+lynx.width>t.x&&lynx.x<t.x+t.w&&Math.abs(lynx.y-(t.y-lynx.height))<3);if(onFloor||onRoof){lynx.dy=lynx.jumpPower;playSfx('jump',0.5);}}
        }
    }
    if(e.code==="KeyZ"){e.preventDefault();keys.KeyZ=true;}
    if(e.code==="KeyR"){e.preventDefault();if(gameState==="gameover"&&checkpoint.active)respawnAtCheckpoint();}
    if(e.code==="KeyC"){e.preventDefault();if(gameState==="start"&&loadGame())continueFromSave();}
    if(e.code==="ArrowLeft"){e.preventDefault();keys.ArrowLeft=true;}
    if(e.code==="ArrowRight"){e.preventDefault();keys.ArrowRight=true;}
    if(e.code==="ArrowUp"){e.preventDefault();keys.ArrowUp=true;}
    if(e.code==="ArrowDown"){e.preventDefault();keys.ArrowDown=true;}
});
document.addEventListener("keyup",function(e){
    if(e.code==="KeyZ")keys.KeyZ=false;
    if(e.code==="ArrowLeft")keys.ArrowLeft=false;
    if(e.code==="ArrowRight")keys.ArrowRight=false;
    if(e.code==="ArrowUp")keys.ArrowUp=false;
    if(e.code==="ArrowDown")keys.ArrowDown=false;
});

// Mouse click — difficulty buttons on start screen
canvas.addEventListener("click",function(e){
    if(gameState!=="start") return;
    let rect=canvas.getBoundingClientRect();
    let scaleX=800/rect.width, scaleY=400/rect.height;
    let mx=(e.clientX-rect.left)*scaleX;
    let my=(e.clientY-rect.top)*scaleY;
    // Difficulty buttons
    let diffs=["easy","normal","hard"];
    diffs.forEach((d,i)=>{
        let bx=130+i*190, by=140, bw=170, bh=54;
        if(mx>=bx&&mx<=bx+bw&&my>=by&&my<=by+bh){ difficulty=d; resetAll(); }
    });
    // Continue button (save slot bar)
    if(mx>=195&&mx<=605&&my>=322&&my<=350&&loadGame()){
        continueFromSave();
    }
});

// ═══════════════════════════════════════════════════════════════
//  TOUCH CONTROLS
// ═══════════════════════════════════════════════════════════════
if ('ontouchstart' in window) {
    document.getElementById('touchControls').style.display='flex';
    function tcBind(id, down, up) {
        let el=document.getElementById(id);
        el.addEventListener('touchstart',e=>{e.preventDefault();down();},{passive:false});
        el.addEventListener('touchend',  e=>{e.preventDefault();up();  },{passive:false});
        el.addEventListener('touchcancel',e=>{e.preventDefault();up(); },{passive:false});
    }
    tcBind('tc-left', ()=>{keys.ArrowLeft=true;}, ()=>{keys.ArrowLeft=false;});
    tcBind('tc-right',()=>{keys.ArrowRight=true;},()=>{keys.ArrowRight=false;});
    tcBind('tc-up',   ()=>{keys.ArrowUp=true;},  ()=>{keys.ArrowUp=false;});
    tcBind('tc-dash', ()=>{keys.KeyZ=true;},      ()=>{keys.KeyZ=false;});
    tcBind('tc-jump', ()=>{
        document.dispatchEvent(new KeyboardEvent('keydown',{code:'Space',bubbles:true}));
    }, ()=>{});
}
</script>
</body>
</html>