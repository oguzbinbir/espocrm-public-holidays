<?php

namespace Espo\Modules\PublicHolidays\Tools;

/**
 * Pure planning logic without database access (unit-tested).
 */
class SyncPlanner
{
    public function __construct(
        private HolidayProvider $holidayProvider,
    ) {}

    /**
     * Holidays a calendar should be linked to: from today (inclusive) until the end
     * of the year `today + leadYears`, without excluded holiday keys.
     *
     * @param string[] $excludedKeys Holiday keys like `epiphany`.
     * @return array<string, Holiday> Unique key => holiday.
     */
    public function getDesiredHolidays(
        ?string $region,
        array $excludedKeys,
        string $today,
        int $leadYears,
    ): array {

        if (!$region || !$this->holidayProvider->isSupported($region)) {
            return [];
        }

        $year = (int) substr($today, 0, 4);
        $result = [];

        for ($y = $year; $y <= $year + max(0, $leadYears); $y++) {
            foreach ($this->holidayProvider->getHolidays($region, $y) as $holiday) {
                if ($holiday->date < $today) {
                    continue;
                }

                if (in_array($holiday->key, $excludedKeys, true)) {
                    continue;
                }

                $result[$holiday->getUniqueKey()] = $holiday;
            }
        }

        return $result;
    }

    /**
     * @param string[] $desiredKeys Unique keys the calendar should be linked to.
     * @param string[] $linkedKeys Unique keys of extension ranges currently linked (future only).
     * @return array{toLink: string[], toUnlink: string[]}
     */
    public function diff(array $desiredKeys, array $linkedKeys): array
    {
        return [
            'toLink' => array_values(array_diff($desiredKeys, $linkedKeys)),
            'toUnlink' => array_values(array_diff($linkedKeys, $desiredKeys)),
        ];
    }

    /**
     * Holiday keys available for a region in the given years (for the exclusion field).
     *
     * @return array<string, string> Holiday key => name.
     */
    public function getAvailableHolidays(string $region, int $fromYear, int $toYear): array
    {
        if (!$this->holidayProvider->isSupported($region)) {
            return [];
        }

        $result = [];

        for ($y = $fromYear; $y <= $toYear; $y++) {
            foreach ($this->holidayProvider->getHolidays($region, $y) as $holiday) {
                $result[$holiday->key] ??= $holiday->name;
            }
        }

        return $result;
    }
}
