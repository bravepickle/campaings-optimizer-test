<?php

namespace AppTest;

use Event;
use PHPUnit\Framework\TestCase;

class EventsDataSourceTest extends TestCase
{
    public function provideItems(): array
    {
        return [
            [
                'source' => __DIR__ . '/input/events.csv',
                [
                    new Event('install', 1, 10, 1709251200,),
                    new Event('purchase', 1, 10, 1709251300,),
                    new Event('install', 1, 12, 1709251400,),
                ]
            ]
        ];
    }

    /**
     * @dataProvider provideItems
     * @return void
     */
    public function testGetList(string $sourcePath, array $expectedItems)
    {
//        var_dump($expectedItems);
//        die("\n".__METHOD__.':'.__LINE__.':'.__FILE__.PHP_EOL);

        $this->assertFileExists($sourcePath);
//        $job = new OptimizationJob($sourcePath);

        $dataSource = new \EventsDataSource($sourcePath);

        /**
         * @var int $key
         * @var Event $actualCampaign
         */
        foreach ($dataSource->getEventsSince('February 1st, 2024') as $key => $actual) {
            $this->assertArrayHasKey($key, $expectedItems);
            /** @var Event $expected */
            $expected = $expectedItems[$key];

            $this->assertInstanceOf(Event::class, $expected);
            $this->assertEquals($expected->type, $actual->type, 'Type not equals');
            $this->assertEquals($expected->campaignId, $actual->campaignId, 'Campaign not equals');
            $this->assertEquals($expected->publisherId, $actual->publisherId, 'Publisher not equals');
            $this->assertEquals($expected->ts, $actual->ts, 'Timestamp not equals');
        }
    }

    public function testNoFileSource()
    {
        $tmpFile = '/tmp/file_not_exists.txt';
        $this->expectExceptionMessage('File not found: ' . $tmpFile);

        $iterator = (new \EventsDataSource($tmpFile))->getEventsSince('today');

        foreach ($iterator as $item) {
            $this->fail('Not expected to iterate over not existing file');
        }
    }
}
