<?php
//C:\laragon\www\web-pdf\app\Http\Controllers\Api\ApiMindMapController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\MindMap;
use App\Jobs\GenerateMindMapChunkJob;
use App\Jobs\MergeMindMapJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Bus;use App\Traits\ChunkableMindMap;
use App\Traits\ConnectsToLMStudio;

class ApiMindMapController extends Controller
{
    use ChunkableMindMap, ConnectsToLMStudio;
    /**
     * Generar mapa mental desde Android.
     * 
     * PDFs pequeños (≤4000 chars) → n8n sincrónico (respuesta inmediata)
     * PDFs grandes (>4000 chars) → cola de trabajos con chunks (asíncrono)
     */
    public function apiGenerar(Request $request)
    {
        $request->validate([
            'document_id' => 'required|exists:documents,id',
            'user_id' => 'required|integer'
        ]);

        try {
            $documento = Document::where('id', $request->document_id)
                ->where('user_id', $request->user_id)
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
                return response()->json(['success' => false, 'message' => 'Documento sin texto procesable.'], 422);
            }

            // Archivar mapas anteriores del usuario
            MindMap::where('user_id', $request->user_id)->update(['status' => 'archivado']);

            $longitudTexto = mb_strlen(trim($contextoTexto));

            // ─── PDF PEQUEÑO: procesar directamente con LM Studio (sincrónico) ───
            if ($longitudTexto <= 4000) {
                Log::info("[MindMap API] PDF pequeño ({$longitudTexto} chars) → procesando vía LM Studio directo");

                $systemPrompt = "Eres un experto creando mapas mentales educativos. Analiza el texto y genera un mapa mental jerárquico con conceptos descriptivos (no palabras sueltas). Cada título debe explicar el concepto en 15-80 caracteres. Extrae 3-6 nodos principales con 2-4 subconceptos cada uno. Máximo 3 niveles.\n\n"
                    . "Devuelve SOLO un JSON válido con esta estructura:\n"
                    . "{\"titulo\": \"Título del mapa\", \"nodos\": [{\"nombre\": \"Nodo\", \"color\": \"#hex\", \"hijos\": [{\"nombre\": \"Subnodo\", \"color\": \"#hex\"}]}]}";

                $userMessage = "Texto del documento:\n\n{$contextoTexto}";

                $mapData = $this->llamarLMStudioJSON($systemPrompt, $userMessage, 0.6, 2048);

                if (!is_array($mapData) || empty($mapData)) {
                    Log::error('[MindMap API] JSON inválido desde LM Studio para PDF pequeño');
                    return response()->json(['success' => false, 'message' => 'La IA devolvió datos vacíos. Intenta con otro documento.'], 500);
                }

                $mapa = MindMap::create([
                    'user_id'         => $request->user_id,
                    'title'           => $mapData['titulo'] ?? $documento->name,
                    'prompt_original' => "Mapa desde App: " . $documento->name,
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
            Log::info("[MindMap API] PDF grande ({$longitudTexto} chars) → procesando vía cola de trabajos");

            $chunks = $this->dividirEnChunks($contextoTexto, 2500);
            $totalChunks = count($chunks);

            $mapa = MindMap::create([
                'user_id'           => $request->user_id,
                'title'             => $documento->name,
                'prompt_original'   => "Mapa desde App: " . $documento->name,
                'map_data'          => ['titulo' => $documento->name, 'nodos' => []],
                'status'            => 'procesando',
                'chunks_total'      => $totalChunks,
                'chunks_completed'  => 0,
                'partial_results'   => [],
            ]);

            Bus::batch(
                array_map(
                    fn($i, $chunk) => new GenerateMindMapChunkJob($mapa->id, $i, $chunk),
                    array_keys($chunks),
                    $chunks
                )
            )->then(function () use ($mapa) {
                Bus::dispatch(new MergeMindMapJob($mapa->id));
                Log::info("[MindMap API] Todos los chunks completados para mapa {$mapa->id}, despachando MergeMindMapJob");
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
    public function apiCheckStatus(Request $request, $id)
    {
        $request->validate([
            'user_id' => 'required|integer'
        ]);

        $mapa = MindMap::where('id', $id)
            ->where('user_id', $request->user_id)
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

    // Autoguardado desde Android
    public function apiAutoguardar(Request $request, $id)
    {
        $request->validate(['map_data' => 'required']);

        $mapa = MindMap::find($id);
        if (!$mapa) {
            return response()->json(['success' => false, 'message' => 'Mapa no encontrado.'], 404);
        }

        $mapa->map_data = $request->map_data;
        $mapa->save();

        return response()->json(['success' => true, 'message' => 'Guardado exitoso.']);
    }

    // Eliminar mapa desde Android
    public function apiEliminar($id)
    {
        $mapa = MindMap::find($id);
        if ($mapa) {
            $mapa->delete();
        }
        return response()->json(['success' => true, 'message' => 'Eliminado exitoso.']);
    }

    // Obtener todos los mapas del usuario desde Android
    public function apiObtenerMisMapas(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer'
        ]);

        try {
            $mapas = MindMap::where('user_id', $request->user_id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'mapas' => $mapas
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener mapas: ' . $e->getMessage()], 500);
        }
    }
}
