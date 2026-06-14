document.addEventListener('DOMContentLoaded', () => {
    const roomCode = document.querySelector('meta[name="room-code"]')?.content;
    const isSolo = document.querySelector('meta[name="is-solo"]')?.content === 'true';
    const nombre = document.querySelector('meta[name="user-name"]')?.content || 'Yo';

    // Pantallas
    const pantallaEspera = document.getElementById('pantalla-espera');
    const pantallaQuiz = document.getElementById('pantalla-quiz');
    const pantallaResultado = document.getElementById('pantalla-resultado');
    const statusGenerating = document.getElementById('status-generating');
    const statusReady = document.getElementById('status-ready');

    // Elementos del quiz
    const contadorPregunta = document.getElementById('contador-pregunta');
    const totalPreguntas = document.getElementById('total-preguntas');
    const textoPregunta = document.getElementById('texto-pregunta');
    const contenedorOpciones = document.getElementById('contenedor-opciones');
    const btnEnviar = document.getElementById('btn-enviar-respuesta');
    const btnDificil = document.getElementById('btn-dificil');
    const barraProgreso = document.getElementById('barra-progreso');
    const scoreActual = document.getElementById('score-actual');

    // Estado
    let preguntas = [];
    let indiceActual = 0;
    let opcionSeleccionada = null;
    let marcada = false;
    let puntuacion = 0;
    let respuestasEnviadas = 0;
    let dificilMarcadas = new Set();

    // Polling: esperar a que se generen las preguntas
    let pollInterval = setInterval(async () => {
        try {
            const res = await fetch(`/solo-exam/api/status/${roomCode}`);
            if (!res.ok) return;
            const data = await res.json();

            if ((data.status === 'en_vivo' || data.status === 'espera') && data.questions && Array.isArray(data.questions) && data.questions.length > 0) {
                clearInterval(pollInterval);
                preguntas = data.questions;
                totalPreguntas.textContent = preguntas.length;

                if (statusGenerating) statusGenerating.classList.add('hidden');
                if (statusReady) statusReady.classList.remove('hidden');

                setTimeout(() => {
                    pantallaEspera.classList.add('hidden');
                    pantallaQuiz.classList.remove('hidden');
                    mostrarPregunta();
                }, 800);
            } else if (data.status === 'finalizado') {
                clearInterval(pollInterval);
                mostrarResultado();
            }
        } catch (e) {
            console.error('Error polling status:', e);
        }
    }, 2000);

    function mostrarPregunta() {
        if (indiceActual >= preguntas.length) {
            finalizarExamen();
            return;
        }

        const pregunta = preguntas[indiceActual];
        opcionSeleccionada = null;
        // Keep dificil state for the question display

        contadorPregunta.textContent = indiceActual + 1;
        textoPregunta.textContent = pregunta.pregunta;

        const progreso = ((indiceActual) / preguntas.length) * 100;
        barraProgreso.style.width = progreso + '%';

        contenedorOpciones.innerHTML = '';

        const letras = ['A', 'B', 'C', 'D', 'E'];
        pregunta.opciones.forEach((texto, i) => {
            const div = document.createElement('div');
            div.className = 'opcion-examen';
            div.innerHTML = `
                <span class="opcion-letra">${letras[i]}</span>
                <span class="opcion-texto">${texto}</span>
            `;
            div.addEventListener('click', () => seleccionarOpcion(i));
            contenedorOpciones.appendChild(div);
        });

        btnEnviar.disabled = true;
        btnEnviar.classList.add('opacity-40', 'cursor-not-allowed');
        btnDificil.classList.remove('bg-amber-500/20', 'text-amber-400', 'border-amber-500/30');
        btnDificil.classList.add('bg-slate-800', 'text-slate-400', 'border-slate-700');
    }

    function seleccionarOpcion(index) {
        opcionSeleccionada = index;
        document.querySelectorAll('.opcion-examen').forEach((el, i) => {
            el.classList.toggle('seleccionada', i === index);
        });
        btnEnviar.disabled = false;
        btnEnviar.classList.remove('opacity-40', 'cursor-not-allowed');
    }

    btnDificil.addEventListener('click', async () => {
        if (indiceActual >= preguntas.length) return;
        const pregunta = preguntas[indiceActual];
        const respuestaCorrecta = pregunta.opciones[pregunta.correcta];

        if (dificilMarcadas.has(indiceActual)) {
            dificilMarcadas.delete(indiceActual);
            btnDificil.classList.remove('bg-amber-500/20', 'text-amber-400', 'border-amber-500/30');
            btnDificil.classList.add('bg-slate-800', 'text-slate-400', 'border-slate-700');
            return;
        }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            await fetch('/solo-exam/api/marcar-dificil', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    room_code: roomCode,
                    question_index: indiceActual,
                    pregunta: pregunta.pregunta,
                    respuesta: respuestaCorrecta
                })
            });
            dificilMarcadas.add(indiceActual);
            btnDificil.classList.remove('bg-slate-800', 'text-slate-400', 'border-slate-700');
            btnDificil.classList.add('bg-amber-500/20', 'text-amber-400', 'border-amber-500/30');
        } catch (e) {
            console.error('Error marcando difícil:', e);
        }
    });

    btnEnviar.addEventListener('click', async () => {
        if (opcionSeleccionada === null) return;

        const pregunta = preguntas[indiceActual];
        const esCorrecta = opcionSeleccionada === pregunta.correcta;

        if (esCorrecta) {
            puntuacion++;
            scoreActual.textContent = puntuacion;
        }

        // Guardar respuesta
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            await fetch('/solo-exam/api/guardar-respuesta', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    room_code: roomCode,
                    question_index: indiceActual,
                    selected_option: opcionSeleccionada,
                    is_correct: esCorrecta,
                    is_flagged: false
                })
            });
        } catch (e) {
            console.error('Error guardando respuesta:', e);
        }

        respuestasEnviadas++;
        indiceActual++;

        if (indiceActual >= preguntas.length) {
            finalizarExamen();
        } else {
            mostrarPregunta();
        }
    });

    async function finalizarExamen() {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            await fetch(`/solo-exam/api/finalizar/${roomCode}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            });
        } catch (e) {
            console.error('Error finalizando examen:', e);
        }

        mostrarResultado();
    }

    function mostrarResultado() {
        pantallaQuiz.classList.add('hidden');
        pantallaEspera.classList.add('hidden');
        pantallaResultado.classList.remove('hidden');

        const total = preguntas.length;
        const porcentaje = total > 0 ? Math.round((puntuacion / total) * 100) : 0;

        document.getElementById('nota-valor').textContent = puntuacion;
        document.getElementById('nota-total').textContent = total;
        document.getElementById('nota-porcentaje').textContent = porcentaje + '%';

        const linkReporte = document.getElementById('link-reporte');
        if (linkReporte) {
            linkReporte.href = `/solo-exam/reporte/${roomCode}`;
        }

        barraProgreso.style.width = '100%';
    }
});
