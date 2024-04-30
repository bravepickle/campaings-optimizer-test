<?php
declare(strict_types=1);

/** @file src/Contract/EventsHandler.php */
/**
 * Notifications handler interface
 */
interface EventsHandler
{
    /**
     * Handle event processing
     * @param string $eventName
     * @param array $context
     * @return void
     */
    public function handle(string $eventName, array $context = []): void;
}

/** @file src/DataSource/CampaignDataSource.php */
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

/** @file src/DataSource/EventsDataSource.php */
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

/** @file src/Job/OptimizationJob.php */
class OptimizationJob
{
    protected const int MATH_PRECISION = 3;
    protected const string START_DATE = '2 weeks ago';

    /**
     * Redis operations batch size
     */
    protected const int BATCH_SIZE = 100;

    /**
     * @param Redis $conn
     * @param string $startDate
     * @param string $campaignsDataSource
     * @param string $eventsDataSource
     * @param bool $flushDbAsap
     * @param int $batchSize
     */
    public function __construct(
        protected readonly Redis $conn,
        protected readonly string $startDate = self::START_DATE,
        protected readonly string $campaignsDataSource = CampaignDataSource::DEFAULT_PATH,
        protected readonly string $eventsDataSource = EventsDataSource::DEFAULT_PATH,
        protected readonly bool $flushDbAsap = true,
        protected readonly int $batchSize = self::BATCH_SIZE,
    ) {
    }

    /**
     * @return void
     * @throws RedisException
     */
    public function run(): void
    {
        $conn = $this->conn;
        $conn->flushDB();

        try {
            $campaignDS = new CampaignDataSource($this->campaignsDataSource);

            // array of Campaign objects
            $campaigns = $campaignDS->getCampaigns();

            $eventsDS = new EventsDataSource($this->eventsDataSource);
            $eventsIterator = $eventsDS->getEventsSince($this->startDate);

            // aggregate events to calculated values. Similar to materialized views.
            // Performance will be increased due to lessened memory consumption and events calc in PHP
            $batch = [];
            $batchCount = 0;
            foreach ($eventsIterator as $event) {
                if (!isset($batch[$event->campaignId][$event->publisherId][$event->type])) {
                    $batch[$event->campaignId][$event->publisherId][$event->type] = 1;
                    ++$batchCount;
                } else {
                    ++$batch[$event->campaignId][$event->publisherId][$event->type];
                }

                if ($batchCount % $this->batchSize === 0) {
                    $this->batchUpdate($conn, $batch);

                    $batchCount = 0;
                    $batch = [];
                }
            }

            if ($batchCount !== 0) {
                $this->batchUpdate($conn, $batch);
            }

            $this->checkCampaigns($campaigns, $conn);
        } finally {
            if ($this->flushDbAsap) {
                $conn->flushDB();
            }
        }
    }

    /**
     * @param Redis $conn
     * @param array $batch
     * @return void
     * @throws RedisException
     */
    protected function batchUpdate(Redis $conn, array $batch): void
    {
        $multi = $conn->pipeline();

        foreach ($batch as $campaignId => $publishers) {
            foreach ($publishers as $publisherId => $events) {
                foreach ($events as $event => $count) {
                    $multi = $multi->hIncrBy(
                        'ev:'.$campaignId,
                        $event.':'.$publisherId,
                        $count
                    );
                }
            }
        }

        $multi->exec();
    }

    /**
     * @param int $campaignId
     * @param int[] $publisherIds
     * @return void
     */
    protected function sendMailWhitelisted(int $campaignId, array $publisherIds): void
    {
        // TODO: send mail on whitelisted publishers per campaign
        Notifier::notify('sendMailWhitelisted', ['campaign' => $campaignId, 'publishers' => $publisherIds]);
    }

    /**
     * @param int $campaignId
     * @param int[] $publisherIds
     * @return void
     */
    protected function sendMailBlacklisted(int $campaignId, array $publisherIds): void
    {
        // TODO: send mail on blacklisted publishers per campaign
        Notifier::notify('sendMailBlacklisted', ['campaign' => $campaignId, 'publishers' => $publisherIds]);
    }

