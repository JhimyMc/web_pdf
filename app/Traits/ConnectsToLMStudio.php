<?php

namespace App\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Trait que proporciona conexión directa a LM Studio (OpenAI-compatible API).
 * Reemplaza la dependencia de n8n para todas las llamadas a IA.
 */
trait ConnectsToLMStudio
{
    /**
     * URL del servidor LM Studio.
     */
    protected function lmStudioUrl(): string
    {
        return env('LM_STUDIO_URL', 'http://26.231.46.210:1234/v1/chat/completions');
    }

    /**
     * API Key para LM Studio.
     */
    protected function lmStudioApiKey(): string
    {
        return 'Bearer ' . env('LM_STUDIO_API_KEY', 'sk-lm-CZRjBleo:wOFAZjS49eyR47dut6z5');
    }

    /**
     * Nombre del modelo a usar.
     */
    protected function lmStudioModel(): string
    {
        return env('LM_STUDIO_MODEL', 'meta-llama-3-8b-instruct');
    }

    /**
     * Llama a LM Studio con messages format (OpenAI-compatible).
     *
     * @param string $systemPrompt  Prompt del sistema (instrucciones)
     * @param string $userMessage   Mensaje del usuario (contexto + pregunta)
     * @param float  $temperature  Temperatura (0.0 - 1.0)
     * @param int    $maxTokens    Máximo de tokens en la respuesta
     * @param int    $timeout      Timeout en segundos
     * @return array|null          ['content' => string, 'raw' => array] o null si falla
     */
    protected function llamarLMStudio(
        string $systemPrompt,
        string $userMessage,
        float $temperature = 0.7,
        int $maxTokens = 2048,
        int $timeout = 300
    ): ?array {
        $payload = [
            'model'       => $this->lmStudioModel(),
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userMessage],
            ],
            'temperature' => $temperature,
            'max_tokens'  => $maxTokens,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->lmStudioApiKey(),
                'Content-Type'  => 'application/json',
            ])->timeout($timeout)->post($this->lmStudioUrl(), $payload);

            if (!$response->successful()) {
                Log::error("[LM Studio] HTTP {$response->status()}: " . substr($response->body(), 0, 200));
                return null;
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;

            if (!$content) {
                Log::error("[LM Studio] Respuesta sin contenido: " . substr(json_encode($data), 0, 200));
                return null;
            }

            return [
                'content' => $content,
                'raw'     => $data,
            ];
        } catch (\Exception $e) {
            Log::error("[LM Studio] Excepción: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Llama a LM Studio y extrae JSON del contenido de la respuesta.
     * Maneja JSON envuelto en markdown code blocks o texto conversacional.
     *
     * @param string $systemPrompt
     * @param string $userMessage
     * @param float  $temperature
     * @param int    $maxTokens
     * @return array|null  Array decodificado del JSON, o null si falla
     */
    protected function llamarLMStudioJSON(
        string $systemPrompt,
        string $userMessage,
        float $temperature = 0.7,
        int $maxTokens = 2048
    ): ?array {
        $resultado = $this->llamarLMStudio($systemPrompt, $userMessage, $temperature, $maxTokens);

        if (!$resultado) {
            return null;
        }

        $content = $resultado['content'];

        // Limpiar markdown code blocks
        $content = preg_replace('/```json\s*/i', '', $content);
        $content = preg_replace('/```\s*/', '', $content);
        $content = trim($content);

        // Intentar decodificar directamente
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Buscar JSON envuelto en texto (buscar el primer { o [ hasta el último } o ])
        if (preg_match('/\{.*\}/s', $content, $jsonMatch)) {
            $decoded = json_decode($jsonMatch[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (preg_match('/\[.*\]/s', $content, $jsonMatch)) {
            $decoded = json_decode($jsonMatch[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        Log::error("[LM Studio] JSON inválido en respuesta: " . substr($content, 0, 300));
        return null;
    }

    /**
     * URL del endpoint de embeddings de LM Studio.
     * Si LM_STUDIO_EMBEDDING_URL está definido, lo usa (para segunda instancia en otro puerto).
     * Si no, deriva la URL del servidor de chat reemplazando /v1/chat/completions por /v1/embeddings.
     */
    protected function lmStudioEmbeddingsUrl(): string
    {
        $envUrl = env('LM_STUDIO_EMBEDDING_URL');
        if ($envUrl) {
            return $envUrl;
        }
        $chatUrl = $this->lmStudioUrl();
        return str_replace('/v1/chat/completions', '/v1/embeddings', $chatUrl);
    }

    /**
     * Nombre del modelo de embeddings a usar.
     */
    protected function embeddingModel(): string
    {
        return env('LM_STUDIO_EMBEDDING_MODEL', 'nomic-embed-text-v1.5');
    }

    /**
     * Genera un embedding vectorial para un texto dado.
     * Usa el endpoint /v1/embeddings de LM Studio (formato OpenAI-compatible).
     *
     * @param string $text Texto a convertir en vector
     * @return array|null Vector de embeddings o null si falla
     */
    protected function generateEmbedding(string $text): ?array
    {
        $payload = [
            'model' => $this->embeddingModel(),
            'input' => $text,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->lmStudioApiKey(),
                'Content-Type'  => 'application/json',
            ])->timeout(60)->post($this->lmStudioEmbeddingsUrl(), $payload);

            if (!$response->successful()) {
                Log::error("[Embeddings] HTTP {$response->status()}: " . substr($response->body(), 0, 200));
                return null;
            }

            $data = $response->json();
            $embedding = $data['data'][0]['embedding'] ?? null;

            if (!$embedding || !is_array($embedding)) {
                Log::error("[Embeddings] Respuesta sin vector: " . substr(json_encode($data), 0, 300));
                return null;
            }

            return $embedding;
        } catch (\Exception $e) {
            Log::error("[Embeddings] Excepción: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Genera embeddings para múltiples textos en lote.
     * LM Studio soporta enviar varios inputs en un solo request.
     *
     * @param array $texts Array de textos a convertir
     * @return array Array de vectores de embedding, en el mismo orden
     */
    protected function generateEmbeddingsBatch(array $texts): array
    {
        $payload = [
            'model' => $this->embeddingModel(),
            'input' => $texts,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->lmStudioApiKey(),
                'Content-Type'  => 'application/json',
            ])->timeout(120)->post($this->lmStudioEmbeddingsUrl(), $payload);

            if (!$response->successful()) {
                Log::error("[Embeddings Batch] HTTP {$response->status()}: " . substr($response->body(), 0, 200));
                return array_fill(0, count($texts), null);
            }

            $data = $response->json();
            $results = $data['data'] ?? [];

            // Ordenar por índice para mantener el orden original
            usort($results, fn($a, $b) => $a['index'] <=> $b['index']);

            return array_map(fn($r) => $r['embedding'] ?? null, $results);
        } catch (\Exception $e) {
            Log::error("[Embeddings Batch] Excepción: " . $e->getMessage());
            return array_fill(0, count($texts), null);
        }
    }

    /**
     * Calcula la similitud coseno entre dos vectores.
     * Retorna un valor entre -1 y 1 (1 = idénticos, 0 = ortogonales, -1 = opuestos).
     *
     * @param array $a Primer vector
     * @param array $b Segundo vector
     * @return float Similitud coseno
     */
    protected function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $len = min(count($a), count($b));

        for ($i = 0; $i < $len; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $denominator = sqrt($normA) * sqrt($normB);

        if ($denominator == 0) {
            return 0.0;
        }

        return $dotProduct / $denominator;
    }

    /**
     * Busca los chunks más similares a un texto de consulta usando embeddings.
     * Implementa búsqueda semántica con cosine similarity en MySQL.
     *
     * @param int    $documentId  ID del documento
     * @param string $queryText   Texto de la pregunta del usuario
     * @param int    $limit       Número máximo de chunks a retornar
     * @param float  $minScore    Similitud mínima para incluir un chunk (0.0 - 1.0)
     * @return \Illuminate\Support\Collection Chunks ordenados por similitud
     */
    protected function searchSemanticChunks(int $documentId, string $queryText, int $limit = 8, float $minScore = 0.0): \Illuminate\Support\Collection
    {
        // 1. Generar embedding de la pregunta
        $queryEmbedding = $this->generateEmbedding($queryText);

        if (!$queryEmbedding) {
            Log::warning("[Semantic Search] No se pudo generar embedding de la pregunta, usando LIKE como fallback");
            return \App\Models\DocumentChunk::where('document_id', $documentId)
                ->orderBy('id', 'asc')
                ->limit($limit)
                ->get();
        }

        // 2. Obtener todos los chunks del documento con embedding
        $chunks = \App\Models\DocumentChunk::where('document_id', $documentId)
            ->whereNotNull('embedding')
            ->get();

        if ($chunks->isEmpty()) {
            Log::warning("[Semantic Search] No hay chunks con embedding para documento {$documentId}");
            return \App\Models\DocumentChunk::where('document_id', $documentId)
                ->orderBy('id', 'asc')
                ->limit($limit)
                ->get();
        }

        // 3. Calcular similitud coseno con cada chunk y puntuar
        $scoredChunks = $chunks->map(function ($chunk) use ($queryEmbedding, $minScore) {
            $chunkEmbedding = $chunk->embedding;
            if (!is_array($chunkEmbedding) || empty($chunkEmbedding)) {
                return null;
            }

            $score = $this->cosineSimilarity($queryEmbedding, $chunkEmbedding);

            if ($score < $minScore) {
                return null;
            }

            $chunk->similarity_score = $score;
            return $chunk;
        })->filter();

        // 4. Ordenar por similitud descendente y limitar
        $results = $scoredChunks->sortByDesc('similarity_score')->take($limit);

        Log::info("[Semantic Search] Documento {$documentId}: " . $results->count() . " chunks relevantes encontrados (mejor score: " . ($results->first()?->similarity_score ?? 0) . ")");

        return $results->values();
    }
}
