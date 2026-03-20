<?php

namespace Slimbot\Tools;

class ExecTool implements ToolInterface
{
    public function getName(): string
    {
        return 'exec';
    }

    public function getDescription(): string
    {
        return 'Execute a shell command';
    }

    public function getParameters(): array
    {
        return [
            'command' => [
                'type' => 'string',
                'description' => 'The shell command to run',
            ]
        ];
    }

    public function execute(array $args): string
    {
        $command = $args['command'];
        $output = shell_exec($command . ' 2>&1');
        return $output ?: "Command executed (no output).";
    }
}
