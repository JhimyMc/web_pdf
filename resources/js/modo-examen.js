/**
 * PLAYDF - LÓGICA DE EXAMEN MVP FINAL
 */

const alumnosSimulados = ["Juan Pérez", "Maria Ramos", "Ricardo Gareca", "Ana Mendoza"];
const iconos = {
    usuario: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>`,
    estrella: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>`
};

let preguntaActual = 1;
let respuestasSeleccionadas = {};

window.irA = function (idPantalla) {
    document.querySelectorAll('.pantalla-examen').forEach(p => {
        p.style.display = 'none';
        p.classList.remove('activa');
    });
    const destino = document.getElementById(idPantalla);
    if (destino) {
        destino.style.display = (idPantalla === 'pantalla-B') ? 'grid' : 'flex';
        destino.classList.add('activa');
    }
};

// --- DOCENTE ---
window.verificarAccesoDocente = function () {
    window.irA('pantalla-C');
};

window.validarConfiguracionDocente = function () {
    const pdf = document.getElementById('select-pdf').value;
    const num = document.getElementById('num-preguntas').value;
    if (!pdf) return alert("Selecciona un PDF.");

    localStorage.clear(); // Limpiamos rastro de sesiones anteriores
    localStorage.setItem('pdf_seleccionado', pdf);
    localStorage.setItem('num_preguntas_total', num);
    localStorage.setItem('estado_examen', 'esperando');
    window.generarSalaExamen();
};

window.generarSalaExamen = function () {
    const codigo = Math.random().toString(36).substring(2, 7).toUpperCase();
    localStorage.setItem('codigo_sala_actual', codigo);
    document.getElementById('codigo-visual').innerText = codigo;
    window.irA('pantalla-D');
    // Iniciamos la lista, pasará a actualizarse con el storage
    simularLista('lista-alumnos-espera');
};

window.iniciarExamenParaTodos = function () {
    localStorage.setItem('estado_examen', 'iniciado_' + Date.now());
    document.getElementById('pdf-monitoreo').innerText = localStorage.getItem('pdf_seleccionado');
    window.irA('pantalla-F-Docente');
    simularProgresoExamen();
};

// --- ESTUDIANTE ---
window.unirseASala = function () {
    const codigoIngresado = document.getElementById('input-codigo-estudiante').value.trim().toUpperCase();
    const nombre = document.getElementById('input-nombre-estudiante').value.trim();
    const codigoReal = localStorage.getItem('codigo_sala_actual');

    if (codigoIngresado === codigoReal && nombre !== "") {
        localStorage.setItem('nombre_estudiante', nombre);
        // Notificamos al docente que alguien entró
        localStorage.setItem('estudiante_conectado', nombre + "_" + Date.now());

        document.getElementById('codigo-estudiante-ver').innerText = codigoReal;
        window.irA('pantalla-E2');
        simularLista('lista-alumnos-estudiante', nombre);
    } else alert("Código o nombre incorrecto.");
};

// --- CUESTIONARIO ---
window.seleccionarOpcion = function (btn, opcion) {
    const botones = btn.parentElement.querySelectorAll('.opcion-btn');
    botones.forEach(b => b.classList.remove('seleccionada'));
    btn.classList.add('seleccionada');

    respuestasSeleccionadas[preguntaActual] = opcion;

    const total = parseInt(localStorage.getItem('num_preguntas_total') || 5);
    const respondidas = Object.keys(respuestasSeleccionadas).length;

    localStorage.setItem('progreso_real', (respondidas / total) * 100);
    localStorage.setItem('nota_temporal_real', respondidas * 4);
};

