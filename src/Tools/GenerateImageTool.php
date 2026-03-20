<?php

namespace Slimbot\Tools;

use GuzzleHttp\Client;

class GenerateImageTool implements ToolInterface
{
    private string $apiKey;
    private string $imagesDir;

    public function __construct(string $apiKey, string $workspacePath)
    {
        $this->apiKey = $apiKey;
        $this->imagesDir = $workspacePath . '/images';
        if (!is_dir($this->imagesDir)) {
            mkdir($this->imagesDir, 0755, true);
        }
    }

    public function getName(): string
    {
        return 'generate_image';
    }

    public function getDescription(): string
    {
        return 'Generate an image using OpenAI DALL-E 3';
    }

    public function getParameters(): array
    {
        return [
            'prompt' => [
                'type' => 'string',
                'description' => 'The prompt to describe the image',
            ],
            'size' => [
                'type' => 'string',
                'description' => 'The size of the image (1024x1024, 1024x1792, or 1792x1024)',
                'default' => '1024x1024',
            ]
        ];
    }

    public function execute(array $args): string
    {
        $prompt = $args['prompt'];
        $size = $args['size'] ?? '1024x1024';

        $client = new Client(['base_uri' => 'https://api.openai.com/v1/']);

        try {
            $response = $client->post('images/generations', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'dall-e-3',
                    'prompt' => $prompt,
                    'n' => 1,
                    'size' => $size,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $imageUrl = $data['data'][0]['url'] ?? null;

            if (!$imageUrl) {
                return "Error: Could not generate image.";
            }

            // Download the image
            $imageData = file_get_contents($imageUrl);
            $filename = 'image_' . time() . '_' . uniqid() . '.png';
            $path = $this->imagesDir . '/' . $filename;

            file_put_contents($path, $imageData);

            return "Image generated and saved to: $path";
        } catch (\Exception $e) {
            return "Error generating image: " . $e->getMessage();
        }
    }
}
