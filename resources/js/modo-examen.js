document.addEventListener('DOMContentLoaded', () => {
    
    // Elementos de "Unirse a Sala"
    const inputCodigo = document.getElementById('input-codigo-sala');
    const inputNombre = document.getElementById('input-nombre-estudiante');
    const btnUnirse = document.getElementById('btn-unirse-sala');

    // Elementos de Creador
    const btnCrear = document.getElementById('btn-crear-sala');
    const btnHistorial = document.getElementById('btn-historial');

    // Funcionalidad UNIRSE (Estudiante)
    if (btnUnirse) {
        btnUnirse.addEventListener('click', () => {
            const codigo = inputCodigo.value.trim().toUpperCase();
            const nombre = inputNombre.value.trim();

            if (codigo.length !== 5 || nombre === "") {
                alert("Por favor, ingresa un código de 5 caracteres y tu nombre.");
                return;
            }

            // Validar si la sala existe y está en estado "espera" o "en_vivo" antes de redirigir
            fetch(`/api/rooms/${codigo}/status`)
                .then(res => res.json() .then(data => {
                    if (data.error) {
                        alert("❌ La sala no existe.");
                    } else if (data.status === 'configurando') {
                        // Solo bloqueamos si el docente sigue literalmente editando el formulario inicial
                        alert("⏳ La sala aún no está lista. El docente la está configurando.");
                    } else if (data.status === 'finalizado') {
                        alert("🛑 Esta sala ya ha finalizado.");
                    } else {
                        // 🚀 AQUÍ: Si está en 'generando', 'espera' o 'en_vivo', lo dejamos entrar de una vez
                        window.location.href = `/sala/play/${codigo}?nombre=${encodeURIComponent(nombre)}`;
                    }
                }))
                .catch(() => alert("Error de conexión. Intenta de nuevo."));
        });
    }

    // Funcionalidad CREAR SALA (Docente/Usuario Registrado)
    if (btnCrear && window.isLoggedIn) {
        btnCrear.addEventListener('click', () => {
            // Redirige a la vista de configuración del creador
            window.location.href = `/sala/configurar`;
        });
    }

    // Funcionalidad HISTORIAL
    if (btnHistorial && window.isLoggedIn) {
        btnHistorial.addEventListener('click', () => {
            window.location.href = `/sala/historial`;
        });
    }
});