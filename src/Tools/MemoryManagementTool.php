<?php

namespace Slimbot\Tools;

class MemoryManagementTool implements ToolInterface
{
    private string $memoryDir;
    private const MAX_QUICK_MEMORY_ENTRIES = 50;

    public function __construct(string $workspacePath)
    {
        $this->memoryDir = $workspacePath . '/memory';
    }

    public function getName(): string
    {
        return 'manage_memory';
    }

    public function getDescription(): string
    {
        return 'Structured memory management: save/read/delete/search notes, facts, and manage reminders. Also supports quick memory for fast persistent storage.';
    }

    public function getParameters(): array
    {
        return [
            'action' => [
                'type' => 'string',
                'enum' => [
                    'save_note',
                    'read_notes',
                    'delete_note',
                    'save_fact',
                    'read_facts',
                    'delete_fact',
                    'edit_fact',
                    'quick_save',
                    'read_quick_memory',
                    'search_memory',
                    'add_reminder',
                    'read_reminders',
                    'delete_reminder'
                ],
                'description' => 'The action to perform',
            ],
            'category' => [
                'type' => 'string',
                'description' => 'Category for notes/facts (e.g., "coding", "personal", "project_info")',
            ],
            'content' => [
                'type' => 'string',
                'description' => 'The content to save, the reminder message, or search query',
            ],
            'old_content' => [
                'type' => 'string',
                'description' => 'For edit_fact: the old text to replace',
            ],
            'timestamp' => [
                'type' => 'string',
                'description' => 'Target time for the reminder (e.g., "18:00" or "+10 minutes")',
            ],
            'end_time' => [
                'type' => 'string',
                'description' => 'Optional end time for an event (e.g., "19:00")',
            ],
            'offsets' => [
                'type' => 'array',
                'items' => ['type' => 'integer'],
                'description' => 'Minutes before target time to trigger early alerts (e.g., [10, 30])',
            ]
        ];
    }

    public function execute(array $args): string
    {
        $action = $args['action'];
        $category = preg_replace('/[^a-zA-Z0-9_\-]/', '', $args['category'] ?? 'general');
        $content = $args['content'] ?? '';

        switch ($action) {
            // --- Notes ---
            case 'save_note':
                return $this->saveToFile('notes', $category, $content);
            case 'read_notes':
                return $this->readFromDir('notes', $category);
            case 'delete_note':
                return $this->deleteEntry('notes', $category, $content);

            // --- Facts ---
            case 'save_fact':
                return $this->saveToFile('facts', $category, $content);
            case 'read_facts':
                return $this->readFromDir('facts', $category);
            case 'delete_fact':
                return $this->deleteEntry('facts', $category, $content);
            case 'edit_fact':
                $oldContent = $args['old_content'] ?? '';
                return $this->editEntry('facts', $category, $oldContent, $content);

            // --- Quick Memory (replaces update_memory) ---
            case 'quick_save':
                return $this->quickSave($content);
            case 'read_quick_memory':
                return $this->readQuickMemory();

            // --- Search ---
            case 'search_memory':
                return $this->searchMemory($content);

            // --- Reminders ---
            case 'add_reminder':
                return $this->addReminder($content, $args['timestamp'] ?? '', $args['end_time'] ?? null, $args['offsets'] ?? []);
            case 'read_reminders':
                return $this->listReminders();
            case 'delete_reminder':
                return $this->deleteReminder($content);

            default:
                return "Invalid action: $action";
        }
    }

    // ──────────────────────────────────────────────
    // Notes & Facts (file-based, categorized)
    // ──────────────────────────────────────────────

    private function saveToFile(string $type, string $category, string $content): string
    {
        $dir = $this->memoryDir . '/' . $type;
        if (!is_dir($dir))
            mkdir($dir, 0755, true);

        $path = $dir . '/' . $category . '.md';
        $entry = "\n[" . date('Y-m-d H:i:s') . "]\n" . $content . "\n---";
        file_put_contents($path, $entry, FILE_APPEND);

        return "Saved to $type/$category.md";
    }