    /**
     * @param Campaign $campaign
     * @param int[] $newBlacklist
     * @param int[] $newWhitelist
     * @return void
     */
    protected function processBlacklistResults(Campaign $campaign, array $newBlacklist, array $newWhitelist): void
    {
        $prevBlacklist = $campaign->getBlackList()->toArray();
        $blacklistChanged = false;

        if ($newWhitelist) {
            $this->sendMailWhitelisted($campaign->id, $newWhitelist);
            $blacklistChanged = true;
        }

        $blacklistedNewPub = array_diff($newBlacklist, $prevBlacklist);
        if ($blacklistedNewPub) {
            $this->sendMailBlacklisted($campaign->id, $blacklistedNewPub);
            $blacklistChanged = true;
        }

        if ($blacklistChanged) {
            $campaign->saveBlacklist($newBlacklist);
            Notifier::notify('saveBlacklist', ['campaign' => $campaign->id, 'publishers' => $newBlacklist]);
        }
    }

    /**
     * @param Campaign $campaign
     * @param array $sourceFields
     * @param array $measureFields
     * @param array $newBlacklist
     * @param array $newWhitelist
     * @return void
     */
    protected function checkThresholdForFields(
        Campaign $campaign,
        array $sourceFields,
        array $measureFields,
        array &$newBlacklist,
        array &$newWhitelist,
    ): void {
        $measured = [];
        foreach ($measureFields as $measureField => $measureCount) {
            $publisherId = (int)substr($measureField, strrpos($measureField, ':') + 1);
            $measured[$publisherId] = $measureCount;
        }

        $prevBlacklist = $campaign->getBlackList()->toArray();
        foreach ($sourceFields as $srcField => $srcCount) {
            // blacklist toggling is allowed only if threshold reached
            if ((int)$srcCount >= $campaign->optProps->threshold) {
                $publisherId = (int)substr($srcField, strrpos($srcField, ':') + 1);

                if (!isset($measured[$publisherId])) {
                    $newBlacklist[] = $publisherId;
                } else {
                    $realRatio = bcdiv($measured[$publisherId], $srcCount, self::MATH_PRECISION);

                    if (bccomp($campaign->optProps->ratioThreshold, $realRatio, self::MATH_PRECISION) === 1) {
                        $newBlacklist[] = $publisherId;
                    } elseif(in_array($publisherId, $prevBlacklist, true)) {
                        $newWhitelist[] = $publisherId;
                    }
                }
            }
        }
    }

    /**
     * @param Generator $campaigns
     * @param Redis $conn
     * @return void
     * @throws RedisException
     */
    protected function checkCampaigns(Generator $campaigns, Redis $conn): void
    {
        foreach ($campaigns as $campaign) {
            $newBlacklist = $newWhitelist = [];
            $srcIterator = $measureIterator = null;

            do {
                // search only source events
                $itemKey = 'ev:'.$campaign->id;
                $this->checkThresholdForFields(
                    $campaign,
                    $conn->hScan(
                        $itemKey,
                        $srcIterator,
                        $campaign->optProps->sourceEvent.':*'
                    ),
                    $conn->hScan(
                        $itemKey,
                        $measureIterator,
                        $campaign->optProps->measuredEvent.':*'
                    ),
                    $newBlacklist,
                    $newWhitelist
                );
            } while ($srcIterator);

            $this->processBlacklistResults($campaign, $newBlacklist, $newWhitelist);
        }
    }
}

/** @file src/Model/Campaign.php */
class Campaign
{
    /**
     * @param int $id
     * @param OptimizationProps $optProps
     * @param SplFixedArray $publisherBlacklist
     */
    public function __construct(
        public readonly int $id,
        public readonly OptimizationProps $optProps,
        private SplFixedArray $publisherBlacklist,
    ) {
    }

    public function getBlackList(): SplFixedArray
    {
        return $this->publisherBlacklist;
    }

    /**
     * @codeCoverageIgnore
     */
    public function saveBlacklist($blacklist): void
    {
        // dont implement
    }
}

/** @file src/Model/Event.php */
class Event
{
    public function __construct(
        public readonly string $type,
        public readonly int $campaignId,
        public readonly int $publisherId,
        public readonly int $ts,
    ) {
    }
}

/** @file src/Model/OptimizationProps.php */
class OptimizationProps
{
    public function __construct(
        public readonly string $sourceEvent,
        public readonly string $measuredEvent,
        public int $threshold,
        public readonly string $ratioThreshold,
    )
    {
    }
}

/** @file src/Service/Notifier.php */
/**
 * Dispatcher for events and handling - simple wrapper
 * Is useful for tests and debugging only. Do not use in production
 */
class Notifier
{
    public static EventsHandler|null $handler = null;

    /**
     * Notify on event
     * @param string $eventName
     * @param array $context
     * @return void
     */
    public static function notify(string $eventName, array $context = []): void
    {
        // handle events only if events handler specified. Otherwise skip
        self::$handler?->handle($eventName, $context);
    }
}
