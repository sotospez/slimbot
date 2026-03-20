<?php

namespace Slimbot\Tools;

use GuzzleHttp\Client;

class TextToSpeechTool implements ToolInterface
{
    private string $apiKey;
    private string $audioDir;

    public function __construct(string $apiKey, string $workspacePath)
    {
        $this->apiKey = $apiKey;
        $this->audioDir = $workspacePath . '/audio';
        if (!is_dir($this->audioDir)) {
            mkdir($this->audioDir, 0755, true);
        }
    }

    public function getName(): string
    {
        return 'text_to_speech';
    }

    public function getDescription(): string
    {
        return 'Convert text to speech audio file using OpenAI TTS';
    }

    public function getParameters(): array
    {
        return [
            'text' => [
                'type' => 'string',
                'description' => 'The text to convert to speech',
            ],
            'voice' => [
                'type' => 'string',
                'description' => 'The voice to use (alloy, echo, fable, onyx, nova, shimmer)',
                'default' => 'alloy',
            ]
        ];
    }

    public function execute(array $args): string
    {
        $text = $args['text'];
        $voice = $args['voice'] ?? 'alloy';

        $client = new Client(['base_uri' => 'https://api.openai.com/v1/']);

        try {
            $response = $client->post('audio/speech', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'tts-1',
                    'input' => $text,
                    'voice' => $voice,
                ],
            ]);

            $filename = 'tts_' . time() . '_' . uniqid() . '.mp3';
            $path = $this->audioDir . '/' . $filename;

            file_put_contents($path, $response->getBody()->getContents());

            $relativePath = 'workspace/audio/' . $filename;
            $viewPath = 'view.php?path=audio/' . $filename;

            //return "Audio generated. File: {$relativePath}\nOpen in web: {$viewPath}";
            return "Audio generated.\nOpen in web: {$viewPath}";
        } catch (\Exception $e) {
            return "Error generating speech: " . $e->getMessage();
        }
    }
}
