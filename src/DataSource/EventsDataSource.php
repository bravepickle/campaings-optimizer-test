<?php
declare(strict_types=1);

class EventsDataSource
{
    public const string DEFAULT_PATH = __DIR__ . '/../../input/events.csv';

    public function __construct(private readonly string $path = self::DEFAULT_PATH)
    {
    }

    public function getEventsSince(string $startDate): Generator
    {
        if (!file_exists($this->path)) {
            throw new RuntimeException('File not found: ' . $this->path);
        }

        $fh = fopen($this->path, 'rb');

        try {
            fgets($fh); // skip first line - contains csv headers

            $startTs = strtotime($startDate);

            while($row = fgetcsv($fh)) {
                [$type, $campaignId, $publisherId, $timestamp] = $row;
                $timestamp = (int)$timestamp;

                if ($timestamp >= $startTs) {
                    yield new Event($type, (int)$campaignId, (int)$publisherId, $timestamp);
                }
            }
        } finally {
            fclose($fh);
        }
    }
}
