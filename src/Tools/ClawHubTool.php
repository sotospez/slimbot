<?php

namespace Slimbot\Tools;

class ClawHubTool implements ToolInterface
{
    private string $workspacePath;

    public function __construct(string $workspacePath)
    {
        $this->workspacePath = $workspacePath;
    }

    public function getName(): string
    {
        return 'clawhub';
    }

    public function getDescription(): string
    {
        return 'Search and install skills from ClawHub.ai registry.';
    }

    public function getParameters(): array
    {
        return [
            'action' => [
                'type' => 'string',
                'enum' => ['search', 'install', 'list'],
                'description' => 'The action to perform',
            ],
            'query' => [
                'type' => 'string',
                'description' => 'Search term or skill slug for installation',
            ]
        ];
    }

    public function execute(array $args): string
    {
        $action = $args['action'];
        $query = $args['query'] ?? '';
        $skillsPath = $this->workspacePath . '/skills';

        if (!is_dir($skillsPath)) {
            mkdir($skillsPath, 0755, true);
        }

        switch ($action) {
            case 'search':
                return shell_exec("npx --yes clawhub@latest search \"$query\" --limit 5 2>&1") ?: "No results found.";
            case 'install':
                $output = shell_exec("npx --yes clawhub@latest install \"$query\" --workdir \"{$this->workspacePath}\" 2>&1");
                return $output ?: "Skill $query installed.";
            case 'list':
                return shell_exec("npx --yes clawhub@latest list --workdir \"{$this->workspacePath}\" 2>&1") ?: "No skills installed.";
            default:
                return "Invalid action: $action";
        }
    }
}
