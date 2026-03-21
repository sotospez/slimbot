<?php

namespace Slimbot;

use Slimbot\AgentFactory;

class AgentServer
{
    private string $projectRoot;
    private int $port;
    private array $agents = [];
    private BackgroundWorker $worker;

    public function __construct(string $projectRoot, int $port = 8080)
    {
        $this->projectRoot = $projectRoot;
        $this->port = $port;
        $this->worker = new BackgroundWorker($projectRoot . '/workspace', $this->agents);
    }

    public function start(): void
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($socket, '0.0.0.0', $this->port);
        socket_listen($socket);

        echo "\n\033[1;32m[Server] Slimbot Agent Server started on port {$this->port}\033[0m\n";
        echo "\033[0;90mListening for connections...\033[0m\n";

        while (true) {
            $read = [$socket];
            $write = null;
            $except = null;
            $changed = socket_select($read, $write, $except, 5);

            $this->worker->tick();

            if ($changed === false) {
                echo "\033[1;31m[Server] socket_select error\033[0m\n";
                continue;
            }

            if ($changed === 0)
                continue;

            $client = socket_accept($socket);
            if ($client === false)
                continue;

            $input = socket_read($client, 1024 * 10);
            if (!$input) {
                socket_close($client);
                continue;
            }

            $data = json_decode($input, true);
            if (!$data || !isset($data['action'])) {
                $this->sendResponse($client, ['error' => 'Invalid request']);
                socket_close($client);
                continue;
            }

            $response = $this->handleAction($data);
            $this->sendResponse($client, $response);
            socket_close($client);
        }
    }

    private function handleAction(array $data): array
    {
        $action = $data['action'];
        $sessionId = $data['session_id'] ?? 'default';

        if (!isset($this->agents[$sessionId])) {
            echo "\033[1;34m[Server] Initializing Agent for session:\033[0m \033[1;37m{$sessionId}\033[0m\n";
            $this->agents[$sessionId] = AgentFactory::create($this->projectRoot, $sessionId);
        }

        /** @var Agent $agent */
        $agent = $this->agents[$sessionId];

        echo "\n\033[1;34m[Server] Action:\033[0m \033[1;37m{$action}\033[0m (Session: \033[1;32m{$sessionId}\033[0m)\n";
        echo str_repeat("-", 45) . "\n";

        switch ($action) {
            case 'chat':
                $message = $data['message'] ?? '';
                $imagePath = $data['image_path'] ?? null;
                echo "\033[1;37m[User]:\033[0m " . $message . "\n";
                $response = $agent->chat($message, $imagePath);
                return ['response' => $response];

            case 'get_history':
                $history = array_filter($agent->getHistory(), function ($msg) {
                    return $msg['role'] !== 'system' && $msg['role'] !== 'tool' && !empty($msg['content']);
                });
                return ['history' => array_values($history)];

            case 'new_messages':
                $since = (int) ($data['since'] ?? 0);
                $history = array_filter($agent->getHistory(), function ($msg) {
                    return $msg['role'] !== 'system' && $msg['role'] !== 'tool' && !empty($msg['content']);
                });
                $all = array_values($history);
                $newMessages = array_slice($all, $since);
                return ['messages' => $newMessages, 'total' => count($all)];

            case 'status':
            case 'ping':
                return ['status' => 'ok', 'session_id' => $sessionId];

            default:
                return ['error' => "Unknown action: $action"];
        }
    }

    private function sendResponse($client, array $response): void
    {
        $json = json_encode($response);
        socket_write($client, $json, strlen($json));
    }
}
