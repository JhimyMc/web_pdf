<?php

namespace App\Jobs;

use App\Models\MindMap;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenerateMindMapChunkJob implements ShouldQueue
{
    use Queueable, Batchable;

    public int $mapId;
    public int $chunkIndex;
    public string $chunkText;

    public int $timeout = 300;
    public int $tries = 2;

    public function __construct(int $mapId, int $chunkIndex, string $chunkText)
    {
        $this->mapId = $mapId;
        $this->chunkIndex = $chunkIndex;
        $this->chunkText = $chunkText;
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $mapa = MindMap::find($this->mapId);
        if (!$mapa) {
            Log::error("[MindMap Job] Mapa {$this->mapId} no encontrado");
            return;
        }

        Log::info("[MindMap Job] Procesando chunk {$this->chunkIndex} para mapa {$this->mapId}");

        $nodos = $this->llamarLMStudio();

        // Usar DB::transaction con lock para evitar race conditions entre jobs concurrentes
        DB::transaction(function () use ($mapa, $nodos) {
            // Re-leer dentro de la transacción para obtener el estado más reciente
            $current = MindMap::where('id', $mapa->id)->lockForUpdate()->first();

            $parciales = $current->partial_results ?? [];
            $parciales[$this->chunkIndex] = $nodos ?? [];

            $completados = ($current->chunks_completed ?? 0) + 1;

            $current->update([
                'partial_results'   => $parciales,
                'chunks_completed'  => $completados,
            ]);

            Log::info("[MindMap Job] Chunk {$this->chunkIndex} completado. {$completados}/{$current->chunks_total} chunks procesados.");
        });
    }

    private function llamarLMStudio(): ?array
    {
        $lmStudioUrl = env('LM_STUDIO_URL', 'http://26.231.46.210:1234/v1/chat/completions');
        $apiKey = 'Bearer ' . env('LM_STUDIO_API_KEY', 'sk-lm-CZRjBleo:wOFAZjS49eyR47dut6z5');

        $payload = [
            'model' => 'meta-llama-3-8b-instruct',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->getSystemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => "Analiza el siguiente texto y extrae los temas principales como nodos de un mapa mental.\n\n\"\"\"\n{$this->chunkText}\n\"\"\"",
                ],
            ],
            'temperature' => 0.4,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => $apiKey,
                'Content-Type'  => 'application/json',
            ])->timeout(300)->post($lmStudioUrl, $payload);

            if (!$response->successful()) {
                Log::error("[MindMap Job] LM Studio HTTP {$response->status()} en chunk {$this->chunkIndex}");
                return null;
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;

            if (!$content) {
                Log::error("[MindMap Job] LM Studio respuesta vacía en chunk {$this->chunkIndex}");
                return null;
            }

            // Limpiar markdown code blocks y texto conversacional
            $cleanContent = preg_replace('/```json\s*/i', '', $content);
            $cleanContent = preg_replace('/```\s*/', '', $cleanContent);
            $cleanContent = trim($cleanContent);

            // Parsear JSON — buscar el primer { hasta el último }
            $nodos = null;
            $start = strpos($cleanContent, '{');
            $end = strrpos($cleanContent, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $nodos = json_decode(substr($cleanContent, $start, $end - $start + 1), true);
            }

            if (!is_array($nodos) || !isset($nodos['nodos'])) {
                Log::error("[MindMap Job] JSON inválido en chunk {$this->chunkIndex}: " . substr($content, 0, 300));
                return null;
            }

            return $nodos;

        } catch (\Exception $e) {
            Log::error("[MindMap Job] Excepción chunk {$this->chunkIndex}: " . $e->getMessage());
            return null;
        }
    }

    private function getSystemPrompt(): string
    {
        return 'Eres un experto en crear mapas mentales educativos de alta calidad. '
            . 'Tu tarea es analizar texto académico y extraer los CONCEPTOS CLAVE como un mapa mental jerárquico.\n\n'
            . 'Devuelve ÚNICAMENTE un JSON válido con esta estructura:\n'
            . '{\n'
            . '  "nodos": [\n'
            . '    {\n'
            . '      "titulo": "Concepto Principal (ej: Fotosíntesis)",\n'
            . '      "hijos": [\n'
            . '        {\n'
            . '          "titulo": "Subconcepto descriptivo (ej: Proceso de conversión de energía luminosa)",\n'
            . '          "hijos": [\n'
            . '            { "titulo": "Detalle específico (ej: Utiliza CO₂ y H₂O para producir glucosa)" }\n'
            . '          ]\n'
            . '        }\n'
            . '      ]\n'
            . '    }\n'
            . '  ]\n'
            . '}\n\n'
            . 'REGLAS CRÍTICAS:\n'
            . '1. NO incluyas texto antes o después del JSON. Solo el JSON.\n'
            . '2. Cada título debe ser DESCRITIVO y COMPLETO — explica el concepto, no solo lo nombra.\n'
            . '   MAL: "Proceso" / BIEN: "La fotosíntesis ocurre en los cloroplastos de las células vegetales"\n'
            . '   MAL: "Tipos" / BIEN: "Existen tres tipos de ecosistemas: terrestre, acuático y aéreo"\n'
            . '   MAL: "Estructura" / BIEN: "La célula tiene membrana, núcleo y citoplasma"\n'
            . '3. Extrae entre 3 y 6 nodos principales con 2-4 subconceptos cada uno.\n'
            . '4. Máximo 3 niveles de profundidad (raíz → hijos → nietos).\n'
            . '5. Cada título entre 15 y 80 caracteres. Que sea informativo, no vacío.\n'
            . '6. Los nodos deben tener RELACIÓN LÓGICA entre padre e hijo.\n'
            . '7. Usa lenguaje claro y académico, apropiado para estudiantes.';
    }
}
