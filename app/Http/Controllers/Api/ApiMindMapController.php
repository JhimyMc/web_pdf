<?php
//C:\laragon\www\web-pdf\app\Http\Controllers\Api\ApiMindMapController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\MindMap;
use Illuminate\Support\Facades\Http;

class ApiMindMapController extends Controller
{
    // 1. Generar mapa desde Android
    public function apiGenerar(Request $request)
    {
        $request->validate([
            'document_id' => 'required|exists:documents,id',
            'user_id' => 'required|integer' // Android nos manda quién es
        ]);

        try {
            $documento = Document::where('id', $request->document_id)
                ->where('user_id', $request->user_id)
                ->firstOrFail();

            // Extraer texto de los chunks
            $contextoTexto = DocumentChunk::where('document_id', $documento->id)
                ->pluck('chunk_text')
                ->implode(' ');

            if (empty(trim($contextoTexto))) {
                return response()->json(['success' => false, 'message' => 'Documento sin texto procesable.'], 422);
            }

            // Llamada a n8n / LM Studio
            $response = Http::timeout(300)->post('http://localhost:5678/webhook/playdf-mapa-mental', [
                'model' => 'meta-llama-3-8b-instruct',
                'context' => mb_substr($contextoTexto, 0, 15000),
                'question' => 'Genera la estructura de nodos jerárquicos completa.',
            ]);

            if (!$response->successful()) {
                return response()->json(['success' => false, 'message' => 'Error al conectar con la IA local.'], 500);
            }

            $jsonData = $response->json();

            // Decodificar JSON seguro
            if (isset($jsonData['answer'])) {
                $rawAnswer = preg_replace('/```json\s*|```\s*/', '', $jsonData['answer']);
                $mapData = json_decode(trim($rawAnswer), true);
            } else {
                $mapData = $jsonData;
            }

            // Archivar mapas anteriores del usuario
            MindMap::where('user_id', $request->user_id)->update(['status' => 'archivado']);

            // Crear nuevo mapa
            $mapa = MindMap::create([
                'user_id' => $request->user_id,
                'title' => $mapData['titulo'] ?? $documento->name,
                'prompt_original' => "Mapa desde App: " . $documento->name,
                'map_data' => $mapData,
                'status' => 'activo',
            ]);

            return response()->json([
                'success' => true,
                'mapa_id' => $mapa->id,
                'titulo' => $mapa->title,
                'map_data' => $mapData,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // 2. Autoguardado desde Android
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

    // 3. Eliminar mapa desde Android
    public function apiEliminar($id)
    {
        $mapa = MindMap::find($id);
        if ($mapa) {
            $mapa->delete();
        }
        return response()->json(['success' => true, 'message' => 'Eliminado exitoso.']);
    }
    // 4. Obtener todos los mapas del usuario desde Android
    public function apiObtenerMisMapas(Request $request)
    {
        // Validamos que el celular nos envíe el ID del usuario
        $request->validate([
            'user_id' => 'required|integer'
        ]);

        try {
            // Buscamos todos los mapas de este usuario, ordenados por los más recientes
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
