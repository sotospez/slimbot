<?php

namespace Slimbot\Tools;

use GuzzleHttp\Client;

class WebSearchTool implements ToolInterface
{
    public function getName(): string
    {
        return 'web_search';
    }

    public function getDescription(): string
    {
        return 'Search the web for information';
    }

    public function getParameters(): array
    {
        return [
            'query' => [
                'type' => 'string',
                'description' => 'The search query',
            ],
            'count' => [
                'type' => 'integer',
                'description' => 'Number of results (default: 5)',
            ]
        ];
    }

    public function execute(array $args): string
    {
        $apiKey = $_ENV['BRAVE_API_KEY'] ?? null;
        if (!$apiKey)
            return "Error: BRAVE_API_KEY not found in .env";

        $query = $args['query'];
        $count = $args['count'] ?? 5;

        $client = new Client();
        try {
            $response = $client->get('https://api.search.brave.com/res/v1/web/search', [
                'query' => ['q' => $query, 'count' => $count],
                'headers' => [
                    'Accept' => 'application/json',
                    'X-Subscription-Token' => $apiKey,
                ]
            ]);
            $data = json_decode($response->getBody(), true);
            $results = $data['web']['results'] ?? [];

            if (empty($results))
                return "No results found for '$query'.";

            $output = "Search results for: $query\n";
            foreach ($results as $i => $result) {
                $output .= ($i + 1) . ". " . $result['title'] . "\n   " . $result['url'] . "\n   " . ($result['description'] ?? '') . "\n\n";
            }
            return $output;
        } catch (\Exception $e) {
            return "Error searching web: " . $e->getMessage();
        }
    }
}
