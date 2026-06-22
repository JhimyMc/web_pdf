<?php
//C:\laragon\www\web-pdf\app\Http\Controllers\DocumentController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use App\Traits\ConnectsToLMStudio;

class DocumentController extends Controller
{
    use ConnectsToLMStudio;
    public function index()
    {
        $documentos = Auth::check() ? Document::where('user_id', Auth::id())->latest()->get() : collect();
        return view('welcome', compact('documentos'));
    }

    public function upload(Request $request)
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

            // Limpieza profunda del texto
            $textoLimpio = preg_replace('/\s+/', ' ', $pdf->getText());

            $documento = Document::create([
                'user_id' => Auth::id(),
                'name' => $originalName,
                'extracted_text' => "Texto guardado en chunks",
                'tokens_estimated' => str_word_count($textoLimpio) * 1.3,
            ]);

            // 🚀 LÓGICA PRO: DIVIDIR EN CHUNKS CON "OVERLAP" (Solapamiento)
            $fragmentos = [];
            $palabrasArray = explode(' ', $textoLimpio);
            $chunkActual = [];
            $longitudActual = 0;
            $maxCaracteres = 1500;
            $palabrasSolapamiento = 40; // Repetimos 40 palabras para no perder el contexto

            foreach ($palabrasArray as $palabra) {
                $chunkActual[] = $palabra;
                $longitudActual += mb_strlen($palabra) + 1; // +1 por el espacio

                if ($longitudActual >= $maxCaracteres) {
                    $fragmentos[] = implode(' ', $chunkActual);
                    // Mantenemos las últimas palabras para el siguiente fragmento
                    $chunkActual = array_slice($chunkActual, -$palabrasSolapamiento);
                    $longitudActual = mb_strlen(implode(' ', $chunkActual));
                }
            }
            if (!empty($chunkActual)) {
                $fragmentos[] = implode(' ', $chunkActual);
            }

            // Guardar fragmentos en BD
            $chunksCreados = [];
            foreach ($fragmentos as $fragmento) {
                $chunksCreados[] = DocumentChunk::create([
                    'document_id' => $documento->id,
                    'chunk_text' => $fragmento
                ]);
            }

            // 🧠 BÚSQUEDA SEMÁNTICA: Generar embeddings para cada chunk
            try {
                $textsToEmbed = array_map(fn($chunk) => $chunk->chunk_text, $chunksCreados);
                $embeddings = $this->generateEmbeddingsBatch($textsToEmbed);

                foreach ($chunksCreados as $i => $chunk) {
                    if (isset($embeddings[$i]) && is_array($embeddings[$i])) {
                        $chunk->update(['embedding' => $embeddings[$i]]);
                    }
                }
                Log::info("[Upload] Embeddings generados para " . count($chunksCreados) . " chunks del documento {$documento->id}");
            } catch (\Exception $e) {
                Log::warning("[Upload] No se pudieron generar embeddings (el modelo de embeddings puede no estar cargado): " . $e->getMessage());
                // Los chunks se guardan sin embedding — la búsqueda degradará a LIKE automáticamente
            }

            return response()->json([
                'success' => true,
                'document' => [
                    'id' => $documento->id,
                    'name' => $documento->name
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Error al leer el PDF: ' . $e->getMessage()], 500);
        }
    }

    public function messages($id)
    {
        try {
            $documento = Document::where('id', $id)->where('user_id', Auth::id())->first();

            if (!$documento) {
                return response()->json(['success' => false, 'message' => 'No encontrado.'], 404);
            }

            $mensajes = $documento->messages->map(function ($msg) {
                return ['sender' => $msg->sender, 'message' => $msg->message];
            });

            return response()->json(['success' => true, 'messages' => $mensajes]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function ask(Request $request)
    {
        $request->validate([
            'question' => 'required|string',
            'document_id' => 'required|integer'
        ]);

        $doc = Document::where('id', $request->document_id)->where('user_id', Auth::id())->firstOrFail();

        ChatMessage::create([
            'user_id' => Auth::id(),
            'document_id' => $doc->id,
            'sender' => 'user',
            'message' => $request->question
        ]);

        // 🚀 LÓGICA RAG SUPERIOR: Detección de "Resumen" MEJORADA
        $pregunta = strtolower($request->question);
        $esResumen = preg_match('/resumen|general|de qu[eé] trata|tema principal|conclusi[oó]n/i', $pregunta);

        $contextoParaLaIA = "";

        if ($esResumen) {
            $totalChunks = DocumentChunk::where('document_id', $doc->id)->count();

            if ($totalChunks <= 8) {
                // Si el PDF es corto (aprox. 10 páginas o menos), le enviamos TODO el contenido
                $chunksRelevantes = DocumentChunk::where('document_id', $doc->id)->orderBy('id', 'asc')->get();
            } else {
                // Si el PDF es gigante, tomamos muestras estratégicas: Principio, Medio y Fin
                $chunksInicio = DocumentChunk::where('document_id', $doc->id)->orderBy('id', 'asc')->limit(3)->get();

                $mitad = (int) floor($totalChunks / 2);
                $chunksMedio = DocumentChunk::where('document_id', $doc->id)->orderBy('id', 'asc')->skip($mitad)->take(2)->get();

                $chunksFin = DocumentChunk::where('document_id', $doc->id)->orderBy('id', 'desc')->limit(3)->get();

                // Unimos las 3 partes para crear un mini-resumen de todo el libro
                $chunksRelevantes = $chunksInicio->merge($chunksMedio)->merge($chunksFin);
            }
        } else {
            // 🧠 BÚSQUEDA SEMÁNTICA: Usar embeddings + cosine similarity
            $chunksConEmbedding = DocumentChunk::where('document_id', $doc->id)
                ->whereNotNull('embedding')
                ->count();

            if ($chunksConEmbedding > 0) {
                // Hay embeddings disponibles → búsqueda semántica
                $chunksRelevantes = $this->searchSemanticChunks($doc->id, $request->question, 8, 0.15);
            } else {
                // Sin embeddings → fallback a búsqueda por palabras clave
                Log::info('[Chat] No hay embeddings para documento ' . $doc->id . ', usando búsqueda LIKE como fallback');
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
        }

        // Construimos el bloque de texto final que viajará a la IA
        foreach ($chunksRelevantes as $chunk) {
            $contextoParaLaIA .= $chunk->chunk_text . "\n\n";
        }

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
            Log::error('[Chat] Error al llamar LM Studio: ' . $e->getMessage());
            $textoIA = "🔌 Error de comunicación con la IA: " . $e->getMessage();
        }

        ChatMessage::create([
            'user_id' => Auth::id(),
            'document_id' => $doc->id,
            'sender' => 'bot',
            'message' => $textoIA
        ]);

        return response()->json(['answer' => $textoIA], 200);
    }

    public function destroy($id)
    {
        try {
            $documento = Document::where('id', $id)->where('user_id', Auth::id())->first();

            if (!$documento) {
                return response()->json(['success' => false, 'message' => 'Documento no encontrado o no tienes permisos.'], 404);
            }

            $files = Storage::disk('public')->files('pdfs');
            foreach ($files as $file) {
                if (str_ends_with($file, $documento->name)) {
                    Storage::disk('public')->delete($file);
                }
            }

            $documento->delete();

            return response()->json(['success' => true, 'message' => 'Documento eliminado.'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Error al eliminar: ' . $e->getMessage()], 500);
        }
    }
}
