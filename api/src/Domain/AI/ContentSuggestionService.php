<?php
declare(strict_types=1);

namespace ProWay\Domain\AI;

class ContentSuggestionService
{
    /** Max AI calls per hour per admin (across all clients) */
    private const RATE_LIMIT_PER_HOUR = 20;

    private string $apiKey;
    private string $apiUrl;
    private string $model;
    private string $provider; // 'claude' or 'openai'

    public function __construct(
        private readonly SuggestionRepository $repo,
    ) {
        // Prefer Claude, fall back to OpenAI
        $claudeKey  = $_ENV['CLAUDE_API_KEY'] ?? (getenv('CLAUDE_API_KEY') ?: '');
        $openaiKey  = $_ENV['OPENAI_API_KEY'] ?? (getenv('OPENAI_API_KEY') ?: '');

        if ($claudeKey !== '') {
            $this->provider = 'claude';
            $this->apiKey   = $claudeKey;
            $this->apiUrl   = 'https://api.anthropic.com/v1/messages';
            $this->model    = $_ENV['CLAUDE_MODEL'] ?? (getenv('CLAUDE_MODEL') ?: 'claude-sonnet-4-6');
        } elseif ($openaiKey !== '') {
            $this->provider = 'openai';
            $this->apiKey   = $openaiKey;
            $this->apiUrl   = $_ENV['AI_API_URL'] ?? (getenv('AI_API_URL') ?: 'https://api.openai.com/v1/chat/completions');
            $this->model    = $_ENV['AI_MODEL'] ?? (getenv('AI_MODEL') ?: 'gpt-4o-mini');
        } else {
            $this->provider = 'none';
            $this->apiKey   = '';
            $this->apiUrl   = '';
            $this->model    = '';
        }
    }

