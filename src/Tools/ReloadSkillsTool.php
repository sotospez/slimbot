<?php

namespace Slimbot\Tools;

use Slimbot\Agent;

class ReloadSkillsTool implements ToolInterface
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
        return 'reload_skills';
    }

    public function getDescription(): string
    {
        return 'Reload all skills into memory by refreshing the system prompt. Use this after skills have been added, modified, or deleted externally (e.g. via write_file or manual editing).';
    }

    public function getParameters(): array
    {
        return [];
    }

    public function execute(array $args): string
    {
        $this->agent->reloadSystemPrompt();

        // List currently loaded skills
        $skillsDir = $this->workspacePath . '/skills';
        $skills = [];

        if (is_dir($skillsDir)) {
            $items = array_diff(scandir($skillsDir), ['.', '..', 'README.md']);
            foreach ($items as $item) {
                if (is_dir($skillsDir . '/' . $item) && file_exists($skillsDir . '/' . $item . '/SKILL.md')) {
                    $skills[] = $item;
                }
            }
        }

        $skillList = empty($skills) ? 'No skills found.' : implode(', ', $skills);
        return "System prompt reloaded successfully. Active skills: {$skillList}";
    }
}
