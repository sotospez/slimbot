#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Slimbot\AgentFactory;

$projectRoot = dirname(__DIR__);

// Load environment
$dotenv = Dotenv::createImmutable($projectRoot);
$dotenv->safeLoad();

echo "=== Multi-Provider Test ===\n\n";

// Test message
$testMessage = "Hello! Just respond with 'OK' to confirm you're working.";

// Available providers
$providers = [
    'openai' => 'OpenAI',
    'google' => 'Google Gemini',
    'claude' => 'Claude',
    'ollama' => 'Ollama',
];

$results = [];

foreach ($providers as $providerName => $displayName) {
    echo "Testing {$displayName}...\n";

    // Check if API key is set (skip ollama as it doesn't need one)
    if ($providerName !== 'ollama') {
        $envKey = strtoupper($providerName) . '_API_KEY';
        if (empty($_ENV[$envKey])) {
            echo "  ⚠️  Skipped: {$envKey} not set in .env\n\n";
            $results[$displayName] = 'SKIPPED';
            continue;
        }
    }

    try {
        // Create provider directly
        $provider = AgentFactory::createProvider($providerName);

        // Simple test message
        $messages = [
            ['role' => 'user', 'content' => $testMessage]
        ];

        echo "  Provider: {$provider->getName()}\n";
        echo "  Default Model: {$provider->getDefaultModel()}\n";

        // Test completion without tools
        $response = $provider->chatCompletion($messages, [], $provider->getDefaultModel());

        if (isset($response['message']['content']) && !empty($response['message']['content'])) {
            echo "  ✅ SUCCESS\n";
            echo "  Response: " . substr($response['message']['content'], 0, 100) . "...\n";
            $results[$displayName] = 'OK';
        } else {
            echo "  ❌ FAILED: Empty response\n";
            $results[$displayName] = 'FAILED';
        }

    } catch (Exception $e) {
        echo "  ❌ ERROR: " . $e->getMessage() . "\n";
        $results[$displayName] = 'ERROR';
    }

    echo "\n";
}

// Summary
echo "=== Summary ===\n";
foreach ($results as $provider => $status) {
    $icon = match($status) {
        'OK' => '✅',
        'SKIPPED' => '⚠️ ',
        'FAILED' => '❌',
        'ERROR' => '❌',
        default => '❓'
    };
    echo "{$icon} {$provider}: {$status}\n";
}

echo "\n=== Testing Provider Factory ===\n";

// Test that AgentFactory can create agents with different providers
$currentProvider = $_ENV['AI_PROVIDER'] ?? 'openai';
echo "Current AI_PROVIDER in .env: {$currentProvider}\n";

try {
    $agent = AgentFactory::create($projectRoot, 'test_session');
    echo "✅ AgentFactory successfully created agent with {$currentProvider}\n";
} catch (Exception $e) {
    echo "❌ AgentFactory failed: " . $e->getMessage() . "\n";
}

echo "\nTest completed!\n";

