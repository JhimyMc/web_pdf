<?php
// C:\laragon\www\web-pdf\app\Http\Controllers\MindMapController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MindMap;
use App\Models\Document;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

class MindMapController extends Controller
{
    /**
     * Mostrar la vista principal del mapa mental del usuario autenticado.
     * Carga el mapa actual y todos los documentos PDF cargados por el usuario.
     */
    public function index()
    {
        $mapaActual = null;
        $documentos = collect();

        if (Auth::check()) {
            $mapaActual = MindMap::where('user_id', Auth::id())
                ->where('status', 'activo')
                ->latest()
                ->first();

            // Obtenemos los documentos que el usuario ya tiene subidos
            $documentos = Document::where('user_id', Auth::id())->latest()->get();
        }

        return view('mapa-mental', compact('mapaActual', 'documentos'));
    }

    /**
     * Sube un PDF rápidamente desde la interfaz del Mapa Mental.
     */
    public function uploadRapido(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:pdf|max:10000',
        ]);

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $path = $file->storeAs('pdfs', time() . '_' . $originalName, 'public');

            $parser = new Parser();
            $pdf = $parser->parseFile(storage_path('app/public/' . $path));
            $textoLimpio = preg_replace('/\s+/', ' ', $pdf->getText());

            // Crear el registro de Documento para asociarlo
            $documento = Document::create([
                'user_id' => Auth::id(),
                'name' => $originalName,
                'path' => $path,
                'content' => $textoLimpio, // Guardar el contenido directamente
            ]);

            return response()->json([
                'success' => true,
                'documento_id' => $documento->id,
                'name' => $documento->name,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al subir el PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar un nuevo mapa mental usando n8n → LM Studio basándose en un documento.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'document_id' => 'required|exists:documents,id',
        ]);

        if (!Auth::check()) {
            return response()->json(['success' => false, 'message' => 'No autenticado.'], 401);
        }

        try {
            $documento = \App\Models\Document::where('id', $request->document_id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $contextoTexto = '';

            // 1. EXTRAER EL TEXTO DE LA BASE DE DATOS COMO LO HACE TU CHAT ORIGINAL
            if (class_exists(\App\Models\DocumentChunk::class)) {
                // Aquí el arreglo mágico: se llama "chunk_text", no "content"
                $contextoTexto = \App\Models\DocumentChunk::where('document_id', $documento->id)
                    ->pluck('chunk_text')
                    ->implode(' ');
            }

            // Validar si encontramos texto útil
            if (empty(trim($contextoTexto))) {
                return response()->json([
                    'success' => false,
                    'message' => 'No pudimos leer el texto de este PDF. Asegúrate de que tu documento haya sido cargado correctamente.'
                ], 422);
            }

            // Enviar a n8n el texto extraído
            $response = \Illuminate\Support\Facades\Http::timeout(300)->post('http://localhost:5678/webhook/playdf-mapa-mental', [
                'model' => 'meta-llama-3-8b-instruct',
                'context' => mb_substr($contextoTexto, 0, 15000), // Protegemos el límite de la IA local
                'question' => 'Genera la estructura de nodos jerárquicos completa.',
            ]);

            if (!$response->successful()) {
                return response()->json(['success' => false, 'message' => 'La IA local no respondió a la solicitud de n8n.'], 500);
            }

            $jsonData = $response->json();

            // Decodificar el JSON de LM Studio enviado desde n8n
            if (isset($jsonData['answer'])) {
                $rawAnswer = preg_replace('/```json\s*|```\s*/', '', $jsonData['answer']);
                $mapData = json_decode(trim($rawAnswer), true);
            } else {
                $mapData = $jsonData;
            }

            if (!is_array($mapData) || !isset($mapData['nodos'])) {
                return response()->json(['success' => false, 'message' => 'El JSON devuelto por la IA es inválido. Formato incorrecto.'], 422);
            }

            \App\Models\MindMap::where('user_id', Auth::id())->update(['status' => 'archivado']);

            $mapa = \App\Models\MindMap::create([
                'user_id' => Auth::id(),
                'title' => $mapData['titulo'] ?? $documento->name,
                'prompt_original' => "Mapa desde PDF: " . $documento->name,
                'map_data' => $mapData,
                'status' => 'activo',
            ]);

            return response()->json([
                'success' => true,
                'mapa_id' => $mapa->id,
                'map_data' => $mapData,
                'titulo'  => $mapa->title,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Actualizar el mapa (editar nodo, agregar, eliminar).
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'map_data' => 'required|array',
        ]);

        $mapa = MindMap::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

        $mapa->update([
            'map_data' => $request->map_data,
            'title' => $request->map_data['titulo'] ?? $mapa->title,
        ]);

        return response()->json(['success' => true, 'map_data' => $mapa->map_data]);
    }

    /**
     * Eliminar el mapa actual del usuario (archivar).
     */
    public function destroy($id)
    {
        $mapa = MindMap::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        $mapa->update(['status' => 'archivado']);

        return response()->json(['success' => true]);
    }
}
