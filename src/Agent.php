<?php
// Autonomous bot

namespace Slimbot;

use Slimbot\Providers\LLMProviderInterface;

class Agent
{
    private LLMProviderInterface $provider;
    private string $model;
    private array $history = [];
    private array $tools = [];
    private string $workspacePath;
    private SessionManager $sessionManager;
    private ?string $currentSessionId = null;
    private ?string $lastCompletionId = null;

    private const MAX_HISTORY_MESSAGES = 20; // Keep last 20 messages in context
    private const MIN_MESSAGES_TO_KEEP = 5;  // Always keep at least 5 messages

    public function __construct(LLMProviderInterface $provider, ?string $model = null, string $workspacePath = 'workspace')
    {
        $this->provider = $provider;
        $this->model = $model ?? $provider->getDefaultModel();
        $this->workspacePath = rtrim($workspacePath, '/');
        $this->sessionManager = new SessionManager($this->workspacePath);
    }

    public function setSession(string $sessionId): void
    {
        $this->currentSessionId = $sessionId;
        $history = $this->sessionManager->load($sessionId);

        $context = $this->getSystemPrompt();

        if (empty($history)) {
            $this->history = [['role' => 'system', 'content' => $context]];
        } else {
            $this->history = $history;
            // Always update the system prompt if the first message is a system message
            if (isset($this->history[0]) && $this->history[0]['role'] === 'system') {
                $this->history[0]['content'] = $context;
            } else {
                array_unshift($this->history, ['role' => 'system', 'content' => $context]);
            }
        }
    }

    private function getSystemPrompt(): string
    {
        $context = "You are an AI assistant.\n";

        $files = [
            'IDENTITY.md' => 'Identity',
            'SOUL.md' => 'Soul',
            'AGENTS.md' => 'Agents Guide',
            'USER.md' => 'User Preferences',
            'memory/MEMORY.md' => 'Memory',
            'ALERTS.md' => 'System Alerts',
            'TOOLS_GUIDE.md' => 'Autonomous Tool Usage Guide',
        ];

        foreach ($files as $file => $label) {
            $path = $this->workspacePath . '/' . $file;
            if (file_exists($path)) {
                $context .= "\n--- $label ---\n" . file_get_contents($path) . "\n";
            }
        }

        // Auto-load all facts into system prompt
        $factsDir = $this->workspacePath . '/memory/facts';
        if (is_dir($factsDir)) {
            $factFiles = glob($factsDir . '/*.md');
            if (!empty($factFiles)) {
                $context .= "\n--- Stored Facts ---\n";
                foreach ($factFiles as $factFile) {
                    $category = pathinfo($factFile, PATHINFO_FILENAME);
                    $context .= "\n### $category\n" . file_get_contents($factFile) . "\n";
                }
            }
        }

        // Load Dynamic Skills
        $skillsDir = $this->workspacePath . '/skills';
        if (is_dir($skillsDir)) {
            $it = new \RecursiveDirectoryIterator($skillsDir);
            $display = new \RecursiveIteratorIterator($it);
            foreach ($display as $file) {
                if ($file->getFilename() === 'SKILL.md') {
                    $relativePath = str_replace($skillsDir, '', $file->getPathname());
                    $context .= "\n--- Skill: $relativePath ---\n" . file_get_contents($file->getPathname()) . "\n";
                }
            }
        }

        $context .= "\n--- Autonomous Thinking Process ---\n";
        $context .= "When you receive a request that requires multiple steps:\n";
        $context .= "1. Call a tool to gather information or analyze state.\n";
        $context .= "2. Process the results internally.\n";
        $context .= "3. Call the next tool based on those results.\n";
        $context .= "4. Repeat until the task is complete.\n";
        $context .= "Example: To crop a face, first use image_vision to get coordinates, THEN use crop_image.\n";

        return $context;
    }

    /**
     * Reload the system prompt to pick up new/changed skills mid-session.
     */
    public function reloadSystemPrompt(): void
    {
        if (isset($this->history[0]) && $this->history[0]['role'] === 'system') {
            $this->history[0]['content'] = $this->getSystemPrompt();
        }
    }

