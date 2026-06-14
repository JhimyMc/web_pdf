/**
 * MAPA MENTAL — mapa-mental.js
 * C:\laragon\www\web-pdf\resources\js\mapa-mental.js
 */

document.addEventListener('DOMContentLoaded', () => {

    // ── Referencias del DOM ───────────────────────────────────────
    const estadoVacio       = document.getElementById('mm-estado-vacio');
    const estadoCargando    = document.getElementById('mm-estado-cargando');
    const estadoMapa        = document.getElementById('mm-estado-mapa');
    const canvasSvg         = document.getElementById('mm-canvas-svg');
    const editPanel         = document.getElementById('mm-edit-panel');

    const modalOverlay      = document.getElementById('mm-modal-overlay');
    const formGenerarMapa   = document.getElementById('form-generar-mapa');
    const selectDocumento   = document.getElementById('mm-select-documento');
    const zonaSubidaRapida  = document.getElementById('mm-zona-subida-rapida');
    const fileRapido        = document.getElementById('mm-file-rapido');
    const textUploadModal   = document.getElementById('text-upload-modal');

    const btnEliminarMapa   = document.getElementById('mm-btn-eliminar');
    
    const btnNuevoVacio     = document.getElementById('mm-btn-nuevo-vacio');
    const btnNuevo          = document.getElementById('mm-btn-nuevo');
    const btnCancelarModal  = document.getElementById('mm-btn-cancelar-modal');

    let mapaData = null;
    let mapaId   = null;
    let nodoSeleccionadoId = null;
    let pollingInterval = null;

    const getCsrf = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };
    // ── Autoguardado Silencioso en Base de Datos ─────────────────
    async function autoguardarMapa() {
        if (!mapaId || !mapaData) return;
        try {
            await fetch(`/ajax/mapa-mental/${mapaId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                },
                body: JSON.stringify({ map_data: mapaData }),
            });
            console.log("Cambios guardados en BD");
        } catch (error) {
            console.error("No se pudo guardar automáticamente:", error);
        }
    }

    // ── Solución: Bug de Pantalla Vacía al volver con el botón "Atrás" ──
    window.addEventListener('pageshow', function (event) {
        // Si la página se carga desde la caché del historial del navegador
        if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
            if (window.mmMapaInicial && window.mmMapaInicial.map_data) {
                mostrarEstado('mapa');
                renderMapa(window.mmMapaInicial.map_data);
                const tituloEl = document.getElementById('mm-titulo-mapa');
                if (tituloEl) tituloEl.textContent = window.mmMapaInicial.titulo;
            }
        }
    });

    // ── Manejo de Visibilidad del Modal ─────────────────────────
    window.abrirModalPrompt = function() {
        if (modalOverlay) {
            modalOverlay.classList.remove('opacity-0', 'pointer-events-none');
            if (modalOverlay.firstElementChild) {
                modalOverlay.firstElementChild.classList.remove('scale-95');
            }
        }
    };

    window.cerrarModal = function() {
        if (modalOverlay) {
            modalOverlay.classList.add('opacity-0', 'pointer-events-none');
            if (modalOverlay.firstElementChild) {
                modalOverlay.firstElementChild.classList.add('scale-95');
            }
        }
        if (formGenerarMapa) formGenerarMapa.reset();
        if (textUploadModal) textUploadModal.textContent = "Subir y usar un nuevo archivo PDF";
    };

    if (btnNuevoVacio) btnNuevoVacio.addEventListener('click', window.abrirModalPrompt);
    if (btnNuevo) btnNuevo.addEventListener('click', window.abrirModalPrompt);
    if (btnCancelarModal) btnCancelarModal.addEventListener('click', window.cerrarModal);

    // Trigger para Drag & Drop e Input File
    if (zonaSubidaRapida && fileRapido) {
        zonaSubidaRapida.addEventListener('click', () => fileRapido.click());
        fileRapido.addEventListener('change', () => {
            if (fileRapido.files.length > 0) {
                textUploadModal.textContent = `📎 Documento cargado: ${fileRapido.files[0].name}`;
                if (selectDocumento) selectDocumento.value = ""; 
            }
        });
    }

    // Cambiar de vistas
    function mostrarEstado(estado) {
        [estadoVacio, estadoCargando, estadoMapa, editPanel].forEach(el => {
            if (el) el.classList.add('hidden', 'oculto');
        });

        if (estado === 'vacio' && estadoVacio) estadoVacio.classList.remove('hidden', 'oculto');
        else if (estado === 'cargando' && estadoCargando) estadoCargando.classList.remove('hidden', 'oculto');
        else if (estado === 'mapa' && estadoMapa) estadoMapa.classList.remove('hidden', 'oculto');
    }

    // ── Procesar Generación del Mapa Mental ──────────────────────
    if (formGenerarMapa) {
        formGenerarMapa.addEventListener('submit', async (e) => {
            e.preventDefault();

            let docId = selectDocumento ? selectDocumento.value : "";
            let pdfName = selectDocumento && selectDocumento.selectedIndex > 0 
                          ? selectDocumento.options[selectDocumento.selectedIndex].text 
                          : "Mapa Mental";

            // Si subió un archivo rápido
            if (!docId && fileRapido && fileRapido.files.length > 0) {
                // Capturar referencia al archivo ANTES de cerrar el modal (reset limpia el input)
                const archivoPDF = fileRapido.files[0];
                pdfName = archivoPDF.name;

                mostrarEstado('cargando');
                cerrarModal();

                const formData = new FormData();
                formData.append('file', archivoPDF);

                try {
                    const uploadRes = await fetch('/ajax/mapa-mental/upload-rapido', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': getCsrf() },
                        body: formData
                    });

                    const uploadData = await uploadRes.json();
                    if (uploadData.success) {
                        docId = uploadData.documento_id;
                    } else {
                        alert("Error al procesar el PDF: " + uploadData.message);
                        mostrarEstado('vacio');
                        return;
                    }
                } catch (error) {
                    alert("Ocurrió un fallo al subir el archivo.");
                    mostrarEstado('vacio');
                    return;
                }
            }

            if (!docId) {
                alert("Por favor selecciona un PDF o sube uno nuevo para continuar.");
                return;
            }

            mostrarEstado('cargando');
            cerrarModal();

            try {
                const response = await fetch('/ajax/mapa-mental/generar', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrf(),
                    },
                    body: JSON.stringify({ document_id: docId }),
                });

                const data = await response.json();

                if (data.success) {
                    // Si el mapa está procesando (PDF grande → cola de trabajos)
                    if (data.status === 'procesando') {
                        mapaId = data.mapa_id;
                        mostrarEstado('cargando');
                        iniciarPollingStatus(data.mapa_id, pdfName || data.titulo);
                        return;
                    }

                    // Si el mapa ya está listo (PDF pequeño → sincrónico)
                    mapaData = data.map_data;
                    mapaId   = data.mapa_id;

                    mostrarEstado('mapa');
                    const tituloEl = document.getElementById('mm-titulo-mapa');
                    const tituloFinal = data.titulo || pdfName || (data.map_data && data.map_data.titulo);
                    if (tituloEl) tituloEl.textContent = tituloFinal;

                    if (typeof renderMapa === 'function') {
                        renderMapa(mapaData, true);
                    }
                } else {
                    alert("Hubo un error con la IA: " + data.message);
                    mostrarEstado('vacio');
                }
            } catch (error) {
                alert("No se pudo conectar con el servidor local o la petición falló.");
                mostrarEstado('vacio');
            }
        });
    }

    // ── Eliminar Mapa Mental ──────────────────────────────
    if (btnEliminarMapa) {
        btnEliminarMapa.addEventListener('click', async () => {
            if (!mapaId) return;
            if (!confirm('¿Deseas archivar este mapa mental? Podrás crear uno nuevo.')) return;

            try {
                const response = await fetch(`/ajax/mapa-mental/${mapaId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': getCsrf(),
                        'Accept': 'application/json',
                    }
                });

                const data = await response.json();
                if (data.success) {
                    mapaData = null;
                    mapaId = null;
                    window.location.reload();
                }
            } catch (err) {
                alert("No se pudo eliminar el mapa.");
            }
        });
    }

    

    // ── Aplanar nodos jerárquicos recursivamente a formato plano ──
    function aplanarNodosRecursive(nodos) {
        const resultado = [];
        let idCounter = 1;

        function procesar(nodo, padreId) {
            const titulo = nodo.titulo || nodo.texto || nodo.text || nodo.label || nodo.name || '';
            if (!titulo.trim()) return;

            const nodeId = nodo.id || ('ai_' + (idCounter++));
            resultado.push({
                id: String(nodeId),
                texto: titulo.trim(),
                titulo: titulo.trim(),
                padre: padreId
            });

            // Procesar hijos recursivamente
            if (Array.isArray(nodo.hijos)) {
                nodo.hijos.forEach(h => procesar(h, String(nodeId)));
            }
            // Procesar sub-nodos (otros nombres posibles)
            if (Array.isArray(nodo.children)) {
                nodo.children.forEach(h => procesar(h, String(nodeId)));
            }
        }

        nodos.forEach(n => {
            // Si ya tiene id y padre, probablemente es plano — solo limpiar
            if (n.id && (n.padre !== undefined || n.parent !== undefined)) {
                const padre = n.padre || n.parent || null;
                resultado.push({
                    id: String(n.id),
                    texto: n.texto || n.text || n.titulo || n.label || 'Sin texto',
                    titulo: n.titulo || n.texto || n.text || n.label || '',
                    padre: padre === null || padre === '' || padre === undefined ? null : String(padre)
                });
            } else {
                // Es jerárquico — aplanar recursivamente
                procesar(n, null);
            }
        });

        return resultado;
    }

    // ── Motor de Renderizado Visual Premium ──
    window.renderMapa = function(mapData) {
        if (typeof d3 === 'undefined') {
            console.error("D3.js no está cargado.");
            return;
        }

        const svg = d3.select('#mm-canvas-svg');
        svg.selectAll('*').remove();

        // Paleta de colores por profundidad
        const DEPTH_COLORS = [
            { bg: '#6366f1', border: '#818cf8', text: '#ffffff' },  // Raíz: indigo
            { bg: '#0891b2', border: '#22d3ee', text: '#ffffff' },  // Nivel 1: cyan
            { bg: '#059669', border: '#34d399', text: '#ffffff' },  // Nivel 2: emerald
            { bg: '#d97706', border: '#fbbf24', text: '#ffffff' },  // Nivel 3: amber
            { bg: '#7c3aed', border: '#a78bfa', text: '#ffffff' },  // Nivel 4: violet
        ];
        const getNodeColor = (depth) => DEPTH_COLORS[Math.min(depth, DEPTH_COLORS.length - 1)];

        // 1. EXTRAER DATOS
        let nodosRAW = [];
        if (Array.isArray(mapData)) nodosRAW = mapData;
        else if (mapData && Array.isArray(mapData.nodos)) nodosRAW = mapData.nodos;
        else if (mapData && Array.isArray(mapData.nodes)) nodosRAW = mapData.nodes;

        nodosRAW = aplanarNodosRecursive(nodosRAW);

        if (nodosRAW.length === 0) {
            svg.append('text').attr('x', 50).attr('y', 50).attr('fill', '#ef4444').attr('font-size', '14px').text('No se generaron datos del mapa mental.');
            return;
        }

        // 2. LIMPIEZA
        let nodosLimpios = nodosRAW.map(n => {
            let textoReal = n.texto || n.text || n.label || n.name || n.concepto || n.titulo || "Sin texto";
            let padreReal = n.padre || n.parent || null;
            let idReal = n.id || ('auto_' + Math.random().toString(36).substr(2, 9));
            return {
                id: String(idReal),
                texto: String(textoReal),
                padre: (padreReal === null || padreReal === "" || padreReal === undefined) ? null : String(padreReal)
            };
        });

        // Parche Súper Raíz
        const raices = nodosLimpios.filter(n => n.padre === null);
        if (raices.length > 1) {
            const idSuperRaiz = "SUPER_ROOT_001";
            let tituloStr = mapData.titulo || mapData.title || "Tema Principal";
            nodosLimpios.push({ id: idSuperRaiz, texto: String(tituloStr), padre: null });
            nodosLimpios = nodosLimpios.map(n => {
                if (n.padre === null && n.id !== idSuperRaiz) n.padre = idSuperRaiz;
                return n;
            });
        } else if (raices.length === 0) {
            nodosLimpios[0].padre = null;
        }

        try {
            // 3. JERARQUÍA D3
            const root = d3.stratify().id(d => d.id).parentId(d => d.padre)(nodosLimpios);

            // 4. LAYOUT
            const nodeWidth = 220;
            const nodeHeight = 64;
            const treeLayout = d3.tree().nodeSize([nodeHeight + 40, nodeWidth + 70]);
            treeLayout(root);

            // 5. ZOOM
            const g = svg.append('g').attr('class', 'mm-capa-nodos');
            const zoom = d3.zoom()
                .scaleExtent([0.1, 3])
                .on('zoom', (event) => g.attr('transform', event.transform));
            svg.call(zoom);

            // 6. DEFINIR DEFS (sombras, gradientes)
            const defs = svg.append('defs');

            // Filtro de sombra suave
            const filter = defs.append('filter').attr('id', 'node-shadow').attr('x', '-20%').attr('y', '-20%').attr('width', '140%').attr('height', '140%');
            filter.append('feDropShadow').attr('dx', '0').attr('dy', '2').attr('stdDeviation', '4').attr('flood-color', 'rgba(0,0,0,0.35)');

            // Filtro de sombra para nodo raíz
            const filterRoot = defs.append('filter').attr('id', 'root-shadow').attr('x', '-30%').attr('y', '-30%').attr('width', '160%').attr('height', '160%');
            filterRoot.append('feDropShadow').attr('dx', '0').attr('dy', '3').attr('stdDeviation', '6').attr('flood-color', 'rgba(99,102,241,0.4)');

            // 7. DIBUJAR CONEXIONES con gradiente de color
            g.selectAll('.link')
                .data(root.links())
                .join('path')
                .attr('class', 'link')
                .attr('fill', 'none')
                .attr('stroke', d => getNodeColor(d.source.depth).border)
                .attr('stroke-width', d => Math.max(1.5, 3 - d.source.depth * 0.5))
                .attr('stroke-opacity', 0.6)
                .attr('d', d => {
                    const startX = d.source.y + nodeWidth;
                    const startY = d.source.x + nodeHeight / 2;
                    const endX = d.target.y;
                    const endY = d.target.x + nodeHeight / 2;
                    return `M ${startX} ${startY} C ${(startX + endX) / 2} ${startY}, ${(startX + endX) / 2} ${endY}, ${endX} ${endY}`;
                });

            // 8. DIBUJAR NODOS con diseño premium
            const node = g.selectAll('.node')
                .data(root.descendants())
                .join('g')
                .attr('class', 'node')
                .attr('transform', d => `translate(${d.y},${d.x})`)
                .style('cursor', 'pointer')
                .on('click', function(event, d) {
                    d3.selectAll('.node rect')
                        .attr('stroke-width', n => n.depth === 0 ? 2 : 1.5)
                        .style('filter', n => n.depth === 0 ? 'url(#root-shadow)' : 'url(#node-shadow)');
                    d3.select(this).select('rect')
                        .attr('stroke', '#3b82f6')
                        .attr('stroke-width', 3)
                        .style('filter', 'url(#root-shadow)');
                    nodoSeleccionadoId = d.id;
                    event.stopPropagation();
                });

            // Fondo del nodo con gradiente por profundidad
            node.append('rect')
                .attr('width', d => d.depth === 0 ? nodeWidth + 20 : nodeWidth)
                .attr('height', nodeHeight)
                .attr('rx', 12)
                .attr('x', d => d.depth === 0 ? -10 : 0)
                .attr('fill', d => getNodeColor(d.depth).bg)
                .attr('stroke', d => getNodeColor(d.depth).border)
                .attr('stroke-width', d => d.depth === 0 ? 2 : 1.5)
                .style('filter', d => d.depth === 0 ? 'url(#root-shadow)' : 'url(#node-shadow)');

            // Icono de profundidad (pequeño punto)
            node.filter(d => d.depth > 0)
                .append('circle')
                .attr('cx', 14)
                .attr('cy', nodeHeight / 2)
                .attr('r', 3)
                .attr('fill', d => getNodeColor(d.depth).border)
                .attr('opacity', 0.7);

            // Texto del nodo — contenedor flex que centra el texto interno
            node.append('foreignObject')
                .attr('width', d => d.depth === 0 ? nodeWidth : nodeWidth - 16)
                .attr('height', nodeHeight)
                .attr('x', d => d.depth === 0 ? 0 : 8)
                .append('xhtml:div')
                .style('width', '100%')
                .style('height', `${nodeHeight}px`)
                .style('display', 'flex')
                .style('align-items', 'center')
                .style('justify-content', 'center')
                .style('padding', '8px 12px')
                .style('box-sizing', 'border-box')
                .attr('title', d => d.data.texto)
                .append('xhtml:span')
                .style('color', d => getNodeColor(d.depth).text)
                .style('font-size', d => d.depth === 0 ? '14px' : d.depth === 1 ? '12px' : '11px')
                .style('font-family', "'Inter', 'Segoe UI', sans-serif")
                .style('font-weight', d => d.depth === 0 ? '700' : d.depth === 1 ? '600' : '400')
                .style('text-align', 'center')
                .style('line-height', '1.3')
                .style('display', '-webkit-box')
                .style('-webkit-line-clamp', '3')
                .style('-webkit-box-orient', 'vertical')
                .style('overflow', 'hidden')
                .style('word-break', 'break-word')
                .text(d => d.data.texto);

            // 9. AUTO-ENCUADRE
            function autoFit() {
                const svgNode = svg.node();
                if (!svgNode) return;
                const width = svgNode.clientWidth || window.innerWidth;
                const height = svgNode.clientHeight || window.innerHeight;
                const bounds = g.node().getBBox();

                if (bounds.width === 0) {
                    svg.call(zoom.transform, d3.zoomIdentity.translate(width / 3, height / 2).scale(0.85));
                    return;
                }

                const dx = bounds.width, dy = bounds.height;
                const x = bounds.x + dx / 2, y = bounds.y + dy / 2;
                let scale = 0.85 / Math.max(dx / width, dy / height);
                scale = Math.max(0.15, Math.min(1.2, scale));

                const transform = d3.zoomIdentity.translate(width / 2 - scale * x, height / 2 - scale * y).scale(scale);
                svg.transition().duration(700).call(zoom.transform, transform);

                // Botones de zoom
                const btnZoomIn = document.getElementById('btn-zoom-in');
                const btnZoomOut = document.getElementById('btn-zoom-out');
                const btnZoomFit = document.getElementById('btn-zoom-fit');
                if(btnZoomIn) btnZoomIn.onclick = () => svg.transition().call(zoom.scaleBy, 1.3);
                if(btnZoomOut) btnZoomOut.onclick = () => svg.transition().call(zoom.scaleBy, 0.7);
                if(btnZoomFit) btnZoomFit.onclick = () => svg.transition().call(zoom.transform, transform);
            }

            autoFit();
            setTimeout(autoFit, 300);

        } catch (error) {
            console.error("Error crítico construyendo la jerarquía:", error);
            svg.append('text').attr('x', 40).attr('y', 40).attr('fill', '#ef4444').text("No se pudo dibujar el mapa (revisa F12).");
        }
    };

    // ── Polling para mapas en proceso (PDFs grandes) ──────────────
    function iniciarPollingStatus(mapId, titulo) {
        // Evitar múltiples intervalos concurrentes
        if (pollingInterval) clearInterval(pollingInterval);
        let intentos = 0;
        const maxIntentos = 120; // Máx 10 minutos (5s x 120)

        pollingInterval = setInterval(async () => {
            intentos++;

            if (intentos > maxIntentos) {
                clearInterval(pollingInterval);
                pollingInterval = null;
                alert("El mapa mental está tomando demasiado tiempo. Intenta con un documento más pequeño.");
                mostrarEstado('vacio');
                return;
            }

            try {
                const res = await fetch(`/ajax/mapa-mental/${mapId}/status`);
                const data = await res.json();

                if (data.status === 'activo') {
                    clearInterval(pollingInterval);
                    pollingInterval = null;
                    mapaData = data.map_data;
                    mapaId = data.mapa_id || mapId;

                    mostrarEstado('mapa');
                    const tituloEl = document.getElementById('mm-titulo-mapa');
                    if (tituloEl) tituloEl.textContent = data.titulo || titulo;

                    if (typeof renderMapa === 'function') {
                        renderMapa(mapaData, true);
                    }
                } else if (data.status === 'error') {
                    clearInterval(pollingInterval);
                    pollingInterval = null;
                    alert("Hubo un error al procesar el mapa mental. Intenta con otro documento.");
                    mostrarEstado('vacio');
                }
                // Si sigue 'procesando', continuamos el polling
            } catch (err) {
                console.error("Error en polling de mapa mental:", err);
            }
        }, 5000); // Cada 5 segundos
    }

    // ── Carga de datos Inicial ────────────────────────────────────
    const mapaInicial = window.mmMapaInicial;
    if (mapaInicial && mapaInicial.map_data) {
        mapaData = mapaInicial.map_data;
        mapaId   = mapaInicial.id;
        mostrarEstado('mapa');
        if (typeof renderMapa === 'function') {
            renderMapa(mapaData, true);
        }
        const tituloEl = document.getElementById('mm-titulo-mapa');
        if (tituloEl) tituloEl.textContent = mapaInicial.titulo || mapaData.titulo;
    } else {
        mostrarEstado('vacio');
    }

    // ── Lógica de Edición del Mapa Mental ───────────────────────

    // Función auxiliar para encontrar el array de nodos (sin importar cómo lo entregó la IA)
    function obtenerNodosArray() {
        if (Array.isArray(mapaData)) return mapaData;
        if (mapaData && Array.isArray(mapaData.nodos)) return mapaData.nodos;
        if (mapaData && Array.isArray(mapaData.nodes)) return mapaData.nodes;
        return [];
    }

    // A) EDITAR TEXTO
    window.editarNodoActivo = function() {
        if (!nodoSeleccionadoId) return alert("Primero haz clic en el concepto que deseas editar.");
        
        let nodos = obtenerNodosArray();
        let nodo = nodos.find(n => n.id == nodoSeleccionadoId);
        if (!nodo) return;

        // Buscamos cuál fue la propiedad de texto que usó la IA (texto, text, label...)
        let textoActual = nodo.texto || nodo.text || nodo.label || nodo.name || nodo.concepto || "";
        
        let nuevoTexto = prompt("Edita el texto:", textoActual);
        if (nuevoTexto !== null && nuevoTexto.trim() !== "") {
            nodo.texto = nuevoTexto; // Forzamos estandarizarlo a 'texto'
            renderMapa(mapaData, true);
            autoguardarMapa();
        }
    };

    // B) AGREGAR RAMA
    window.agregarRamaActiva = function() {
        if (!nodoSeleccionadoId) return alert("Haz clic en un concepto padre para agregarle una rama.");
        
        let texto = prompt("Escribe el nuevo concepto:");
        if (texto && texto.trim() !== "") {
            let nodos = obtenerNodosArray();
            nodos.push({
                id: 'nodo_manual_' + Math.random().toString(36).substr(2, 9), // ID único
                texto: texto.trim(),
                padre: String(nodoSeleccionadoId)
            });
            renderMapa(mapaData, true);
            autoguardarMapa();
        }
    };

    // C) ELIMINAR NODO (Y sus hijos)
    window.eliminarNodoActivo = function() {
        if (!nodoSeleccionadoId) return alert("Selecciona el concepto que deseas eliminar.");
        
        let nodos = obtenerNodosArray();
        let nodo = nodos.find(n => n.id == nodoSeleccionadoId);
        
        // Evitar que borren la raíz principal por accidente
        if (!nodo || !nodo.padre || nodo.padre === "SUPER_ROOT_001") {
            return alert("No puedes eliminar el tema principal. Usa el botón 'Eliminar' general si quieres archivar todo el mapa.");
        }

        if (confirm("¿Seguro que deseas eliminar este concepto y todas las ramas que dependan de él?")) {
            // Algoritmo para encontrar todos los hijos, nietos, etc.
            let idsABorrar = new Set([String(nodoSeleccionadoId)]);
            let tamañoAnterior = 0;
            
            while(idsABorrar.size > tamañoAnterior) {
                tamañoAnterior = idsABorrar.size;
                nodos.forEach(n => {
                    if (idsABorrar.has(String(n.padre))) idsABorrar.add(String(n.id));
                });
            }

            // Filtramos conservando solo los que no están en la lista de borrado
            let nuevosNodos = nodos.filter(n => !idsABorrar.has(String(n.id)));
            
            // Actualizamos la variable original respetando su estructura
            if (Array.isArray(mapaData)) mapaData = nuevosNodos;
            else if (mapaData.nodos) mapaData.nodos = nuevosNodos;
            else if (mapaData.nodes) mapaData.nodes = nuevosNodos;

            nodoSeleccionadoId = null; // Limpiamos selección
            renderMapa(mapaData, true);
            autoguardarMapa();
        }
    };
});