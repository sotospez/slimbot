<?php

namespace Slimbot\Tools;

class ListSkillsTool implements ToolInterface
{
    private string $workspacePath;

    public function __construct(string $workspacePath)
    {
        $this->workspacePath = $workspacePath;
    }

    public function getName(): string
    {
        return 'list_skills';
    }

    public function getDescription(): string
    {
        return 'List available skills in the workspace';
    }

    public function getParameters(): array
    {
        return [];
    }

    public function execute(array $args): string
    {
        $skillsDir = $this->workspacePath . '/skills';
        if (!is_dir($skillsDir))
            return "Skills directory not found.";
        $skills = array_diff(scandir($skillsDir), ['.', '..', 'README.md']);
        return empty($skills) ? "No skills installed." : "Available skills: " . implode(", ", $skills);
    }
}
