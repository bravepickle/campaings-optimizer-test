<?php

namespace AppTest;

use Notifier;
use OptimizationJob;
use PHPUnit\Framework\TestCase;
use Redis;
use RedisException;

class OptimizationJobTest extends TestCase
{
    /**
     * @return array
     */
    public function provideRun(): array
    {
        return [
            [
                'expectedEvents' => [
                    ['event' => 'sendMailWhitelisted', 'context' => ['campaign' => 1, 'publishers' => [200]]],
                    ['event' => 'sendMailBlacklisted', 'context' => ['campaign' => 1, 'publishers' => [20, 40]]],
                    ['event' => 'saveBlacklist', 'context' => ['campaign' => 1, 'publishers' => [20, 40, 100]]],
                ],
                'campaignsSrc' => 'campaigns_50.csv',
                'eventsSrc' => 'events_50.csv',
            ],
            [
                'expectedEvents' => [
                    ['event' => 'sendMailWhitelisted', 'context' => ['campaign' => 4, 'publishers' => [300]]],
                    ['event' => 'saveBlacklist', 'context' => ['campaign' => 4, 'publishers' => []]],
                ],
                'campaignsSrc' => 'campaigns_threshold.csv',
                'eventsSrc' => 'events_threshold.csv',
            ],
        ];
    }

    /**
     * @param array $expectedEvents
     * @param string $campaignsSrc
     * @param string $eventsSrc
     * @return void
     * @throws RedisException
     * @dataProvider provideRun
     */
    public function testRun(array $expectedEvents, string $campaignsSrc, string $eventsSrc): void
    {
        $conn = new Redis(['host' => 'redis-db']);
        $conn->select(2);

        $eventsHandler = new EventsHandlerStub();
        Notifier::$handler = $eventsHandler;

        $job = new OptimizationJob(
            $conn,
            '2024-02-01 00:00:00',
            __DIR__ . '/input/'.$campaignsSrc,
            __DIR__ . '/input/'.$eventsSrc,
            false,
            2
        );
        $job->run();

        $actualEvents = $eventsHandler->getEvents();

        $this->assertEquals($expectedEvents, $actualEvents, 'Events mismatch');
    }
}
