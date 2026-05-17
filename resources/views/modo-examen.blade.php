<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlayDF - Modo Examen</title>
    {{-- Agregamos FontAwesome para el icono de la bandera --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/css/modo-examen.css', 'resources/js/modo-examen.js'])
</head>

<body class="body-examen" data-auth="{{ Auth::check() ? 'true' : 'false' }}">

    <header class="header-principal flex-wrap gap-4">
        <div class="contenedor-logo-gamificacion flex-wrap">
            <div class="logo-contenedor" onclick="window.location.href='/'" style="cursor:pointer">
                <span class="logo-letra-p">P</span>
                <div class="logo-bloque-derecho">
                    <span class="logo-texto-lay">lay</span>
                    <span class="logo-texto-df">DF</span>
                </div>
            </div>
        </div>

        <div class="nav-auth">
            @auth
                <span class="link-auth">{{ Auth::user()->name }}</span>
            @else
                <a href="{{ route('login') }}" class="link-auth">Entrar</a>
            @endauth
        </div>
    </header>

    <main class="layout-examen">
        <div class="contenedor-examen">

            {{-- PANTALLA 1: INGRESO DE CÓDIGO (ESTUDIANTE) --}}
            <div id="pantalla-ingreso" class="pantalla-examen activa">
                <h2 class="titulo-pantalla">Unirse al Examen</h2>
                <div class="form-examen">
                    <label class="label-examen">Código de Sala (5 dígitos):</label>
                    <input type="text" id="input-codigo" class="input-examen" maxlength="5" placeholder="EJ: FNAF5"
                        style="text-transform: uppercase;">

                    <label class="label-examen">Tu Nombre o Apodo:</label>
                    <input type="text" id="input-nombre" class="input-examen" placeholder="Escribe tu nombre aquí">

                    <button class="boton-accion-principal" onclick="validarYUnirse()">INGRESAR A LA SALA</button>
                </div>
            </div>

            {{-- PANTALLA 2: ESPERA (LOBBY) --}}
            <div id="pantalla-espera" class="pantalla-examen">
                <h2 class="titulo-pantalla">¡Listo, <span id="nombre-espera"></span>!</h2>
                <div class="status-box">
                    <span class="punto-verde-parpadeante"></span>
                    Esperando que el profesor inicie el examen...
                </div>
                <div class="codigo-sala-container">
                    <small>CÓDIGO:</small> <b id="codigo-ver">-----</b>
                </div>
                <p style="text-align: center; color: #888;">El examen comenzará automáticamente en tu pantalla.</p>
            </div>

            {{-- PANTALLA 3: EL EXAMEN (DINÁMICO) --}}
            <div id="pantalla-quiz" class="pantalla-examen">
                <div class="header-quiz">
                    <span>Pregunta <b id="pregunta-actual-num">1</b></span>
                    {{-- BOTÓN DE BANDERA --}}
                    <button id="btn-bandera" class="btn-flag" onclick="toggleFlag()"
                        title="Reportar error en esta pregunta">
                        <i class="fa-solid fa-flag"></i> <span id="txt-bandera">Reportar</span>
                    </button>
                </div>

                <h2 id="pregunta-texto" class="titulo-pantalla">Cargando pregunta...</h2>

                <div id="contenedor-opciones" class="opciones-quiz">
                </div>

                <div class="navegacion-quiz">
                    <button class="boton-accion-exito" id="btn-siguiente" onclick="enviarRespuesta()">
                        CONFIRMAR Y SIGUIENTE
                    </button>
                </div>
            </div>

            {{-- PANTALLA 4: RESULTADOS --}}
            <div id="pantalla-resultado" class="pantalla-examen">
                <div class="card-resultado">
                    <h2>🏆 ¡Examen Terminado!</h2>
                    <p>Tu puntuación es:</p>
                    <div class="score-final">
                        <span id="nota-valor">0</span><small>/20</small>
                    </div>
                    <button class="boton-accion-principal" onclick="window.location.href='/'">Volver al Inicio</button>
                </div>
            </div>

        </div>
    </main>

    @include('partials.footer')

    {{-- Script de lógica en tiempo real --}}
    <script>
        let roomCode = '';
        let studentName = '';
        let currentQuestionIndex = 0;
        let isFlagged = false;
        let questions = [];

        function irA(id) {
            document.querySelectorAll('.pantalla-examen').forEach(p => p.classList.remove('activa'));
            document.getElementById(id).classList.add('activa');
        }

        async function validarYUnirse() {
            roomCode = document.getElementById('input-codigo').value.toUpperCase();
            studentName = document.getElementById('input-nombre').value;

            if (roomCode.length < 5 || studentName === "") {
                alert("Completa los datos");
                return;
            }

            // Llamada a Laravel para verificar si la sala existe
            const response = await fetch(`/api/rooms/${roomCode}/status`);
            if (response.ok) {
                document.getElementById('nombre-espera').innerText = studentName;
                document.getElementById('codigo-ver').innerText = roomCode;
                irA('pantalla-espera');
                empezarPolling();
            } else {
                alert("La sala no existe o ya terminó.");
            }
        }

        function empezarPolling() {
            const interval = setInterval(async () => {
                const response = await fetch(`/api/rooms/${roomCode}/status`);
                const data = await response.json();

                // Si el docente cambió el estado a "iniciado" (ej: current_question >= 0)
                if (data.current_question >= 0) {
                    clearInterval(interval);
                    questions = JSON.parse(data.questions);
                    iniciarQuiz();
                }
            }, 3000);
        }

        function iniciarQuiz() {
            irA('pantalla-quiz');
            mostrarPregunta();
        }

        function mostrarPregunta() {
            const q = questions[currentQuestionIndex];
            document.getElementById('pregunta-texto').innerText = q.titulo;
            document.getElementById('pregunta-actual-num').innerText = currentQuestionIndex + 1;

            const contenedor = document.getElementById('contenedor-opciones');
            contenedor.innerHTML = '';
            isFlagged = false; // Reset bandera
            actualizarEstiloBandera();

            q.opciones.forEach((op, index) => {
                const btn = document.createElement('button');
                btn.className = 'opcion-btn';
                btn.innerText = op;
                btn.onclick = () => seleccionarOpcion(index);
                contenedor.appendChild(btn);
            });
        }

        function toggleFlag() {
            isFlagged = !isFlagged;
            actualizarEstiloBandera();
        }

        function actualizarEstiloBandera() {
            const btn = document.getElementById('btn-bandera');
            btn.style.color = isFlagged ? '#e50914' : '#888';
            document.getElementById('txt-bandera').innerText = isFlagged ? 'Reportada' : 'Reportar';
        }

        async function enviarRespuesta() {
            const q = questions[currentQuestionIndex];
            const seleccion = document.querySelector(
            '.opcion-btn.seleccionada'); // Asumiendo que añades esta clase al hacer clic

            if (!seleccion) {
                alert("Selecciona una opción antes de continuar");
                return;
            }

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
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                // Pasar a la siguiente o terminar
                if (currentQuestionIndex < questions.length - 1) {
                    currentQuestionIndex++;
                    mostrarPregunta();
                } else {
                    irA('pantalla-resultado');
                    // Aquí podrías calcular la nota final basada en las respuestas correctas
                }
            } catch (error) {
                console.error("Error al enviar respuesta:", error);
            }
        }

        // Pequeño ajuste para que los botones se vean seleccionados
        function seleccionarOpcion(index) {
            const botones = document.querySelectorAll('.opcion-btn');
            botones.forEach(b => b.classList.remove('seleccionada'));
            botones[index].classList.add('seleccionada');
        }
    </script>
</body>

</html>
