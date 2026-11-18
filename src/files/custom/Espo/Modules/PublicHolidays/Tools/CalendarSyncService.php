<?php

namespace Espo\Modules\PublicHolidays\Tools;

use DateTimeZone;
use Espo\Core\Field\Date;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Log;
use Espo\Entities\User;
use Espo\Entities\WorkingTimeCalendar;
use Espo\Entities\WorkingTimeRange;
use Espo\ORM\EntityManager;
use Throwable;

/**
 * Creates public holiday exceptions (working time ranges) and links them to calendars.
 *
 * Rules:
 * - Only ranges created by this extension (identified by `phHolidayKey`) are touched.
 * - Nothing is ever deleted. Links are removed only for future ranges of this extension
 *   (when the region or the exclusions of a calendar change).
 * - Past holidays are never touched.
 */
class CalendarSyncService
{
    public const FIELD_REGION = 'phHolidayRegion';
    public const FIELD_EXCLUDED = 'phExcludedHolidays';
    public const FIELD_KEY = 'phHolidayKey';

    private const RANGE_DESCRIPTION = 'Created automatically by the Public Holidays extension.';

    private SyncPlanner $planner;

    public function __construct(
        private EntityManager $entityManager,
        private Config $config,
        private Log $log,
        HolidayProvider $holidayProvider,
    ) {
        $this->planner = new SyncPlanner($holidayProvider);
    }

    /**
     * Sync all calendars.
     */
    public function syncAll(?string $today = null): SyncResult
    {
        $result = new SyncResult();

        $calendars = $this->entityManager
            ->getRDBRepositoryByClass(WorkingTimeCalendar::class)
            ->find();

        foreach ($calendars as $calendar) {
            try {
                $result->add($this->syncCalendar($calendar, $today));
            } catch (Throwable $e) {
                $this->log->error("Public Holidays: Sync of calendar {$calendar->getId()} failed. " .
                    $e->getMessage(), ['exception' => $e]);
            }
        }

        return $result;
    }

    /**
     * Sync a single calendar.
     *
     * @param ?string $today A date in `Y-m-d` format. Defaults to the current date in the system time zone.
     */
    public function syncCalendar(WorkingTimeCalendar $calendar, ?string $today = null): SyncResult
    {
        $today ??= $this->getToday();
        $settings = SyncSettings::fromConfig($this->config);
        $result = new SyncResult();

        if (!$this->isEligible($calendar, $settings)) {
            $result->calendarsSkipped++;

            return $result;
        }

        $result->calendarsProcessed++;

        $region = $calendar->get(self::FIELD_REGION) ?: null;
        /** @var string[] $excluded */
        $excluded = $calendar->get(self::FIELD_EXCLUDED) ?? [];

        $desired = $this->planner->getDesiredHolidays($region, $excluded, $today, $settings->leadYears);
        $linked = $this->getLinkedFutureRanges($calendar, $today);

        $diff = $this->planner->diff(array_keys($desired), array_keys($linked));

        $relation = $this->entityManager
            ->getRDBRepositoryByClass(WorkingTimeCalendar::class)
            ->getRelation($calendar, 'ranges');

        foreach ($diff['toUnlink'] as $key) {
            $relation->unrelate($linked[$key]);
            $result->unlinked++;
        }

        if ($diff['toLink'] === []) {
            return $result;
        }

        $existing = $this->findRangesByKeys($diff['toLink']);

        foreach ($diff['toLink'] as $key) {
            $range = $existing[$key] ?? null;

            if (!$range) {
                $range = $this->createRange($desired[$key]);
                $result->rangesCreated++;
            }

            $relation->relate($range);
            $result->linked++;
        }

        return $result;
    }

    /**
     * Whether the calendar is assigned to at least one active (regular or admin) user.
     * If the "only active users" setting is disabled, every calendar is eligible.
     */
    public function isEligible(WorkingTimeCalendar $calendar, ?SyncSettings $settings = null): bool
    {
        $settings ??= SyncSettings::fromConfig($this->config);

        if (!$settings->onlyActiveUsers) {
            return true;
        }

        $user = $this->entityManager
            ->getRDBRepositoryByClass(User::class)
            ->select(['id'])
            ->where([
                'workingTimeCalendarId' => $calendar->getId(),
                'isActive' => true,
                'type' => [User::TYPE_REGULAR, User::TYPE_ADMIN],
            ])
            ->findOne();

        return $user !== null;
    }

    /**
     * @return array<string, string> Holiday key => name.
     */
    public function getAvailableHolidays(string $region): array
    {
        $year = (int) substr($this->getToday(), 0, 4);
        $settings = SyncSettings::fromConfig($this->config);

        return $this->planner->getAvailableHolidays($region, $year, $year + max(1, $settings->leadYears));
    }

    /**
     * @return array<string, WorkingTimeRange>
     */
    private function getLinkedFutureRanges(WorkingTimeCalendar $calendar, string $today): array
    {
        $collection = $this->entityManager
            ->getRDBRepositoryByClass(WorkingTimeCalendar::class)
            ->getRelation($calendar, 'ranges')
            ->where([
                self::FIELD_KEY . '!=' => null,
                'dateStart>=' => $today,
            ])
            ->find();

        $map = [];

        foreach ($collection as $range) {
            /** @var WorkingTimeRange $range */
            $map[(string) $range->get(self::FIELD_KEY)] = $range;
        }

        return $map;
    }

    /**
     * @param string[] $keys
     * @return array<string, WorkingTimeRange>
     */
    private function findRangesByKeys(array $keys): array
    {
        $collection = $this->entityManager
            ->getRDBRepositoryByClass(WorkingTimeRange::class)
            ->where([self::FIELD_KEY => $keys])
            ->order('createdAt')
            ->find();

        $map = [];

        foreach ($collection as $range) {
            $map[(string) $range->get(self::FIELD_KEY)] ??= $range;
        }

        return $map;
    }

    private function createRange(Holiday $holiday): WorkingTimeRange
    {
        $range = $this->entityManager->getRDBRepositoryByClass(WorkingTimeRange::class)->getNew();

        $range->setMultiple([
            'name' => $holiday->name,
            'type' => WorkingTimeRange::TYPE_NON_WORKING,
            'dateStart' => $holiday->date,
            'dateEnd' => $holiday->date,
            'description' => self::RANGE_DESCRIPTION . ' (' . $holiday->region . ')',
            self::FIELD_KEY => $holiday->getUniqueKey(),
        ]);

        $this->entityManager->saveEntity($range);

        return $range;
    }

    private function getToday(): string
    {
        $timeZone = $this->config->get('timeZone') ?: 'UTC';

        try {
            $tz = new DateTimeZone($timeZone);
        } catch (Throwable) {
            $tz = new DateTimeZone('UTC');
        }

        return Date::createToday($tz)->toString();
    }
}
