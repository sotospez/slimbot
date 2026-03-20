<?php

namespace Slimbot\Providers;

use GuzzleHttp\Client;

class GoogleGeminiProvider implements LLMProviderInterface
{
    private Client $client;
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->client = new Client(['base_uri' => 'https://generativelanguage.googleapis.com/']);
    }

    public function getName(): string
    {
        return 'google';
    }

    public function getDefaultModel(): string
    {
        return 'gemini-2.0-flash';
    }

    public function chatCompletion(array $messages, array $toolDefinitions, string $model): array
    {
        // Convert OpenAI messages to Gemini format
        $systemInstruction = null;
        $geminiContents = [];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $text = $this->extractTextContent($msg['content']);
                $systemInstruction = ($systemInstruction ?? '') . ($systemInstruction ? "\n\n" : '') . $text;
                continue;
            }

            if ($msg['role'] === 'tool') {
                // Gemini expects function responses as 'function' role
                $geminiContents[] = [
                    'role' => 'function',
                    'parts' => [
                        [
                            'functionResponse' => [
                                'name' => $msg['name'] ?? 'unknown',
                                'response' => [
                                    'result' => $msg['content'],
                                ],
                            ],
                        ],
                    ],
                ];
                continue;
            }

            if ($msg['role'] === 'assistant' && isset($msg['tool_calls'])) {
                // Convert tool calls to Gemini functionCall format
                $parts = [];
                if (!empty($msg['content'])) {
                    $parts[] = ['text' => $msg['content']];
                }
                foreach ($msg['tool_calls'] as $tc) {
                    $parts[] = [
                        'functionCall' => [
                            'name' => $tc['function']['name'],
                            'args' => json_decode($tc['function']['arguments'], true) ?? new \stdClass(),
                        ],
                    ];
                }
                $geminiContents[] = ['role' => 'model', 'parts' => $parts];
                continue;
            }

            // Regular messages
            $role = ($msg['role'] === 'assistant') ? 'model' : 'user';
            $parts = $this->convertContentToParts($msg['content']);
            $geminiContents[] = ['role' => $role, 'parts' => $parts];
        }

        // Merge consecutive same-role messages (Gemini requirement)
        $geminiContents = $this->mergeConsecutiveContents($geminiContents);

        $payload = [
            'contents' => $geminiContents,
        ];

        if ($systemInstruction) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $systemInstruction]],
            ];
        }

        if (!empty($toolDefinitions)) {
            $payload['tools'] = [
                ['functionDeclarations' => $this->convertToolDefinitions($toolDefinitions)],
            ];
        }

        // Gemini generateContent endpoint
        $endpoint = "v1beta/models/{$model}:generateContent";

        $response = $this->client->post($endpoint, [
            'query' => ['key' => $this->apiKey],
            'headers' => ['Content-Type' => 'application/json'],
            'json' => $payload,
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return $this->normalizeResponse($data);
    }

    private function extractTextContent($content): string
    {
        if (is_string($content)) {
            return $content;
        }
        if (is_array($content)) {
            $texts = [];
            foreach ($content as $part) {
                if (isset($part['type']) && $part['type'] === 'text') {
                    $texts[] = $part['text'];
                }
            }
            return implode("\n", $texts);
        }
        return '';
    }

    private function convertContentToParts($content): array
    {
        if (is_string($content)) {
            return [['text' => $content]];
        }

        if (is_array($content)) {
            $parts = [];
            foreach ($content as $item) {
                if ($item['type'] === 'text') {
                    $parts[] = ['text' => $item['text']];
                } elseif ($item['type'] === 'image_url') {
                    $url = $item['image_url']['url'];
                    if (str_starts_with($url, 'data:')) {
                        preg_match('/data:(image\/\w+);base64,(.+)/', $url, $matches);
                        if ($matches) {
                            $parts[] = [
                                'inlineData' => [
                                    'mimeType' => $matches[1],
                                    'data' => $matches[2],
                                ],
                            ];
                        }
                    }
                }
            }
            return $parts;
        }

        return [['text' => (string) $content]];
    }

    /**
     * Merge consecutive messages with same role (Gemini requirement).
     */
    private function mergeConsecutiveContents(array $contents): array
    {
        if (empty($contents)) {
            return $contents;
        }

        $merged = [$contents[0]];

        for ($i = 1; $i < count($contents); $i++) {
            $last = &$merged[count($merged) - 1];

            if ($contents[$i]['role'] === $last['role']) {
                $last['parts'] = array_merge($last['parts'], $contents[$i]['parts']);
            } else {
                $merged[] = $contents[$i];
            }

            unset($last);
        }

        return $merged;
    }

    /**
     * Convert OpenAI tool definitions to Gemini functionDeclarations format.
     */
    private function convertToolDefinitions(array $tools): array
    {
        $declarations = [];
        foreach ($tools as $tool) {
            $fn = $tool['function'];
            $params = $fn['parameters'] ?? [];

            // Gemini uses a slightly different schema format
            $declaration = [
                'name' => $fn['name'],
                'description' => $fn['description'],
            ];

            if (!empty($params)) {
                $declaration['parameters'] = $this->convertSchemaForGemini($params);
            }

            $declarations[] = $declaration;
        }
        return $declarations;
    }

    /**
     * Convert JSON Schema to Gemini-compatible schema (remove unsupported fields).
     */
    private function convertSchemaForGemini(array $schema): array
    {
        // Gemini doesn't support 'additionalProperties' and some other JSON Schema features
        $result = [];
        foreach ($schema as $key => $value) {
            if ($key === 'additionalProperties') {
                continue;
            }
            if (is_array($value)) {
                $result[$key] = $this->convertSchemaForGemini($value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * Normalize Gemini response to OpenAI-compatible format.
     */
    private function normalizeResponse(array $data): array
    {
        $candidate = $data['candidates'][0] ?? null;
        if (!$candidate || !isset($candidate['content']['parts'])) {
            return [
                'id' => null,
                'message' => ['role' => 'assistant', 'content' => 'Error: Empty response from Gemini'],
            ];
        }

        $content = null;
        $toolCalls = [];
        $callIndex = 0;

        foreach ($candidate['content']['parts'] as $part) {
            if (isset($part['text'])) {
                $content = ($content ?? '') . $part['text'];
            } elseif (isset($part['functionCall'])) {
                $toolCalls[] = [
                    'id' => 'call_gemini_' . uniqid(),
                    'type' => 'function',
                    'function' => [
                        'name' => $part['functionCall']['name'],
                        'arguments' => json_encode($part['functionCall']['args'] ?? new \stdClass()),
                    ],
                ];
                $callIndex++;
            }
        }

        $message = [
            'role' => 'assistant',
            'content' => $content,
        ];

        if (!empty($toolCalls)) {
            $message['tool_calls'] = $toolCalls;
        }

        return [
            'id' => null,
            'message' => $message,
        ];
    }
}
