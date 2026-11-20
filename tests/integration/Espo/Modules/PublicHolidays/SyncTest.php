<?php

namespace tests\integration\Espo\Modules\PublicHolidays;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Field\DateTime;
use Espo\Core\Record\CreateParams;
use Espo\Core\Record\ServiceContainer;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Entities\User;
use Espo\Entities\WorkingTimeCalendar;
use Espo\Entities\WorkingTimeRange;
use Espo\Modules\PublicHolidays\Api\GetRegionHolidays;
use Espo\Modules\PublicHolidays\Api\PostSyncCalendar;
use Espo\Modules\PublicHolidays\Jobs\SyncPublicHolidays;
use Espo\Modules\PublicHolidays\Tools\CalendarSyncService;
use Espo\Tools\WorkingTime\CalendarUtilityFactory;
use tests\integration\Core\BaseTestCase;

/**
 * Runs against a real EspoCRM instance and database.
 *
 * Most tests pass an explicit "today" to the sync service so that they do not depend
 * on the date the tests are run.
 */
class SyncTest extends BaseTestCase
{
    private const TODAY = '2026-11-18';

    // 2026: 25.12., 26.12. + 2027: 12 holidays in Baden-Württemberg.
    private const BW_COUNT_FROM_TODAY = 14;

    private function getService(): CalendarSyncService
    {
        return $this->getInjectableFactory()->create(CalendarSyncService::class);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createCalendar(array $data = [], bool $withActiveUser = true): WorkingTimeCalendar
    {
        $em = $this->getEntityManager();

        /** @var WorkingTimeCalendar $calendar */
        $calendar = $em->getRDBRepositoryByClass(WorkingTimeCalendar::class)->getNew();
        $calendar->set(['name' => 'Test ' . uniqid()] + $data);

        // Without hooks, so that tests control when the sync happens.
        $em->saveEntity($calendar, [SaveOption::SKIP_HOOKS => true]);

        if ($withActiveUser) {
            $this->createUserForCalendar($calendar, true);
        }

        return $calendar;
    }

    private function createUserForCalendar(WorkingTimeCalendar $calendar, bool $isActive): User
    {
        $em = $this->getEntityManager();

        $user = $this->createUser('user' . uniqid());
        $user->set('workingTimeCalendarId', $calendar->getId());
        $user->set('isActive', $isActive);

        $em->saveEntity($user, [SaveOption::SKIP_HOOKS => true]);

        return $user;
    }

    private function updateCalendarWithoutHooks(WorkingTimeCalendar $calendar, array $data): void
    {
        $calendar->set($data);

        $this->getEntityManager()->saveEntity($calendar, [SaveOption::SKIP_HOOKS => true]);
    }

    /**
     * @return array<string, WorkingTimeRange> Key or name => range.
     */
    private function getLinkedRanges(WorkingTimeCalendar $calendar, ?string $fromDate = null): array
    {
        $where = [];

        if ($fromDate) {
            $where['dateStart>='] = $fromDate;
        }

        $collection = $this->getEntityManager()
            ->getRDBRepositoryByClass(WorkingTimeCalendar::class)
            ->getRelation($calendar, 'ranges')
            ->where($where)
            ->find();

        $map = [];

        foreach ($collection as $range) {
            $map[$range->get('phHolidayKey') ?? $range->get('name')] = $range;
        }

        return $map;
    }

    private function setConfig(string $name, mixed $value): void
    {
        $configWriter = $this->getInjectableFactory()->create(ConfigWriter::class);
        $configWriter->set($name, $value);
        $configWriter->save();
    }

    public function testCreatesAndLinksHolidays(): void
    {
        $calendar = $this->createCalendar(['phHolidayRegion' => 'DE-BW']);

        $result = $this->getService()->syncCalendar($calendar, self::TODAY);

        $this->assertSame(1, $result->calendarsProcessed);
        $this->assertSame(self::BW_COUNT_FROM_TODAY, $result->rangesCreated);
        $this->assertSame(self::BW_COUNT_FROM_TODAY, $result->linked);
        $this->assertSame(0, $result->unlinked);

        $ranges = $this->getLinkedRanges($calendar);

        $this->assertCount(self::BW_COUNT_FROM_TODAY, $ranges);
        $this->assertArrayHasKey('DE-BW:2026-12-25:christmasDay', $ranges);
        $this->assertArrayHasKey('DE-BW:2027-05-27:corpusChristi', $ranges);
        $this->assertArrayNotHasKey('DE-BW:2026-11-01:allSaintsDay', $ranges, 'Past holidays must not be created.');

        $range = $ranges['DE-BW:2027-05-27:corpusChristi'];

        $this->assertSame(WorkingTimeRange::TYPE_NON_WORKING, $range->get('type'));
        $this->assertSame('2027-05-27', $range->get('dateStart'));
        $this->assertSame('2027-05-27', $range->get('dateEnd'));
        $this->assertNotEmpty($range->get('name'));

        // Translated description with a readable region name (system language).
        $description = (string) $range->get('description');

        $this->assertStringContainsString('Baden-Württemberg', $description);
        $this->assertStringNotContainsString('{region}', $description);
        $this->assertStringNotContainsString('phAutoCreated', $description);
    }

    public function testIsIdempotent(): void
    {
        $calendar = $this->createCalendar(['phHolidayRegion' => 'DE-BW']);

        $this->getService()->syncCalendar($calendar, self::TODAY);
        $result = $this->getService()->syncCalendar($calendar, self::TODAY);

        $this->assertSame(0, $result->rangesCreated);
        $this->assertSame(0, $result->linked);
        $this->assertSame(0, $result->unlinked);
        $this->assertCount(self::BW_COUNT_FROM_TODAY, $this->getLinkedRanges($calendar));
    }

    public function testCalendarsOfSameRegionShareRanges(): void
    {
        $calendar1 = $this->createCalendar(['phHolidayRegion' => 'DE-BW']);
        $calendar2 = $this->createCalendar(['phHolidayRegion' => 'DE-BW']);

        $this->getService()->syncCalendar($calendar1, self::TODAY);
        $result = $this->getService()->syncCalendar($calendar2, self::TODAY);

        $this->assertSame(0, $result->rangesCreated);
        $this->assertSame(self::BW_COUNT_FROM_TODAY, $result->linked);

        $count = $this->getEntityManager()
            ->getRDBRepositoryByClass(WorkingTimeRange::class)
            ->where(['phHolidayKey' => 'DE-BW:2027-05-27:corpusChristi'])
            ->count();

        $this->assertSame(1, $count);
    }

    public function testExclusions(): void
    {
        $calendar = $this->createCalendar(['phHolidayRegion' => 'DE-BW']);

        $this->getService()->syncCalendar($calendar, self::TODAY);

        $this->updateCalendarWithoutHooks($calendar, ['phExcludedHolidays' => ['epiphany', 'corpusChristi']]);

        $result = $this->getService()->syncCalendar($calendar, self::TODAY);

        $this->assertSame(2, $result->unlinked);
        $this->assertSame(0, $result->linked);

        $ranges = $this->getLinkedRanges($calendar);

        $this->assertArrayNotHasKey('DE-BW:2027-01-06:epiphany', $ranges);
        $this->assertArrayNotHasKey('DE-BW:2027-05-27:corpusChristi', $ranges);
        $this->assertCount(self::BW_COUNT_FROM_TODAY - 2, $ranges);

        // The range itself is kept (it may be used by other calendars).
        $this->assertNotNull(
            $this->getEntityManager()
                ->getRDBRepositoryByClass(WorkingTimeRange::class)
                ->where(['phHolidayKey' => 'DE-BW:2027-01-06:epiphany'])
                ->findOne()
        );
    }

    public function testRegionChange(): void
    {
        $calendar = $this->createCalendar(['phHolidayRegion' => 'DE-BW']);

        $this->getService()->syncCalendar($calendar, self::TODAY);

        $this->updateCalendarWithoutHooks($calendar, ['phHolidayRegion' => 'DE-BE']);

        $result = $this->getService()->syncCalendar($calendar, self::TODAY);

        $this->assertSame(self::BW_COUNT_FROM_TODAY, $result->unlinked);

        $ranges = $this->getLinkedRanges($calendar);

        foreach (array_keys($ranges) as $key) {
            $this->assertStringStartsWith('DE-BE:', $key);
        }

        // Berlin: International Women's Day.
        $this->assertArrayHasKey('DE-BE:2027-03-08:internationalWomensDay', $ranges);
        $this->assertArrayNotHasKey('DE-BE:2027-01-06:epiphany', $ranges);
    }

    public function testEmptyRegionUnlinksFutureOnly(): void
    {
        $calendar = $this->createCalendar(['phHolidayRegion' => 'DE-BW']);

        $this->getService()->syncCalendar($calendar, self::TODAY);

        $this->updateCalendarWithoutHooks($calendar, ['phHolidayRegion' => '']);

        // Half a year later.
        $result = $this->getService()->syncCalendar($calendar, '2027-06-01');

        // 2027: Jun–Dec: 03.10., 01.11., 25.12., 26.12.
        $this->assertSame(4, $result->unlinked);

        $ranges = $this->getLinkedRanges($calendar);

        // Past holidays stay linked.
        $this->assertArrayHasKey('DE-BW:2026-12-25:christmasDay', $ranges);
        $this->assertArrayHasKey('DE-BW:2027-05-27:corpusChristi', $ranges);
        $this->assertArrayNotHasKey('DE-BW:2027-12-25:christmasDay', $ranges);
    }

    public function testManualRangesAreNotTouched(): void
    {
        $em = $this->getEntityManager();

        $calendar = $this->createCalendar(['phHolidayRegion' => 'DE-BW']);

        /** @var WorkingTimeRange $manual */
        $manual = $em->getRDBRepositoryByClass(WorkingTimeRange::class)->getNew();
        $manual->set([
            'name' => 'Urlaub',
            'type' => WorkingTimeRange::TYPE_NON_WORKING,
            'dateStart' => '2027-07-29',
            'dateEnd' => '2027-08-25',
        ]);
        $em->saveEntity($manual);

        $em->getRDBRepositoryByClass(WorkingTimeCalendar::class)
            ->getRelation($calendar, 'ranges')
            ->relate($manual);

        $this->getService()->syncCalendar($calendar, self::TODAY);

        $this->updateCalendarWithoutHooks($calendar, ['phHolidayRegion' => '']);
        $this->getService()->syncCalendar($calendar, self::TODAY);

        $ranges = $this->getLinkedRanges($calendar);

        $this->assertCount(1, $ranges);
        $this->assertArrayHasKey('Urlaub', $ranges);
    }

    public function testCalendarWithoutActiveUserIsSkipped(): void
    {
        $calendar = $this->createCalendar(['phHolidayRegion' => 'DE-BW'], false);
        $this->createUserForCalendar($calendar, false);

        $result = $this->getService()->syncCalendar($calendar, self::TODAY);

        $this->assertSame(1, $result->calendarsSkipped);
        $this->assertSame(0, $result->calendarsProcessed);
        $this->assertCount(0, $this->getLinkedRanges($calendar));
    }

    public function testCalendarWithoutUserWhenSettingDisabled(): void
    {
        $this->setConfig('phOnlyActiveUsers', false);

        $calendar = $this->createCalendar(['phHolidayRegion' => 'DE-BW'], false);

        $result = $this->getService()->syncCalendar($calendar, self::TODAY);

        $this->assertSame(1, $result->calendarsProcessed);
        $this->assertCount(self::BW_COUNT_FROM_TODAY, $this->getLinkedRanges($calendar));
    }

    public function testLeadYearsSetting(): void
    {
        $this->setConfig('phLeadYears', 0);

        $calendar = $this->createCalendar(['phHolidayRegion' => 'DE-BW']);

        $this->getService()->syncCalendar($calendar, self::TODAY);

        $this->assertCount(2, $this->getLinkedRanges($calendar));

        $this->setConfig('phLeadYears', 2);

        $result = $this->getService()->syncCalendar($calendar, self::TODAY);

        $this->assertSame(24, $result->linked);
    }

    public function testCalendarHookOnRegionChange(): void
    {
        $em = $this->getEntityManager();

        $calendar = $this->createCalendar();

        $calendar->set('phHolidayRegion', 'DE-BW');
        $em->saveEntity($calendar);

        $today = date('Y-m-d');
        $ranges = $this->getLinkedRanges($calendar, $today);

        $this->assertNotEmpty($ranges);

        foreach (array_keys($ranges) as $key) {
            $this->assertStringStartsWith('DE-BW:', $key);
        }

        // Exclusions are applied immediately as well.
        $calendar->set('phExcludedHolidays', ['christmasDay']);
        $em->saveEntity($calendar);

        foreach (array_keys($this->getLinkedRanges($calendar, $today)) as $key) {
            $this->assertStringEndsNotWith(':christmasDay', $key);
        }
    }

    public function testUserHookOnCalendarAssignment(): void
    {
        $em = $this->getEntityManager();

        $calendar = $this->createCalendar(['phHolidayRegion' => 'DE-BW'], false);

        $this->assertCount(0, $this->getLinkedRanges($calendar));

        $user = $this->createUser('newEmployee');
        $user->set('workingTimeCalendarId', $calendar->getId());
        $em->saveEntity($user);

        $this->assertNotEmpty($this->getLinkedRanges($calendar, date('Y-m-d')));
    }

    public function testJob(): void
    {
        $calendar1 = $this->createCalendar(['phHolidayRegion' => 'DE-BW']);
        $calendar2 = $this->createCalendar(['phHolidayRegion' => 'DE-BY']);
        $calendar3 = $this->createCalendar();

        $this->getInjectableFactory()->create(SyncPublicHolidays::class)->run();

        $today = date('Y-m-d');

        $this->assertNotEmpty($this->getLinkedRanges($calendar1, $today));
        $this->assertNotEmpty($this->getLinkedRanges($calendar2, $today));
        $this->assertCount(0, $this->getLinkedRanges($calendar3));

        // Second run changes nothing.
        $result = $this->getService()->syncAll();

        $this->assertSame(0, $result->linked);
        $this->assertSame(0, $result->unlinked);
        $this->assertSame(0, $result->rangesCreated);
    }

    public function testWorkingTimeUtilityRespectsHolidays(): void
    {
        $em = $this->getEntityManager();

        $calendar = $this->createCalendar(['phHolidayRegion' => 'DE-BW']);

        $user = $this->createUser('worker');
        $user->set('workingTimeCalendarId', $calendar->getId());
        $em->saveEntity($user, [SaveOption::SKIP_HOOKS => true]);

        $utility = $this->getInjectableFactory()
            ->create(CalendarUtilityFactory::class)
            ->createForUser($user);

        // Thursday, Corpus Christi.
        $holiday = DateTime::fromString('2027-05-27 10:00');

        $this->assertTrue($utility->isWorkingDay($holiday));

        $this->getService()->syncCalendar($calendar, self::TODAY);

        $utility = $this->getInjectableFactory()
            ->create(CalendarUtilityFactory::class)
            ->createForUser($user);

        $this->assertFalse($utility->isWorkingDay($holiday));
        $this->assertTrue($utility->isWorkingDay(DateTime::fromString('2027-05-28 10:00')));
    }

    public function testApiSync(): void
    {
        $calendar = $this->createCalendar(['phHolidayRegion' => 'DE-BW']);

        $action = $this->getInjectableFactory()->create(PostSyncCalendar::class);

        $response = $action->process(
            $this->createRequest('POST', routeParams: ['id' => $calendar->getId()])
        );

        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame(1, $data['calendarsProcessed']);
        $this->assertGreaterThan(0, $data['linked']);
    }

    public function testApiRegionHolidays(): void
    {
        $action = $this->getInjectableFactory()->create(GetRegionHolidays::class);

        $response = $action->process($this->createRequest('GET', ['region' => 'DE-BW']));

        $data = json_decode((string) $response->getBody(), true);
        $keys = array_column($data, 'key');

        $this->assertCount(12, $data);
        $this->assertContains('corpusChristi', $keys);
        $this->assertContains('epiphany', $keys);
    }

    public function testRegionFieldIsValidated(): void
    {
        $service = $this->getContainer()
            ->getByClass(ServiceContainer::class)
            ->get(WorkingTimeCalendar::ENTITY_TYPE);

        $calendar = $service->create((object) [
            'name' => 'Valid',
            'phHolidayRegion' => 'DE-BW',
        ], CreateParams::create());

        $this->assertSame('DE-BW', $calendar->get('phHolidayRegion'));

        $this->expectException(BadRequest::class);

        $service->create((object) [
            'name' => 'Invalid',
            'phHolidayRegion' => 'XX-INVALID',
        ], CreateParams::create());
    }
}
