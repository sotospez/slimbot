<?php

namespace Slimbot\Tools;

class ListFilesTool implements ToolInterface
{
    public function getName(): string
    {
        return 'list_files';
    }

    public function getDescription(): string
    {
        return 'List files in a directory';
    }

    public function getParameters(): array
    {
        return [
            'directory' => [
                'type' => 'string',
                'description' => 'The directory to list files from',
            ]
        ];
    }

    public function execute(array $args): string
    {
        $dir = $args['directory'] ?? '.';
        if (!is_dir($dir))
            return "Error: Directory not found.";
        $files = scandir($dir);
        return implode("\n", $files);
    }
}
