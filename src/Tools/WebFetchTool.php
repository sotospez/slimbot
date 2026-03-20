<?php

namespace Slimbot\Tools;

use GuzzleHttp\Client;

class WebFetchTool implements ToolInterface
{
    public function getName(): string
    {
        return 'web_fetch';
    }

    public function getDescription(): string
    {
        return 'Fetch a web page and extract its text content';
    }

    public function getParameters(): array
    {
        return [
            'url' => [
                'type' => 'string',
                'description' => 'The URL to fetch',
            ]
        ];
    }

    public function execute(array $args): string
    {
        $url = $args['url'];
        $client = new Client(['timeout' => 10, 'allow_redirects' => true]);
        try {
            $response = $client->get($url, [
                'headers' => ['User-Agent' => 'Mozilla/5.0 (Slimbot PHP Assistant)']
            ]);
            $html = (string) $response->getBody();

            $text = $html;
            $text = preg_replace('/<script\b[^>]*>([\s\S]*?)<\/script>/i', '', $text);
            $text = preg_replace('/<style\b[^>]*>([\s\S]*?)<\/style>/i', '', $text);

            $text = preg_replace_callback('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>([\s\S]*?)<\/a>/i', function ($m) {
                return "[" . strip_tags($m[2]) . "](" . $m[1] . ")";
            }, $text);

            for ($i = 6; $i >= 1; $i--) {
                $text = preg_replace('/<h' . $i . '[^>]*>([\s\S]*?)<\/h' . $i . '>/i', "\n" . str_repeat('#', $i) . " $1\n", $text);
            }

            $text = strip_tags($text);
            $text = html_entity_decode($text);
            $text = preg_replace('/[ \t]+/', ' ', $text);
            $text = preg_replace('/\n{3,}/', "\n\n", $text);

            $text = trim($text);
            if (strlen($text) > 5000) {
                $text = substr($text, 0, 5000) . "... [Content truncated]";
            }

            return $text ?: "Could not extract content from $url";
        } catch (\Exception $e) {
            return "Error fetching URL: " . $e->getMessage();
        }
    }
}
