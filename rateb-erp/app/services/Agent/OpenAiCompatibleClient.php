<?php
declare(strict_types=1);

namespace Rateb\App\Services\Agent;

/**
 * OpenAI Compatible Client
 * Works with any OpenAI-compatible API (OpenAI, Azure, local models via vLLM/Ollama, etc.)
 * All configuration from config/agent.php which reads from environment
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
        $this->model = $config['model'] ?? 'gpt-4o-mini';
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.openai.com/v1', '/');
        $this->apiKey = $config['api_key'] ?? '';
        $this->timeout = (int) ($config['timeout'] ?? 30);
        $this->maxTokens = (int) ($config['max_tokens'] ?? 2000);
        $this->temperature = (float) ($config['temperature'] ?? 0.1);
        $this->provider = $config['provider'] ?? 'openai_compatible';

        if ($this->apiKey === '') {
            throw new \RuntimeException('LLM API key not configured. Set RATEB_AGENT_LLM_API_KEY in environment.');
        }
    }

    public function chatCompletion(array $messages, array $tools, ?string $toolChoice = 'auto'): array
    {
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
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $errNo = curl_errno($ch);
        $errMsg = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errNo !== 0) {
            throw new \RuntimeException("LLM request failed: {$errMsg} (errno: {$errNo})");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('LLM response JSON decode error: ' . json_last_error_msg());
        }

        if ($httpCode !== 200) {
            $error = $data['error']['message'] ?? 'Unknown LLM error';
            throw new \RuntimeException("LLM API error ({$httpCode}): {$error}");
        }

        $choice = $data['choices'][0] ?? null;
        if (!$choice) {
            throw new \RuntimeException('LLM response missing choices');
        }

        $message = $choice['message'] ?? [];
        $usage = $data['usage'] ?? null;

        return [
            'message' => $message,
            'usage' => $usage,
            'model' => $data['model'] ?? $this->model,
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