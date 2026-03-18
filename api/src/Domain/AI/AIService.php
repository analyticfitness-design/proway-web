<?php
declare(strict_types=1);

namespace ProWay\Domain\AI;

use PDO;

class AIService
{
    private string $apiKey;
    private string $apiUrl;
    private string $model;

    public function __construct(
        private readonly PDO            $db,
        private readonly PromptManager  $prompts,
    ) {
        $this->apiKey = $_ENV['OPENAI_API_KEY'] ?? (getenv('OPENAI_API_KEY') ?: '');
        $this->apiUrl = $_ENV['AI_API_URL']     ?? (getenv('AI_API_URL')     ?: 'https://api.openai.com/v1/chat/completions');
        $this->model  = $_ENV['AI_MODEL']       ?? (getenv('AI_MODEL')       ?: 'gpt-4o-mini');
    }

    /**
     * Generate a project quote.
     * Returns the AI text response and logs cost.
     */
    public function generateQuote(string $title, string $description, string $clientName): string
    {
        $prompt = $this->prompts->get('quote', [
            'title'       => $title,
            'description' => $description,
            'client_name' => $clientName,
        ]);

        return $this->complete($prompt, 'quote');
    }

    /**
     * Generate a video script.
     */
    public function generateScript(string $type, string $topic, int $duration, string $tone): string
    {
        $prompt = $this->prompts->get('script', [
            'type'     => $type,
            'topic'    => $topic,
            'duration' => $duration,
            'tone'     => $tone,
        ]);

        return $this->complete($prompt, 'script');
    }

    /**
     * Generate social media content.
     */
    public function generateContent(string $platform, string $topic, string $goal): string
    {
        $prompt = $this->prompts->get('content', [
            'platform' => $platform,
            'topic'    => $topic,
            'goal'     => $goal,
        ]);

        return $this->complete($prompt, 'content');
    }

    /**
     * Chat with the assistant.
     * @param array<array{role: string, content: string}> $messages Conversation history
     */
    public function chat(array $messages, string $clientName = '', string $planType = ''): string
    {
        $systemPrompt = $this->prompts->get('chat', [
            'client_name' => $clientName,
            'plan_type'   => $planType,
        ]);

        // Prepend system message
        $fullMessages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages
        );

        return $this->callApi($fullMessages, 'chat');
    }

    /**
     * Send a single completion request (system prompt → user message).
     */
    private function complete(string $systemPrompt, string $type): string
    {
        return $this->callApi([
            ['role' => 'system', 'content' => $systemPrompt],
        ], $type);
    }

    /**
     * Make the HTTP call to the AI API and log cost.
     * @param array<array{role: string, content: string}> $messages
     */
    private function callApi(array $messages, string $type): string
    {
        $payload = json_encode([
            'model'       => $this->model,
            'messages'    => $messages,
            'max_tokens'  => 1500,
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
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new \RuntimeException("AI API call failed (HTTP $httpCode)");
        }

        $data    = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? '';
        $tokens  = $data['usage']['total_tokens'] ?? 0;

        $this->logUsage($type, $tokens);

        return $content;
    }

    /**
     * Log AI usage for cost tracking (fire-and-forget, swallows exceptions).
     */
    private function logUsage(string $type, int $tokens): void
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO ai_usage_log (request_type, tokens_used, created_at) VALUES (?, ?, NOW())'
            );
            $stmt->execute([$type, $tokens]);
        } catch (\Throwable) {
            // Non-critical: if logging fails, do not break the AI response
        }
    }
}
