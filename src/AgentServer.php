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

        echo "Agent server started on port {$this->port} (with background worker)...\n";

        while (true) {
            // Use socket_select to wait for connections with a timeout
            $read = [$socket];
            $write = null;
            $except = null;
            $changed = socket_select($read, $write, $except, 5); // 5-second timeout

            // Run background tasks during every iteration (throttled internally)
            $this->worker->tick();

            if ($changed === false) {
                echo "socket_select error\n";
                continue;
            }

            if ($changed === 0) {
                // Timeout, no new connections — just loop back
                continue;
            }

            $client = socket_accept($socket);
            if ($client === false)
                continue;

            $input = socket_read($client, 1024 * 10); // 10KB buffer
            if (!$input) {
                socket_close($client);
                continue;
            }

            $data = json_decode($input, true);
            if (!$data || !isset($data['action'])) {
                $this->sendResponse($client, ['error' => 'Invalid request']);
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
            echo "Initializing agent for session: $sessionId\n";
            $this->agents[$sessionId] = AgentFactory::create($this->projectRoot, $sessionId);
        }

        /** @var Agent $agent */
        $agent = $this->agents[$sessionId];

        switch ($action) {
            case 'chat':
                $message = $data['message'] ?? '';
                $imagePath = $data['image_path'] ?? null;
                $response = $agent->chat($message, $imagePath);
                return ['response' => $response];

            case 'get_history':
                $history = array_filter($agent->getHistory(), function ($msg) {
                    return $msg['role'] !== 'system'
                        && $msg['role'] !== 'tool'
                        && !empty($msg['content']);
                });
                return ['history' => array_values($history)];

            case 'new_messages':
                $since = (int) ($data['since'] ?? 0);
                $history = array_filter($agent->getHistory(), function ($msg) {
                    return $msg['role'] !== 'system'
                        && $msg['role'] !== 'tool'
                        && !empty($msg['content']);
                });
                $all = array_values($history);
                $newMessages = array_slice($all, $since);
                return ['messages' => $newMessages, 'total' => count($all)];

            case 'status':
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
