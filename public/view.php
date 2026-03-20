<?php
// Secure file proxy to serve files from authorized workspace directories

require_once __DIR__ . '/../vendor/autoload.php';

$path = $_GET['path'] ?? '';
if (empty($path)) {
    http_response_code(400);
    die('Path required');
}

// Normalize and secure: decode, prevent directory traversal, and allow optional workspace prefix
$path = urldecode($path);
$path = str_replace(['../', '..\\'], '', $path);
$path = ltrim($path, '/');
if (str_starts_with($path, 'workspace/')) {
    $path = substr($path, strlen('workspace/'));
}

$workspaceRoot = realpath(__DIR__ . '/../workspace');
$requestedFile = realpath($workspaceRoot . '/' . $path);

// Security: Ensure the file is actually inside the workspace directory
if (!$requestedFile || strpos($requestedFile, $workspaceRoot) !== 0) {
    http_response_code(403);
    die('Access denied');
}

// Security: Only allow specific subdirectories
$allowedDirs = ['images', 'audio', 'uploads'];
$dirName = basename(dirname($requestedFile));
if (!in_array($dirName, $allowedDirs)) {
    http_response_code(403);
    die('Directory not allowed');
}

if (!file_exists($requestedFile)) {
    http_response_code(404);
    die('File not found');
}

$ext = strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION));
$mimeTypes = [
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'gif' => 'image/gif',
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
];

if (!isset($mimeTypes[$ext])) {
    http_response_code(415);
    die('Unsupported file type');
}

header('Content-Type: ' . $mimeTypes[$ext]);
header('Content-Disposition: inline; filename="' . basename($requestedFile) . '"');
header('Accept-Ranges: bytes');
header('Content-Length: ' . filesize($requestedFile));
readfile($requestedFile);
exit;
