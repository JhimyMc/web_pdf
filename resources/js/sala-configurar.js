document.addEventListener('DOMContentLoaded', () => {
    const btnSubir = document.getElementById('btn-subir-pdf');
    const inputFile = document.getElementById('input-subir-pdf');
    const selectDoc = document.getElementById('select-documento');
    const pantallaCarga = document.getElementById('pantalla-carga');

    const modalSalaActiva = document.getElementById('modal-sala-activa');
    const btnCerrarModal = document.getElementById('btn-cerrar-modal');
    const btnCrearNueva = document.getElementById('btn-crear-nueva');
    const formCrearNueva = document.getElementById('form-crear-nueva');

    const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // ── Manejo del modal de sala activa ──
    if (modalSalaActiva && formCrearNueva) {
        // Cerrar modal
        if (btnCerrarModal) {
            btnCerrarModal.addEventListener('click', () => {
                modalSalaActiva.classList.add('hidden');
            });
        }

        // Crear nueva sala con los valores del formulario principal
        btnCrearNueva.addEventListener('click', (e) => {
            e.preventDefault();

            const docId = selectDoc?.value;
            if (!docId) {
                alert('Primero selecciona o sube un documento en el formulario.');
                return;
            }

            const numQuestions = document.querySelector('select[name="num_questions"]')?.value || '10';
            const difficulty = document.querySelector('select[name="difficulty"]')?.value || 'intermedio';

            document.getElementById('modal-document-id').value = docId;
            document.getElementById('modal-num-questions').value = numQuestions;
            document.getElementById('modal-difficulty').value = difficulty;

            formCrearNueva.submit();
        });
    }

    // ── Subir PDF ──
    if (btnSubir && inputFile) {
        btnSubir.addEventListener('click', () => inputFile.click());

        inputFile.addEventListener('change', async function() {
            if (this.files.length > 0) {
                await subirArchivo(this.files[0]);
            }
        });
    }

    async function subirArchivo(file) {
        pantallaCarga.classList.remove('hidden');
        pantallaCarga.classList.add('flex');

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

            const data = await response.json();
            
            pantallaCarga.classList.add('hidden');
            pantallaCarga.classList.remove('flex');

            if(data.success) {
                const nuevaOpcion = document.createElement('option');
                nuevaOpcion.value = data.document.id;
                nuevaOpcion.text = data.document.name;
                
                selectDoc.appendChild(nuevaOpcion);
                selectDoc.value = data.document.id;
            } else {
                alert("Hubo un problema al procesar el archivo en el servidor.");
            }
        } catch (error) {
            pantallaCarga.classList.add('hidden');
            pantallaCarga.classList.remove('flex');
            alert("Error de red al intentar subir el PDF.");
        }
    }
});