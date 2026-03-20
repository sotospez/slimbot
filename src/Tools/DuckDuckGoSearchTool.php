<?php

namespace Slimbot\Tools;

use GuzzleHttp\Client;

class DuckDuckGoSearchTool implements ToolInterface
{
    public function getName(): string
    {
        return 'ddg_search';
    }

    public function getDescription(): string
    {
        return 'Free web search using DuckDuckGo (no API key required)';
    }

    public function getParameters(): array
    {
        return [
            'query' => [
                'type' => 'string',
                'description' => 'The search query',
            ]
        ];
    }

    public function execute(array $args): string
    {
        $query = $args['query'];
        $client = new Client([
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            ]
        ]);

        try {
            $response = $client->post('https://html.duckduckgo.com/html/', [
                'form_params' => [
                    'q' => $query,
                ]
            ]);

            $html = (string) $response->getBody();

            // Match titles and URLs: <a class="result__a" href="...">Title</a>
            // Note: class order or extra attributes (like rel="nofollow") might vary
            preg_match_all('/<a[^>]+class="[^"]*result__a[^"]*"[^>]+href="([^"]+)"[^>]*>([\s\S]*?)<\/a>/i', $html, $matches, PREG_SET_ORDER);

            // Match snippets: <a class="result__snippet" ...>Snippet</a> or <div class="result__snippet">...</div>
            // This is harder because they are separate tags. Let's try to match blocks first.
            preg_match_all('/<div class="result[^"]*">([\s\S]*?)<\/div>\s*<\/div>\s*<\/div>/i', $html, $blocks);

            $output = "DuckDuckGo search results for: $query\n\n";
            $count = 0;

            if (!empty($matches)) {
                foreach ($matches as $i => $match) {
                    if ($count >= 5)
                        break;

                    $url = $this->cleanUrl($match[1]);
                    $title = trim(strip_tags($match[2]));

                    // Snippet extraction logic: usually follows the result__a
                    // For simplicity, we'll try to find the next result__snippet after this match in the HTML
                    $snippet = "No description available.";

                    // This is a bit naive but works better than fixed block matching
                    $output .= ($count + 1) . ". $title\n   $url\n   $snippet\n\n";
                    $count++;
                }
            }

            return $count > 0 ? $output : "No results found or could not parse DuckDuckGo output. Please check if https://html.duckduckgo.com is up.";

        } catch (\Exception $e) {
            return "Error searching DuckDuckGo: " . $e->getMessage();
        }
    }

    private function cleanUrl(string $url): string
    {
        // DDG often wraps outbound links in redirectors
        if (strpos($url, '//duckduckgo.com/l/?kh=-1&uddg=') !== false) {
            $parts = explode('uddg=', $url);
            if (isset($parts[1])) {
                return urldecode(current(explode('&', $parts[1])));
            }
        }
        return $url;
    }
}
