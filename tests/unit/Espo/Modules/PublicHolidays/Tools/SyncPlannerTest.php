<?php

namespace tests\unit\Espo\Modules\PublicHolidays\Tools;

use Espo\Modules\PublicHolidays\Tools\Holiday;
use Espo\Modules\PublicHolidays\Tools\HolidayProvider;
use Espo\Modules\PublicHolidays\Tools\SyncPlanner;
use Espo\Modules\PublicHolidays\Tools\UnsupportedRegion;
use PHPUnit\Framework\TestCase;

class SyncPlannerTest extends TestCase
{
    private function createProvider(): HolidayProvider
    {
        return new class implements HolidayProvider {
            public function getHolidays(string $region, int $year): array
            {
                if ($region !== 'XX') {
                    throw UnsupportedRegion::create($region);
                }

                return [
                    new Holiday($region, 'newYear', 'New Year', "$year-01-01"),
                    new Holiday($region, 'midYear', 'Mid Year', "$year-06-15"),
                    new Holiday($region, 'christmas', 'Christmas', "$year-12-25"),
                ];
            }

            public function isSupported(string $region): bool
            {
                return $region === 'XX';
            }
        };
    }

    public function testFromTodayUntilEndOfNextYear(): void
    {
        $planner = new SyncPlanner($this->createProvider());

        $result = $planner->getDesiredHolidays('XX', [], '2026-11-18', 1);

        $this->assertSame([
            'XX:2026-12-25:christmas',
            'XX:2027-01-01:newYear',
            'XX:2027-06-15:midYear',
            'XX:2027-12-25:christmas',
        ], array_keys($result));
    }

    public function testTodayIsIncluded(): void
    {
        $planner = new SyncPlanner($this->createProvider());

        $result = $planner->getDesiredHolidays('XX', [], '2027-06-15', 0);

        $this->assertSame(['XX:2027-06-15:midYear', 'XX:2027-12-25:christmas'], array_keys($result));
    }

    public function testLeadYearsZeroOnlyCurrentYear(): void
    {
        $planner = new SyncPlanner($this->createProvider());

        $result = $planner->getDesiredHolidays('XX', [], '2026-01-01', 0);

        $this->assertCount(3, $result);

        foreach ($result as $holiday) {
            $this->assertStringStartsWith('2026-', $holiday->date);
        }
    }

    public function testLeadYearsTwo(): void
    {
        $planner = new SyncPlanner($this->createProvider());

        $result = $planner->getDesiredHolidays('XX', [], '2026-01-01', 2);

        $this->assertCount(9, $result);
        $this->assertArrayHasKey('XX:2028-12-25:christmas', $result);
    }

    public function testExclusions(): void
    {
        $planner = new SyncPlanner($this->createProvider());

        $result = $planner->getDesiredHolidays('XX', ['midYear', 'christmas'], '2026-01-01', 1);

        $this->assertSame(['XX:2026-01-01:newYear', 'XX:2027-01-01:newYear'], array_keys($result));
    }

    public function testNoRegion(): void
    {
        $planner = new SyncPlanner($this->createProvider());

        $this->assertSame([], $planner->getDesiredHolidays(null, [], '2026-01-01', 1));
        $this->assertSame([], $planner->getDesiredHolidays('', [], '2026-01-01', 1));
    }

    public function testUnsupportedRegion(): void
    {
        $planner = new SyncPlanner($this->createProvider());

        $this->assertSame([], $planner->getDesiredHolidays('YY', [], '2026-01-01', 1));
    }

    public function testDiff(): void
    {
        $planner = new SyncPlanner($this->createProvider());

        $diff = $planner->diff(['a', 'b', 'c'], ['b', 'c', 'd']);

        $this->assertSame(['a'], $diff['toLink']);
        $this->assertSame(['d'], $diff['toUnlink']);
    }

    public function testDiffNothingToDo(): void
    {
        $planner = new SyncPlanner($this->createProvider());

        $diff = $planner->diff(['a', 'b'], ['b', 'a']);

        $this->assertSame([], $diff['toLink']);
        $this->assertSame([], $diff['toUnlink']);
    }

    public function testAvailableHolidays(): void
    {
        $planner = new SyncPlanner($this->createProvider());

        $this->assertSame(
            ['newYear' => 'New Year', 'midYear' => 'Mid Year', 'christmas' => 'Christmas'],
            $planner->getAvailableHolidays('XX', 2026, 2027)
        );

        $this->assertSame([], $planner->getAvailableHolidays('YY', 2026, 2027));
    }

    public function testUniqueKey(): void
    {
        $holiday = new Holiday('DE-BW', 'corpusChristi', 'Fronleichnam', '2027-05-27');

        $this->assertSame('DE-BW:2027-05-27:corpusChristi', $holiday->getUniqueKey());
    }
}
