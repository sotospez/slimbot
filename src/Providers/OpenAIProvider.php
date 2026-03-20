<?php

namespace Slimbot\Providers;

use GuzzleHttp\Client;

class OpenAIProvider implements LLMProviderInterface
{
    private Client $client;
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->client = new Client(['base_uri' => 'https://api.openai.com/v1/']);
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function getDefaultModel(): string
    {
        return 'gpt-5.2';
    }

    public function chatCompletion(array $messages, array $toolDefinitions, string $model): array
    {
        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];

        if (!empty($toolDefinitions)) {
            $payload['tools'] = $toolDefinitions;
        }

        $response = $this->client->post('chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return [
            'id' => $data['id'] ?? null,
            'message' => $data['choices'][0]['message'],
        ];
    }
}
