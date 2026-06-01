/**
 * TARJETAS DE ESTUDIO — tarjetas-estudio.js
 */

document.addEventListener('DOMContentLoaded', () => {

    // ── Referencias del DOM ───────────────────────────────────
    const estadoVacio     = document.getElementById('te-estado-vacio');
    const estadoCargando  = document.getElementById('te-estado-cargando');
    const estadoLista     = document.getElementById('te-estado-lista');
    const estadoCards     = document.getElementById('te-estado-cards');

    const modalOverlay    = document.getElementById('te-modal-overlay');
    const formGenerar     = document.getElementById('te-form-generar');
    const selectDocumento = document.getElementById('te-select-documento');
    const zonaSubida      = document.getElementById('te-zona-subida');
    const fileRapido      = document.getElementById('te-file-rapido');
    const textUpload      = document.getElementById('te-text-upload');

    const listaSets       = document.getElementById('te-lista-sets');
    const cardsContainer  = document.getElementById('te-cards-container');
    const cardsTitulo     = document.getElementById('te-cards-titulo');
    const cardsCounter    = document.getElementById('te-cards-counter');
    const btnPrev         = document.getElementById('te-btn-prev');
    const btnNext         = document.getElementById('te-btn-next');
    const btnShuffle      = document.getElementById('te-btn-shuffle');
    const btnVolver       = document.getElementById('te-btn-volver');
    const btnNuevoVacio   = document.getElementById('te-btn-nuevo-vacio');

    const toast           = document.getElementById('te-toast');
    const toastMsg        = document.getElementById('te-toast-msg');

    // Progreso
    const progressContainer = document.getElementById('te-progress-container');
    const progressFill      = document.getElementById('te-progress-fill');
    const progressText      = document.getElementById('te-progress-text');

    let setsData  = [];
    let cardsData = [];
    let currentCardIndex = 0;
    let isFlipped = false;
    let reviewedCards = new Set();  // índices de tarjetas que ya fueron volteadas
    let difficultCards = new Set(); // índices de tarjetas marcadas como difíciles

    const getCsrf = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    // ── Utilidades ─────────────────────────────────────────────
    function mostrarEstado(estado) {
        [estadoVacio, estadoCargando, estadoLista, estadoCards].forEach(el => {
            if (el) el.classList.add('oculto');
        });
        const map = {
            vacio:   estadoVacio,
            cargando: estadoCargando,
            lista:   estadoLista,
            cards:   estadoCards,
        };
        if (map[estado]) map[estado].classList.remove('oculto');
    }

    function mostrarToast(mensaje, tipo = 'exito') {
        if (!toast || !toastMsg) return;
        toastMsg.textContent = mensaje;
        toast.className = 'te-toast ' + tipo;
        toast.classList.remove('oculto');
        clearTimeout(toast._timeout);
        toast._timeout = setTimeout(() => toast.classList.add('oculto'), 3000);
    }

    // ── Modal ──────────────────────────────────────────────────
    window.abrirModal = function() {
        if (modalOverlay) {
            modalOverlay.classList.remove('oculto');
        }
    };

    window.cerrarModal = function() {
        if (modalOverlay) modalOverlay.classList.add('oculto');
        if (formGenerar) formGenerar.reset();
        if (textUpload) textUpload.textContent = 'Subir y usar un nuevo archivo PDF';
        if (fileRapido) fileRapido.value = '';
    };

    if (btnNuevoVacio) btnNuevoVacio.addEventListener('click', window.abrirModal);

    // Subida rápida
    if (zonaSubida && fileRapido) {
        zonaSubida.addEventListener('click', () => fileRapido.click());
        fileRapido.addEventListener('change', () => {
            if (fileRapido.files.length > 0) {
                textUpload.textContent = '📎 ' + fileRapido.files[0].name;
                if (selectDocumento) selectDocumento.value = '';
            }
        });
    }

    // ── Generar Tarjetas ───────────────────────────────────────
    if (formGenerar) {
        formGenerar.addEventListener('submit', async (e) => {
            e.preventDefault();

            let docId = selectDocumento ? selectDocumento.value : '';

            // Si subió un archivo rápido
            if (!docId && fileRapido && fileRapido.files.length > 0) {
                mostrarEstado('cargando');
                window.cerrarModal();

                const formData = new FormData();
                formData.append('file', fileRapido.files[0]);

                try {
                    const uploadRes = await fetch('/ajax/mapa-mental/upload-rapido', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': getCsrf() },
                        body: formData
                    });
                    const uploadData = await uploadRes.json();
                    if (uploadData.success) {
                        docId = uploadData.documento_id;
                    } else {
                        mostrarToast('Error al procesar el PDF: ' + uploadData.message, 'error');
                        mostrarEstado('lista');
                        return;
                    }
                } catch (err) {
                    mostrarToast('Fallo al subir el archivo.', 'error');
                    mostrarEstado('lista');
                    return;
                }
            }

            if (!docId) {
                mostrarToast('Selecciona un PDF o sube uno nuevo.', 'error');
                return;
            }

            mostrarEstado('cargando');
            window.cerrarModal();

            try {
                const response = await fetch('/ajax/tarjetas-estudio/generar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrf(),
                    },
                    body: JSON.stringify({ document_id: docId }),
                });

                const data = await response.json();

                if (data.success) {
                    mostrarToast('¡Tarjetas generadas exitosamente!', 'exito');
                    // Recargar la página para mostrar el nuevo set
                    window.location.reload();
                } else {
                    mostrarToast('Error: ' + data.message, 'error');
                    mostrarEstado('lista');
                }
            } catch (err) {
                mostrarToast('No se pudo conectar con el servidor.', 'error');
                mostrarEstado('lista');
            }
        });
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
        sets.forEach((set, index) => {
            const cardCount = set.cards_count || set.cards?.length || 0;
            const item = document.createElement('div');
            item.className = 'te-set-item';
            const diffCount = set.difficult_count || 0;
            const diffBadge = diffCount > 0
                ? `<span class="te-diff-badge" title="${diffCount} tarjetas difíciles"><i class="fa-solid fa-star"></i> ${diffCount}</span>`
                : '';

            item.innerHTML = `
                <div class="te-set-info">
                    <div class="te-set-icono">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                    <div style="min-width:0">
                        <div class="te-set-titulo">${set.title}</div>
                        <div class="te-set-meta">${cardCount} tarjetas · ${new Date(set.created_at).toLocaleDateString()} ${diffBadge}</div>
                    </div>
                </div>
                <div class="te-set-acciones">
                    <button class="te-btn-eliminar-set" data-set-id="${set.id}" title="Eliminar set">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>
            `;

            // Click en el set → mostrar tarjetas
            item.addEventListener('click', async (e) => {
                const btnEliminar = e.target.closest('.te-btn-eliminar-set');
                if (btnEliminar) return; // No abrir si es click en eliminar
                await abrirSet(set.id);
            });

            listaSets.appendChild(item);
        });

        // Eventos para botones de eliminar
        document.querySelectorAll('.te-btn-eliminar-set').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const setId = btn.dataset.setId;
                if (!confirm('¿Eliminar este set de tarjetas?')) return;

                try {
                    const response = await fetch(`/ajax/tarjetas-estudio/${setId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': getCsrf(),
                            'Accept': 'application/json',
                        }
                    });
                    const data = await response.json();
                    if (data.success) {
                        mostrarToast('Set eliminado', 'exito');
                        window.location.reload();
                    }
                } catch (err) {
                    mostrarToast('Error al eliminar', 'error');
                }
            });
        });
    }

    // ── Abrir un set de tarjetas ────────────────────────────────
    async function abrirSet(setId) {
        mostrarEstado('cargando');
        setIdActual = setId;

        try {
            const response = await fetch(`/ajax/tarjetas-estudio/${setId}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();

            if (data.success) {
                cardsData = data.set.cards;
                currentCardIndex = 0;
                isFlipped = false;

                // Cargar progreso persistido desde la BD
                const indices = data.set.reviewed_indices || [];
                reviewedCards = new Set(indices);

                // Cargar tarjetas difíciles
                const diffIndices = data.set.difficult_indices || [];
                difficultCards = new Set(diffIndices);

                if (cardsTitulo) cardsTitulo.textContent = data.set.title;

                actualizarProgreso();
                renderTarjetaActual();
                mostrarEstado('cards');
            } else {
                mostrarToast('Error al cargar tarjetas', 'error');
                mostrarEstado('lista');
            }
        } catch (err) {
            mostrarToast('Error de conexión', 'error');
            mostrarEstado('lista');
        }
    }

    // ── Actualizar barra de progreso ───────────────────────────
    function actualizarProgreso() {
        if (!progressFill || !progressText || !progressContainer) return;
        const total = cardsData.length;
        const repasadas = reviewedCards.size;
        const pct = total > 0 ? Math.round((repasadas / total) * 100) : 0;

        progressText.textContent = `${repasadas} / ${total}`;
        progressFill.style.width = pct + '%';

        // Completado
        if (total > 0 && repasadas >= total) {
            progressContainer.classList.add('completado');
            progressText.textContent = `✅ ${repasadas} / ${total}`;
        } else {
            progressContainer.classList.remove('completado');
        }
    }

    // ── Renderizar tarjeta actual ──────────────────────────────
    function renderTarjetaActual() {
        if (!cardsContainer) return;

        if (cardsData.length === 0) {
            cardsContainer.innerHTML = `
                <div class="te-estado-vacio" style="min-height:40vh">
                    <p style="color: var(--color-gris-medio);">No hay tarjetas en este set.</p>
                </div>
            `;
            return;
        }

        const card = cardsData[currentCardIndex];

        if (cardsCounter) {
            cardsCounter.textContent = `${currentCardIndex + 1} / ${cardsData.length}`;
        }

        if (btnPrev) btnPrev.disabled = currentCardIndex === 0;
        if (btnNext) btnNext.disabled = currentCardIndex === cardsData.length - 1;

        const isDifficult = difficultCards.has(currentCardIndex);
        const difficultBtnClass = isDifficult ? 'te-dif-btn activo' : 'te-dif-btn';

        cardsContainer.innerHTML = `
            <div class="te-flip-container">
                <div class="te-flip-card" id="te-flip-card">
                    <div class="te-flip-front">
                        <div class="te-flip-label front-label">Pregunta</div>
                        <div class="te-flip-text">${escapeHtml(card.front)}</div>
                        <div class="te-flip-hint">
                            <i class="fa-solid fa-hand-pointer"></i> Toca para ver respuesta
                        </div>
                    </div>
                    <div class="te-flip-back">
                        <div class="te-flip-label back-label">Respuesta</div>
                        <div class="te-flip-text">${escapeHtml(card.back)}</div>
                        <div class="te-flip-hint">
                            <i class="fa-solid fa-hand-pointer"></i> Toca para ver pregunta
                        </div>
                    </div>
                    <button class="${difficultBtnClass}" id="te-dif-btn" title="${isDifficult ? 'Quitar difícil' : 'Marcar como difícil'}">
                        <i class="fa-solid fa-star"></i>
                    </button>
                </div>
            </div>
        `;

        // Evento para marcar/desmarcar difícil
        const difBtn = document.getElementById('te-dif-btn');
        if (difBtn) {
            difBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleDifficult(currentCardIndex);
            });
        }

        // Evento de flip
        const flipCard = document.getElementById('te-flip-card');
        if (flipCard) {
            flipCard.addEventListener('click', () => {
                isFlipped = !isFlipped;
                flipCard.classList.toggle('volteada', isFlipped);

                // Marcar como repasada al voltear y persistir en BD
                if (isFlipped) {
                    if (!reviewedCards.has(currentCardIndex)) {
                        reviewedCards.add(currentCardIndex);
                        guardarReview(currentCardIndex);
                        actualizarProgreso();
                    }
                }
            });
        }
    }

    // ── Escape HTML básico ─────────────────────────────────────
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ── Barajar tarjetas ───────────────────────────────────────
    function shuffleCards() {
        for (let i = cardsData.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [cardsData[i], cardsData[j]] = [cardsData[j], cardsData[i]];
        }
        currentCardIndex = 0;
        isFlipped = false;
        renderTarjetaActual();
        mostrarToast('Tarjetas barajadas 🔀', 'exito');
    }

    if (btnShuffle) {
        btnShuffle.addEventListener('click', shuffleCards);
    }

    // ── Navegación entre tarjetas ──────────────────────────────
    function navegar(delta) {
        const newIndex = currentCardIndex + delta;
        if (newIndex < 0 || newIndex >= cardsData.length) return;

        // Si la tarjeta actual está volteada, resetear antes de cambiar
        const flipCard = document.getElementById('te-flip-card');
        if (flipCard && isFlipped) {
            flipCard.classList.remove('volteada');
            isFlipped = false;
        }

        currentCardIndex = newIndex;
        renderTarjetaActual();
    }

    // ── Guardar review en BD ────────────────────────────────────
    let setIdActual = null;
    let reviewQueue = [];
    let reviewTimer = null;

    function guardarReview(cardIndex) {
        if (!setIdActual) return;

        // Agregar a la cola evitando duplicados
        if (!reviewQueue.includes(cardIndex)) {
            reviewQueue.push(cardIndex);
        }

        // Debounce: enviar 500ms después del último flip
        clearTimeout(reviewTimer);
        reviewTimer = setTimeout(() => {
            const indices = [...reviewQueue];
            reviewQueue = [];

            indices.forEach(idx => {
                fetch(`/ajax/tarjetas-estudio/${setIdActual}/reviewed`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrf(),
                    },
                    body: JSON.stringify({ card_index: idx }),
                }).catch(() => {}); // fire & forget silencioso
            });
        }, 500);
    }

    // ── Marcar/Desmarcar tarjeta como difícil ────────────────────
    async function toggleDifficult(cardIndex) {
        if (!setIdActual) return;

        const isCurrentlyDifficult = difficultCards.has(cardIndex);

        // Actualizar UI localmente de inmediato
        if (isCurrentlyDifficult) {
            difficultCards.delete(cardIndex);
        } else {
            difficultCards.add(cardIndex);
        }
        actualizarDifficultBtn();

        const method = isCurrentlyDifficult ? 'DELETE' : 'POST';

        try {
            await fetch(`/ajax/tarjetas-estudio/${setIdActual}/difficult`, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                },
                body: JSON.stringify({ card_index: cardIndex }),
            });
        } catch (err) {
            // Revertir en caso de error
            if (isCurrentlyDifficult) {
                difficultCards.add(cardIndex);
            } else {
                difficultCards.delete(cardIndex);
            }
            actualizarDifficultBtn();
            mostrarToast('Error al guardar', 'error');
        }
    }

    function actualizarDifficultBtn() {
        const difBtn = document.getElementById('te-dif-btn');
        if (!difBtn) return;
        const isDiff = difficultCards.has(currentCardIndex);
        difBtn.classList.toggle('activo', isDiff);
        difBtn.title = isDiff ? 'Quitar difícil' : 'Marcar como difícil';
    }

    if (btnPrev) btnPrev.addEventListener('click', () => navegar(-1));
    if (btnNext) btnNext.addEventListener('click', () => navegar(1));

    // Teclado: flechas izquierda/derecha
    document.addEventListener('keydown', (e) => {
        if (!cardsContainer || cardsContainer.classList.contains('oculto')) return;
        if (e.key === 'ArrowLeft') navegar(-1);
        if (e.key === 'ArrowRight') navegar(1);
        if (e.key === ' ' || e.key === 'Enter') {
            e.preventDefault();
            const flipCard = document.getElementById('te-flip-card');
            if (flipCard) {
                isFlipped = !isFlipped;
                flipCard.classList.toggle('volteada', isFlipped);

                // Persistir en BD al voltear con teclado también
                if (isFlipped) {
                    if (!reviewedCards.has(currentCardIndex)) {
                        reviewedCards.add(currentCardIndex);
                        guardarReview(currentCardIndex);
                        actualizarProgreso();
                    }
                }
            }
        }
        // Tecla D = marcar/desmarcar difícil
        if (e.key === 'd' || e.key === 'D') {
            e.preventDefault();
            toggleDifficult(currentCardIndex);
        }
    });

    // Volver a la lista
    if (btnVolver) {
        btnVolver.addEventListener('click', () => {
            mostrarEstado('lista');
            cardsData = [];
            currentCardIndex = 0;
            isFlipped = false;
        });
    }

    // ── Inicializar con datos del servidor ─────────────────────
    if (window.teSetsIniciales && window.teSetsIniciales.length > 0) {
        renderListaSets(window.teSetsIniciales);
        mostrarEstado('lista');
    } else {
        mostrarEstado('vacio');
    }
});