    /**
     * Generate 10 content ideas for a platform + niche.
     * Returns cached result if fresh (< 24 h), otherwise calls AI API.
     *
     * @return array{id: int, result_text: string, tokens_used: int, cached: bool}
     * @throws \RuntimeException on rate limit, missing API key, or API failure
     */
    public function generateSuggestions(int $clientId, string $platform, string $niche): array
    {
        $promptType = "content_suggestions:{$platform}:{$niche}";

        // Check for fresh cached result
        $fresh = $this->repo->findFresh($clientId, $promptType);
        if ($fresh !== null) {
            return [
                'id'          => (int) $fresh['id'],
                'result_text' => $fresh['result_text'],
                'tokens_used' => (int) $fresh['tokens_used'],
                'cached'      => true,
                'created_at'  => $fresh['created_at'],
            ];
        }

        // Check API key
        if ($this->provider === 'none') {
            throw new \RuntimeException(
                'No hay API key de IA configurada. Agrega CLAUDE_API_KEY o OPENAI_API_KEY en las variables de entorno.'
            );
        }

        // Rate limit: max 20 calls per hour (across all clients for this admin session)
        $recentCount = $this->repo->countRecentByClient($clientId);
        if ($recentCount >= self::RATE_LIMIT_PER_HOUR) {
            throw new \RuntimeException(
                'Limite de velocidad alcanzado. Maximo ' . self::RATE_LIMIT_PER_HOUR . ' consultas por hora. Intenta de nuevo mas tarde.'
            );
        }

        $systemPrompt = "Eres un estratega de contenido para fitness en LATAM. Genera 10 ideas de contenido especificas para {$platform} en el nicho de {$niche}. Para cada idea incluye:\n- Titulo del contenido\n- Formato (reel 30s, reel 60s, carrusel, story, video largo)\n- Hook de apertura (primeros 3 segundos)\n- 3-5 hashtags sugeridos\n- Mejor horario de publicacion (hora Colombia)\nResponde en espanol. Se especifico y creativo.";

        $userPrompt = "Genera las 10 ideas de contenido para {$platform} en el nicho de {$niche}. Hazlas relevantes para tendencias actuales en LATAM.";

        $result = $this->callAI($systemPrompt, $userPrompt);

        if ($result === null) {
            throw new \RuntimeException('La llamada a la API de IA fallo. Intenta de nuevo mas tarde.');
        }

        $tokensUsed = $result['tokens'] ?? 0;
        $text       = $result['text'] ?? '';

        // Store in DB with 24h expiry
        $id = $this->repo->create([
            'client_id'    => $clientId,
            'prompt_type'  => $promptType,
            'context_json' => json_encode([
                'platform' => $platform,
                'niche'    => $niche,
                'provider' => $this->provider,
                'model'    => $this->model,
            ]),
            'result_text'  => $text,
            'tokens_used'  => $tokensUsed,
            'expires_at'   => date('Y-m-d H:i:s', strtotime('+24 hours')),
        ]);

        return [
            'id'          => $id,
            'result_text' => $text,
            'tokens_used' => $tokensUsed,
            'cached'      => false,
            'created_at'  => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Generate a trend analysis for a platform + month.
     *
     * @throws \RuntimeException on missing API key or API failure
     */
    public function generateTrendAnalysis(int $clientId, string $platform, string $month): array
    {
        $promptType = "trend_analysis:{$platform}:{$month}";

        // Check cache
        $fresh = $this->repo->findFresh($clientId, $promptType);
        if ($fresh !== null) {
            return [
                'id'          => (int) $fresh['id'],
                'result_text' => $fresh['result_text'],
                'tokens_used' => (int) $fresh['tokens_used'],
                'cached'      => true,
                'created_at'  => $fresh['created_at'],
            ];
        }

        if ($this->provider === 'none') {
            throw new \RuntimeException(
                'No hay API key de IA configurada. Agrega CLAUDE_API_KEY o OPENAI_API_KEY en las variables de entorno.'
            );
        }

        // Rate limit
        $recentCount = $this->repo->countRecentByClient($clientId);
        if ($recentCount >= self::RATE_LIMIT_PER_HOUR) {
            throw new \RuntimeException(
                'Limite de velocidad alcanzado. Maximo ' . self::RATE_LIMIT_PER_HOUR . ' consultas por hora.'
            );
        }

        $systemPrompt = "Eres un analista de tendencias de contenido digital para fitness en LATAM. Analiza las tendencias de {$platform} para el mes de {$month}. Incluye:\n- Top 5 tendencias del mes\n- Formatos mas virales\n- Audios/sonidos trending\n- Hashtags del momento\n- Horarios pico de engagement\n- Predicciones para el proximo mes\nResponde en espanol con datos especificos.";

        $userPrompt = "Genera un analisis completo de tendencias de {$platform} para fitness en LATAM durante {$month}.";

        $result = $this->callAI($systemPrompt, $userPrompt);

        if ($result === null) {
            throw new \RuntimeException('La llamada a la API de IA fallo. Intenta de nuevo mas tarde.');
        }

        $tokensUsed = $result['tokens'] ?? 0;
        $text       = $result['text'] ?? '';

        $id = $this->repo->create([
            'client_id'    => $clientId,
            'prompt_type'  => $promptType,
            'context_json' => json_encode([
                'platform' => $platform,
                'month'    => $month,
                'provider' => $this->provider,
                'model'    => $this->model,
            ]),
            'result_text'  => $text,
            'tokens_used'  => $tokensUsed,
            'expires_at'   => date('Y-m-d H:i:s', strtotime('+24 hours')),
        ]);

        return [
            'id'          => $id,
            'result_text' => $text,
            'tokens_used' => $tokensUsed,
            'cached'      => false,
            'created_at'  => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * List recent suggestions for a client.
     * @return array<int, array>
     */
    public function listForClient(int $clientId, int $limit = 5): array
    {
        return $this->repo->findLatestForClient($clientId, $limit);
    }

    /**
     * Call the AI provider (Claude or OpenAI) via cURL.
     * @return array{text: string, tokens: int}|null
     */
    private function callAI(string $systemPrompt, string $userPrompt): ?array
    {
        if ($this->provider === 'claude') {
            return $this->callClaude($systemPrompt, $userPrompt);
        }

        return $this->callOpenAI($systemPrompt, $userPrompt);
    }

    /**
     * Call Claude (Anthropic Messages API).
     * @return array{text: string, tokens: int}|null
     */
    private function callClaude(string $systemPrompt, string $userPrompt): ?array
    {
        $payload = json_encode([
            'model'      => $this->model,
            'max_tokens' => 2048,
            'system'     => $systemPrompt,
            'messages'   => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            error_log("[AI] Claude API call failed (HTTP {$httpCode}): " . ($response ?: 'no response'));
            return null;
        }

        $data = json_decode((string) $response, true);
        $text = $data['content'][0]['text'] ?? '';
        $inputTokens  = $data['usage']['input_tokens'] ?? 0;
        $outputTokens = $data['usage']['output_tokens'] ?? 0;

        return [
            'text'   => $text,
            'tokens' => $inputTokens + $outputTokens,
        ];
    }

    /**
     * Call OpenAI Chat Completions API.
     * @return array{text: string, tokens: int}|null
     */
    private function callOpenAI(string $systemPrompt, string $userPrompt): ?array
    {
        $payload = json_encode([
            'model'       => $this->model,
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
            'max_tokens'  => 2048,
            'temperature' => 0.7,
        ]);

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            error_log("[AI] OpenAI API call failed (HTTP {$httpCode}): " . ($response ?: 'no response'));
            return null;
        }

        $data    = json_decode((string) $response, true);
        $text    = $data['choices'][0]['message']['content'] ?? '';
        $tokens  = $data['usage']['total_tokens'] ?? 0;

        return [
            'text'   => $text,
            'tokens' => $tokens,
        ];
    }
}
