document.addEventListener('DOMContentLoaded', () => {
    const btnCrearSala = document.getElementById('btnCrearSala');
    
    if (btnCrearSala) {
        btnCrearSala.addEventListener('click', async function() {
            const btn = this;
            const btnText = document.getElementById('btnText');
            const resultadoDiv = document.getElementById('resultado');
            const codigoTexto = document.getElementById('codigoSala');
            const indexPregunta = document.getElementById('indexPregunta');

            // Bloquear interfaz para evitar doble envío
            btn.disabled = true;
            btnText.innerText = "Procesando Estructura con IA...";
            btn.classList.add('opacity-60', 'cursor-not-allowed');

            try {
                // Petición asíncrona fluida a la API
                const response = await fetch('/api/rooms/create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        pdf_name: "Examen_Historia_Prueba.pdf",
                        questions: "Texto simulado procesado del PDF para la IA..."
                    })
                });

                const data = await response.json();

                if (response.ok) {
                    // Actualización inmediata del DOM
                    codigoTexto.innerText = data.room_code;
                    indexPregunta.innerText = "Pregunta 1";
                    
                    resultadoDiv.classList.remove('hidden');
                    btnText.innerText = "¡Sala Creada Exitosamente!";
                    btn.classList.replace('bg-blue-600', 'bg-emerald-600');
                    btn.classList.replace('hover:bg-blue-500', 'hover:bg-emerald-500');
                } else {
                    throw new Error(data.message || "Error al procesar en el servidor.");
                }

            } catch (error) {
                console.error("Error en el flujo Ajax:", error);
                alert("⚠️ Error: " + error.message);
                btnText.innerText = "Reintentar Generar Sala";
                btn.disabled = false;
                btn.classList.remove('opacity-60', 'cursor-not-allowed');
            }
        });
    }
});