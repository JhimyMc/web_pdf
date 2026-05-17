document.addEventListener('DOMContentLoaded', () => {
    const roomCode = document.querySelector('meta[name="room-code"]').getAttribute('content');
    let currentStatus = document.querySelector('meta[name="room-status"]').getAttribute('content');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    const statusContainer = document.getElementById('status-container');
    const statusText = document.getElementById('status-text');
    const indicadorPulso = document.getElementById('indicador-pulso');
    const btnReintentar = document.getElementById('btn-reintentar-ia');
    let timeoutReintento = null; 

    const btnIniciar = document.getElementById('btn-iniciar-examen');
    const btnFinalizar = document.getElementById('btn-finalizar-examen');
    const btnDescargar = document.getElementById('btn-descargar-reporte');
    const tablaBody = document.getElementById('tabla-estudiantes-body');
    const contadorAlumnos = document.getElementById('contador-alumnos');
    const filtroInput = document.getElementById('filtro-alumnos');

    let intervaloPolling = null;
    let estudiantesData = [];

    if (currentStatus === 'configurando' || currentStatus === 'generando') {
        actualizarUI(currentStatus);
        solicitarPreguntasIA();
    } else {
        actualizarUI(currentStatus);
        iniciarPolling();
    }

    async function solicitarPreguntasIA() {
        try {
            statusText.innerText = "IA generando preguntas del PDF... Por favor espera.";
            statusContainer.className = "bg-amber-500/10 border border-amber-500/20 text-amber-400 rounded-xl p-3 text-sm font-medium";
            indicadorPulso.className = "absolute top-0 right-0 w-full h-1 bg-amber-500 animate-pulse";
            
            if (btnReintentar) btnReintentar.classList.add('hidden');
            if (timeoutReintento) clearTimeout(timeoutReintento);
            
            timeoutReintento = setTimeout(() => {
                if (currentStatus === 'configurando' || currentStatus === 'generando') {
                    if (btnReintentar) btnReintentar.classList.remove('hidden');
                }
            }, 15000); // Aparece a los 15 segundos

            const response = await fetch(`/sala/api/rooms/${roomCode}/generate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            });

            const data = await response.json();

            if (response.ok && !data.error) {
                iniciarPolling();
            } else {
                statusText.innerText = data.error || "Error al intentar conectar con el motor de IA.";
                statusContainer.className = "bg-red-500/10 border border-red-500/20 text-red-400 rounded-xl p-3 text-sm font-medium";
                indicadorPulso.className = "absolute top-0 right-0 w-full h-1 bg-red-500";
            }
        } catch (error) {
            console.error("Error al solicitar IA:", error);
            statusText.innerText = "Error de red: Verifica tu conexión local.";
            statusContainer.className = "bg-red-500/10 border border-red-500/20 text-red-400 rounded-xl p-3 text-sm font-medium";
        }
    }

    function actualizarUI(estado) {
        if (estado === 'configurando' || estado === 'generando') {
            statusText.innerText = "IA generando preguntas del PDF... Por favor espera.";
            statusContainer.className = "bg-amber-500/10 border border-amber-500/20 text-amber-400 rounded-xl p-3 text-sm font-medium";
            indicadorPulso.className = "absolute top-0 right-0 w-full h-1 bg-amber-500 animate-pulse";
            
            if (btnIniciar) {
                btnIniciar.disabled = true;
                btnIniciar.className = "w-full bg-slate-700 text-slate-500 font-bold py-3 rounded-xl cursor-not-allowed flex items-center justify-center gap-2 shadow-none";
            }
        }
        else if (estado === 'espera') {
            statusText.innerText = "Sala lista. Esperando estudiantes...";
            statusContainer.className = "bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-xl p-3 text-sm font-medium";
            indicadorPulso.className = "absolute top-0 right-0 w-full h-1 bg-emerald-500";
            
            if (btnIniciar) {
                btnIniciar.disabled = false;
                btnIniciar.classList.remove('hidden');
                btnIniciar.className = "w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-3 rounded-xl transition-colors flex items-center justify-center gap-2 shadow-lg shadow-emerald-500/20";
            }
            if (btnFinalizar) btnFinalizar.classList.add('hidden');
            if (btnDescargar) btnDescargar.classList.add('hidden');
            if (btnReintentar) btnReintentar.classList.add('hidden');
        } 
        else if (estado === 'en_vivo') {
            statusText.innerText = "¡Examen en curso!";
            statusContainer.className = "bg-red-500/10 border border-red-500/20 text-red-500 rounded-xl p-3 text-sm font-medium";
            indicadorPulso.className = "absolute top-0 right-0 w-full h-1 bg-red-500 animate-pulse";
            
            if (btnIniciar) btnIniciar.classList.add('hidden');
            if (btnFinalizar) {
                btnFinalizar.classList.remove('hidden');
                btnFinalizar.classList.add('flex');
                
            }
            if (btnDescargar) btnDescargar.classList.add('hidden');
            if (btnReintentar) btnReintentar.classList.add('hidden');
        }
        else if (estado === 'finalizado') {
            statusText.innerText = "Examen finalizado.";
            statusContainer.className = "bg-slate-800 border border-slate-700 text-slate-400 rounded-xl p-3 text-sm font-medium";
            indicadorPulso.className = "absolute top-0 right-0 w-full h-1 bg-slate-600";
            
            if (btnIniciar) btnIniciar.classList.add('hidden');
            if (btnFinalizar) btnFinalizar.classList.add('hidden');
            if (btnDescargar) {
                btnDescargar.classList.remove('hidden');
                btnDescargar.classList.add('flex');
            }
            
            if (intervaloPolling) clearInterval(intervaloPolling);
            if (btnReintentar) btnReintentar.classList.add('hidden');
        }
        if (btnReintentar) {
        btnReintentar.addEventListener('click', () => {
            if (!confirm("¿Seguro que deseas reiniciar el proceso de la IA?")) return;
            solicitarPreguntasIA();
        });
    }
    }

    function iniciarPolling() {
        if (intervaloPolling) clearInterval(intervaloPolling);

        intervaloPolling = setInterval(async () => {
            try {
                const res = await fetch(`/api/rooms/${roomCode}/status`);
                if (!res.ok) return;

                const data = await res.json();
                
                estudiantesData = data.students || [];
                renderizarTabla();
                
                if (data.status !== currentStatus) {
                    currentStatus = data.status;
                    actualizarUI(currentStatus);
                }
            } catch (e) {
                console.error("Error en polling en vivo:", e);
            }
        }, 2000);
    }

    function renderizarTabla() {
        const filtro = filtroInput?.value.toLowerCase() || "";
        const filtrados = estudiantesData.filter(e => e.student_name.toLowerCase().includes(filtro))
                                         .sort((a, b) => b.score - a.score);
        
        if (contadorAlumnos) contadorAlumnos.innerText = estudiantesData.length;
        if (!tablaBody) return;

        tablaBody.innerHTML = '';

        if (filtrados.length === 0) {
            tablaBody.innerHTML = `<tr><td colspan="5" class="px-4 py-8 text-center text-slate-500 italic">No hay estudiantes aún.</td></tr>`;
            return;
        }

        filtrados.forEach((est, index) => {
            const tr = document.createElement('tr');
            tr.className = "hover:bg-slate-800/30 transition-colors";
            
            const flagHTML = est.is_flagged 
                ? `<i class="fa-solid fa-flag text-red-500 animate-bounce"></i>` 
                : `<i class="fa-solid fa-check text-slate-600"></i>`;

            tr.innerHTML = `
                <td class="px-4 py-3 text-slate-400">${index + 1}</td>
                <td class="px-4 py-3 font-medium text-slate-200">${est.student_name}</td>
                <td class="px-4 py-3 text-center text-slate-400 text-xs">${est.answered_questions} respondidas</td>
                <td class="px-4 py-3 text-center font-bold text-emerald-400">${est.score} pts</td>
                <td class="px-4 py-3 text-center">${flagHTML}</td>
            `;
            tablaBody.appendChild(tr);
        });
    }

    if (filtroInput) filtroInput.addEventListener('input', renderizarTabla);

    if (btnIniciar) {
        btnIniciar.addEventListener('click', async () => {
            if (!confirm("¿Iniciar el examen ahora? Los alumnos no podrán unirse después.")) return;
            
            await fetch(`/sala/api/rooms/${roomCode}/start`, { 
                method: 'POST', 
                headers: { 'X-CSRF-TOKEN': csrfToken } 
            });
            currentStatus = 'en_vivo';
            actualizarUI(currentStatus);
        });
    }

    if (btnFinalizar) {
        btnFinalizar.addEventListener('click', async () => {
            if (!confirm("¿Terminar el examen para todos los alumnos?")) return;
            
            await fetch(`/sala/api/rooms/${roomCode}/end`, { 
                method: 'POST', 
                headers: { 'X-CSRF-TOKEN': csrfToken } 
            });
            currentStatus = 'finalizado';
            actualizarUI(currentStatus);
        });
    }

    if (btnDescargar) {
        btnDescargar.addEventListener('click', () => {
            window.open(`/sala/reporte/${roomCode}`, '_blank');
        });
    }

    // BOTÓN DE CANCELAR REPARADO Y ESCUCHANDO DENTRO DEL BODY
    const btnCancelar = document.getElementById('btn-cancelar-sala');
    if (btnCancelar) {
        btnCancelar.addEventListener('click', async () => {
            if (!confirm("¿Estás seguro de que deseas cancelar esta sala? Todos los alumnos serán desconectados y la sala se eliminará.")) return;
            
            try {
                const response = await fetch(`/sala/api/rooms/${roomCode}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    window.location.href = '/sala/configurar';
                } else {
                    const respError = await response.json();
                    alert("No se pudo cancelar la sala: " + (respError.error || "Error del servidor."));
                }
            } catch (e) {
                console.error(e);
                alert("Error de red al intentar cancelar la sala.");
            }
        });
    }
});