<?php

namespace Slimbot;

class SessionManager
{
    private string $sessionsDir;

    public function __construct(string $workspacePath)
    {
        $this->sessionsDir = $workspacePath . '/sessions';
        if (!is_dir($this->sessionsDir)) {
            mkdir($this->sessionsDir, 0777, true);
        }
    }

    public function load(string $sessionId): array
    {
        $path = $this->getPath($sessionId);
        if (!file_exists($path)) {
            return [];
        }

        $messages = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if ($data && isset($data['role'])) {
                $messages[] = $data;
            }
        }
        return $messages;
    }

    public function save(string $sessionId, array $messages): void
    {
        $path = $this->getPath($sessionId);
        $content = '';
        foreach ($messages as $msg) {
            // Only save messages that are not system messages (optional, but nanobot saves all)
            $content .= json_encode($msg, JSON_UNESCAPED_UNICODE) . "\n";
        }
        file_put_contents($path, $content);
    }

    private function getPath(string $sessionId): string
    {
        $safeId = preg_replace('/[^a-z0-9_-]/i', '_', $sessionId);
        return $this->sessionsDir . '/' . $safeId . '.jsonl';
    }
}
