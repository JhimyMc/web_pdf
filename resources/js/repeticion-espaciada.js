/**
 * REPETICIÓN ESPACIADA (SRS) — repeticion-espaciada.js
 * Implementa el algoritmo SM-2 de programación espaciada.
 */

document.addEventListener('DOMContentLoaded', () => {

    // ── Referencias del DOM ───────────────────────────────────
    const estadoVacio    = document.getElementById('srs-estado-vacio');
    const estadoCargando = document.getElementById('srs-estado-cargando');
    const estadoLista    = document.getElementById('srs-estado-lista');
    const estadoRevision = document.getElementById('srs-estado-revision');

    const statsBar       = document.getElementById('srs-stats-bar');
    const listaSets      = document.getElementById('srs-lista-sets');
    const cardContainer  = document.getElementById('srs-card-container');
    const qualityButtons = document.getElementById('srs-quality-buttons');
    const revisionTitulo = document.getElementById('srs-revision-titulo');
    const revisionSub    = document.getElementById('srs-revision-sub');
    const revisionCounter= document.getElementById('srs-revision-counter');
    const progressContainer = document.getElementById('srs-progress-container');
    const progressFill   = document.getElementById('srs-progress-fill');
    const completadoDiv  = document.getElementById('srs-completado');

    const toast          = document.getElementById('srs-toast');
    const toastMsg       = document.getElementById('srs-toast-msg');

    const statDue        = document.getElementById('srs-stat-due');
    const statMastered   = document.getElementById('srs-stat-mastered');
    const statTotal      = document.getElementById('srs-stat-total');

    let setsData = [];
    let currentSetId = null;
    let currentSetTitle = '';
    let reviewCards = [];
    let currentReviewIndex = 0;
    let reviewedCount = 0;
    let totalReviewCount = 0;
    let isFlipped = false;

    const getCsrf = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    // ── Utilidades ─────────────────────────────────────────────
    function mostrarEstado(estado) {
        [estadoVacio, estadoCargando, estadoLista, estadoRevision].forEach(el => {
            if (el) el.classList.add('oculto');
        });
        const map = {
            vacio:    estadoVacio,
            cargando: estadoCargando,
            lista:    estadoLista,
            revision: estadoRevision,
        };
        if (map[estado]) map[estado].classList.remove('oculto');
    }

    function mostrarToast(mensaje, tipo = 'exito') {
        if (!toast || !toastMsg) return;
        toastMsg.textContent = mensaje;
        toast.className = 'srs-toast ' + tipo;
        toast.classList.remove('oculto');
        clearTimeout(toast._timeout);
        toast._timeout = setTimeout(() => toast.classList.add('oculto'), 3000);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ── Calcular intervalos SM-2 para preview ──────────────────
    function calcularIntervalo(easeFactor, quality) {
        // quality 0-3 → mapeo SM-2 1-5
        const qMap = [1, 3, 4, 5];
        const q = qMap[quality];

        if (q < 3) return 1;

        const reps = 1; // simplificado para preview
        if (reps === 1) return 1;
        if (reps === 2) return 6;
        return Math.round(6 * easeFactor);
    }

    // ── Cargar stats globales ──────────────────────────────────
    async function cargarStats() {
        try {
            const res = await fetch('/ajax/srs/stats', {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data.success) {
                if (statDue) statDue.textContent = data.due_now;
                if (statMastered) statMastered.textContent = data.mastered;
                if (statTotal) statTotal.textContent = data.total_cards;
            }
        } catch (e) {
            // silencioso
        }
    }

    // ── Renderizar lista de sets ───────────────────────────────
    function renderListaSets(sets) {
        if (!listaSets) return;
        setsData = sets;

        if (sets.length === 0) {
            mostrarEstado('vacio');
            return;
        }

        listaSets.innerHTML = '';
        sets.forEach(set => {
            const dueCount = set.due_count || 0;
            const totalCards = set.cards_count || 0;

            const dueBadge = dueCount > 0
                ? `<span class="srs-set-due-badge" title="${dueCount} tarjetas pendientes"><i class="fa-solid fa-clock"></i> ${dueCount} pendientes</span>`
                : '';

            const item = document.createElement('div');
            item.className = 'srs-set-item';
            item.innerHTML = `
                <div class="srs-set-info">
                    <div class="srs-set-icono">
                        <i class="fa-solid fa-brain"></i>
                    </div>
                    <div style="min-width:0">
                        <div class="srs-set-titulo">${escapeHtml(set.title)}</div>
                        <div class="srs-set-meta">${totalCards} tarjetas${dueBadge}</div>
                    </div>
                </div>
            `;

            item.addEventListener('click', () => abrirRevision(set.id, set.title));
            listaSets.appendChild(item);
        });
    }

    // ── Abrir vista de revisión ────────────────────────────────
    async function abrirRevision(setId, setTitle) {
        currentSetId = setId;
        currentSetTitle = setTitle;
        mostrarEstado('cargando');

        try {
            // 1. Sincronizar SRS si es necesario
            await fetch('/ajax/srs/sync', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                },
                body: JSON.stringify({ set_id: setId }),
            });

            // 2. Obtener cola de revisión
            const res = await fetch(`/ajax/srs/${setId}/queue`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();

            if (data.success) {
                reviewCards = data.due_cards;
                totalReviewCount = data.total_due;
                reviewedCount = 0;
                currentReviewIndex = 0;
                isFlipped = false;

                if (revisionTitulo) revisionTitulo.textContent = data.set.title;
                if (revisionSub) revisionSub.textContent = `${data.total_due} tarjetas para hoy`;

                if (reviewCards.length === 0) {
                    mostrarEstado('revision');
                    mostrarCompletado();
                } else {
                    mostrarEstado('revision');
                    renderReviewCard();
                }
            } else {
                mostrarToast('Error al cargar revisión', 'error');
                mostrarEstado('lista');
            }
        } catch (e) {
            mostrarToast('Error de conexión', 'error');
            mostrarEstado('lista');
        }
    }

    // ── Renderizar tarjeta de revisión ─────────────────────────
    function renderReviewCard() {
        if (!cardContainer) return;

        if (currentReviewIndex >= reviewCards.length) {
            mostrarCompletado();
            return;
        }

        const card = reviewCards[currentReviewIndex];
        isFlipped = false;

        // Actualizar counter
        if (revisionCounter) {
            revisionCounter.textContent = `${currentReviewIndex + 1} / ${reviewCards.length}`;
        }

        // Actualizar progreso
        const pct = totalReviewCount > 0 ? Math.round((reviewedCount / totalReviewCount) * 100) : 0;
        if (progressFill) progressFill.style.width = pct + '%';

        // Calcular intervalos preview
        const ef = card.ease_factor || 2.5;
        const intHard = calcularIntervalo(ef, 1);
        const intGood = calcularIntervalo(ef, 2);
        const intEasy = calcularIntervalo(ef, 3);

        const hardLabel = intHard === 1 ? '1 día' : `${intHard} días`;
        const goodLabel = intGood === 1 ? '1 día' : `${intGood} días`;
        const easyLabel = intEasy === 1 ? '1 día' : `${intEasy} días`;

        const elHard = document.getElementById('srs-q-hard-interval');
        const elGood = document.getElementById('srs-q-good-interval');
        const elEasy = document.getElementById('srs-q-easy-interval');
        if (elHard) elHard.textContent = hardLabel;
        if (elGood) elGood.textContent = goodLabel;
        if (elEasy) elEasy.textContent = easyLabel;

        // Ocultar botones de calidad hasta que se volte
        if (qualityButtons) qualityButtons.classList.add('oculto');
        if (completadoDiv) completadoDiv.classList.add('oculto');

        cardContainer.innerHTML = `
            <div class="srs-flip-container">
                <div class="srs-flip-card" id="srs-flip-card">
                    <div class="srs-flip-front">
                        <div class="srs-flip-label front-label">Pregunta</div>
                        <div class="srs-flip-text">${escapeHtml(card.front)}</div>
                        <div class="srs-flip-hint">
                            <i class="fa-solid fa-hand-pointer"></i> Toca para ver respuesta
                        </div>
                    </div>
                    <div class="srs-flip-back">
                        <div class="srs-flip-label back-label">Respuesta</div>
                        <div class="srs-flip-text">${escapeHtml(card.back)}</div>
                        <div class="srs-flip-hint">
                            <i class="fa-solid fa-hand-pointer"></i> Evalúa tu repaso
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Evento flip
        const flipCard = document.getElementById('srs-flip-card');
        if (flipCard) {
            flipCard.addEventListener('click', () => {
                if (!isFlipped) {
                    isFlipped = true;
                    flipCard.classList.add('volteada');
                    if (qualityButtons) qualityButtons.classList.remove('oculto');
                }
            });
        }
    }

    // ── Mostrar estado completado ──────────────────────────────
    function mostrarCompletado() {
        if (cardContainer) cardContainer.innerHTML = '';
        if (qualityButtons) qualityButtons.classList.add('oculto');
        if (completadoDiv) completadoDiv.classList.remove('oculto');
        if (progressFill) progressFill.style.width = '100%';
        if (revisionCounter) revisionCounter.textContent = `✅ ${reviewCards.length} / ${reviewCards.length}`;
        cargarStats();
    }

    // ── Enviar review (calidad) ────────────────────────────────
    async function enviarReview(quality) {
        if (currentReviewIndex >= reviewCards.length) return;

        const card = reviewCards[currentReviewIndex];

        // UI optimista
        reviewedCount++;
        currentReviewIndex++;
        isFlipped = false;

        renderReviewCard();

        try {
            await fetch(`/ajax/srs/${currentSetId}/review`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                },
                body: JSON.stringify({
                    card_index: card.card_index,
                    quality: quality,
                }),
            });
        } catch (e) {
            // silencioso — la tarjeta ya avanzó visualmente
        }
    }

    // Eventos de botones de calidad
    if (qualityButtons) {
        qualityButtons.addEventListener('click', (e) => {
            const btn = e.target.closest('.srs-q-btn');
            if (!btn) return;
            const quality = parseInt(btn.dataset.quality, 10);
            if (!isNaN(quality)) {
                enviarReview(quality);
            }
        });
    }

    // ── Sincronizar todos los sets ──────────────────────────────
    window.srsSincronizarTodos = async function() {
        mostrarEstado('cargando');
        let totalCreated = 0;

        for (const set of setsData) {
            try {
                const res = await fetch('/ajax/srs/sync', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrf(),
                    },
                    body: JSON.stringify({ set_id: set.id }),
                });
                const data = await res.json();
                if (data.success) totalCreated += data.created || 0;
            } catch (e) { /* continue */ }
        }

        mostrarToast(`SRS sincronizado: ${totalCreated} tarjetas nuevas`, 'exito');
        cargarStats();
        mostrarEstado('lista');
        // Recargar la página para actualizar due_counts
        window.location.reload();
    };

    // Botón sincronizar en estado vacío
    const btnSyncVacio = document.getElementById('srs-btn-sincronizar-vacio');
    if (btnSyncVacio) {
        btnSyncVacio.addEventListener('click', window.srsSincronizarTodos);
    }

    // ── Volver a la lista ──────────────────────────────────────
    window.srsVolverALista = function() {
        mostrarEstado('lista');
        cargarStats();
    };

    const btnVolver = document.getElementById('srs-btn-volver');
    if (btnVolver) {
        btnVolver.addEventListener('click', window.srsVolverALista);
    }

    // ── Teclado: espacio/voltear, 1-4 calificar ────────────────
    document.addEventListener('keydown', (e) => {
        if (estadoRevision && estadoRevision.classList.contains('oculto')) return;

        if (e.key === ' ' || e.key === 'Enter') {
            e.preventDefault();
            if (!isFlipped && currentReviewIndex < reviewCards.length) {
                const flipCard = document.getElementById('srs-flip-card');
                if (flipCard) {
                    isFlipped = true;
                    flipCard.classList.add('volteada');
                    if (qualityButtons) qualityButtons.classList.remove('oculto');
                }
            }
        }

        if (isFlipped) {
            if (e.key === '1') enviarReview(0); // Again
            if (e.key === '2') enviarReview(1); // Hard
            if (e.key === '3') enviarReview(2); // Good
            if (e.key === '4') enviarReview(3); // Easy
        }
    });

    // ── Inicializar ────────────────────────────────────────────
    cargarStats();

    if (window.srsSetsIniciales && window.srsSetsIniciales.length > 0) {
        renderListaSets(window.srsSetsIniciales);
        mostrarEstado('lista');
    } else {
        mostrarEstado('vacio');
    }
});
