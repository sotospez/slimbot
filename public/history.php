<?php
/**
 * Lightweight proxy — all requests go through the running AgentServer.
 * No Agent instances are created here.
 */

header('Content-Type: application/json');

function queryServer(array $payload): ?array
{
    $host = '127.0.0.1';
    $port = 8080;
    $socket = @fsockopen($host, $port, $errno, $errstr, 2);
    if (!$socket) {
        return null;
    }
    fwrite($socket, json_encode($payload));
    $response = '';
    while (!feof($socket)) {
        $response .= fgets($socket, 4096);
    }
    fclose($socket);
    return json_decode($response, true);
}

$sessionId = $_GET['sessionId'] ?? $_POST['sessionId'] ?? 'web_session';

$result = queryServer([
    'action' => 'get_history',
    'session_id' => $sessionId,
]);

if ($result === null) {
    echo json_encode(['error' => 'Server not running']);
    exit;
}

echo json_encode($result);
