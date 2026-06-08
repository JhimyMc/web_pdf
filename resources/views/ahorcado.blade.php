<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ahorcado - PlayDF</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #0a0a0a; --surface: #1a1a2e; --surface2: #16213e; --border: #2d2d44; --text: #e2e8f0; --text-sub: #94a3b8; --red: #ef4444; --green: #10b981; --amber: #f59e0b; --blue: #3b82f6; --purple: #8b5cf6; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .container { max-width: 900px; margin: 0 auto; padding: 24px 16px; }
        .header { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; }
        .header h1 { font-size: 24px; font-weight: 800; }
        .header h1 span { color: var(--red); }
        .back-btn { color: var(--text-sub); text-decoration: none; font-size: 24px; cursor: pointer; }
        .back-btn:hover { color: var(--text); }

        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-sub); }
        .empty-state svg { width: 64px; height: 64px; margin-bottom: 16px; opacity: 0.3; }
        .empty-state h3 { color: var(--text); margin-bottom: 8px; }

        .game-area { display: none; }
        .game-area.active { display: block; }

        .card-select { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 20px; margin-bottom: 20px; }
        .card-select h3 { font-size: 16px; margin-bottom: 12px; }
        .card-list { display: grid; gap: 8px; max-height: 300px; overflow-y: auto; }
        .card-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: var(--bg); border: 1px solid var(--border); border-radius: 10px; cursor: pointer; transition: all 0.2s; }
        .card-item:hover { border-color: var(--purple); background: rgba(139,92,246,0.08); }
        .card-item .front { font-weight: 600; font-size: 13px; flex: 1; }
        .card-item .set-name { color: var(--text-sub); font-size: 11px; }
        .card-item .play-icon { color: var(--purple); }

        .hangman-board { text-align: center; margin-bottom: 24px; }
        .hangman-drawing { font-size: 14px; color: var(--text-sub); margin-bottom: 16px; font-family: monospace; line-height: 1.4; white-space: pre; }

        .phrase-display { font-size: 28px; font-weight: 800; letter-spacing: 4px; margin: 20px 0; min-height: 40px; display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; }
        .phrase-char { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 44px; border-bottom: 3px solid var(--border); font-size: 20px; transition: all 0.3s; }
        .phrase-char.revealed { border-bottom-color: var(--green); color: var(--green); }
        .phrase-char.space { width: 20px; border-bottom: none; }

        .attempts-bar { display: flex; justify-content: center; gap: 8px; margin: 16px 0; }
        .attempt-dot { width: 12px; height: 12px; border-radius: 50%; background: var(--border); transition: background 0.3s; }
        .attempt-dot.wrong { background: var(--red); }

        .keyboard { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; margin: 20px 0; }
        .key { width: 38px; height: 42px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--text); font-size: 14px; font-weight: 700; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; }
        .key:hover:not(:disabled) { border-color: var(--purple); background: rgba(139,92,246,0.15); transform: scale(1.05); }
        .key:disabled { opacity: 0.25; cursor: not-allowed; }
        .key.correct { background: rgba(16,185,129,0.2); border-color: var(--green); color: var(--green); }
        .key.wrong { background: rgba(239,68,68,0.15); border-color: var(--red); color: var(--red); }

        .game-result { text-align: center; padding: 24px; border-radius: 16px; margin: 20px 0; }
        .game-result.won { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); }
        .game-result.lost { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); }
        .game-result h2 { font-size: 20px; margin-bottom: 8px; }
        .game-result p { color: var(--text-sub); margin-bottom: 16px; }
        .game-result .xp-badge { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.3); border-radius: 10px; color: var(--amber); font-weight: 700; }

        .recent-games { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 20px; margin-top: 24px; }
        .recent-games h3 { font-size: 14px; margin-bottom: 12px; color: var(--text-sub); }
        .game-row { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border); }
        .game-row:last-child { border-bottom: none; }
        .game-row .result-icon { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; }
        .game-row .result-icon.won { background: rgba(16,185,129,0.15); color: var(--green); }
        .game-row .result-icon.lost { background: rgba(239,68,68,0.15); color: var(--red); }
        .game-row .phrase { flex: 1; font-size: 13px; }
        .game-row .xp { font-size: 12px; color: var(--amber); font-weight: 600; }
        .game-row .time { font-size: 11px; color: var(--text-sub); }

        .btn-primary { background: var(--purple); color: white; border: none; padding: 12px 24px; border-radius: 12px; font-weight: 700; cursor: pointer; font-size: 14px; transition: all 0.2s; }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-secondary { background: var(--surface); color: var(--text); border: 1px solid var(--border); padding: 10px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; font-size: 13px; }
        .btn-secondary:hover { border-color: var(--text-sub); }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <a href="/" class="back-btn">&larr;</a>
        <h1><span>Ahorcado</span> PlayDF</h1>
    </div>

    @if($difficultCards->isEmpty())
        <div class="empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
            <h3>No tienes tarjetas difíciles</h3>
            <p>Marca tarjetas como difíciles en <a href="/tarjetas-estudio" style="color:var(--purple)">Tarjetas de Estudio</a> para jugar al ahorcado</p>
        </div>
    @else
        <div class="card-select" id="cardSelect">
            <h3>Selecciona una tarjeta difícil para jugar</h3>
            <div class="card-list">
                @foreach($difficultCards as $card)
                    <div class="card-item" onclick="startGame({{ $card['card_id'] }}, {{ $card['set_id'] }}, {{ $card['card_index'] }})" data-card-id="{{ $card['card_id'] }}">
                        <div>
                            <div class="front">{{ Str::limit($card['front'], 80) }}</div>
                            <div class="set-name">{{ $card['set_title'] }}</div>
                        </div>
                        <div class="play-icon">&#9654;</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="game-area" id="gameArea">
            <div class="hangman-board">
                <div class="hangman-drawing" id="hangmanDrawing"></div>
                <div class="attempts-bar" id="attemptsBar"></div>
                <p style="color:var(--text-sub);font-size:13px" id="hintText"></p>
            </div>

            <div class="phrase-display" id="phraseDisplay"></div>

            <div class="keyboard" id="keyboard"></div>

            <div class="game-result" id="gameResult" style="display:none"></div>
        </div>

        @if($recentGames->isNotEmpty())
        <div class="recent-games">
            <h3>Partidas Recientes</h3>
            @foreach($recentGames as $game)
            <div class="game-row">
                @if($game['won'])
                <div class="result-icon won">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </div>
                @else
                <div class="result-icon lost">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </div>
                @endif
                <div class="phrase">{{ $game['phrase'] }}{{ mb_strlen($game['phrase']) >= 50 ? '...' : '' }}</div>
                @if($game['xp_earned'] > 0)
                <div class="xp">+{{ $game['xp_earned'] }} XP</div>
                @endif
                <div class="time">{{ $game['created_at'] }}</div>
            </div>
            @endforeach
        </div>
        @endif
    @endif
