<?php

namespace Slimbot\Tools;

use Slimbot\Agent;

class CreateSkillTool implements ToolInterface
{
    private string $workspacePath;
    private Agent $agent;

    public function __construct(string $workspacePath, Agent $agent)
    {
        $this->workspacePath = $workspacePath;
        $this->agent = $agent;
    }

    public function getName(): string
    {
        return 'create_skill';
    }

    public function getDescription(): string
    {
        return 'Create or update a skill by writing a SKILL.md file and immediately reloading it into memory. Use this when the user asks you to learn a new behavior, adopt a new response style, or add a new capability.';
    }

    public function getParameters(): array
    {
        return [
            'name' => [
                'type' => 'string',
                'description' => 'Skill slug name (lowercase, hyphens allowed, e.g. "formatted-code", "friendly-greek")',
            ],
            'content' => [
                'type' => 'string',
                'description' => 'Full content of the SKILL.md file. Use markdown with a YAML front-matter block (---\nname: ...\ndescription: ...\n---) followed by instructions.',
            ],
        ];
    }

    public function execute(array $args): string
    {
        $name = preg_replace('/[^a-z0-9\-]/', '', strtolower($args['name'] ?? ''));
        $content = $args['content'] ?? '';

        if (empty($name)) {
            return 'Error: Skill name is required (lowercase letters, numbers, hyphens only).';
        }

        if (empty($content)) {
            return 'Error: Skill content is required.';
        }

        $skillDir = $this->workspacePath . '/skills/' . $name;

        if (!is_dir($skillDir)) {
            mkdir($skillDir, 0755, true);
        }

        $skillFile = $skillDir . '/SKILL.md';
        $isUpdate = file_exists($skillFile);

        file_put_contents($skillFile, $content);

        // Immediately reload the system prompt so the new skill takes effect
        $this->agent->reloadSystemPrompt();

        $action = $isUpdate ? 'updated' : 'created';
        return "Skill '{$name}' {$action} successfully and loaded into memory. It is now active.";
    }
}
