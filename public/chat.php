<?php
/**
 * Chat endpoint — sends messages through the running AgentServer.
 * Handles multipart/form-data for image uploads.
 */

require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json');

function queryServer(array $payload): ?array
{
    $host = '127.0.0.1';
    $port = 8080;
    $socket = @fsockopen($host, $port, $errno, $errstr, 30); // Longer timeout for chat
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

// Get message from POST or JSON body
$message = $_POST['message'] ?? '';
$sessionId = $_POST['sessionId'] ?? 'web_session';
$imagePath = null;

if (empty($message)) {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $message = $input['message'] ?? '';
        $sessionId = $input['sessionId'] ?? 'web_session';
    }
}

// Handle image upload
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploadsDir = __DIR__ . '/../workspace/uploads';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    $filename = time() . '_' . basename($_FILES['image']['name']);
    $imagePath = $uploadsDir . '/' . $filename;
    move_uploaded_file($_FILES['image']['tmp_name'], $imagePath);
}

if (empty($message)) {
    echo json_encode(['error' => 'Empty message']);
    exit;
}

$result = queryServer([
    'action' => 'chat',
    'message' => $message,
    'session_id' => $sessionId,
    'image_path' => $imagePath,
]);

if ($result === null) {
    // Fallback: create agent directly if server is not running
    try {
        $projectRoot = realpath(__DIR__ . '/../');
        $agent = \Slimbot\AgentFactory::create($projectRoot, $sessionId);
        $response = $agent->chat($message, $imagePath);
        echo json_encode(['response' => $response]);
    } catch (\Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode($result);
