<?php
declare(strict_types=1);

namespace Rateb\App\Services\Agent;

/**
 * OpenAI Compatible Client
 * Works with any OpenAI-compatible API (OpenAI, Azure, local models via vLLM/Ollama, etc.)
 * All configuration from config/agent.php which reads from environment.
 * Failures throw RuntimeException with stable codes (never leak secrets).
 */
final class OpenAiCompatibleClient implements LlmClientInterface
{
    private string $model;
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;
    private int $maxTokens;
    private float $temperature;
    private string $provider;

    public function __construct(array $config = [])
    {
        $this->model = (string) ($config['model'] ?? 'gpt-4o-mini');
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.openai.com/v1'), '/');
        $this->apiKey = (string) ($config['api_key'] ?? '');
        $this->timeout = max(1, (int) ($config['timeout'] ?? 30));
        $this->maxTokens = max(1, (int) ($config['max_tokens'] ?? 2000));
        $this->temperature = (float) ($config['temperature'] ?? 0.1);
        $this->provider = (string) ($config['provider'] ?? 'openai_compatible');
        // Phase 5: do not crash construction when key missing — fail at call time with stable code.
    }

    public function chatCompletion(array $messages, array $tools, ?string $toolChoice = 'auto'): array
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('llm_not_configured');
        }

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = $toolChoice;
        }

        $ch = curl_init($this->baseUrl . '/chat/completions');
        if ($ch === false) {
            throw new \RuntimeException('llm_request_failed');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $errNo = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errNo === 28) {
            throw new \RuntimeException('llm_timeout');
        }
        if ($errNo !== 0) {
            throw new \RuntimeException('llm_request_failed');
        }
        if (!is_string($response) || trim($response) === '') {
            throw new \RuntimeException('llm_empty_response');
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new \RuntimeException('llm_invalid_response');
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException('llm_api_error');
        }

        $choice = $data['choices'][0] ?? null;
        if (!is_array($choice)) {
            throw new \RuntimeException('llm_empty_response');
        }

        $message = $choice['message'] ?? null;
        if (!is_array($message)) {
            throw new \RuntimeException('llm_invalid_response');
        }

        return [
            'message' => $message,
            'usage' => is_array($data['usage'] ?? null) ? $data['usage'] : null,
            'model' => (string) ($data['model'] ?? $this->model),
        ];
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }
}
