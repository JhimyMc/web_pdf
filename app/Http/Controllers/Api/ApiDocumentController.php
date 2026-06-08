<?php
// C:\laragon\www\web-pdf\app\Http\Controllers\Api\ApiDocumentController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\Storage;

class ApiDocumentController extends Controller
{
    /**
     * 📱 Eliminar un documento PDF desde la app móvil
     */
    public function apiEliminarDocumento(Request $request, $id)
    {
        try {
            $userId = $request->query('user_id') ?? $request->input('user_id');

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'user_id es requerido.'
                ], 400);
            }

            $documento = Document::where('id', $id)->where('user_id', $userId)->first();

            if (!$documento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Documento no encontrado o no tienes permisos.'
                ], 404);
            }

            // Eliminar archivo físico del storage si existe
            $files = Storage::disk('public')->files('pdfs');
            foreach ($files as $file) {
                if (str_ends_with($file, $documento->name)) {
                    Storage::disk('public')->delete($file);
                }
            }

            // Eliminar registros relacionados
            DocumentChunk::where('document_id', $documento->id)->delete();
            ChatMessage::where('document_id', $documento->id)->delete();
            $documento->delete();

            return response()->json([
                'success' => true,
                'message' => 'Documento eliminado correctamente.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }
}
