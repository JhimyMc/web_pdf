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
}