</div>

<script>
let currentGameId = null;
let guessedLetters = [];
let wrongGuesses = 0;
let maxAttempts = 5;
let maskedPhrase = '';
let gameOver = false;

const hangmanStages = [
    '',
    '  O',
    '  O\n /',
    '  O\n /|',
    '  O\n /|\\',
    '  O\n /|\\\n /',
];

function startGame(cardId, setId, cardIndex) {
    fetch('/ajax/ahorcado/start', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ card_id: cardId, set_id: setId, card_index: cardIndex })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) { alert(data.message); return; }
        currentGameId = data.game_id;
        maskedPhrase = data.masked_phrase;
        guessedLetters = [];
        wrongGuesses = 0;
        maxAttempts = data.max_attempts;
        gameOver = false;

        document.getElementById('cardSelect').style.display = 'none';
        document.getElementById('gameArea').classList.add('active');
        document.getElementById('hintText').textContent = data.front;
        document.getElementById('gameResult').style.display = 'none';

        renderAttempts();
        renderPhrase();
        renderKeyboard();
    });
}

function guessLetter(letter, btn) {
    if (gameOver || guessedLetters.includes(letter)) return;

    fetch('/ajax/ahorcado/guess', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ game_id: currentGameId, letter: letter })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;

        guessedLetters.push(letter);
        if (!data.correct) wrongGuesses = data.wrong_guesses;

        maskedPhrase = data.masked_phrase;

        if (data.correct) { btn.classList.add('correct'); }
        else { btn.classList.add('wrong'); }
        btn.disabled = true;

        renderAttempts();
        renderPhrase();
        updateDrawing();

        if (data.game_over) {
            gameOver = true;
            showResult(data.won, data.xp_earned, data.new_achievements || []);
        }
    });
}

