<?php
//C:\laragon\www\web-pdf\app\Http\Controllers\DocumentController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

class DocumentController extends Controller
{
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
            foreach ($fragmentos as $fragmento) {
                DocumentChunk::create([
                    'document_id' => $documento->id,
                    'chunk_text' => $fragmento
                ]);
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
            // 🔍 Búsqueda normal por palabras clave (Para preguntas específicas)
            $palabras = array_filter(explode(' ', $request->question), function ($w) {
                return mb_strlen($w) > 3; // Ignorar preposiciones cortas
            });

            $query = DocumentChunk::where('document_id', $doc->id);

            if (!empty($palabras)) {
                $query->where(function ($q) use ($palabras) {
                    foreach ($palabras as $palabra) {
                        $q->orWhere('chunk_text', 'LIKE', '%' . $palabra . '%');
                    }
                });
            }

            // Tomamos los 8 fragmentos que más coinciden con la pregunta
            $chunksRelevantes = $query->limit(8)->get();

            // Si la IA no encuentra palabras clave, le pasamos los primeros fragmentos para que no se quede ciega
            if ($chunksRelevantes->isEmpty()) {
                $chunksRelevantes = DocumentChunk::where('document_id', $doc->id)->orderBy('id', 'asc')->limit(6)->get();
            }
        }

        // Construimos el bloque de texto final que viajará a n8n
        foreach ($chunksRelevantes as $chunk) {
            $contextoParaLaIA .= $chunk->chunk_text . "\n\n";
        }
        $n8nWebhookUrl = 'http://127.0.0.1:5678/webhook/playdf-chat';

        try {
            $response = Http::timeout(300)->post($n8nWebhookUrl, [
                'model' => 'meta-llama-3-8b-instruct',
                'question' => $request->question,
                'context' => $contextoParaLaIA
            ]);

            if ($response->successful()) {
                $resultado = $response->json();
                $textoIA = $resultado['answer'] ?? ($resultado['choices'][0]['message']['content'] ?? null);

                if (!$textoIA) {
                    $textoIA = "⚠️ Error en formato JSON de n8n.";
                }
            } else {
                $textoIA = "⚠️ El servidor de n8n denegó la petición (Código HTTP: " . $response->status() . ").";
            }
        } catch (\Exception $e) {
            $textoIA = "🔌 Error de comunicación con n8n: " . $e->getMessage();
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
