<?php

namespace Slimbot\Providers;

use GuzzleHttp\Client;

class ClaudeProvider implements LLMProviderInterface
{
    private Client $client;
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->client = new Client(['base_uri' => 'https://api.anthropic.com/v1/']);
    }

    public function getName(): string
    {
        return 'claude';
    }

    public function getDefaultModel(): string
    {
        return 'claude-sonnet-4-20250514';
    }

    public function chatCompletion(array $messages, array $toolDefinitions, string $model): array
    {
        // Separate system message from conversation messages
        $systemContent = '';
        $claudeMessages = [];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemContent .= ($systemContent ? "\n\n" : '') . $this->extractTextContent($msg['content']);
                continue;
            }

            if ($msg['role'] === 'tool') {
                // Claude expects tool results as 'user' role with tool_result content
                $claudeMessages[] = [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'tool_result',
                            'tool_use_id' => $msg['tool_call_id'],
                            'content' => $msg['content'],
                        ]
                    ],
                ];
                continue;
            }

            if ($msg['role'] === 'assistant' && isset($msg['tool_calls'])) {
                // Convert OpenAI tool_calls to Claude tool_use format
                $content = [];
                if (!empty($msg['content'])) {
                    $content[] = ['type' => 'text', 'text' => $msg['content']];
                }
                foreach ($msg['tool_calls'] as $tc) {
                    $content[] = [
                        'type' => 'tool_use',
                        'id' => $tc['id'],
                        'name' => $tc['function']['name'],
                        'input' => json_decode($tc['function']['arguments'], true) ?? [],
                    ];
                }
                $claudeMessages[] = ['role' => 'assistant', 'content' => $content];
                continue;
            }

            // Regular user/assistant messages
            $claudeMessages[] = [
                'role' => $msg['role'],
                'content' => $this->convertContent($msg['content']),
            ];
        }

        // Merge consecutive messages with same role (Claude requirement)
        $claudeMessages = $this->mergeConsecutiveMessages($claudeMessages);

        $payload = [
            'model' => $model,
            'max_tokens' => 4096,
            'messages' => $claudeMessages,
        ];

        if ($systemContent) {
            $payload['system'] = $systemContent;
        }

        if (!empty($toolDefinitions)) {
            $payload['tools'] = $this->convertToolDefinitions($toolDefinitions);
        }

        $response = $this->client->post('messages', [
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return $this->normalizeResponse($data);
    }

    /**
     * Extract text content from a message content field (string or array).
     */
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

    /**
     * Convert OpenAI content format to Claude content format.
     */
    private function convertContent($content)
    {
        if (is_string($content)) {
            return $content;
        }

        if (is_array($content)) {
            $claudeContent = [];
            foreach ($content as $part) {
                if ($part['type'] === 'text') {
                    $claudeContent[] = ['type' => 'text', 'text' => $part['text']];
                } elseif ($part['type'] === 'image_url') {
                    $url = $part['image_url']['url'];
                    if (str_starts_with($url, 'data:')) {
                        // Base64 image
                        preg_match('/data:(image\/\w+);base64,(.+)/', $url, $matches);
                        if ($matches) {
                            $claudeContent[] = [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'media_type' => $matches[1],
                                    'data' => $matches[2],
                                ],
                            ];
                        }
                    }
                }
            }
            return $claudeContent;
        }

        return $content;
    }

    /**
     * Claude requires no two consecutive messages with the same role.
     * Merge them if needed.
     */
    private function mergeConsecutiveMessages(array $messages): array
    {
        if (empty($messages)) {
            return $messages;
        }

        $merged = [$messages[0]];

        for ($i = 1; $i < count($messages); $i++) {
            $last = &$merged[count($merged) - 1];

            if ($messages[$i]['role'] === $last['role']) {
                // Merge content
                $lastContent = is_array($last['content']) ? $last['content'] : [['type' => 'text', 'text' => $last['content']]];
                $newContent = is_array($messages[$i]['content']) ? $messages[$i]['content'] : [['type' => 'text', 'text' => $messages[$i]['content']]];
                $last['content'] = array_merge($lastContent, $newContent);
            } else {
                $merged[] = $messages[$i];
            }

            unset($last);
        }

        return $merged;
    }

    /**
     * Convert OpenAI tool definitions to Claude format.
     */
    private function convertToolDefinitions(array $tools): array
    {
        $claudeTools = [];
        foreach ($tools as $tool) {
            $fn = $tool['function'];
            $claudeTools[] = [
                'name' => $fn['name'],
                'description' => $fn['description'],
                'input_schema' => $fn['parameters'],
            ];
        }
        return $claudeTools;
    }

    /**
     * Normalize Claude response to OpenAI-compatible format.
     */
    private function normalizeResponse(array $data): array
    {
        $content = null;
        $toolCalls = [];

        foreach ($data['content'] as $block) {
            if ($block['type'] === 'text') {
                $content = ($content ?? '') . $block['text'];
            } elseif ($block['type'] === 'tool_use') {
                $toolCalls[] = [
                    'id' => $block['id'],
                    'type' => 'function',
                    'function' => [
                        'name' => $block['name'],
                        'arguments' => json_encode($block['input']),
                    ],
                ];
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
            'id' => $data['id'] ?? null,
            'message' => $message,
        ];
    }
}