function renderAttempts() {
    const bar = document.getElementById('attemptsBar');
    bar.innerHTML = '';
    for (let i = 0; i < maxAttempts; i++) {
        const dot = document.createElement('div');
        dot.className = 'attempt-dot' + (i < wrongGuesses ? ' wrong' : '');
        bar.appendChild(dot);
    }
}

function renderPhrase() {
    const display = document.getElementById('phraseDisplay');
    display.innerHTML = '';
    const chars = maskedPhrase.split(' ');
    chars.forEach(ch => {
        const span = document.createElement('span');
        span.className = 'phrase-char' + (ch !== '_' ? ' revealed' : '') + (ch === '' ? ' space' : '');
        span.textContent = ch === '' ? '' : (ch === '_' ? '' : ch);
        display.appendChild(span);
    });
}

function updateDrawing() {
    document.getElementById('hangmanDrawing').textContent = hangmanStages[Math.min(wrongGuesses, hangmanStages.length - 1)];
}

function renderKeyboard() {
    const kb = document.getElementById('keyboard');
    kb.innerHTML = '';
    const letters = 'ABCDEFGHIJKLMNÑOPQRSTUVWXYZ';
    for (const l of letters) {
        const btn = document.createElement('button');
        btn.className = 'key';
        btn.textContent = l;
        btn.onclick = () => guessLetter(l.toLowerCase(), btn);
        if (guessedLetters.includes(l.toLowerCase())) {
            btn.disabled = true;
            btn.classList.add(guessedLetters.indexOf(l.toLowerCase()) !== -1 && !maskedPhrase.toLowerCase().includes(l.toLowerCase()) ? 'wrong' : 'correct');
        }
        kb.appendChild(btn);
    }
}

function showResult(won, xp, achievements) {
    const el = document.getElementById('gameResult');
    el.style.display = 'block';
    el.className = 'game-result ' + (won ? 'won' : 'lost');

    let html = '';
    if (won) {
        html = `<h2 style="color:var(--green)">¡Ganaste!</h2>`;
        html += `<p>Descifraste la frase correctamente</p>`;
        if (xp > 0) html += `<div class="xp-badge">+${xp} XP</div>`;
    } else {
        html = `<h2 style="color:var(--red)">Perdiste</h2>`;
        html += `<p>La frase era: <strong>${maskedPhrase.replace(/_/g, '___')}</strong></p>`;
    }

    if (achievements.length > 0) {
        html += '<div style="margin-top:16px">';
        achievements.forEach(a => {
            html += `<div style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:8px;margin:4px;font-size:12px;color:var(--green)">${a.name} +${a.xp} XP</div>`;
        });
        html += '</div>';
    }

    html += '<div style="margin-top:16px"><button class="btn-primary" onclick="resetGame()">Jugar de Nuevo</button></div>';
    el.innerHTML = html;
}

function resetGame() {
    document.getElementById('cardSelect').style.display = 'block';
    document.getElementById('gameArea').classList.remove('active');
    document.getElementById('hangmanDrawing').textContent = '';
    guessedLetters = [];
    wrongGuesses = 0;
    gameOver = false;
}
</script>
</body>
</html>
