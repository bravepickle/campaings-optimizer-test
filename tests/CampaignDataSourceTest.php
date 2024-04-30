<?php

namespace AppTest;

use Campaign;
use PHPUnit\Framework\TestCase;

class CampaignDataSourceTest extends TestCase
{
    public function provideItems(): array
    {
        return [
            [
                'source' => __DIR__ . '/input/campaigns.csv',
                [
                    new Campaign(
                        1,
                        (new \OptimizationProps('install', 'purchase', 100, '1.5')),
                        \SplFixedArray::fromArray([1, 3], false),
                    ),
                    new Campaign(
                        2,
                        (new \OptimizationProps('install', 'update', 200, '2')),
                        new \SplFixedArray(),
                    ),
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

        $dataSource = new \CampaignDataSource($sourcePath);

        /**
         * @var int $key
         * @var Campaign $actual
         */
        foreach ($dataSource->getCampaigns() as $key => $actual) {
            $this->assertArrayHasKey($key, $expectedItems);
            /** @var Campaign $expected */
            $expected = $expectedItems[$key];

            $this->assertInstanceOf(Campaign::class, $expected);
            $this->assertEquals($expected->id, $actual->id, 'ID not equals');
            $this->assertEquals($expected->getBlackList()->toArray(), $actual->getBlackList()->toArray(), 'Blacklist not equals');

            $this->assertEquals(
                $expected->optProps->sourceEvent,
                $actual->optProps->sourceEvent,
                'Prop sourceEvent not equals'
            );
            $this->assertEquals(
                $expected->optProps->measuredEvent,
                $actual->optProps->measuredEvent,
                'Prop measuredEvent not equals'
            );
            $this->assertEquals(
                $expected->optProps->threshold,
                $actual->optProps->threshold,
                'Prop threshold not equals'
            );
            $this->assertEquals(
                $expected->optProps->ratioThreshold,
                $actual->optProps->ratioThreshold,
                'Prop ratioThreshold not equals'
            );

//            $this->assertObjectEquals($expectedItems[$key], $actualCampaign);
        }

//        var_dump($expectedItems[0]);

//        $this->assertTrue(true);
    }

    public function testNoFileSource()
    {
        $tmpFile = '/tmp/file_not_exists.txt';
        $this->expectExceptionMessage('File not found: ' . $tmpFile);

        $iterator = (new \CampaignDataSource($tmpFile))->getCampaigns();

        foreach ($iterator as $item) {
            $this->fail('Not expected to iterate over not existing file');
        }
    }
}
