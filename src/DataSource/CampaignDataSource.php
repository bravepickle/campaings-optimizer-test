<?php
declare(strict_types=1);

class CampaignDataSource
{
    /**
     * Default path to source file
     */
    public const string DEFAULT_PATH = __DIR__ . '/../../input/campaigns.csv';

    public function __construct(private readonly string $path = self::DEFAULT_PATH)
    {
    }

    public function getCampaigns(): Generator
    {
        if (!file_exists($this->path)) {
            throw new RuntimeException('File not found: ' . $this->path);
        }

        $fh = fopen($this->path, 'rb');
        try {
            fgets($fh); // skip first line - contains csv headers

            $intMapper = static fn($v) => (int)$v;
            while($row = fgetcsv($fh)) {
                [$id, $sourceEvent, $measuredEvent, $threshold, $ratio, $blacklist] = $row;

                $props = new OptimizationProps($sourceEvent, $measuredEvent, (int)$threshold, $ratio);
                $blacklistArr = $blacklist === '' ? [] : array_map($intMapper, explode(',', $blacklist));
                yield new Campaign((int)$id, $props, \SplFixedArray::fromArray($blacklistArr, false));
            }
        } finally {
            fclose($fh);
        }
    }
}
