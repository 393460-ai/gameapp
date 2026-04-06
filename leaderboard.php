<?php
session_start();
$currentPlayer = isset($_SESSION['playerName']) ? $_SESSION['playerName'] : '';

// ── If requested as JSON (AJAX refresh) return raw data ──────
if (isset($_GET['json'])) {
    header('Content-Type: application/json');
    $file = 'data/leaderboard.json';
    // Fall back to root leaderboard.json if data/ doesn't exist yet
    if (!file_exists($file)) $file = 'leaderboard.json';
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?? [];
        usort($data, fn($a,$b) => $b['score'] <=> $a['score']);
        echo json_encode(array_slice($data, 0, 20));
    } else {
        echo '[]';
    }
    exit;
}

// ── Normal page load — read scores server-side ───────────────
$leaderboard = [];
$file = 'data/leaderboard.json';
if (!file_exists($file)) $file = 'leaderboard.json';
if (file_exists($file)) {
    $data = json_decode(file_get_contents($file), true) ?? [];
    usort($data, fn($a,$b) => $b['score'] <=> $a['score']);
    $leaderboard = array_slice($data, 0, 20);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Leaderboard - High Speed Lynx</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background-color: #FFF2CC;
            color: #333;
            font-family: 'Arial', sans-serif;
            text-align: center;
            padding: 40px 20px 60px;
        }

        h1 {
            font-size: 3em;
            color: #FF8C00;
            text-shadow: 2px 2px #FFDAB9;
            letter-spacing: 2px;
            margin-bottom: 6px;
        }

        .subtitle {
            color: #999;
            font-size: 0.95em;
            margin-bottom: 24px;
        }

        /* ── Status bar ── */
        #status-bar {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,140,0,0.12);
            border: 1.5px solid rgba(255,140,0,0.35);
            border-radius: 20px;
            padding: 6px 18px;
            font-size: 0.85em;
            color: #CC6600;
            margin-bottom: 22px;
        }
        #status-dot {
            width: 9px; height: 9px;
            border-radius: 50%;
            background: #FF8C00;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0%,100% { opacity: 1; transform: scale(1); }
            50%      { opacity: 0.4; transform: scale(0.8); }
        }
        #status-dot.fetching { background: #00BB77; animation: spin 0.8s linear infinite; border-radius: 0; width:10px; height:10px; border:2px solid #00BB77; border-top-color:transparent; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Table ── */
        .table-wrap {
            overflow: hidden;
            border-radius: 14px;
            border: 3px solid #FF8C00;
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
            width: min(700px, 95%);
            margin: 0 auto 28px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        thead th {
            background: #FF8C00;
            color: white;
            font-size: 1.1em;
            font-weight: bold;
            padding: 14px 12px;
            letter-spacing: 0.5px;
        }

        tbody tr {
            border-bottom: 1.5px solid #FFDAB9;
            transition: background 0.15s;
        }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:nth-child(even) { background: #FFF8DC; }
        tbody tr:hover { background: #FFE8A0; }

        td {
            padding: 13px 12px;
            font-size: 1.05em;
            vertical-align: middle;
        }

        /* Rank cell */
        .rank { font-weight: bold; font-size: 1.2em; width: 60px; }
        .medal-1 { color: #FFD700; text-shadow: 0 0 8px rgba(255,215,0,0.6); }
        .medal-2 { color: #AAAAAA; }
        .medal-3 { color: #CD7F32; }

        /* My row highlight */
        tr.my-row { background: #FFF0D0 !important; }
        tr.my-row td { font-weight: bold; color: #CC5500; }
        tr.my-row .rank { color: #FF8C00; }

        /* Score */
        .score { font-weight: bold; color: #FF6600; font-size: 1.1em; }
        tr.my-row .score { color: #CC3300; }

        /* Date */
        .date { color: #AAA; font-size: 0.88em; }

        /* Empty state */
        .empty { padding: 30px; color: #AAA; font-style: italic; font-size: 1.1em; }

        /* ── Buttons ── */
        .btn-row { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; }

        .back-btn, .refresh-btn {
            padding: 12px 26px;
            font-size: 1em;
            font-weight: bold;
            font-family: Arial, sans-serif;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: 0.2s;
            box-shadow: 0 4px 8px rgba(0,0,0,0.12);
        }
        .back-btn    { background: #FF8C00; color: white; }
        .back-btn:hover { background: #E67E22; transform: scale(1.04); }
        .refresh-btn { background: white; color: #FF8C00; border: 2px solid #FF8C00; }
        .refresh-btn:hover { background: #FFF0DC; transform: scale(1.04); }

        /* Countdown ring */
        #countdown {
            font-size: 0.78em;
            color: #CC6600;
            margin-top: 10px;
        }
        #countdown span { font-weight: bold; }
    </style>
</head>
<body>

<h1>🏆 LEADERBOARD</h1>
<p class="subtitle">High Speed Lynx — Top 20 Scores</p>

<div id="status-bar">
    <div id="status-dot"></div>
    <span id="status-text">Live • refreshes every 15s</span>
</div>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Rank</th>
                <th>Player</th>
                <th>Score</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody id="lb-body">
            <?php if (empty($leaderboard)): ?>
                <tr><td colspan="4" class="empty">No scores yet — be the first to run!</td></tr>
            <?php else: ?>
                <?php foreach ($leaderboard as $i => $entry):
                    $rank = $i + 1;
                    $isMe = ($currentPlayer !== '' && $entry['playerName'] === $currentPlayer);
                    $medal = $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : ($rank === 3 ? '🥉' : '#'.$rank));
                    $medalClass = $rank === 1 ? 'medal-1' : ($rank === 2 ? 'medal-2' : ($rank === 3 ? 'medal-3' : ''));
                    // Format date
                    $raw = $entry['dateTime'] ?? ($entry['timestamp'] ?? '');
                    $dateStr = '—';
                    if ($raw) {
                        try {
                            $d = new DateTime($raw);
                            $dateStr = $d->format('M j, g:ia');
                        } catch (Exception $e) {}
                    }
                ?>
                <tr class="<?= $isMe ? 'my-row' : '' ?>">
                    <td class="rank <?= $medalClass ?>"><?= $medal ?></td>
                    <td><?= htmlspecialchars($entry['playerName']) ?><?= $isMe ? ' 👈 You' : '' ?></td>
                    <td class="score"><?= number_format($entry['score']) ?></td>
                    <td class="date"><?= $dateStr ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="btn-row">
    <a href="index.php" class="back-btn">🎮 Return to Menu</a>
    <button class="refresh-btn" onclick="refreshNow()">🔄 Refresh Now</button>
</div>
<p id="countdown">Next auto-refresh in <span id="timer">15</span>s</p>

<script>
const CURRENT_PLAYER = <?php echo json_encode($currentPlayer); ?>;
const REFRESH_INTERVAL = 15; // seconds
let countdown = REFRESH_INTERVAL;
let fetching = false;

function formatDate(raw) {
    if (!raw) return '—';
    try {
        const d = new Date(raw);
        return d.toLocaleDateString('en-US', {month:'short', day:'numeric'})
             + ', ' + d.toLocaleTimeString('en-US', {hour:'numeric', minute:'2-digit'});
    } catch(e) { return raw; }
}

function medal(rank) {
    if (rank === 1) return '🥇';
    if (rank === 2) return '🥈';
    if (rank === 3) return '🥉';
    return '#' + rank;
}

function medalClass(rank) {
    if (rank === 1) return 'medal-1';
    if (rank === 2) return 'medal-2';
    if (rank === 3) return 'medal-3';
    return '';
}

function buildRows(data) {
    if (!data || data.length === 0) {
        return '<tr><td colspan="4" class="empty">No scores yet — be the first to run!</td></tr>';
    }
    return data.slice(0, 20).map((entry, i) => {
        const rank  = i + 1;
        const isMe  = CURRENT_PLAYER && entry.playerName === CURRENT_PLAYER;
        const score = entry.score.toLocaleString();
        const raw   = entry.dateTime || entry.timestamp || '';
        const date  = formatDate(raw);
        const m     = medal(rank);
        const mc    = medalClass(rank);
        const youTag = isMe ? ' 👈 You' : '';
        const name  = entry.playerName.replace(/&/g,'&amp;').replace(/</g,'&lt;');
        return `<tr class="${isMe ? 'my-row' : ''}">
            <td class="rank ${mc}">${m}</td>
            <td>${name}${youTag}</td>
            <td class="score">${score}</td>
            <td class="date">${date}</td>
        </tr>`;
    }).join('');
}

function setStatus(text, spinning) {
    document.getElementById('status-text').textContent = text;
    const dot = document.getElementById('status-dot');
    dot.className = spinning ? 'fetching' : '';
}

function refreshNow() {
    if (fetching) return;
    fetching = true;
    countdown = REFRESH_INTERVAL;
    document.getElementById('timer').textContent = REFRESH_INTERVAL;
    setStatus('Refreshing...', true);

    fetch('leaderboard.php?json=1&_=' + Date.now())
        .then(r => r.json())
        .then(data => {
            document.getElementById('lb-body').innerHTML = buildRows(data);
            setStatus('Live • refreshes every ' + REFRESH_INTERVAL + 's', false);
        })
        .catch(() => {
            setStatus('Could not refresh — check connection', false);
        })
        .finally(() => { fetching = false; });
}

// Countdown timer
setInterval(() => {
    countdown--;
    if (countdown <= 0) {
        refreshNow();
        countdown = REFRESH_INTERVAL;
    }
    document.getElementById('timer').textContent = Math.max(0, countdown);
}, 1000);
</script>

</body>
</html>