    private function readFromDir(string $type, string $category): string
    {
        if ($category === 'all' || $category === 'general') {
            // List all files in type directory
            $dir = $this->memoryDir . '/' . $type;
            if (!is_dir($dir))
                return "No $type found.";

            $files = glob($dir . '/*.md');
            if (empty($files))
                return "No $type found.";

            $output = "--- All $type ---\n";
            foreach ($files as $file) {
                $cat = pathinfo($file, PATHINFO_FILENAME);
                $output .= "\n### $cat\n" . file_get_contents($file) . "\n";
            }
            return $output;
        }

        $path = $this->memoryDir . '/' . $type . '/' . $category . '.md';
        if (!file_exists($path)) {
            return "No $type found for category: $category";
        }
        return "--- $type ($category) ---\n" . file_get_contents($path);
    }

    private function deleteEntry(string $type, string $category, string $search): string
    {
        $path = $this->memoryDir . '/' . $type . '/' . $category . '.md';
        if (!file_exists($path))
            return "No $type found for category: $category";

        $content = file_get_contents($path);
        $entries = preg_split('/\n---\s*/', $content);
        $remaining = [];
        $deleted = false;

        foreach ($entries as $entry) {
            $entry = trim($entry);
            if (empty($entry))
                continue;

            if (!$deleted && stripos($entry, $search) !== false) {
                $deleted = true;
                continue; // Skip this entry (delete it)
            }
            $remaining[] = $entry;
        }

        if (!$deleted)
            return "No matching entry found in $type/$category.";

        if (empty($remaining)) {
            unlink($path);
            return "Deleted entry and removed empty file $type/$category.md";
        }

        file_put_contents($path, implode("\n---\n", $remaining) . "\n---");
        return "Deleted matching entry from $type/$category.md";
    }

    private function editEntry(string $type, string $category, string $oldContent, string $newContent): string
    {
        $path = $this->memoryDir . '/' . $type . '/' . $category . '.md';
        if (!file_exists($path))
            return "No $type found for category: $category";

        $content = file_get_contents($path);

        if (stripos($content, $oldContent) === false)
            return "Could not find the text to edit in $type/$category.md";

        $content = str_ireplace($oldContent, $newContent, $content);
        file_put_contents($path, $content);

        return "Updated entry in $type/$category.md";
    }

    // ──────────────────────────────────────────────
    // Quick Memory (replaces UpdateMemoryTool)
    // ──────────────────────────────────────────────

    private function quickSave(string $content): string
    {
        $path = $this->memoryDir . '/MEMORY.md';

        // Read existing entries
        $lines = file_exists($path) ? file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

        // Add new entry
        $lines[] = date('Y-m-d H:i:s') . ": " . $content;

        // Enforce max entries limit — keep the most recent ones
        if (count($lines) > self::MAX_QUICK_MEMORY_ENTRIES) {
            $lines = array_slice($lines, -self::MAX_QUICK_MEMORY_ENTRIES);
        }

        file_put_contents($path, "\n" . implode("\n", $lines) . "\n");

        $count = count($lines);
        return "Quick memory saved. ({$count}/" . self::MAX_QUICK_MEMORY_ENTRIES . " entries)";
    }

    private function readQuickMemory(): string
    {
        $path = $this->memoryDir . '/MEMORY.md';
        if (!file_exists($path))
            return "No quick memory saved yet.";

        return "--- Quick Memory ---\n" . file_get_contents($path);
    }

    // ──────────────────────────────────────────────
    // Search across all memory
    // ──────────────────────────────────────────────

