<?php

namespace Slimbot\Tools;

use Slimbot\SessionManager;

class HistoryTool implements ToolInterface
{
    private SessionManager $sessionManager;
    private string $workspacePath;

    public function __construct(string $workspacePath)
    {
        $this->workspacePath = $workspacePath;
        $this->sessionManager = new SessionManager($workspacePath);
    }

    public function getName(): string
    {
        return 'manage_history';
    }

    public function getDescription(): string
    {
        return 'Access older chat history that has been truncated from the current context.';
    }

    public function getParameters(): array
    {
        return [
            'action' => [
                'type' => 'string',
                'enum' => ['view_history', 'search_history'],
                'description' => 'The action to perform',
            ],
            'session_id' => [
                'type' => 'string',
                'description' => 'The session ID to look into',
            ],
            'limit' => [
                'type' => 'integer',
                'description' => 'Number of messages to retrieve (default: 10)',
            ],
            'query' => [
                'type' => 'string',
                'description' => 'Search query for search_history',
            ]
        ];
    }

    public function execute(array $args): string
    {
        $action = $args['action'];
        $sessionId = $args['session_id'] ?? 'default';
        $limit = $args['limit'] ?? 10;

        $history = $this->sessionManager->load($sessionId);
        if (empty($history)) {
            return "No history found for session: $sessionId";
        }

        switch ($action) {
            case 'view_history':
                $tail = array_slice($history, -$limit);
                return $this->formatHistory($tail);
            case 'search_history':
                $query = $args['query'] ?? '';
                if (empty($query)) {
                    return "Search query is required.";
                }
                $matches = array_filter($history, function ($msg) use ($query) {
                    return stripos($msg['content'] ?? '', $query) !== false;
                });
                if (empty($matches)) {
                    return "No matches found for query: $query";
                }
                return $this->formatHistory(array_slice($matches, -$limit));
            default:
                return "Invalid action: $action";
        }
    }

    private function formatHistory(array $messages): string
    {
        $output = "--- Historical Messages ---\n";
        foreach ($messages as $msg) {
            $role = strtoupper($msg['role']);
            $time = $msg['timestamp'] ?? 'unknown';
            $output .= "[$time] $role: {$msg['content']}\n\n";
        }
        return $output;
    }
}
