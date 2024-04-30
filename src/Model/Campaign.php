<?php

declare(strict_types=1);

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
