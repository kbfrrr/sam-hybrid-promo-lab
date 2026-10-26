<?php
declare(strict_types=1);

namespace Lab;

use InvalidArgumentException;
use RuntimeException;

final class ActivityLogger
{
    private const METADATA_KEYS = [
        'requestId', 'campaignId', 'customerId', 'messageId',
        'phase', 'status', 'receiveCount', 'errorType',
    ];

    public function __construct(private string $path = __DIR__ . '/../logs/events.jsonl') {}

    public function record(string $event, array $metadata = []): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/D', $event)) {
            throw new InvalidArgumentException('Invalid operational event name.');
        }
        $entry = ['timestamp' => gmdate('Y-m-d\TH:i:s\Z'), 'event' => $event];
        foreach (self::METADATA_KEYS as $key) {
            if (isset($metadata[$key]) && is_scalar($metadata[$key])) {
                $entry[$key] = $metadata[$key];
            }
        }
        // Do not log customer names, email addresses, payloads, or raw exception messages.
        $directory = dirname($this->path);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create the operational log directory.');
        }
        $line = json_encode($entry, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        if (file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Could not append to the operational log.');
        }
    }
}
