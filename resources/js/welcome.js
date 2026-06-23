document.addEventListener('DOMContentLoaded', () => {
    const fileInput = document.getElementById('pdf-file-input');
    const fileInputMovil = document.getElementById('pdf-file-input-movil');
    const btnCargarDoc = document.getElementById('btn-cargar-doc');
    const btnCargarPdfMovil = document.getElementById('btn-cargar-pdf-movil');
    const btnSeleccionarCentro = document.getElementById('btn-seleccionar-centro');
    const zonaDrop = document.getElementById('zona-drop');
    const pantallaCarga = document.getElementById('pantalla-carga');
    const contenedorChat = document.getElementById('contenedor-chat');
    const historialChat = document.getElementById('historial-chat');
    const listaPdfs = document.getElementById('lista-pdfs') || document.querySelector('.lista-pdfs-clase');

    const wrapperBusqueda = document.getElementById('wrapper-busqueda');
    const inputPregunta = document.getElementById('input-pregunta');
    const btnEnviarPregunta = document.getElementById('btn-enviar-pregunta');

    let documentoSeleccionadoId = null;

    const getCsrfToken = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    function verificarAutenticacion() {
        if (!window.isLoggedIn) {
            alert("Debes iniciar sesión para interactuar con PlayDF.");
            window.location.href = window.loginRoute;
            return false;
        }
        return true;
    }

    // ⚡ FUNCIÓN UNIFICADA DE APERTURA DEL SELECTOR
    function dispararSelectorArchivos(e) {
        if(e) e.stopPropagation();
        if (verificarAutenticacion()) {
            if(window.cerrarMenuMovilId) window.cerrarMenuMovilId(); // Cierra el menú móvil si estaba abierto
            fileInput.click();
        }
    }

    // Evento Escritorio Lateral
    if(btnCargarDoc) btnCargarDoc.addEventListener('click', dispararSelectorArchivos);
    
    // Evento Móvil Lateral
    if(btnCargarPdfMovil) btnCargarPdfMovil.addEventListener('click', dispararSelectorArchivos);

    // Evento Zona Central
    if(btnSeleccionarCentro) btnSeleccionarCentro.addEventListener('click', dispararSelectorArchivos);

    if(zonaDrop) {
        zonaDrop.addEventListener('click', () => {
            if(fileInput) dispararSelectorArchivos();
        });

        zonaDrop.addEventListener('dragover', (e) => {
            e.preventDefault();
        });

        zonaDrop.addEventListener('drop', async (e) => {
            e.preventDefault();
            if (!verificarAutenticacion()) return;

            const files = e.dataTransfer.files;
            if (files.length > 0 && files[0].type === 'application/pdf') {
                await subirYProcesarPDF(files[0]);
            } else {
                alert('Por favor, suelta un archivo PDF válido.');
            }
        });
    }

    if(fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0 && verificarAutenticacion()) {
                subirYProcesarPDF(this.files[0]);
            }
        });
    }

    if(fileInputMovil) {
        fileInputMovil.addEventListener('change', function() {
            if (this.files.length > 0 && verificarAutenticacion()) {
                subirYProcesarPDF(this.files[0]);
            }
        });
    }

    async function subirYProcesarPDF(file) {
        pantallaCarga.classList.remove('hidden');
        zonaDrop.classList.add('hidden');

        const formData = new FormData();
        formData.append('file', file);

        try {
            const response = await fetch('/ajax/documents/upload', {
                method: 'POST',
                body: formData,
                credentials: 'include',
                headers: { 
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                }
            });

            if (!response.ok) {
                alert(`Error del servidor (${response.status}).`);
                pantallaCarga.classList.add('hidden');
                zonaDrop.classList.remove('hidden');
                return;
            }

            const data = await response.json();
            pantallaCarga.classList.add('hidden');

            if(data.success) {
                if (listaPdfs) {
                    const nuevoDocHtml = `
                        <div id="doc-item-${data.document.id}" class="pdf-item group flex items-center justify-between p-2.5 bg-slate-900 border border-slate-800 rounded-xl hover:bg-slate-850 transition-colors" data-id="${data.document.id}" data-name="${data.document.name}">
                            <button 
                                onclick="seleccionarDocumento(${data.document.id}, '${data.document.name}')"
                                class="flex items-center gap-2.5 text-xs text-slate-300 font-medium overflow-hidden text-ellipsis whitespace-nowrap text-left flex-1 mr-2">
                                <i class="fa-solid fa-file-pdf text-red-500 text-sm flex-shrink-0"></i>
                                <span class="truncate" title="${data.document.name}">${data.document.name}</span>
                            </button>
                            <button 
                                onclick="eliminarDocumento(event, ${data.document.id})" 
                                class="text-slate-500 hover:text-red-400 p-1 px-2 rounded-lg hover:bg-slate-800 transition-colors"
                                title="Eliminar documento">
                                <i class="fa-solid fa-trash-can text-xs"></i>
                            </button>
                        </div>
                    `;
                    listaPdfs.insertAdjacentHTML('afterbegin', nuevoDocHtml);
                }
                
                const textoVacio = document.getElementById('texto-vacio-fuentes');
                if (textoVacio) textoVacio.remove();

                activarDocumento(data.document.id, data.document.name);
            } else {
                zonaDrop.classList.remove('hidden');
                alert("Hubo un problema al procesar el archivo en el servidor.");
            }
        } catch (error) {
            console.error(error);
            pantallaCarga.classList.add('hidden');
            zonaDrop.classList.remove('hidden');
            alert("No se pudo conectar con el servidor.");
        }
    }

    async function activarDocumento(id, nombreArchivo) {
        documentoSeleccionadoId = id;
        window.documentoSeleccionadoId = id;
        
        const elementoVisual = document.getElementById(`doc-item-${id}`);

        document.querySelectorAll('.pdf-item').forEach(item => item.classList.remove('border-blue-500', 'bg-slate-850'));
        if(elementoVisual) elementoVisual.classList.add('border-blue-500', 'bg-slate-850');

        zonaDrop.classList.add('hidden');
        pantallaCarga.classList.add('hidden');
        contenedorChat.classList.remove('hidden');

        if (wrapperBusqueda) wrapperBusqueda.classList.remove('opacity-40');
        inputPregunta.classList.remove('cursor-not-allowed', 'text-slate-400');
        inputPregunta.classList.add('text-slate-200');
        inputPregunta.placeholder = `Haz una pregunta sobre "${nombreArchivo}"...`;
        inputPregunta.removeAttribute('disabled');
        
        btnEnviarPregunta.disabled = false;
        btnEnviarPregunta.classList.remove('text-slate-600', 'cursor-not-allowed');
        btnEnviarPregunta.classList.add('text-blue-500');
        btnEnviarPregunta.removeAttribute('disabled');

        historialChat.innerHTML = '<div class="text-slate-500 text-xs italic p-2">Cargando conversación...</div>';

        try {
            const response = await fetch(`/ajax/documents/${id}/messages`, { credentials: 'include' });

            if (!response.ok) {
                historialChat.innerHTML = '<div class="text-red-400 text-xs p-2">Error al cargar historial.</div>';
                return;
            }

            const data = await response.json();
            historialChat.innerHTML = '';

            const welcomeDiv = document.createElement('div');
            welcomeDiv.className = 'flex justify-start chat-msg-animate';
            welcomeDiv.innerHTML = `
                <div class="bg-gradient-to-br from-slate-950 to-slate-900 border border-slate-800 text-slate-200 text-xs p-4 rounded-2xl rounded-tl-none max-w-sm shadow-xl">
                    <p class="font-bold text-blue-400 mb-1">PlayDF Listo</p>
                    Documento: <span class="text-red-400 font-semibold">${nombreArchivo}</span>.
                </div>
            `;
            historialChat.appendChild(welcomeDiv);

            if(data.success && data.messages && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    const msgDiv = document.createElement('div');
                    msgDiv.className = (msg.sender === 'user' ? 'flex justify-end' : 'flex justify-start') + ' chat-msg-animate';
                    
                    // ✨ CORRECCIÓN AQUÍ: Se añade marked.parse() y la clase markdown-body también al cargar el historial del bot
                    msgDiv.innerHTML = msg.sender === 'user' 
                        ? `<div class="bg-blue-600 text-white text-xs p-3 rounded-2xl rounded-tr-none max-w-xs shadow-md">${msg.message}</div>`
                        : `<div class="bg-slate-950 border border-slate-800 text-slate-200 text-xs p-3 rounded-2xl rounded-tl-none max-w-xs shadow-md markdown-body">${marked.parse(msg.message)}</div>`;
                    historialChat.appendChild(msgDiv);
                });
            }
        } catch (e) {
            historialChat.innerHTML = '<div class="text-red-400 text-xs p-2">Error inesperado al recuperar el historial.</div>';
        }
        historialChat.scrollTop = historialChat.scrollHeight;
    }

    async function mandarPregunta() {
        const query = inputPregunta.value.trim();
        if(!query || !documentoSeleccionadoId) return;

        const userDiv = document.createElement('div');
        userDiv.className = 'flex justify-end chat-msg-animate';
        userDiv.innerHTML = `<div class="bg-blue-600 text-white text-xs p-3 rounded-2xl rounded-tr-none max-w-xs shadow-md">${query}</div>`;
        historialChat.appendChild(userDiv);
        
        inputPregunta.value = '';
        historialChat.scrollTop = historialChat.scrollHeight;

        const loadId = 'load-' + Date.now();
        const loadDiv = document.createElement('div');
        loadDiv.className = 'flex justify-start chat-msg-animate';
        loadDiv.innerHTML = `
            <div id="${loadId}" class="bg-slate-950 border border-slate-800 text-slate-400 text-xs px-4 py-3 rounded-2xl rounded-tl-none shadow-md flex items-center gap-1.5">
                <span class="typing-dot" style="animation-delay: 0s"></span>
                <span class="typing-dot" style="animation-delay: 0.15s"></span>
                <span class="typing-dot" style="animation-delay: 0.3s"></span>
            </div>`;
        historialChat.appendChild(loadDiv);
        historialChat.scrollTop = historialChat.scrollHeight;

        try {
            const response = await fetch('/ajax/chat/ask', {
                method: 'POST',
                credentials: 'include', 
                headers: { 
                    'Content-Type': 'application/json', 
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({ question: query, document_id: documentoSeleccionadoId })
            });

            if (!response.ok) {
                if(document.getElementById(loadId)) document.getElementById(loadId).parentElement.remove();
                const errorDiv = document.createElement('div');
                errorDiv.className = 'flex justify-start chat-msg-animate';
                errorDiv.innerHTML = `<div class="bg-red-950 border border-red-800 text-red-300 text-xs p-3 rounded-2xl rounded-tl-none max-w-xs shadow-md">⚠️ Error del servidor.</div>`;
                historialChat.appendChild(errorDiv);
                return;
            }

            const data = await response.json();
            if(document.getElementById(loadId)) document.getElementById(loadId).parentElement.remove();

            const botDiv = document.createElement('div');
            botDiv.className = 'flex justify-start chat-msg-animate';
            
            // ✨ CORRECCIÓN AQUÍ: Garantizar el parsing correcto
            botDiv.innerHTML = `<div class="bg-slate-950 border border-slate-800 text-slate-200 text-xs p-3 rounded-2xl rounded-tl-none max-w-xs shadow-md markdown-body">${marked.parse(data.answer)}</div>`;
            historialChat.appendChild(botDiv);
        } catch (e) {
            if(document.getElementById(loadId)) document.getElementById(loadId).parentElement.remove();
            const fatalDiv = document.createElement('div');
            fatalDiv.className = 'flex justify-start chat-msg-animate';
            fatalDiv.innerHTML = `<div class="bg-red-950 border border-red-800 text-red-300 text-xs p-3 rounded-2xl rounded-tl-none max-w-xs shadow-md">🚨 Error de red.</div>`;
            historialChat.appendChild(fatalDiv);
        }
        historialChat.scrollTop = historialChat.scrollHeight;
    }

    if(btnEnviarPregunta) btnEnviarPregunta.addEventListener('click', mandarPregunta);
    if(inputPregunta) {
        inputPregunta.addEventListener('keypress', (e) => { 
            if(e.key === 'Enter') mandarPregunta(); 
        });
    }

    async function eliminarDocumento(event, id) {
        event.stopPropagation();
        if (!confirm("¿Estás seguro de que deseas eliminar este PDF?")) return;

        try {
            const response = await fetch(`/documentos/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();

            if (data.success) {
                const contenedorDoc = document.getElementById(`doc-item-${id}`);
                if (contenedorDoc) contenedorDoc.remove();

                if (window.documentoSeleccionadoId === id) {
                    window.documentoSeleccionadoId = null;
                    document.getElementById('contenedor-chat').classList.add('hidden');
                    if (wrapperBusqueda) wrapperBusqueda.classList.add('hidden');
                    historialChat.innerHTML = '';
                }
            } else {
                alert("Error: " + data.message);
            }
        } catch (error) {
            alert("Ocurrió un error de red al intentar eliminar.");
        }
    }

    window.eliminarDocumento = eliminarDocumento;
    window.seleccionarDocumento = activarDocumento;
});