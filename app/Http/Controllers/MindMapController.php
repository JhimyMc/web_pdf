<?php
// C:\laragon\www\web-pdf\app\Http\Controllers\MindMapController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MindMap;
use App\Models\Document;
use App\Jobs\GenerateMindMapChunkJob;
use App\Jobs\MergeMindMapJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Collection;
use Smalot\PdfParser\Parser;
use App\Traits\ChunkableMindMap;
use App\Models\DocumentChunk;

class MindMapController extends Controller
{
    use ChunkableMindMap;
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
                'extracted_text' => $textoLimpio,
                'tokens_estimated' => (int) (mb_strlen($textoLimpio) / 4),
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
     * Generar un nuevo mapa mental basándose en un documento.
     * 
     * PDFs pequeños (≤4000 chars) → n8n sincrónico (respuesta inmediata)
     * PDFs grandes (>4000 chars) → cola de trabajos con chunks (asíncrono)
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
            $documento = Document::where('id', $request->document_id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            // Prioridad: DocumentChunk > extracted_text del documento
            $contextoTexto = '';

            if (class_exists(DocumentChunk::class)) {
                $chunkText = DocumentChunk::where('document_id', $documento->id)
                    ->pluck('chunk_text')
                    ->implode(' ');
                if (!empty(trim($chunkText))) {
                    $contextoTexto = $chunkText;
                }
            }

            // Fallback: usar extracted_text directamente del documento
            if (empty(trim($contextoTexto))) {
                $contextoTexto = $documento->extracted_text ?? '';
            }

            if (empty(trim($contextoTexto))) {
                return response()->json([
                    'success' => false,
                    'message' => 'No pudimos leer el texto de este PDF. Asegúrate de que tu documento haya sido cargado correctamente.'
                ], 422);
            }

            // Archivar mapas anteriores del usuario
            MindMap::where('user_id', Auth::id())->update(['status' => 'archivado']);

            $longitudTexto = mb_strlen(trim($contextoTexto));

            // ─── PDF PEQUEÑO: procesar directamente con n8n (sincrónico) ───
            if ($longitudTexto <= 4000) {
                Log::info("[MindMap] PDF pequeño ({$longitudTexto} chars) → procesando vía n8n");

                $response = Http::timeout(300)->post('http://localhost:5678/webhook/playdf-mapa-mental', [
                    'model'    => 'meta-llama-3-8b-instruct',
                    'context'  => $contextoTexto,
                    'question' => 'Analiza el texto y genera un mapa mental jerárquico con conceptos descriptivos (no palabras sueltas). Cada título debe explicar el concepto en 15-80 caracteres. Extrae 3-6 nodos principales con 2-4 subconceptos cada uno. Máximo 3 niveles. Devuelve SOLO el JSON.',
                ]);

                if (!$response->successful()) {
                    return response()->json(['success' => false, 'message' => 'Error al conectar con la IA local.'], 500);
                }

                $jsonData = $response->json();

                // Decodificar JSON — maneja múltiples formatos de respuesta de n8n
                $rawAnswer = $jsonData['answer'] ?? $jsonData['output'] ?? $jsonData['response'] ?? json_encode($jsonData);

                // Limpiar markdown code blocks y texto conversacional
                $rawAnswer = preg_replace('/```json\s*/i', '', $rawAnswer);
                $rawAnswer = preg_replace('/```\s*/', '', $rawAnswer);
                $rawAnswer = trim($rawAnswer);

                // Buscar el JSON envuelto en texto
                $start = strpos($rawAnswer, '{');
                $end = strrpos($rawAnswer, '}');
                if ($start !== false && $end !== false && $end > $start) {
                    $mapData = json_decode(substr($rawAnswer, $start, $end - $start + 1), true);
                } else {
                    $mapData = json_decode($rawAnswer, true);
                }

                if (!is_array($mapData) || empty($mapData)) {
                    Log::error('[MindMap] JSON inválido desde n8n: ' . substr($rawAnswer, 0, 300));
                    return response()->json(['success' => false, 'message' => 'La IA devolvió datos vacíos. Intenta con otro documento.'], 500);
                }

                $mapa = MindMap::create([
                    'user_id'         => Auth::id(),
                    'title'           => $mapData['titulo'] ?? $documento->name,
                    'prompt_original' => "Mapa desde PDF: " . $documento->name,
                    'map_data'        => $mapData,
                    'status'          => 'activo',
                ]);

                return response()->json([
                    'success'  => true,
                    'mapa_id'  => $mapa->id,
                    'status'   => 'activo',
                    'titulo'   => $mapa->title,
                    'map_data' => $mapData,
                ]);
            }

            // ─── PDF GRANDE: procesar por partes con la cola de trabajos ───
            Log::info("[MindMap] PDF grande ({$longitudTexto} chars) → procesando vía cola de trabajos");

            $chunks = $this->dividirEnChunks($contextoTexto, 2500);
            $totalChunks = count($chunks);

            $mapa = MindMap::create([
                'user_id'           => Auth::id(),
                'title'             => $documento->name,
                'prompt_original'   => "Mapa desde PDF: " . $documento->name,
                'map_data'          => ['titulo' => $documento->name, 'nodos' => []],
                'status'            => 'procesando',
                'chunks_total'      => $totalChunks,
                'chunks_completed'  => 0,
                'partial_results'   => [],
            ]);

            // Despachar jobs por cada chunk + job de fusión al final
            Bus::batch(
                array_map(
                    fn($i, $chunk) => new GenerateMindMapChunkJob($mapa->id, $i, $chunk),
                    array_keys($chunks),
                    $chunks
                )
            )->then(function () use ($mapa) {
                Bus::dispatch(new MergeMindMapJob($mapa->id));
                Log::info("[MindMap] Todos los chunks completados para mapa {$mapa->id}, despachando MergeMindMapJob");
            })->onConnection('database')->onQueue('default')->dispatch();

            return response()->json([
                'success'       => true,
                'mapa_id'       => $mapa->id,
                'status'        => 'procesando',
                'chunks_total'  => $totalChunks,
                'titulo'        => $mapa->title,
                'message'       => "Mapa mental en proceso. {$totalChunks} fragmentos enviados a la IA.",
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Verificar el estado de generación de un mapa mental.
     */
    public function checkStatus($id)
    {
        $mapa = MindMap::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return response()->json([
            'success'           => true,
            'status'            => $mapa->status,
            'chunks_total'      => $mapa->chunks_total,
            'chunks_completed'  => $mapa->chunks_completed,
            'map_data'          => $mapa->status === 'activo' ? $mapa->map_data : null,
            'titulo'            => $mapa->title,
        ]);
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
