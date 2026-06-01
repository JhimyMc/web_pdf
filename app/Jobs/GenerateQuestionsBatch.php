<?php

namespace App\Jobs;

use App\Models\Room;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenerateQuestionsBatch implements ShouldQueue
{
    use Queueable;

    public string $roomCode;
    public string $context;
    public int $numQuestions;
    public string $difficulty;
    public int $batchIndex;
    public int $totalBatches;

    /**
     * Timeout: hasta 10 minutos por batch (LM Studio puede tardar).
     */
    public int $timeout = 600;

    /**
     * Número de reintentos.
     */
    public int $tries = 2;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $roomCode,
        string $context,
        int $numQuestions,
        string $difficulty,
        int $batchIndex,
        int $totalBatches
    ) {
        $this->roomCode = $roomCode;
        $this->context = $context;
        $this->numQuestions = $numQuestions;
        $this->difficulty = $difficulty;
        $this->batchIndex = $batchIndex;
        $this->totalBatches = $totalBatches;
    }

    /**
     * Execute the job: llama a LM Studio y guarda las preguntas en la sala.
     */
    public function handle(): void
    {
        Log::info("[Job] Iniciando batch {$this->batchIndex}/{$this->totalBatches} para sala {$this->roomCode}");

        $room = Room::where('code', $this->roomCode)->first();

        if (!$room) {
            Log::error("[Job] Sala {$this->roomCode} no encontrada");
            return;
        }

        // 1. Llamar a LM Studio
        $preguntasGeneradas = $this->llamarLMStudio();

        if ($preguntasGeneradas === null) {
            Log::error("[Job] Batch {$this->batchIndex}/{$this->totalBatches} falló para sala {$this->roomCode}");
            $this->incrementarBatch($room, true);
            return;
        }

        // 2. Acumular preguntas con las existentes
        $preguntasActuales = $room->questions ?? [];
        if (!is_array($preguntasActuales)) {
            $preguntasActuales = [];
        }

        $todasLasPreguntas = array_merge($preguntasActuales, $preguntasGeneradas);

        // 3. Si es el último batch, sanitizar
        $esUltimo = ($this->batchIndex >= $this->totalBatches - 1);

        if ($esUltimo) {
            $todasLasPreguntas = $this->sanitizarPreguntas($todasLasPreguntas, $room->num_questions ?? 10);

            if (empty($todasLasPreguntas)) {
                $todasLasPreguntas = [[
                    'pregunta' => 'Error al generar preguntas válidas. Intente crear la sala de nuevo.',
                    'opciones' => ['Reintentar', 'Opción B', 'Opción C', 'Opción D', 'Opción E'],
                    'correcta' => 0
                ]];
            }

            $room->update([
                'questions'         => $todasLasPreguntas,
                'status'            => 'espera',
                'batches_completed' => $this->totalBatches,
            ]);

            Log::info("[Job] Batch FINAL {$this->batchIndex}/{$this->totalBatches} COMPLETO para sala {$this->roomCode}. " .
                count($todasLasPreguntas) . " preguntas guardadas.");
        } else {
            $room->update([
                'questions'         => $todasLasPreguntas,
                'batches_completed' => $this->batchIndex + 1,
            ]);

            Log::info("[Job] Batch PARCIAL {$this->batchIndex}/{$this->totalBatches} completado para sala {$this->roomCode}. " .
                count($todasLasPreguntas) . " preguntas acumuladas.");
        }
    }

    /**
     * Llama a LM Studio DIRECTAMENTE (sin n8n) y devuelve las preguntas parseadas.
     */
    private function llamarLMStudio(): ?array
    {
        $lmStudioUrl = 'http://26.231.46.210:1234/v1/chat/completions';
        $apiKey = 'Bearer sk-lm-CZRjBleo:wOFAZjS49eyR47dut6z5';

        $systemPrompt = $this->getSystemPrompt();

        $payload = [
            'model' => 'meta-llama-3-8b-instruct',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => "Genera EXACTAMENTE {$this->numQuestions} preguntas diferentes basándote EXCLUSIVAMENTE en el siguiente texto:\n\n\"\"\"\n{$this->context}\n\"\"\""
                ]
            ],
            'temperature' => 0.6,
        ];

        try {
            Log::info("[Job] Enviando a LM Studio sala {$this->roomCode}, solicitando {$this->numQuestions} preguntas...");

            $response = Http::withHeaders([
                'Authorization' => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(600)->post($lmStudioUrl, $payload);

            if (!$response->successful()) {
                Log::error("[Job] LM Studio HTTP {$response->status()} para sala {$this->roomCode}");
                return null;
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;

            if (!$content) {
                Log::error("[Job] LM Studio respuesta vacía para sala {$this->roomCode}");
                return null;
            }

            // Parsear JSON de la respuesta
            $questions = null;
            if (preg_match('/\[.*\]/s', $content, $matches)) {
                $questions = json_decode($matches[0], true);
            } else {
                $questions = json_decode($content, true);
            }

            if (!is_array($questions)) {
                Log::error("[Job] LM Studio JSON inválido: " . substr($content, 0, 200));
                return null;
            }

            Log::info("[Job] LM Studio generó " . count($questions) . " preguntas para sala {$this->roomCode}");
            return $questions;

        } catch (\Exception $e) {
            Log::error("[Job] LM Studio EXCEPCIÓN: " . $e->getMessage());
            return null;
        }
    }

    /**
     * System prompt idéntico al de n8n.
     */
    private function getSystemPrompt(): string
    {
        return "Eres un profesor universitario experto creando exámenes. Tu tarea es generar EXACTAMENTE la cantidad de preguntas solicitadas basándote EXCLUSIVAMENTE en el texto.\n\n"
            . "REGLAS ESTRICTAS:\n"
            . "1. Devuelve ÚNICAMENTE un array JSON válido. NADA de texto antes o después.\n"
            . "2. Las preguntas deben ser SOBRE EL CONTENIDO TEMÁTICO del texto, NO sobre el archivo, documento o PDF en sí.\n"
            . "3. NUNCA generes preguntas como: '¿Dónde se almacena?', '¿Qué tipo de archivo es?', '¿Cuál es el título del documento?'.\n"
            . "4. VARIEDAD: Las preguntas NO DEBEN REPETIRSE. Cambia de tema, explora todo el texto.\n"
            . "5. OPCIONES LIMPIAS: NO incluyas letras como \"A)\", \"B)\", \"C)\" ni viñetas en las opciones. Escribe solo el texto de la respuesta.\n"
            . "6. ESTRUCTURA: Exactamente 5 opciones por pregunta. La clave 'correcta' debe ser un número entero (0 al 4).\n"
            . "7. Dificultad solicitada: {$this->difficulty}.\n\n"
            . "EJEMPLO DEL FORMATO ESPERADO:\n"
            . "[\n"
            . "  {\n"
            . "    \"pregunta\": \"¿Cuál es el propósito principal de un Firewall?\",\n"
            . "    \"opciones\": [\"Bloquear accesos no autorizados\", \"Acelerar el internet\", \"Crear copias de seguridad\", \"Revisar el código fuente\", \"Ninguna de las anteriores\"],\n"
            . "    \"correcta\": 0\n"
            . "  }\n"
            . "]";
    }

    /**
     * Incrementa el contador de batches completados (incluso en fallos).
     */
    private function incrementarBatch(Room $room, bool $fallo): void
    {
        $completados = min($room->batches_completed + 1, $this->totalBatches);
        $room->update(['batches_completed' => $completados]);
    }

    /**
     * Filtra preguntas genéricas y asegura estructura correcta.
     */
    private function sanitizarPreguntas(array $preguntas, int $limite): array
    {
        $preguntas = array_values(array_filter($preguntas, fn($q) => !$this->esPreguntaGenerica($q)));

        if (count($preguntas) > $limite) {
            $preguntas = array_slice($preguntas, 0, $limite);
        }

        $comodines = ["Todas las anteriores", "Ninguna de las anteriores", "Falta información", "No aplica"];

        foreach ($preguntas as &$q) {
            if (!isset($q['opciones']) || !is_array($q['opciones'])) {
                $q['opciones'] = ["Opción A", "Opción B", "Opción C", "Opción D", "Opción E"];
            }

            $c = 0;
            while (count($q['opciones']) < 5) {
                $q['opciones'][] = $comodines[$c] ?? "Otra opción";
                $c++;
            }

            if (count($q['opciones']) > 5) {
                $q['opciones'] = array_slice($q['opciones'], 0, 5);
            }

            if (!isset($q['correcta']) || !is_numeric($q['correcta']) || $q['correcta'] < 0 || $q['correcta'] > 4) {
                $q['correcta'] = 0;
            }
        }

        return $preguntas;
    }

    /**
     * Detecta preguntas genéricas sobre el archivo/documento.
     */
    private function esPreguntaGenerica(array $q): bool
    {
        $pregunta = strtolower($q['pregunta'] ?? '');

        $patrones = [
            '/d[oó]nde.*(almacen|guard|archivo|pdf|documento|file)/i',
            '/qu[eé] beneficio.*(almacen|guard|nube|cloud|archivo)/i',
            '/c[oó]mo se (guarda|almacena|crea|sube|almacen)/i',
            '/qu[eé] tipo de (archivo|documento|formato)/i',
            '/para qu[eé] sirve (el|la|este|esta) (archivo|documento|pdf|aplicaci[oó]n|app)/i',
            '/cu[aá]l es el (nombre|tama[nn]o|formato) (del|de el|de la) (archivo|documento)/i',
            '/en qu[eé] (formato|idioma) est[aá]/i',
            '/qu[eé] (aplicaci[oó]n|app|programa|software) se usa/i',
            '/d[oó]nde est[aá] (guardado|almacenado|el archivo|el documento)/i',
            '/qu[eé] es (un|una) (pdf|archivo|documento)/i',
            '/cu[aá]l es el t[íi]tulo (del|de el) (documento|archivo|pdf)/i',
            '/cu[aá]ntas p[aá]ginas tiene/i',
            '/qu[eé] (contiene|incluye|tiene) el (documento|archivo|pdf)/i',
            '/c[oó]mo se llama el (documento|archivo|pdf)/i',
            '/d[oó]nde se (encuentra|localiza|ubica) (el|este) (archivo|documento)/i',
            '/cu[aá]l es la (fecha|fuente|referencia) (del|de) (documento|archivo)/i',
        ];

        foreach ($patrones as $patron) {
            if (preg_match($patron, $pregunta)) {
                return true;
            }
        }

        return false;
    }
}
