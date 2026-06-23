<!DOCTYPE html>
<html lang="es">
<head>
    <script>(function(){var t=localStorage.getItem('playdf-theme');if(t==='light')document.documentElement.classList.add('light-mode');else if(!t&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: light)').matches)document.documentElement.classList.add('light-mode');})();</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/icon-192x192.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/icon-512x512.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#4A90E2">
    <meta name="description" content="Juego de ahorcado educativo — PlayDF">
    <title>Ahorcado - PlayDF</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/css/app.css', 'resources/css/ahorcado.css', 'resources/js/app.js', 'resources/js/dark-toggle.js'])
</head>
<body class="cuerpo-aplicacion font-sans min-h-screen flex flex-col">

    <header class="cabecera-principal px-4 md:px-6 py-4 flex flex-row items-center justify-between shadow-md sticky top-0 z-40">
        <div class="flex items-center gap-3">
            <button id="btn-abrir-menu-movil" class="boton-menu-movil md:hidden text-xl p-1 mr-1" title="Abrir menú">
                <i class="fa-solid fa-bars"></i>
            </button>

            @include('partials.logo')

            <div class="hidden sm:flex items-center gap-3 ml-2 md:ml-6 racha-nivel-contenedor px-3 py-1 rounded-full text-xs">
                <span class="text-amber-400"><i class="fa-solid fa-fire"></i> Racha: <span id="header-streak">-</span></span>
                <span class="text-blue-400"><i class="fa-solid fa-star"></i> Nivel <span id="header-level">-</span></span>
            </div>
        </div>

        <div class="flex items-center gap-3 md:gap-4">
            <button onclick="toggleTheme()" class="theme-toggle-btn" title="Cambiar tema">
                <i class="fa-solid fa-moon icon-moon"></i>
                <i class="fa-solid fa-sun icon-sun"></i>
            </button>
            @auth
                <div class="relative" id="user-spinner">
                    <button id="user-spinner-btn" class="flex items-center gap-2 text-xs md:text-sm usuario-identificado px-3 py-1.5 rounded-xl hover:bg-white/10 transition-colors">
                        <i class="fa-solid fa-user"></i>
                        <span class="max-w-[100px] md:max-w-none truncate">{{ Auth::user()->name }}</span>
                        <i class="fa-solid fa-chevron-down text-[10px] transition-transform duration-200" id="spinner-arrow"></i>
                    </button>
                    <div id="user-dropdown" class="hidden absolute right-0 top-full mt-2 w-52 rounded-xl shadow-2xl overflow-hidden z-50" style="background: var(--modal-bg); border: 1px solid var(--modal-border);">
                        <div class="px-4 py-3" style="border-bottom: 1px solid var(--modal-border);">
                            <p class="text-xs" style="color: var(--modal-subtext);">Conectado como</p>
                            <p class="text-sm font-semibold truncate" style="color: var(--modal-text);">{{ Auth::user()->name }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2.5 text-xs text-red-400 transition-colors flex items-center gap-2.5">
                                <i class="fa-solid fa-right-from-bracket"></i> Salir
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="text-xs md:text-sm enlace-autenticacion">Entrar</a>
                <a href="{{ route('register') }}" class="boton-registrarse text-white text-[11px] md:text-xs font-bold px-2.5 md:px-3 py-2 rounded-lg transition-colors">Registrarse</a>
            @endauth
        </div>
    </header>

    <div class="px-4 md:px-8 pt-4 pb-1">
        <div class="flex items-center gap-2 text-xs" style="color: var(--text-sub)">
            <a href="/" class="hover:text-white transition-colors flex items-center gap-1.5">
                <i class="fa-solid fa-house text-[10px]"></i> PlayDF
            </a>
            <i class="fa-solid fa-chevron-right text-[9px]"></i>
            <span style="color: var(--text)">
                <i class="fa-solid fa-puzzle-piece text-purple-400 mr-1"></i>Ahorcado
            </span>
        </div>
    </div>

    <div class="container flex-1">

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
                <svg class="hangman-svg" viewBox="0 0 200 220" xmlns="http://www.w3.org/2000/svg">
                    <!-- Gallows -->
                    <line class="gallows" x1="30" y1="210" x2="170" y2="210"/>
                    <line class="gallows" x1="60" y1="210" x2="60" y2="20"/>
                    <line class="gallows" x1="60" y1="20" x2="130" y2="20"/>
                    <line class="gallows" x1="130" y1="20" x2="130" y2="45"/>
                    <!-- Decorative accent -->
                    <line class="gallows-accent" x1="55" y1="215" x2="175" y2="215"/>

                    <!-- Head -->
                    <circle class="head" id="hmHead" cx="130" cy="65" r="20"/>
                    <!-- Eyes (X X when dead) -->
                    <line class="face" id="hmEyeL1" x1="122" y1="60" x2="126" y2="64"/>
                    <line class="face" id="hmEyeL2" x1="126" y1="60" x2="122" y2="64"/>
                    <line class="face" id="hmEyeR1" x1="134" y1="60" x2="138" y2="64"/>
                    <line class="face" id="hmEyeR2" x1="138" y1="60" x2="134" y2="64"/>
                    <!-- Mouth (sad) -->
                    <path class="face" id="hmMouth" d="M124,76 Q130,72 136,76"/>

                    <!-- Body -->
                    <line class="body-part" id="hmBody" x1="130" y1="85" x2="130" y2="140"/>
                    <!-- Left Arm -->
                    <line class="body-part" id="hmArmL" x1="130" y1="100" x2="105" y2="125"/>
                    <!-- Right Arm -->
                    <line class="body-part" id="hmArmR" x1="130" y1="100" x2="155" y2="125"/>
                    <!-- Left Leg -->
                    <line class="body-part" id="hmLegL" x1="130" y1="140" x2="110" y2="180"/>
                    <!-- Right Leg -->
                    <line class="body-part" id="hmLegR" x1="130" y1="140" x2="150" y2="180"/>
                </svg>
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

const hangmanParts = [
    'hmHead',   // 1 error: cabeza
    'hmBody',   // 2 errores: cuerpo
    'hmArmL',   // 3 errores: brazo izq
    'hmArmR',   // 4 errores: brazo der
    'hmLegL',   // 5 errores: ambas piernas (game over)
    'hmLegR',   // 5 errores: ambas piernas (game over)
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

let secretPhrase = '';

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
            secretPhrase = data.secret_phrase || maskedPhrase;
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
    // Mostrar partes del cuerpo según errores
    for (let i = 0; i < wrongGuesses && i < hangmanParts.length; i++) {
        const el = document.getElementById(hangmanParts[i]);
        if (el) el.classList.add('visible');
    }
    // Mostrar cara (ojos + boca) cuando hay cabeza
    if (wrongGuesses >= 1) {
        document.querySelectorAll('.face').forEach(f => f.classList.add('visible'));
    }
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
        html += `<p>La respuesta correcta era:</p>`;
        html += `<div style="font-size:20px;font-weight:800;color:var(--green);letter-spacing:3px;margin:12px 0;padding:12px 20px;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:10px;display:inline-block">${secretPhrase}</div>`;
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
    // Resetear SVG
    document.querySelectorAll('.body-part, .head, .face').forEach(el => el.classList.remove('visible'));
    guessedLetters = [];
    wrongGuesses = 0;
    gameOver = false;
}
</script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const html = document.documentElement;
        const STORAGE_KEY = 'playdf-theme';
        function getInitialTheme() {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved === 'light' || saved === 'dark') return saved;
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) return 'light';
            return 'dark';
        }
        function applyTheme(theme) {
            if (theme === 'light') html.classList.add('light-mode');
            else html.classList.remove('light-mode');
            localStorage.setItem(STORAGE_KEY, theme);
        }
        applyTheme(getInitialTheme());
        window.toggleTheme = function() {
            const isLight = html.classList.contains('light-mode');
            applyTheme(isLight ? 'dark' : 'light');
        };
    });
    </script>

    <script>
    (function() {
        var spinnerBtn = document.getElementById('user-spinner-btn');
        var dropdown = document.getElementById('user-dropdown');
        var arrow = document.getElementById('spinner-arrow');
        if (spinnerBtn && dropdown) {
            spinnerBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                var isOpen = !dropdown.classList.contains('hidden');
                dropdown.classList.toggle('hidden');
                arrow.style.transform = isOpen ? '' : 'rotate(180deg)';
            });
            document.addEventListener('click', function(e) {
                if (!dropdown.contains(e.target) && e.target !== spinnerBtn) {
                    dropdown.classList.add('hidden');
                    arrow.style.transform = '';
                }
            });
        }
    })();
    </script>
</body>
</html>
