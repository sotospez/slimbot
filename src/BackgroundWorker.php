<?php

namespace Slimbot;

/**
 * BackgroundWorker processes scheduled tasks like reminders
 * and writes them directly into session history as bot messages.
 */
class BackgroundWorker
{
    private string $workspacePath;
    private int $lastCheck = 0;
    private int $checkInterval = 30; // Check every 30 seconds
    private array $agents; // Reference to server's agents

    public function __construct(string $workspacePath, array &$agents)
    {
        $this->workspacePath = rtrim($workspacePath, '/');
        $this->agents = &$agents;
    }

    /**
     * Tick is called frequently by the server loop.
     * It only does real work every $checkInterval seconds.
     */
    public function tick(): void
    {
        $now = time();
        if ($now - $this->lastCheck < $this->checkInterval) {
            return;
        }
        $this->lastCheck = $now;

        $this->processReminders();
        $this->cleanupOldArchives();
    }

    /**
     * Scan all reminder JSON files and trigger any that are due.
     */
    private function processReminders(): void
    {
        $remindersDir = $this->workspacePath . '/memory/reminders';
        if (!is_dir($remindersDir)) {
            return;
        }

        $files = glob($remindersDir . '/*.json');
        if (empty($files)) {
            return;
        }

        $now = time();
        $alerts = [];

        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (!$data || !isset($data['start_time'])) {
                continue;
            }

            $triggered = $data['triggered'] ?? [];
            $modified = false;

            // Check offset alerts (e.g., 10 minutes before)
            if (!empty($data['offsets'])) {
                foreach ($data['offsets'] as $offsetMinutes) {
                    $alertTime = $data['start_time'] - ($offsetMinutes * 60);
                    $offsetKey = "offset_{$offsetMinutes}";

                    if ($now >= $alertTime && !in_array($offsetKey, $triggered)) {
                        $alerts[] = "⏰ **Reminder in {$offsetMinutes} min**: {$data['message']} (at " . date('H:i', $data['start_time']) . ")";
                        $triggered[] = $offsetKey;
                        $modified = true;
                    }
                }
            }

            // Check main time
            if ($now >= $data['start_time'] && !in_array('main', $triggered)) {
                $alerts[] = "🔔 **Reminder NOW**: {$data['message']}";
                $triggered[] = 'main';
                $modified = true;
            }

            // Check end time
            if (isset($data['end_time']) && $now >= $data['end_time'] && !in_array('end', $triggered)) {
                $alerts[] = "🏁 **Event ended**: {$data['message']}";
                $triggered[] = 'end';
                $modified = true;
            }

            // Update the file if we triggered something
            if ($modified) {
                $data['triggered'] = $triggered;
                file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
            }

            // Archive fully completed reminders
            if (in_array('main', $triggered)) {
                $hasEnd = isset($data['end_time']);
                if (!$hasEnd || in_array('end', $triggered)) {
                    $archiveDir = $this->workspacePath . '/memory/reminders/archive';
                    if (!is_dir($archiveDir)) {
                        mkdir($archiveDir, 0755, true);
                    }
                    rename($file, $archiveDir . '/' . basename($file));
                }
            }
        }

        // Push alerts directly into all active session histories
        if (!empty($alerts)) {
            $message = implode("\n", $alerts);

            foreach ($this->agents as $sessionId => $agent) {
                $agent->saveMessage('assistant', $message);
                echo "[BackgroundWorker] Alert pushed to session '$sessionId': " . substr($message, 0, 80) . "\n";
            }

            // Also save to web_session file directly if no agent is loaded for it
            if (!isset($this->agents['web_session'])) {
                $sessionFile = $this->workspacePath . '/sessions/web_session.jsonl';
                $entry = json_encode([
                    'role' => 'assistant',
                    'content' => $message,
                    'timestamp' => date('Y-m-d H:i:s'),
                ], JSON_UNESCAPED_UNICODE) . "\n";
                file_put_contents($sessionFile, $entry, FILE_APPEND);
                echo "[BackgroundWorker] Alert written to web_session file\n";
            }

            echo "[BackgroundWorker] " . count($alerts) . " alert(s) triggered at " . date('H:i:s') . "\n";
        }
    }

    /**
     * Delete archived reminders older than 30 days.
     */
    private function cleanupOldArchives(): void
    {
        $archiveDir = $this->workspacePath . '/memory/reminders/archive';
        if (!is_dir($archiveDir)) {
            return;
        }

        $files = glob($archiveDir . '/*.json');
        $now = time();
        $thirtyDays = 30 * 24 * 60 * 60;

        foreach ($files as $file) {
            if ($now - filemtime($file) > $thirtyDays) {
                unlink($file);
            }
        }
    }
}
