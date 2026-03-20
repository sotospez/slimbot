#!/usr/bin/env php
<?php

echo "Starting test...\n";

require_once __DIR__ . '/../vendor/autoload.php';

echo "Autoload OK\n";

use Dotenv\Dotenv;

echo "Dotenv loaded\n";

$projectRoot = dirname(__DIR__);
$dotenv = Dotenv::createImmutable($projectRoot);
$dotenv->safeLoad();

echo "Environment loaded\n";
echo "AI_PROVIDER: " . ($_ENV['AI_PROVIDER'] ?? 'not set') . "\n";

use Slimbot\AgentFactory;

echo "AgentFactory loaded\n";

try {
    $provider = AgentFactory::createProvider('openai');
    echo "Provider created: " . $provider->getName() . "\n";
    echo "Default model: " . $provider->getDefaultModel() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Test complete\n";

