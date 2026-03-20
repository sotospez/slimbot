<?php

namespace Slimbot\Tools;

class CropImageTool implements ToolInterface
{
    private string $imagesDir;

    public function __construct(string $workspacePath)
    {
        $this->imagesDir = $workspacePath . '/images';
        if (!is_dir($this->imagesDir)) {
            mkdir($this->imagesDir, 0755, true);
        }
    }

    public function getName(): string
    {
        return 'crop_image';
    }

    public function getDescription(): string
    {
        return 'Crop an image using the GD library. Use with image_vision to crop specific objects identified by coordinates.';
    }

    public function getParameters(): array
    {
        return [
            'image_path' => [
                'type' => 'string',
                'description' => 'Path to the image file to crop',
            ],
            'x' => [
                'type' => 'integer',
                'description' => 'The X coordinate of the top-left corner',
            ],
            'y' => [
                'type' => 'integer',
                'description' => 'The Y coordinate of the top-left corner',
            ],
            'width' => [
                'type' => 'integer',
                'description' => 'The width of the crop',
            ],
            'height' => [
                'type' => 'integer',
                'description' => 'The height of the crop',
            ]
        ];
    }

    public function execute(array $args): string
    {
        $path = $args['image_path'];
        $x = (int) $args['x'];
        $y = (int) $args['y'];
        $width = (int) $args['width'];
        $height = (int) $args['height'];

        if (!file_exists($path)) {
            return "Error: Image file not found at $path";
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $image = null;

        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $image = imagecreatefromjpeg($path);
                break;
            case 'png':
                $image = imagecreatefrompng($path);
                break;
            case 'webp':
                $image = imagecreatefromwebp($path);
                break;
            default:
                return "Error: Unsupported image format ($extension). Supported: jpg, jpeg, png, webp.";
        }

        if (!$image) {
            return "Error: Could not load image.";
        }

        $cropped = imagecrop($image, ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height]);

        if (!$cropped) {
            imagedestroy($image);
            return "Error: Could not crop image (coordinates likely out of bounds).";
        }

        $filename = 'cropped_' . time() . '_' . uniqid() . '.' . $extension;
        $outputPath = $this->imagesDir . '/' . $filename;

        $saved = false;
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $saved = imagejpeg($cropped, $outputPath);
                break;
            case 'png':
                $saved = imagepng($cropped, $outputPath);
                break;
            case 'webp':
                $saved = imagewebp($cropped, $outputPath);
                break;
        }

        imagedestroy($image);
        imagedestroy($cropped);

        if (!$saved) {
            return "Error: Could not save cropped image.";
        }

        return "Image cropped and saved to: $outputPath";
    }
}
