<?php

namespace Slimbot\Tools;

class EditFileTool implements ToolInterface
{
    public function getName(): string
    {
        return 'edit_file';
    }

    public function getDescription(): string
    {
        return 'Replace a specific block of text in a file';
    }

    public function getParameters(): array
    {
        return [
            'path' => [
                'type' => 'string',
                'description' => 'The path to the file',
            ],
            'target' => [
                'type' => 'string',
                'description' => 'The exact text to find',
            ],
            'replacement' => [
                'type' => 'string',
                'description' => 'The text to replace it with',
            ]
        ];
    }

    public function execute(array $args): string
    {
        $path = $args['path'];
        $target = $args['target'];
        $replacement = $args['replacement'];

        if (!file_exists($path))
            return "Error: File not found.";

        $content = file_get_contents($path);
        if (strpos($content, $target) === false) {
            return "Error: Target content not found in file.";
        }

        $newContent = str_replace($target, $replacement, $content);
        file_put_contents($path, $newContent);
        return "File edited successfully.";
    }
}
