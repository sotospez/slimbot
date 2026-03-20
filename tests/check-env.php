#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$projectRoot = dirname(__DIR__);
$dotenv = Dotenv::createImmutable($projectRoot);
$dotenv->safeLoad();

$providers = ['OPENAI', 'GOOGLE', 'CLAUDE'];

echo "=== Environment Check ===\n\n";

foreach ($providers as $provider) {
    $key = $provider . '_API_KEY';
    $value = $_ENV[$key] ?? null;

    if ($value) {
        $masked = substr($value, 0, 10) . '...' . substr($value, -4);
        echo "✅ {$key}: {$masked}\n";
    } else {
        echo "❌ {$key}: NOT SET\n";
    }
}

echo "\nAI_PROVIDER: " . ($_ENV['AI_PROVIDER'] ?? 'NOT SET') . "\n";
echo "AI_MODEL: " . ($_ENV['AI_MODEL'] ?? 'NOT SET') . "\n";
echo "OLLAMA_HOST: " . ($_ENV['OLLAMA_HOST'] ?? 'NOT SET') . "\n";

