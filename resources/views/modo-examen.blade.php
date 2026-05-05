<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlayDF - Modo Examen</title>
    {{-- Importación de estilos y JS --}}
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
            
            <div class="seccion-gamificacion">
                <div class="indicador-racha">Racha: 5 Dias</div>
                <div class="indicador-nivel">Nivel: 12</div>
            </div>
        </div>
        
        <div class="nav-auth">
            @if (Route::has('login'))
                @auth
                    <span class="link-auth" style="margin-right: 15px; color: var(--color-gris-claro);">
                        {{ Auth::user()->name }}
                    </span>
                    <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                        @csrf
                        <a href="{{ route('logout') }}" class="link-auth" 
                        onclick="event.preventDefault(); this.closest('form').submit();">
                            Salir
                        </a>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="link-auth">Entrar</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="boton-registro">Registrarse</a>
                    @endif
                @endauth
            @endif
        </div>
    </header>

    <main class="layout-examen">
        <div class="contenedor-examen">

            <div id="pantalla-B" class="pantalla-examen activa">
                <h2 class="titulo-pantalla">¿Cómo deseas ingresar?</h2>
                <div class="botones-rol-grid">
                    <button class="boton-rol-card" onclick="irA('pantalla-C')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 18a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><circle cx="12" cy="10" r="2"></circle><line x1="7" x2="7" y1="2" y2="4"></line><line x1="17" x2="17" y1="2" y2="4"></line></svg>
                        <span class="texto-rol">Soy Docente</span>
                    </button>
                    
                    <button class="boton-rol-card" onclick="irA('pantalla-E')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
                        <span class="texto-rol">Soy Estudiante</span>
                    </button>
                </div>
            </div>

            <div id="pantalla-C" class="pantalla-examen">
                <button class="boton-volver" onclick="irA('pantalla-B')">← Volver</button>
                <h2 class="titulo-pantalla">Configurar Evaluación</h2>
                
                <div class="form-examen">
                    <label class="label-examen">1. Selecciona el material (PDF):</label>
                    <select id="select-pdf" class="input-examen">
                        <option value="">-- Seleccionar archivo --</option>
                        <option value="Clase_01_Arquitectura.pdf">Clase_01_Arquitectura.pdf</option>
                        <option value="Semana_05_Ingenieria_Web.pdf">Semana_05_Ingenieria_Web.pdf</option>
                        <option value="subir">➕ Subir nuevo PDF...</option>
                    </select>

                    <label class="label-examen">2. Número de preguntas:</label>
                    <input type="number" id="num-preguntas" class="input-examen" value="5" min="1" max="20">

                    <button class="boton-accion-principal" onclick="validarConfiguracionDocente()">Crear Sala de Examen</button>
                </div>
            </div>

            <div id="pantalla-D" class="pantalla-examen">
                <button class="boton-volver" onclick="irA('pantalla-C')">← Configuración</button>
                <h2 class="titulo-pantalla">Sala de Espera</h2>
                <div class="codigo-sala-container">
                    <span id="codigo-visual">-----</span>
                </div>

                <div class="status-box">
                    <span class="punto-verde-parpadeante"></span>
                    Conectando alumnos en tiempo real...
                </div>

                <div id="lista-alumnos-espera" class="lista-alumnos">
                    <div class="alumno-item">Esperando participantes...</div>
                </div>

                <button class="boton-accion-exito" onclick="iniciarExamenParaTodos()">
                    EMPEZAR AHORA
                </button>
            </div>

            <div id="pantalla-E" class="pantalla-examen">
                <button class="boton-volver" onclick="irA('pantalla-B')">← Volver</button>
                <h2 class="titulo-pantalla">Unirse al Examen</h2>
                <div class="form-examen">
                    <label class="label-examen">Código de Sala:</label>
                    <input type="text" id="input-codigo-estudiante" class="input-examen" maxlength="5" placeholder="Ej: XJ82P" style="text-transform: uppercase;">
                    
                    <label class="label-examen">Tu Nombre:</label>
                    <input type="text" id="input-nombre-estudiante" class="input-examen" placeholder="Nombre Apellido">
                    
                    <button class="boton-accion-principal" onclick="unirseASala()">Validar y Entrar</button>
                </div>
            </div>

            <div id="pantalla-E2" class="pantalla-examen">
                <h2 class="titulo-pantalla">¡Te has unido!</h2>
                <div class="status-box">
                    <span class="punto-verde-parpadeante"></span>
                    Esperando que el profesor inicie...
                </div>
                
                <div class="codigo-sala-container" style="padding: 10px;">
                    <small>SALA:</small> <b id="codigo-estudiante-ver">-----</b>
                </div>

                <div id="lista-alumnos-estudiante" class="lista-alumnos"></div>
            </div>

                <div id="pantalla-F-Docente" class="pantalla-examen">
                    <h2 class="titulo-pantalla">Monitoreo en Vivo</h2>
                    <p style="color: var(--color-gris-claro);">PDF Analizado: <span id="pdf-monitoreo" style="color: var(--color-primario); font-weight: bold;"></span></p>
                    
                    <div id="ranking-docente-viva" class="ranking-container-docente"></div>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button class="boton-accion-exito" style="flex: 1; margin-top:0;" onclick="alert('Resultados guardados en la base de datos.')">
                            Descargar Reporte
                        </button>
                        <button class="boton-accion-principal" style="flex: 1; margin-top:0;" onclick="window.location.href='/'">
                            Salir al Inicio
                        </button>
                    </div>
                </div>

            <div id="pantalla-F-Estudiante" class="pantalla-examen">
                <div class="header-quiz" style="width: 100%; display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <span>Pregunta <b id="pregunta-actual-num">1</b> de <b>5</b></span>
                </div>
                <h2 id="pregunta-texto" class="titulo-pantalla" style="font-size: 1.4rem; min-height: 80px;">¿Cargando pregunta desde el PDF...?</h2>
                
                <div class="opciones-quiz">
                    <button class="opcion-btn" onclick="seleccionarOpcion(this, 'A')">Opción A</button>
                    <button class="opcion-btn" onclick="seleccionarOpcion(this, 'B')">Opción B</button>
                    <button class="opcion-btn" onclick="seleccionarOpcion(this, 'C')">Opción C</button>
                </div>

                <div class="navegacion-quiz" style="display: flex; gap: 10px; margin-top: 30px; width: 100%;">
                    <button class="boton-volver" style="margin:0; flex: 1;" onclick="cambiarPregunta(-1)">Anterior</button>
                    <button class="boton-volver" style="margin:0; flex: 1;" onclick="cambiarPregunta(1)">Siguiente</button>
                </div>

                <button class="boton-accion-exito" style="margin-top: 20px; width: 100%;" onclick="finalizarExamenEstudiante()">
                    ENTREGAR EXAMEN
                </button>
            </div>

            <div id="pantalla-G" class="pantalla-examen">
                <div class="card-resultado" style="text-align: center; background: #1a1a1a; padding: 40px; border-radius: 20px; border: 2px solid var(--color-primario);">
                    <h2 style="font-size: 2rem;">🏆 Examen Finalizado</h2>
                    <p style="margin: 20px 0; color: var(--color-gris-claro);">Tu nota final es:</p>
                    <div style="font-size: 4rem; font-weight: 900; color: var(--color-primario);">
                        <span id="nota-estudiante-valor">0.0</span><small style="font-size: 1.5rem;">/20</small>
                    </div>
                    <button class="boton-accion-principal" style="margin-top: 30px;" onclick="window.location.href='/'">Volver al Inicio</button>
                </div>
            </div>

        </div>
    </main>

    @include('partials.footer')
</body>
</html>