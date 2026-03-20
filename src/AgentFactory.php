<?php

namespace Slimbot;

use Slimbot\Tools;
use Slimbot\Providers;
use Dotenv\Dotenv;

class AgentFactory
{
    public static function create(string $projectRoot, string $sessionId = 'default'): Agent
    {
        $dotenv = Dotenv::createImmutable($projectRoot);
        $dotenv->safeLoad();

        // Determine AI provider
        $providerName = strtolower($_ENV['AI_PROVIDER'] ?? 'openai');
        $model = $_ENV['AI_MODEL'] ?? null;

        $provider = self::createProvider($providerName);

        $workspacePath = $projectRoot . '/workspace';
        $agent = new Agent($provider, $model ?: null, $workspacePath);
        $agent->setSession($sessionId);

        // Register tools
        $agent->registerToolObject(new Tools\ListFilesTool());
        $agent->registerToolObject(new Tools\ListSkillsTool($workspacePath));
        $agent->registerToolObject(new Tools\CreateSkillTool($workspacePath, $agent));
        $agent->registerToolObject(new Tools\ReloadSkillsTool($workspacePath, $agent));
        $agent->registerToolObject(new Tools\MemoryManagementTool($workspacePath));
        $agent->registerToolObject(new Tools\WriteFileTool($projectRoot));
        $agent->registerToolObject(new Tools\EditFileTool());
        $agent->registerToolObject(new Tools\ExecTool());
        $agent->registerToolObject(new Tools\WebSearchTool());
        $agent->registerToolObject(new Tools\WebFetchTool());
        $agent->registerToolObject(new Tools\DuckDuckGoSearchTool());
        $agent->registerToolObject(new Tools\ClawHubTool($workspacePath));
        $agent->registerToolObject(new Tools\HistoryTool($workspacePath));

        // Multimodal tools (always use OpenAI API key)
        $openaiKey = $_ENV['OPENAI_API_KEY'] ?? null;
        if ($openaiKey) {
            $agent->registerToolObject(new Tools\SpeechToTextTool($openaiKey));
            $agent->registerToolObject(new Tools\TextToSpeechTool($openaiKey, $workspacePath));
            $agent->registerToolObject(new Tools\GenerateImageTool($openaiKey, $workspacePath));
            $agent->registerToolObject(new Tools\ImageVisionTool($openaiKey));
            $agent->registerToolObject(new Tools\CropImageTool($workspacePath));
        }

        return $agent;
    }

    /**
     * Create an LLM provider by name.
     *
     * @param string $name Provider name: openai, google, claude, ollama
     * @return Providers\LLMProviderInterface
     */
    public static function createProvider(string $name): Providers\LLMProviderInterface
    {
        return match ($name) {
            'openai' => new Providers\OpenAIProvider(
                self::requireEnv('OPENAI_API_KEY')
            ),
            'google' => new Providers\GoogleGeminiProvider(
                self::requireEnv('GOOGLE_API_KEY')
            ),
            'claude' => new Providers\ClaudeProvider(
                self::requireEnv('CLAUDE_API_KEY')
            ),
            'ollama' => new Providers\OllamaProvider(
                $_ENV['OLLAMA_HOST'] ?? 'http://localhost:11434'
            ),
            default => throw new \Exception("Unknown AI provider: {$name}. Supported: openai, google, claude, ollama"),
        };
    }

    private static function requireEnv(string $key): string
    {
        $value = $_ENV[$key] ?? null;
        if (!$value) {
            throw new \Exception("{$key} not found in .env");
        }
        return $value;
    }
}
