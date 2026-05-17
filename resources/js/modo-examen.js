let roomCode = '';
let studentName = '';
let currentQuestionIndex = 0;
let isFlagged = false;
let questions = [];

// Cambiar de pantallas simulando una Single Page Application (SPA)
window.irA = function(id) {
    document.getElementById('pantalla-ingreso').classList.add('hidden');
    document.getElementById('pantalla-espera').classList.add('hidden');
    document.getElementById('pantalla-quiz').classList.add('hidden');
    document.getElementById('pantalla-resultado').classList.add('hidden');
    document.getElementById(id).classList.remove('hidden');
}

window.validarYUnirse = async function() {
    roomCode = document.getElementById('input-codigo').value.toUpperCase().trim();
    studentName = document.getElementById('input-nombre').value.trim();

    if(roomCode.length < 5 || studentName === "") {
        alert("⚠️ Por favor completa los campos correctamente.");
        return;
    }

    try {
        const response = await fetch(`/api/rooms/${roomCode}/status`);
        if(response.ok) {
            document.getElementById('nombre-espera').innerText = studentName;
            document.getElementById('codigo-ver').innerText = roomCode;
            irA('pantalla-espera');
            empezarPolling();
        } else {
            alert("❌ La sala especificada no existe o se encuentra inactiva.");
        }
    } catch (error) {
        alert("Hubo un problema de red al intentar unirse.");
    }
}

function empezarPolling() {
    const interval = setInterval(async () => {
        try {
            const response = await fetch(`/api/rooms/${roomCode}/status`);
            const data = await response.json();

            // Si el docente ya activó el examen (index >= 0)
            if(data.current_question_index >= 0 || data.current_question >= 0) {
                clearInterval(interval);
                questions = typeof data.questions === 'string' ? JSON.parse(data.questions) : data.questions;
                iniciarQuiz();
            }
        } catch (e) {
            console.error("Error buscando estado de sala...", e);
        }
    }, 3000); // Consulta el servidor cada 3 segundos sin refrescar la web
}

function iniciarQuiz() {
    irA('pantalla-quiz');
    mostrarPregunta();
}

function mostrarPregunta() {
    if(!questions || questions.length === 0) {
        // Mock de respaldo para testeo local rápido
        questions = [{
            titulo: "¿Quién descubrió América en el año 1492?",
            opciones: ["Julio César", "Cristóbal Colón", "Napoleón Bonaparte", "Simón Bolívar"],
            respuesta_correcta: 1
        }];
    }

    const q = questions[currentQuestionIndex];
    document.getElementById('pregunta-texto').innerText = q.titulo;
    document.getElementById('pregunta-actual-num').innerText = currentQuestionIndex + 1;
    
    const contenedor = document.getElementById('contenedor-opciones');
    contenedor.innerHTML = '';
    isFlagged = false;
    actualizarEstiloBandera();

    q.opciones.forEach((op, index) => {
        const btn = document.createElement('button');
        btn.className = 'opcion-btn-custom';
        btn.innerText = `${String.fromCharCode(65 + index)}) ${op}`;
        btn.onclick = () => seleccionarOpcion(btn, index);
        contenedor.appendChild(btn);
    });
}

function seleccionarOpcion(elemento, index) {
    const botones = document.querySelectorAll('.opcion-btn-custom');
    botones.forEach(b => b.classList.remove('seleccionada'));
    elemento.classList.add('seleccionada');
}

window.toggleFlag = function() {
    isFlagged = !isFlagged;
    actualizarEstiloBandera();
}

function actualizarEstiloBandera() {
    const btn = document.getElementById('btn-bandera');
    const txt = document.getElementById('txt-bandera');
    if(isFlagged) {
        btn.classList.replace('bg-slate-800', 'bg-red-950');
        btn.classList.add('text-red-400', 'border-red-800');
        txt.innerText = 'Pregunta Reportada';
    } else {
        btn.className = "flex items-center gap-1.5 px-2.5 py-1 rounded bg-slate-800 border border-slate-700 hover:text-red-400 transition-colors";
        txt.innerText = 'Reportar duda';
    }
}

window.enviarRespuesta = async function() {
    const seleccion = document.querySelector('.opcion-btn-custom.seleccionada');
    
    if (!seleccion) {
        alert("⚠️ Selecciona una opción antes de continuar.");
        return;
    }

    const q = questions[currentQuestionIndex];
    const indiceSeleccionado = Array.from(seleccion.parentNode.children).indexOf(seleccion);
    const esCorrecta = (indiceSeleccionado === q.respuesta_correcta);

    const payload = {
        room_code: roomCode,
        student_name: studentName,
        question_index: currentQuestionIndex,
        selected_option: indiceSeleccionado,
        is_flagged: isFlagged,
        is_correct: esCorrecta
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
            document.getElementById('nota-valor').innerText = esCorrecta ? "20" : "12"; 
            irA('pantalla-resultado');
        }
    } catch (error) {
        console.error("Error al transmitir datos:", error);
        irA('pantalla-resultado');
    }
}