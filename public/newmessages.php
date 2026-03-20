<?php
/**
 * Lightweight endpoint to check for new messages since a given index.
 * Queries the running AgentServer — no Agent creation.
 */

header('Content-Type: application/json');

$sessionId = $_GET['sessionId'] ?? 'web_session';
$since = (int) ($_GET['since'] ?? 0);

$host = '127.0.0.1';
$port = 8080;
$socket = @fsockopen($host, $port, $errno, $errstr, 2);
if (!$socket) {
    echo json_encode(['messages' => [], 'total' => $since]);
    exit;
}

$data = json_encode([
    'action' => 'new_messages',
    'session_id' => $sessionId,
    'since' => $since,
]);
fwrite($socket, $data);

$response = '';
while (!feof($socket)) {
    $response .= fgets($socket, 4096);
}
fclose($socket);

$result = json_decode($response, true);
echo json_encode($result ?? ['messages' => [], 'total' => $since]);
