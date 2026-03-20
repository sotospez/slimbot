<?php

namespace Slimbot\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

/**
 * Ollama provider using OpenAI-compatible API endpoint.
 * Ollama runs locally and exposes /v1/chat/completions.
 */
class OllamaProvider implements LLMProviderInterface
{
    private Client $client;

    public function __construct(string $host = 'http://localhost:11434')
    {
        $host = rtrim($host, '/');
        $this->client = new Client(['base_uri' => $host . '/v1/']);
    }

    public function getName(): string
    {
        return 'ollama';
    }

    public function getDefaultModel(): string
    {
        return 'llama3.1';
    }

    public function chatCompletion(array $messages, array $toolDefinitions, string $model): array
    {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
        ];

        if (!empty($toolDefinitions)) {
            $payload['tools'] = $toolDefinitions;
        }

        try {
            $response = $this->client->post('chat/completions', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 120, // Local models can be slow
            ]);
        } catch (ClientException $e) {
            $body = (string) $e->getResponse()?->getBody();
            $isToolUnsupported = stripos($body, 'does not support tools') !== false;

            if (!$isToolUnsupported || empty($toolDefinitions)) {
                throw $e;
            }

            unset($payload['tools']);
            $response = $this->client->post('chat/completions', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 120,
            ]);
        }

        $data = json_decode($response->getBody()->getContents(), true);

        $message = $data['choices'][0]['message'] ?? ['role' => 'assistant', 'content' => null];
        $message = $this->normalizeToolJsonInContent($message);

        return [
            'id' => $data['id'] ?? null,
            'message' => $message,
        ];
    }

    private function normalizeToolJsonInContent(array $message): array
    {
        if (!empty($message['tool_calls']) || !isset($message['content']) || !is_string($message['content'])) {
            return $message;
        }

        $content = trim($message['content']);
        if ($content === '') {
            return $message;
        }

        // Strip fenced JSON blocks if the model wrapped the payload.
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        $content = trim($content);

        if ($content === '' || ($content[0] !== '{' && $content[0] !== '[')) {
            return $message;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return $message;
        }

        if (isset($decoded['tool_calls']) && is_array($decoded['tool_calls'])) {
            $message['tool_calls'] = $decoded['tool_calls'];
            $message['content'] = null;
            return $message;
        }

        if (isset($decoded['function_call']) && is_array($decoded['function_call'])) {
            $message['tool_calls'] = [
                [
                    'id' => 'call_ollama_' . uniqid(),
                    'type' => 'function',
                    'function' => [
                        'name' => $decoded['function_call']['name'] ?? 'unknown',
                        'arguments' => json_encode($decoded['function_call']['arguments'] ?? new \stdClass()),
                    ],
                ],
            ];
            $message['content'] = null;
            return $message;
        }

        if (isset($decoded['function']) && array_key_exists('args', $decoded)) {
            $message['tool_calls'] = [
                [
                    'id' => 'call_ollama_' . uniqid(),
                    'type' => 'function',
                    'function' => [
                        'name' => $decoded['function'],
                        'arguments' => json_encode($decoded['args'] ?? new \stdClass()),
                    ],
                ],
            ];
            $message['content'] = null;
            return $message;
        }

        if (isset($decoded['name']) && array_key_exists('arguments', $decoded)) {
            $message['tool_calls'] = [
                [
                    'id' => 'call_ollama_' . uniqid(),
                    'type' => 'function',
                    'function' => [
                        'name' => $decoded['name'],
                        'arguments' => json_encode($decoded['arguments'] ?? new \stdClass()),
                    ],
                ],
            ];
            $message['content'] = null;
        }

        return $message;
    }
}