window.cambiarPregunta = function (direccion) {
    const total = parseInt(localStorage.getItem('num_preguntas_total') || 5);
    preguntaActual += direccion;
    if (preguntaActual < 1) preguntaActual = 1;
    if (preguntaActual > total) preguntaActual = total;

    document.getElementById('pregunta-actual-num').innerText = preguntaActual;
    document.getElementById('pregunta-texto').innerText = `Pregunta #${preguntaActual} analizada del PDF seleccionado.`;

    const botones = document.querySelectorAll('.opcion-btn');
    botones.forEach(b => b.classList.remove('seleccionada'));
};

window.finalizarExamenEstudiante = function () {
    const respondidas = Object.keys(respuestasSeleccionadas).length;
    const notaFinal = respondidas * 4;
    document.getElementById('nota-estudiante-valor').innerText = notaFinal.toFixed(1);
    window.irA('pantalla-G');
    localStorage.setItem('examen_finalizado_real', 'true');
};

// --- SIMULACIONES ---
function simularLista(idContenedor, nombreReal = null) {
    const contenedor = document.getElementById(idContenedor);
    if (!contenedor) return;

    // Si no se pasa nombre real, intentamos buscarlo en localStorage (para la vista del docente)
    if (!nombreReal) nombreReal = localStorage.getItem('nombre_estudiante');

    contenedor.innerHTML = "";
    let lista = [...alumnosSimulados];
    if (nombreReal) lista.unshift(nombreReal + (idContenedor.includes('estudiante') ? " (Tú)" : ""));

    lista.forEach((alumno, i) => {
        const div = document.createElement('div');
        div.className = "alumno-item animar-entrada";
        const esReal = nombreReal && alumno.includes(nombreReal);
        div.innerHTML = `${esReal ? iconos.estrella : iconos.usuario} <span>${alumno}</span>`;
        if (esReal) div.style.color = "var(--color-primario)";
        contenedor.appendChild(div);
    });
}

function simularProgresoExamen() {
    const rankingDocente = document.getElementById('ranking-docente-viva');
    const nombreReal = localStorage.getItem('nombre_estudiante') || "Estudiante Real";
    const alumnos = [nombreReal, ...alumnosSimulados];

    rankingDocente.innerHTML = "";
    alumnos.forEach(nombre => {
        const div = document.createElement('div');
        div.className = "ranking-item-docente";
        div.innerHTML = `
            <span>${nombre}</span> 
            <div class="progreso-bg"><div class="barra" id="barra-${nombre.replace(/\s/g, '')}" style="width:0%"></div></div> 
            <b id="puntos-${nombre.replace(/\s/g, '')}">0.0</b>`;
        rankingDocente.appendChild(div);
    });

    const intervaloProgreso = setInterval(() => {
        alumnos.forEach(nombre => {
            const isReal = (nombre === nombreReal);
            const barra = document.getElementById(`barra-${nombre.replace(/\s/g, '')}`);
            const puntosTxt = document.getElementById(`puntos-${nombre.replace(/\s/g, '')}`);

            if (isReal) {
                barra.style.width = (localStorage.getItem('progreso_real') || 0) + "%";
                puntosTxt.innerText = parseFloat(localStorage.getItem('nota_temporal_real') || 0).toFixed(1);
            } else {
                let avance = parseFloat(barra.style.width) || 0;
                if (avance < 100) {
                    avance += Math.random() * 10;
                    if (avance > 100) avance = 100;
                    barra.style.width = avance + "%";
                    puntosTxt.innerText = ((avance / 100) * 20).toFixed(1);
                }
            }
        });
    }, 2000);
}

// --- ESCUCHADOR GLOBAL ---
window.addEventListener('storage', (e) => {
    // 1. El docente detecta que un alumno real se unió
    if (e.key === 'estudiante_conectado') {
        simularLista('lista-alumnos-espera');
    }
    // 2. El estudiante detecta que el docente inició el examen
    if (e.key.startsWith('estado_examen') && e.newValue.startsWith('iniciado')) {
        if (document.getElementById('pantalla-E2').classList.contains('activa')) {
            window.irA('pantalla-F-Estudiante');
        }
    }
});