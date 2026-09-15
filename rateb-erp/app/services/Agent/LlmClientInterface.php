<?php
declare(strict_types=1);

namespace Rateb\App\Services\Agent;

/**
 * LLM Client Interface
 * Provider-agnostic contract for LLM interactions
 */

interface LlmClientInterface
{
    /**
     * Send a chat completion request
     *
     * @param array<string, mixed> $messages  Array of {role: string, content: string}
     * @param array<string, mixed> $tools     Tool definitions in OpenAI format
     * @param string|null $toolChoice         'auto' | 'none' | specific tool name
     * @return array{
     *     message: array{role: string, content: string|null, tool_calls?: list<array{id: string, type: string, function: array{name: string, arguments: string}}>},
     *     usage: array{prompt_tokens: int, completion_tokens: int, total_tokens: int}|null,
     *     model: string
     * }
     */
    public function chatCompletion(array $messages, array $tools, ?string $toolChoice = 'auto'): array;

    /**
     * Get the model name being used
     */
    public function getModel(): string;

    /**
     * Get the provider name
     */
    public function getProvider(): string;
}