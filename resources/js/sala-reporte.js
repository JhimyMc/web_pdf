document.addEventListener('DOMContentLoaded', () => {
    const filtroEstudiante = document.getElementById('filtro-estudiante');
    const chkBanderas = document.getElementById('chk-solo-banderas');
    const chkIncorrectas = document.getElementById('chk-solo-incorrectas');
    const btnImprimir = document.getElementById('btn-imprimir');
    const overlayCarga = document.getElementById('overlay-carga-pdf');
    const btnPdfTexto = document.getElementById('btn-pdf-texto');
    const itemsRespuesta = document.querySelectorAll('.respuesta-item');
    const preguntasBloque = document.querySelectorAll('.pregunta-bloque');

    function aplicarFiltros() {
        const estudiante = filtroEstudiante?.value || 'todos';
        const soloBanderas = chkBanderas?.checked || false;
        const soloIncorrectas = chkIncorrectas?.checked || false;

        itemsRespuesta.forEach(item => {
            const estNombre = item.dataset.estudiante;
            const tieneBandera = item.dataset.bandera === 'true';
            const esCorrecta = item.dataset.correcta === 'true';

            let visible = true;

            if (estudiante !== 'todos' && estNombre !== estudiante) {
                visible = false;
            }
            if (soloBanderas && !tieneBandera) {
                visible = false;
            }
            if (soloIncorrectas && esCorrecta) {
                visible = false;
            }

            item.style.display = visible ? 'flex' : 'none';
        });

        preguntasBloque.forEach(bloque => {
            const respuestasVisibles = bloque.querySelectorAll('.respuesta-item[style*="display: flex"]');
            const respuestasTotales = bloque.querySelectorAll('.respuesta-item');
            const sinRespuesta = bloque.querySelector('.respuestas-estudiantes p');

            if (respuestasTotales.length === 0) {
                bloque.style.display = 'block';
                return;
            }

            if (respuestasVisibles.length === 0 && (estudiante !== 'todos' || soloBanderas || soloIncorrectas)) {
                bloque.style.display = 'none';
            } else {
                bloque.style.display = 'block';
            }
        });
    }

    if (filtroEstudiante) filtroEstudiante.addEventListener('change', aplicarFiltros);
    if (chkBanderas) chkBanderas.addEventListener('change', aplicarFiltros);
    if (chkIncorrectas) chkIncorrectas.addEventListener('change', aplicarFiltros);

    // Generar PDF con html2pdf.js
    if (btnImprimir) {
        btnImprimir.addEventListener('click', async () => {
            // Mostrar overlay de carga
            overlayCarga?.classList.remove('hidden');
            btnImprimir.disabled = true;
            if (btnPdfTexto) btnPdfTexto.textContent = 'Generando...';

            try {
                // Obtener el elemento a convertir
                const elemento = document.getElementById('contenedor-reporte');

                // Crear un contenedor temporal que incluye header resumido + contenido + footer
                const tempDiv = document.createElement('div');
                tempDiv.style.cssText = 'padding: 20px; font-family: Arial, sans-serif;';

                // Título del reporte
                const titulo = document.createElement('h1');
                titulo.style.cssText = 'font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 4px;';
                titulo.textContent = document.querySelector('h1')?.textContent || 'Reporte de Sala';
                tempDiv.appendChild(titulo);

                const subtitulo = document.createElement('p');
                subtitulo.style.cssText = 'font-size: 13px; color: #64748b; margin-bottom: 16px;';
                const subOriginal = document.getElementById('reporte-subtitulo');
                subtitulo.textContent = subOriginal?.textContent || '';
                tempDiv.appendChild(subtitulo);

                // Línea divisoria
                const hr = document.createElement('hr');
                hr.style.cssText = 'border: none; border-top: 1px solid #e2e8f0; margin-bottom: 16px;';
                tempDiv.appendChild(hr);

                // Clonar el contenido del reporte
                const clone = elemento.cloneNode(true);
                // Resetear filtros visuales en el clon
                clone.querySelectorAll('.respuesta-item').forEach(el => {
                    el.style.display = 'flex';
                });
                clone.querySelectorAll('.pregunta-bloque').forEach(el => {
                    el.style.display = 'block';
                });
                tempDiv.appendChild(clone);

                // Adjuntar temporalmente al DOM para que html2canvas pueda leer los estilos
                document.body.appendChild(tempDiv);

                const opt = {
                    margin:       [10, 12, 10, 12],
                    filename:     `Reporte_Sala_${document.querySelector('meta[name="room-code"]')?.content || 'unknown'}.pdf`,
                    image:        { type: 'jpeg', quality: 0.98 },
                    html2canvas:  {
                        scale: 2,
                        useCORS: true,
                        letterRendering: true,
                        logging: false
                    },
                    jsPDF:        {
                        unit: 'mm',
                        format: 'a4',
                        orientation: 'portrait'
                    },
                    pagebreak: {
                        mode: ['avoid-all', 'css', 'legacy']
                    }
                };

                await html2pdf().set(opt).from(tempDiv).save();

                // Limpiar: remover el elemento temporal del DOM
                if (document.body.contains(tempDiv)) {
                    document.body.removeChild(tempDiv);
                }
            } catch (error) {
                console.error('Error generando PDF:', error);
                alert('Hubo un error al generar el PDF. Intenta de nuevo.');
            } finally {
                overlayCarga?.classList.add('hidden');
                btnImprimir.disabled = false;
                if (btnPdfTexto) btnPdfTexto.textContent = 'Descargar PDF';
            }
        });
    }

    // Resaltar fila al hover en estudiante de la tabla
    document.querySelectorAll('table tbody tr').forEach(tr => {
        tr.addEventListener('mouseenter', () => {
            const nombre = tr.querySelector('td:nth-child(2)')?.textContent?.trim();
            if (nombre) {
                itemsRespuesta.forEach(item => {
                    if (item.dataset.estudiante === nombre) {
                        item.style.backgroundColor = 'rgba(59, 130, 246, 0.08)';
                    }
                });
            }
        });
        tr.addEventListener('mouseleave', () => {
            itemsRespuesta.forEach(item => {
                item.style.backgroundColor = '';
            });
        });
    });


});