    private function initializeContext(): void
    {
        $this->history = [['role' => 'system', 'content' => $this->getSystemPrompt()]];
    }

    public function registerToolObject(Tools\ToolInterface $tool): void
    {
        $this->registerTool($tool->getName(), [$tool, 'execute'], $tool->getDescription(), $tool->getParameters());
    }

    public function registerTool(string $name, callable $handler, string $description, array $parameters): void
    {
        $this->tools[$name] = [
            'handler' => $handler,
            'definition' => [
                'type' => 'function',
                'function' => [
                    'name' => $name,
                    'description' => $description,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) $parameters,
                        'required' => array_keys($parameters),
                    ],
                ],
            ],
        ];
    }

    public function chat(string $message, ?string $imagePath = null): string
    {
        if ($this->currentSessionId === null) {
            $this->setSession('default');
        }

        $content = $message;

        if ($imagePath && file_exists($imagePath)) {
            $type = pathinfo($imagePath, PATHINFO_EXTENSION);
            $imgData = file_get_contents($imagePath);
            $base64 = 'data:image/' . $type . ';base64,' . base64_encode($imgData);

            $content = [
                [
                    'type' => 'text',
                    'text' => $message,
                ],
                [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => $base64,
                    ],
                ],
            ];
        }

        $this->history[] = ['role' => 'user', 'content' => $content];

        $this->truncateHistory();

        $response = $this->think();

        $this->sessionManager->save($this->currentSessionId, $this->history);

        return $response;
    }

    private function think(): string
    {
        $toolDefinitions = array_map(fn($t) => $t['definition'], array_values($this->tools));

        $result = $this->provider->chatCompletion($this->history, $toolDefinitions, $this->model);

        $this->lastCompletionId = $result['id'] ?? null;

        $choice = $result['message'];
        $choice['timestamp'] = date('Y-m-d H:i:s');
        if ($this->lastCompletionId) {
            $choice['completion_id'] = $this->lastCompletionId;
        }
        $this->history[] = $choice;

        if (isset($choice['tool_calls'])) {
            foreach ($choice['tool_calls'] as $toolCall) {
                $toolName = $toolCall['function']['name'];
                $args = json_decode($toolCall['function']['arguments'], true);

                if (isset($this->tools[$toolName])) {
                    $result = call_user_func($this->tools[$toolName]['handler'], $args);
                    $this->history[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCall['id'],
                        'name' => $toolName,
                        'content' => is_string($result) ? $result : json_encode($result),
                        'timestamp' => date('Y-m-d H:i:s'),
                    ];
                }
            }
            return $this->think();
        }

        return $choice['content'] ?? '';
    }

    private function truncateHistory(): void
    {
        if (count($this->history) <= self::MAX_HISTORY_MESSAGES) {
            return;
        }

        // Always keep the system prompt (first message)
        $systemPrompt = $this->history[0];

        // Take the last N messages, but ensure we don't break tool call sequences
        $startIndex = count($this->history) - self::MIN_MESSAGES_TO_KEEP;

        // If we're starting in the middle of a tool sequence (with a 'tool' role), move back
        // until we find the 'assistant' message that initiated it.
        while ($startIndex > 1 && $this->history[$startIndex]['role'] === 'tool') {
            $startIndex--;
        }

        // If the message before our start is an assistant message with tool_calls,
        // we should probably include it too if we are including any of its tool responses.
        // But the while loop above already handles the case where we start at a tool response.

        $tail = array_slice($this->history, $startIndex);

        // Reconstruct history
        $this->history = array_merge([$systemPrompt], [['role' => 'system', 'content' => '[Previous messages truncated. Use history tool to view older context.]']], $tail);
    }

    public function getHistory(): array
    {
        return $this->history;
    }

    public function saveMessage(string $role, string $content): void
    {
        $message = [
            'role' => $role,
            'content' => $content,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        $this->history[] = $message;

        if ($this->currentSessionId) {
            $sessionFile = $this->workspacePath . '/sessions/' . $this->currentSessionId . '.jsonl';
            file_put_contents($sessionFile, json_encode($message) . "\n", FILE_APPEND);
        }
    }
}
