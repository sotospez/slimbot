<?php

namespace Slimbot\Providers;

/**
 * Interface for LLM providers.
 * All providers must normalize their responses to this format.
 */
interface LLMProviderInterface
{
    /**
     * Send a chat completion request.
     *
     * @param array  $messages        Messages array in OpenAI format
     * @param array  $toolDefinitions Tool definitions in OpenAI format
     * @param string $model           Model name to use
     * @return array Normalized response: [
     *   'id'      => string|null,
     *   'message' => [
     *     'role'       => 'assistant',
     *     'content'    => string|null,
     *     'tool_calls' => array|null  (OpenAI tool_calls format)
     *   ]
     * ]
     */
    public function chatCompletion(array $messages, array $toolDefinitions, string $model): array;

    /**
     * Get the default model name for this provider.
     */
    public function getDefaultModel(): string;

    /**
     * Get the provider name (e.g., 'openai', 'google', 'claude', 'ollama').
     */
    public function getName(): string;
}
