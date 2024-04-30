<?php
declare(strict_types=1);

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
