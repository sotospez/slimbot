<?php

namespace Slimbot\Tools;

use GuzzleHttp\Client;

class SpeechToTextTool implements ToolInterface
{
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function getName(): string
    {
        return 'speech_to_text';
    }

    public function getDescription(): string
    {
        return 'Transcribe an audio file to text using OpenAI Whisper';
    }

    public function getParameters(): array
    {
        return [
            'audio_path' => [
                'type' => 'string',
                'description' => 'Path to the audio file (mp3, mp4, mpeg, mpga, m4a, wav, or webm)',
            ]
        ];
    }

    public function execute(array $args): string
    {
        $path = $args['audio_path'];
        if (!file_exists($path)) {
            return "Error: Audio file not found at $path";
        }

        $client = new Client(['base_uri' => 'https://api.openai.com/v1/']);

        try {
            $response = $client->post('audio/transcriptions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ],
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => fopen($path, 'r'),
                        'filename' => basename($path),
                    ],
                    [
                        'name' => 'model',
                        'contents' => 'whisper-1',
                    ],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['text'] ?? "Error: Could not transcribe audio.";
        } catch (\Exception $e) {
            return "Error transcribing audio: " . $e->getMessage();
        }
    }
}
