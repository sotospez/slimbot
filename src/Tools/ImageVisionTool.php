<?php

namespace Slimbot\Tools;

use GuzzleHttp\Client;

class ImageVisionTool implements ToolInterface
{
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function getName(): string
    {
        return 'image_vision';
    }

    public function getDescription(): string
    {
        return 'Describe or analyze an image using OpenAI Vision. Can be used to find coordinates (x, y, width, height) of objects for cropping.';
    }

    public function getParameters(): array
    {
        return [
            'image_path' => [
                'type' => 'string',
                'description' => 'Path to the image file',
            ],
            'prompt' => [
                'type' => 'string',
                'description' => 'What to look for or describe. Tip: Ask for JSON coordinates if you intend to crop (e.g., "Find the face and give me x, y, width, height").',
                'default' => 'What is in this image?',
            ]
        ];
    }

    public function execute(array $args): string
    {
        $path = $args['image_path'];
        $prompt = $args['prompt'] ?? 'What is in this image?';

        if (!file_exists($path)) {
            return "Error: Image file not found at $path";
        }

        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);
        $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);

        $client = new Client(['base_uri' => 'https://api.openai.com/v1/']);

        try {
            $response = $client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => $prompt,
                                ],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => $base64,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'max_tokens' => 300,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['choices'][0]['message']['content'] ?? "Error: Could not analyze image.";
        } catch (\Exception $e) {
            return "Error analyzing image: " . $e->getMessage();
        }
    }
}
