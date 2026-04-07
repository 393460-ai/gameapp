<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>About - High Speed Lynx</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body {
      background-color: #FFF2CC;
    }
    .about-wrap {
      max-width: 680px;
      margin: 0 auto;
      padding: 2rem 1rem;
      font-family: sans-serif;
      color: #1a1a1a;
    }
    .lynx-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #ff8c00;
      color: #fff;
      font-size: 13px;
      font-weight: 600;
      padding: 4px 14px;
      border-radius: 20px;
      margin-bottom: 1.5rem;
    }
    .lynx-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #ffe066;
      display: inline-block;
    }
    h1.lynx-title {
      font-size: 28px;
      font-weight: 600;
      margin: 0 0 0.4rem;
      color: #1a1a1a;
    }
    .lynx-sub {
      font-size: 15px;
      color: #666;
      margin: 0 0 2rem;
    }
    .divider {
      border: none;
      border-top: 1px solid #e5e5e5;
      margin: 2rem 0;
    }
    .section-label {
      font-size: 11px;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #999;
      margin: 0 0 1rem;
    }
    .card {
      background: #fff;
      border: 1px solid #e5e5e5;
      border-radius: 12px;
      padding: 1.25rem;
      margin-bottom: 1rem;
    }
    .controls-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }
    .control-pill {
      background: #f7f7f7;
      border-radius: 8px;
      padding: 10px 14px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .control-key {
      background: #ff8c00;
      color: #fff;
      font-size: 11px;
      font-weight: 600;
      padding: 3px 8px;
      border-radius: 5px;
      white-space: nowrap;
    }
    .control-desc {
      font-size: 13px;
      color: #555;
    }
    .controls-note {
      font-size: 13px;
      color: #666;
      margin: 1rem 0 0;
    }
    .tool-row {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 10px 0;
      border-bottom: 1px solid #e5e5e5;
    }
    .tool-row:last-child {
      border-bottom: none;
      padding-bottom: 0;
    }
    .tool-icon {
      width: 36px;
      height: 36px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      flex-shrink: 0;
    }
    .tool-name {
      font-size: 14px;
      font-weight: 600;
      color: #1a1a1a;
      margin: 0 0 2px;
    }
    .tool-desc {
      font-size: 13px;
      color: #666;
      margin: 0;
    }
    .credit-block {
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .avatar {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: #ff8c00;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: 16px;
      color: #fff;
      flex-shrink: 0;
    }
    .credit-name {
      font-size: 18px;
      font-weight: 600;
      color: #1a1a1a;
      margin: 0 0 2px;
    }
    .credit-role {
      font-size: 13px;
      color: #666;
      margin: 0;
    }
    .contact-note {
      font-size: 14px;
      color: #666;
      margin: 0 0 0.75rem;
    }
    .contact-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 14px;
      color: #ff8c00;
      text-decoration: none;
    }
    .contact-link:hover {
      text-decoration: underline;
    }
    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #ff8c00;
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 10px 20px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      margin-top: 0.5rem;
    }
    .back-btn:hover {
      background: #e07800;
    }
    .why-para {
      font-size: 15px;
      color: #555;
      line-height: 1.7;
      margin: 0;
    }
  </style>
</head>
<body>
  <?php include "functions.php"; ?>

  <div class="about-wrap">

    <div class="lynx-badge"><span class="lynx-dot"></span> High Speed Lynx</div>
    <h1 class="lynx-title">About the game</h1>
    <p class="lynx-sub">A fast-paced platformer built with passion, AI collaboration, and way too many late nights.</p>

    <hr class="divider">

    <p class="section-label">Why I built this</p>
    <div class="card">
      <p class="why-para">Platformers have always been my thing. Growing up, games like that were what got me hooked on gaming in the first place  the speed, the challenge, the satisfaction of nailing a tough level. When a school project gave me the chance to actually build something from scratch, I knew immediately what I wanted to make. I wanted to create something people could genuinely play and enjoy, not just a bland demo. High Speed Lynx started as a coding challenge to push myself, and ended up becoming something I'm really proud of.</p>
    </div>

    <hr class="divider">

    <p class="section-label">How to play</p>
    <div class="card">
      <div class="controls-grid">
        <div class="control-pill"><span class="control-key">→ / ←</span><span class="control-desc">Move</span></div>
        <div class="control-pill"><span class="control-key">SPACE</span><span class="control-desc">Jump</span></div>
        <div class="control-pill"><span class="control-key">Z</span><span class="control-desc">Dash</span></div>
        <div class="control-pill"><span class="control-key">ESC / P</span><span class="control-desc">Pause</span></div>
        <div class="control-pill"><span class="control-key">R</span><span class="control-desc">Respawn at checkpoint</span></div>
        <div class="control-pill"><span class="control-key">C</span><span class="control-desc">Continue save</span></div>
      </div>
      <p class="controls-note">Dodge enemies, collect coins, and defeat the Cyber Guardian across 3 levels. Unlock DASH by collecting enough coins.</p>
    </div>

    <hr class="divider">

    <p class="section-label">How it was built</p>
    <div class="card">
      <div class="tool-row">
        <div class="tool-icon" style="background:#e8f4fd;">🤖</div>
        <div>
          <p class="tool-name">Google Gemini</p>
          <p class="tool-desc">Used to help design game mechanics, level structure, and brainstorm enemy behavior.</p>
        </div>
      </div>
      <div class="tool-row">
        <div class="tool-icon" style="background:#fdf3e8;">🧠</div>
        <div>
          <p class="tool-name">Claude AI</p>
          <p class="tool-desc">Helped write and refine the game code, debug logic, and polish the overall experience.</p>
        </div>
      </div>
      <div class="tool-row">
        <div class="tool-icon" style="background:#edf7ed;">💻</div>
        <div>
          <p class="tool-name">PHP + JavaScript</p>
          <p class="tool-desc">The game runs on vanilla JS with a PHP backend handling saves, scores, and the leaderboard.</p>
        </div>
      </div>
    </div>

    <hr class="divider">

    <p class="section-label">Credits</p>
    <div class="card">
      <div class="credit-block">
        <div class="avatar">MW</div>
        <div>
          <p class="credit-name">Myles Whitton</p>
          <p class="credit-role">Solo developer — design, code, and everything in between</p>
        </div>
      </div>
    </div>

    <hr class="divider">

    <p class="section-label">Contact</p>
    <div class="card">
      <p class="contact-note">Got feedback, found a bug, or just want to say hi?</p>
      <a class="contact-link" href="mailto:393460@guhsd.net">✉ 393460@guhsd.net</a>
    </div>

    <br>
    <a class="back-btn" href="index.php">← Back to game</a>

  </div>
</body>
</html>