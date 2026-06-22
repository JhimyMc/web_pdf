document.addEventListener('DOMContentLoaded', () => {
    const roomCode = document.querySelector('meta[name="room-code"]').getAttribute('content');
    const userName = document.querySelector('meta[name="user-name"]').getAttribute('content');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    const pantallaEspera = document.getElementById('pantalla-espera');
    const pantallaQuiz = document.getElementById('pantalla-quiz');
    const pantallaResultado = document.getElementById('pantalla-resultado');

    let questions = [];
    let currentQuestionIndex = 0;
    let isFlagged = false;
    let seleccionActual = null;
    let puntuacionTotal = 0;
    let intervaloEspera = null;

    // 1. INICIAR POLLING PARA ESPERAR AL CREADOR
    iniciarPolling();

    function iniciarPolling() {
        intervaloEspera = setInterval(async () => {
            try {
                const response = await fetch(`/api/rooms/${roomCode}/status`);
                const data = await response.json();

                if (data.status === 'en_vivo') {
                    // Check if this student has been kicked
                    const myStudent = (data.students || []).find(s => s.student_name === userName);
                    if (myStudent && myStudent.is_kicked) {
                        clearInterval(intervaloEspera);
                        mostrarExpulsado();
                        return;
                    }
                    clearInterval(intervaloEspera);
                    questions = data.questions || [];
                    empezarExamen();
                } else if (data.status === 'finalizado') {
                    clearInterval(intervaloEspera);
                    mostrarResultados();
                }
            } catch (e) {
                console.error("Error consultando estado de la sala", e);
            }
        }, 2000);
    }

    function empezarExamen() {
        if (!questions || questions.length === 0) {
            alert("Error: El examen no tiene preguntas válidas.");
            return;
        }
        
        document.getElementById('total-preguntas').innerText = questions.length;
        pantallaEspera.classList.add('hidden');
        pantallaQuiz.classList.remove('hidden');
        mostrarPregunta();

        // Intentar entrar en fullscreen al iniciar el examen
        solicitarFullscreen();
    }

    // ═══════════════════════════════════════════════════════════
    // FULLSCREEN + PREVENCIÓN DE CAMBIO DE PESTAÑA
    // ═══════════════════════════════════════════════════════════
    let examenActivo = false;
    const overlaySeguridad = document.getElementById('overlay-seguridad');

    function solicitarFullscreen() {
        examenActivo = true;
        const el = document.documentElement;
        const req = el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen || el.msRequestFullscreen;
        if (req) {
            req.call(el).catch(() => {}); // Silenciar si el navegador lo bloquea
        }
    }

    // Detectar salida de fullscreen
    document.addEventListener('fullscreenchange', () => {
        if (examenActivo && !document.fullscreenElement) {
            mostrarOverlaySeguridad();
        }
    });
    document.addEventListener('webkitfullscreenchange', () => {
        if (examenActivo && !document.webkitFullscreenElement) {
            mostrarOverlaySeguridad();
        }
    });

    // Detectar cambio de pestaña / visibilidad
    document.addEventListener('visibilitychange', () => {
        if (examenActivo && document.hidden) {
            mostrarOverlaySeguridad();
        }
    });

    // Detectar cambio de ventana (minimizar, cambiar de app)
    window.addEventListener('blur', () => {
        if (examenActivo) {
            mostrarOverlaySeguridad();
        }
    });

    function mostrarOverlaySeguridad() {
        if (overlaySeguridad) {
            overlaySeguridad.classList.remove('hidden');
            overlaySeguridad.classList.add('flex');
        }
    }

    // Botón para reanudar (volver al examen)
    const btnReanudar = document.getElementById('btn-reanudar-examen');
    if (btnReanudar) {
        btnReanudar.addEventListener('click', () => {
            overlaySeguridad.classList.add('hidden');
            overlaySeguridad.classList.remove('flex');
            solicitarFullscreen();
        });
    }

    function mostrarPregunta() {
        isFlagged = false;
        seleccionActual = null;
        actualizarBotonBandera();
        
        const q = questions[currentQuestionIndex];
        document.getElementById('contador-pregunta').innerText = currentQuestionIndex + 1;
        document.getElementById('texto-pregunta').innerText = q.pregunta;

        const contenedor = document.getElementById('contenedor-opciones');
        contenedor.innerHTML = '';

        // Aleatorizar opciones guardando su índice correcto original
        let opciones = q.opciones.map((texto, i) => ({ texto: texto, indiceOriginal: i }));
        opciones = opciones.sort(() => Math.random() - 0.5);

        opciones.forEach((opcion, indexPantalla) => {
            const btn = document.createElement('button');
            btn.className = "opcion-btn bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 font-medium p-4 md:p-5 rounded-xl text-left transition-all relative overflow-hidden";
            
            const letra = String.fromCharCode(65 + indexPantalla); // A, B, C, D...
            btn.innerHTML = `<span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-800 text-blue-400 font-bold mr-3 border border-slate-700">${letra}</span> ${opcion.texto}`;
            
            btn.addEventListener('click', () => {
                document.querySelectorAll('.opcion-btn').forEach(b => {
                    b.classList.remove('bg-blue-600/20', 'border-blue-500', 'text-white', 'shadow-[0_0_15px_rgba(59,130,246,0.3)]');
                });
                btn.classList.add('bg-blue-600/20', 'border-blue-500', 'text-white', 'shadow-[0_0_15px_rgba(59,130,246,0.3)]');
                
                seleccionActual = {
                    indiceUsuario: opcion.indiceOriginal, 
                    esCorrecta: (opcion.indiceOriginal === q.correcta)
                };
            });
            contenedor.appendChild(btn);
        });
    }

    // BOTÓN DE DUDA / BANDERA
    const btnBandera = document.getElementById('btn-bandera');
    if(btnBandera) {
        btnBandera.addEventListener('click', () => {
            isFlagged = !isFlagged;
            actualizarBotonBandera();
        });
    }

    function actualizarBotonBandera() {
        if (isFlagged) {
            btnBandera.className = "flex items-center gap-2 bg-amber-500/20 text-amber-400 px-4 py-2 rounded-xl border border-amber-500 transition-colors";
        } else {
            btnBandera.className = "flex items-center gap-2 bg-slate-800 hover:bg-amber-500/20 hover:text-amber-400 text-slate-400 px-4 py-2 rounded-xl transition-colors border border-slate-700";
        }
    }

    // ENVIAR RESPUESTA AL SERVIDOR
    const btnEnviar = document.getElementById('btn-enviar-respuesta');
    if(btnEnviar) {
        btnEnviar.addEventListener('click', async () => {
            if (!seleccionActual && !isFlagged) {
                alert("Debes seleccionar una opción o marcar la bandera de duda para avanzar.");
                return;
            }

            // Bloquear botón mientras envía
            btnEnviar.disabled = true;
            btnEnviar.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando...';

            if (seleccionActual && seleccionActual.esCorrecta) {
                puntuacionTotal += 1; // Ajusta los puntos como prefieras
            }

            const payload = {
                room_code: roomCode,
                student_name: userName,
                question_index: currentQuestionIndex,
                selected_option: seleccionActual ? seleccionActual.indiceUsuario : -1,
                is_correct: seleccionActual ? seleccionActual.esCorrecta : false,
                is_flagged: isFlagged
            };

            try {
                await fetch('/api/responses/send', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                });

                if (currentQuestionIndex < questions.length - 1) {
                    currentQuestionIndex++;
                    mostrarPregunta();
                } else {
                    mostrarResultados();
                }
            } catch (e) {
                console.error("Error al enviar", e);
                alert("Ocurrió un error enviando tu respuesta. Intenta de nuevo.");
            } finally {
                btnEnviar.disabled = false;
                btnEnviar.innerHTML = 'Siguiente Pregunta <i class="fa-solid fa-arrow-right"></i>';
            }
        });
    }

    function mostrarExpulsado() {
        examenActivo = false;
        pantallaEspera.classList.add('hidden');
        pantallaQuiz.classList.add('hidden');
        pantallaResultado.classList.add('hidden');
        // Ocultar overlay de seguridad si está visible
        if (overlaySeguridad) {
            overlaySeguridad.classList.add('hidden');
            overlaySeguridad.classList.remove('flex');
        }
        // Salir de fullscreen si está activo
        if (document.fullscreenElement) {
            document.exitFullscreen().catch(() => {});
        }
        
        // Show kicked screen
        const kickedDiv = document.createElement('div');
        kickedDiv.id = 'pantalla-expulsado';
        kickedDiv.className = 'flex-1 flex flex-col items-center justify-center p-6 z-10 text-center';
        kickedDiv.innerHTML = `
            <i class="fa-solid fa-user-slash text-6xl text-red-500 mb-6"></i>
            <h2 class="text-3xl font-black text-white mb-4">Has sido expulsado</h2>
            <p class="text-slate-400 mb-8 max-w-sm">El creador de la sala te ha removido del examen. No puedes seguir respondiendo.</p>
            <a href="/modo-examen" class="text-blue-400 hover:text-blue-300 font-medium transition-colors border border-blue-500/30 px-6 py-2 rounded-lg">Volver al Menú</a>
        `;
        document.body.appendChild(kickedDiv);
    }

    function mostrarResultados() {
        pantallaEspera.classList.add('hidden');
        pantallaQuiz.classList.add('hidden');
        pantallaResultado.classList.remove('hidden');
        document.getElementById('nota-valor').innerText = puntuacionTotal;
    }
});