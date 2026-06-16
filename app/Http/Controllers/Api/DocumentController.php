<?php
// C:\laragon\www\web-pdf\app\Http\Controllers\Api\DocumentController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;
use App\Traits\ConnectsToLMStudio;

class DocumentController extends Controller
{
    use ConnectsToLMStudio;
    /**
     * 📱 NUEVO MÉTODO: Recibe el PDF de Android, extrae el texto, 
     * lo divide en chunks con overlap y lo asocia al docente.
     */
    public function apiSubirPdfDocente(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'file'    => 'required|mimes:pdf|max:10000',
        ]);

        // La existencia del usuario ya se valida en el middleware ValidateUserId

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();

            // Guardar el PDF en el storage de Laragon
            $path = $file->storeAs('pdfs', time() . '_' . $originalName, 'public');

            // Instanciar el Parser de PHP para extraer el texto crudo del documento
            $parser = new Parser();
            $pdf = $parser->parseFile(storage_path('app/public/' . $path));
            $textoLimpio = preg_replace('/\s+/', ' ', $pdf->getText());

            // Crear el registro del documento en la BD vinculándolo al user_id recibido del móvil
            $documento = Document::create([
                'user_id'          => $request->user_id,
                'name'             => $originalName,
                'extracted_text'   => "Texto guardado en chunks desde App",
                'tokens_estimated' => str_word_count($textoLimpio) * 1.3,
            ]);

            // 🚀 LÓGICA DE PROCESAMIENTO RAG: CHUNKS CON SOLAPAMIENTO (OVERLAP)
            $fragmentos = [];
            $palabrasArray = explode(' ', $textoLimpio);
            $chunkActual = [];
            $longitudActual = 0;
            $maxCaracteres = 1500;
            $palabrasSolapamiento = 40;

            foreach ($palabrasArray as $palabra) {
                $chunkActual[] = $palabra;
                $longitudActual += mb_strlen($palabra) + 1;

                if ($longitudActual >= $maxCaracteres) {
                    $fragmentos[] = implode(' ', $chunkActual);
                    $chunkActual = array_slice($chunkActual, -$palabrasSolapamiento);
                    $longitudActual = mb_strlen(implode(' ', $chunkActual));
                }
            }
            if (!empty($chunkActual)) {
                $fragmentos[] = implode(' ', $chunkActual);
            }

            // Almacenar cada fragmento en la tabla document_chunks
            foreach ($fragmentos as $fragmento) {
                DocumentChunk::create([
                    'document_id' => $documento->id,
                    'chunk_text'  => $fragmento
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => '✅ PDF recibido, procesado y fragmentado con éxito en Laragon.'
            ], 200);
        } catch (\Exception $e) {
            Log::error('[Subir PDF] Error al procesar: ' . $e->getMessage(), [
                'user_id' => $request->input('user_id'),
                'file' => $request->file('file')?->getClientOriginalName(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo en el servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 📱 NUEVO MÉTODO: Retorna los PDFs pertenecientes a un docente
     * mapeando la lista de manera directa [] para Retrofit
     */
    public function apiObtenerPdfsDocente(Request $request)
    {
        $userId = $request->query('user_id');

        if (!$userId) {
            return response()->json([], 400);
        }

        // Buscamos los documentos mapeando los campos para que coincidan con ServerPdfData de Android
        $pdfs = Document::where('user_id', $userId)
            ->latest()
            ->get()
            ->map(function ($doc) {
                return [
                    'id'      => $doc->id,
                    'nombre'  => $doc->name, // Mapeamos 'name' de la BD a 'nombre' de la App
                    'user_id' => $doc->user_id
                ];
            });

        return response()->json($pdfs, 200);
    }

    public function apiAskPublic(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'question' => 'required|string',
            'document_id' => 'nullable|integer',
        ]);

        try {
            if ($request->filled('document_id')) {
                $doc = Document::where('id', $request->document_id)
                    ->where('user_id', $request->user_id)
                    ->first();
            } else {
                $doc = Document::where('user_id', $request->user_id)
                    ->latest()
                    ->first();
            }

            if (!$doc) {
                return response()->json([
                    'answer' => '⚠️ No se encontró ningún documento PDF asignado.'
                ], 404);
            }

            ChatMessage::create([
                'user_id' => $request->user_id,
                'document_id' => $doc->id,
                'sender' => 'user',
                'message' => $request->question,
            ]);

            $pregunta = strtolower($request->question);
            $esResumen = preg_match('/resumen|general|de qu[eé] trata|tema principal|conclusi[oó]n/i', $pregunta);
            $contextoParaLaIA = "";

            if ($esResumen) {
                $totalChunks = DocumentChunk::where('document_id', $doc->id)->count();

                if ($totalChunks <= 8) {
                    $chunksRelevantes = DocumentChunk::where('document_id', $doc->id)->orderBy('id', 'asc')->get();
                } else {
                    $chunksInicio = DocumentChunk::where('document_id', $doc->id)->orderBy('id', 'asc')->limit(3)->get();
                    $mitad = (int) floor($totalChunks / 2);
                    $chunksMedio = DocumentChunk::where('document_id', $doc->id)->orderBy('id', 'asc')->skip($mitad)->take(2)->get();
                    $chunksFin = DocumentChunk::where('document_id', $doc->id)->orderBy('id', 'desc')->limit(3)->get();

                    $chunksRelevantes = $chunksInicio->merge($chunksMedio)->merge($chunksFin);
                }
            } else {
                $palabras = array_filter(explode(' ', $request->question), function ($w) {
                    return mb_strlen($w) > 3;
                });

                $query = DocumentChunk::where('document_id', $doc->id);

                if (!empty($palabras)) {
                    $query->where(function ($q) use ($palabras) {
                        foreach ($palabras as $palabra) {
                            $q->orWhere('chunk_text', 'LIKE', '%' . $palabra . '%');
                        }
                    });
                }

                $chunksRelevantes = $query->limit(8)->get();

                if ($chunksRelevantes->isEmpty()) {
                    $chunksRelevantes = DocumentChunk::where('document_id', $doc->id)->orderBy('id', 'asc')->limit(6)->get();
                }
            }

            foreach ($chunksRelevantes as $chunk) {
                $contextoParaLaIA .= $chunk->chunk_text . "\n\n";
            }

            $textoIA = "";
            try {
                $systemPrompt = "Eres un asistente experto en análisis de documentos. Responde preguntas sobre el contenido del documento de forma clara y concisa. Si la información no está en el texto, indica que no puedes responderla basándote en el documento proporcionado.";
                $userMessage = "Contexto del documento:\n\n{$contextoParaLaIA}\n\nPregunta: {$request->question}";

                $resultado = $this->llamarLMStudio($systemPrompt, $userMessage, 0.7, 1024);

                if ($resultado) {
                    $textoIA = $resultado['content'];
                } else {
                    $textoIA = "⚠️ No se pudo conectar con el motor de IA. Intenta más tarde.";
                }
            } catch (\Exception $e) {
                Log::error('[Chat API] Error al llamar LM Studio: ' . $e->getMessage());
                $textoIA = "🔌 Error de comunicación con la IA: " . $e->getMessage();
            }

            ChatMessage::create([
                'user_id' => $request->user_id,
                'document_id' => $doc->id,
                'sender' => 'bot',
                'message' => $textoIA,
            ]);

            return response()->json([
                'answer' => $textoIA
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'answer' => '❌ Error interno en el servidor: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * 📱 Retorna el historial de mensajes formateado para Android
     */
    public function apiObtenerHistorial($id)
    {
        try {
            // Buscamos el documento
            $documento = Document::find($id);

            if (!$documento) {
                return response()->json([], 404);
            }

            // Retornamos sus mensajes asociados
            $mensajes = ChatMessage::where('document_id', $id)
                ->orderBy('id', 'asc')
                ->get()
                ->map(function ($msg) {
                    return [
                        'id'          => $msg->id,
                        'user_id'     => $msg->user_id,
                        'document_id' => $msg->document_id,
                        'sender'      => $msg->sender, // "user" o "bot"
                        'message'     => $msg->message,
                        'created_at'  => $msg->created_at ? $msg->created_at->toDateTimeString() : null
                    ];
                });

            return response()->json($mensajes, 200);
        } catch (\Exception $e) {
            return response()->json([], 500);
        }
    }
}
