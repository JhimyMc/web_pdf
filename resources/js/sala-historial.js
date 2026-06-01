document.addEventListener('DOMContentLoaded', () => {
    const buscador = document.getElementById('buscador-historial');
    const filtroEstado = document.getElementById('filtro-estado');
    const tarjetas = document.querySelectorAll('.sala-tarjeta');

    function aplicarFiltros() {
        const busqueda = buscador?.value?.toLowerCase()?.trim() || '';
        const estado = filtroEstado?.value || 'todos';

        tarjetas.forEach(tarjeta => {
            const codigo = tarjeta.dataset.codigo?.toLowerCase() || '';
            const pdf = tarjeta.dataset.pdf?.toLowerCase() || '';
            const estadoTarjeta = tarjeta.dataset.estado || '';

            let visible = true;

            // Filtro por búsqueda
            if (busqueda) {
                if (!codigo.includes(busqueda) && !pdf.includes(busqueda)) {
                    visible = false;
                }
            }

            // Filtro por estado
            if (estado !== 'todos' && estadoTarjeta !== estado) {
                visible = false;
            }

            tarjeta.style.display = visible ? 'flex' : 'none';
            tarjeta.style.flexDirection = 'column';
        });

        // Mostrar mensaje si no hay resultados
        const contenedor = document.getElementById('contenedor-salas');
        if (!contenedor) return;

        const visibles = Array.from(tarjetas).filter(t => t.style.display !== 'none');
        let mensajeVacio = contenedor.querySelector('.mensaje-vacio');

        if (visibles.length === 0 && tarjetas.length > 0) {
            if (!mensajeVacio) {
                mensajeVacio = document.createElement('div');
                mensajeVacio.className = 'mensaje-vacio col-span-full text-center py-16';
                mensajeVacio.innerHTML = `
                    <i class="fa-solid fa-search text-4xl text-slate-700 mb-4"></i>
                    <p class="text-slate-500 text-sm">No se encontraron salas con los filtros actuales.</p>
                    <button onclick="document.getElementById('buscador-historial').value=''; document.getElementById('filtro-estado').value='todos'; aplicarFiltros();"
                        class="text-red-500 hover:text-red-400 text-xs underline mt-2 cursor-pointer">Limpiar filtros</button>
                `;
                contenedor.appendChild(mensajeVacio);
            }
        } else if (mensajeVacio) {
            mensajeVacio.remove();
        }
    }

    if (buscador) buscador.addEventListener('input', aplicarFiltros);
    if (filtroEstado) filtroEstado.addEventListener('change', aplicarFiltros);
});