    private function searchMemory(string $query): string
    {
        $results = [];
        $queryLower = strtolower($query);

        // Search MEMORY.md
        $memoryFile = $this->memoryDir . '/MEMORY.md';
        if (file_exists($memoryFile)) {
            $content = file_get_contents($memoryFile);
            if (stripos($content, $query) !== false) {
                $lines = explode("\n", $content);
                foreach ($lines as $line) {
                    if (stripos($line, $query) !== false) {
                        $results[] = "[Quick Memory] " . trim($line);
                    }
                }
            }
        }

        // Search facts and notes
        foreach (['facts', 'notes'] as $type) {
            $dir = $this->memoryDir . '/' . $type;
            if (!is_dir($dir))
                continue;

            foreach (glob($dir . '/*.md') as $file) {
                $category = pathinfo($file, PATHINFO_FILENAME);
                $content = file_get_contents($file);
                if (stripos($content, $query) !== false) {
                    $entries = preg_split('/\n---\s*/', $content);
                    foreach ($entries as $entry) {
                        if (stripos($entry, $query) !== false) {
                            $results[] = "[$type/$category] " . trim(substr($entry, 0, 200));
                        }
                    }
                }
            }
        }

        if (empty($results))
            return "No memory entries found matching: $query";

        return "--- Search Results for \"$query\" ---\n" . implode("\n\n", $results);
    }

    // ──────────────────────────────────────────────
    // Reminders
    // ──────────────────────────────────────────────

    private function addReminder(string $message, string $timeStr, ?string $endTimeStr = null, array $offsets = []): string
    {
        $startTime = strtotime($timeStr);
        if (!$startTime)
            return "Invalid start time format: $timeStr";

        $endTime = $endTimeStr ? strtotime($endTimeStr) : null;

        $dir = $this->memoryDir . '/reminders';
        if (!is_dir($dir))
            mkdir($dir, 0755, true);

        $reminder = [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'message' => $message,
            'offsets' => $offsets,
            'triggered' => [],
            'created_at' => time()
        ];

        file_put_contents($dir . '/' . uniqid() . '.json', json_encode($reminder));

        $msg = "Reminder set for " . date('Y-m-d H:i:s', $startTime);
        if ($endTime)
            $msg .= " (until " . date('H:i:s', $endTime) . ")";
        if (!empty($offsets))
            $msg .= " with early alerts at " . implode(", ", $offsets) . " mins before.";

        return $msg;
    }

    private function listReminders(): string
    {
        $dir = $this->memoryDir . '/reminders';
        if (!is_dir($dir))
            return "No reminders set.";

        $files = glob($dir . '/*.json');
        if (empty($files))
            return "No active reminders.";

        $output = "--- Active Reminders ---\n";
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (!$data)
                continue;

            $id = pathinfo($file, PATHINFO_FILENAME);
            $day = date('l, M j', $data['start_time']);
            $time = date('H:i', $data['start_time']);
            $end = isset($data['end_time']) ? date('H:i', $data['end_time']) : null;
            $status = !empty($data['triggered']) ? 'DONE 🔔' : 'PENDING ⏰';

            $output .= "- [{$id}] **{$data['message']}**\n  - Date: $day\n  - Time: $time";
            if ($end)
                $output .= " (until $end)";
            $output .= "\n  - Status: **$status**\n";
        }

        return $output;
    }

    private function deleteReminder(string $search): string
    {
        $dir = $this->memoryDir . '/reminders';
        if (!is_dir($dir))
            return "No reminders directory found.";

        $files = glob($dir . '/*.json');
        if (empty($files))
            return "No reminders to delete.";

        // Try to match by ID (filename) first
        foreach ($files as $file) {
            $id = pathinfo($file, PATHINFO_FILENAME);
            if ($id === $search) {
                $data = json_decode(file_get_contents($file), true);
                unlink($file);
                return "Deleted reminder: " . ($data['message'] ?? $id);
            }
        }

        // Otherwise search by message content
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['message']) && stripos($data['message'], $search) !== false) {
                unlink($file);
                return "Deleted reminder: {$data['message']}";
            }
        }

        return "No reminder found matching: $search";
    }
}
