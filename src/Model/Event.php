<?php
declare(strict_types=1);

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
