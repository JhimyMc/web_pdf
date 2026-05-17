document.addEventListener('DOMContentLoaded', () => {
    const btnSubir = document.getElementById('btn-subir-pdf');
    const inputFile = document.getElementById('input-subir-pdf');
    const selectDoc = document.getElementById('select-documento');
    const pantallaCarga = document.getElementById('pantalla-carga');

    const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]').getAttribute('content');

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
        formData.append('file', file); // Usamos el endpoint de upload que ya existe para el chat

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
                // Crear la nueva opción
                const nuevaOpcion = document.createElement('option');
                nuevaOpcion.value = data.document.id;
                nuevaOpcion.text = data.document.name;
                
                // Agregarla al select y seleccionarla automáticamente
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