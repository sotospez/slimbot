<?php

namespace Slimbot\Tools;

class WriteFileTool implements ToolInterface
{
    private string $projectRoot;

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = $projectRoot;
    }

    public function getName(): string
    {
        return 'write_file';
    }

    public function getDescription(): string
    {
        return 'Create or overwrite a file with contents';
    }

    public function getParameters(): array
    {
        return [
            'path' => [
                'type' => 'string',
                'description' => 'The relative or absolute path to the file',
            ],
            'content' => [
                'type' => 'string',
                'description' => 'The full content to write',
            ]
        ];
    }

    public function execute(array $args): string
    {
        $path = $args['path'];
        $content = $args['content'];

        // Resolve absolute path
        $fullPath = (strpos($path, '/') === 0) ? $path : $this->projectRoot . '/' . $path;
        $realRoot = realpath($this->projectRoot);
        $realDir = realpath(dirname($fullPath));

        if (!$realDir || strpos($realDir, $realRoot) !== 0) {
            return "Error: Access denied to path " . $path;
        }

        file_put_contents($fullPath, $content);
        return "File written successfully to " . $path;
    }
}
