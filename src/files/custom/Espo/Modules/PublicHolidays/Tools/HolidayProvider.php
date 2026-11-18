<?php

namespace Espo\Modules\PublicHolidays\Tools;

interface HolidayProvider
{
    /**
     * Official public holidays of a region in a year, ordered by date.
     *
     * @return Holiday[]
     * @throws UnsupportedRegion
     */
    public function getHolidays(string $region, int $year): array;

    public function isSupported(string $region): bool;
}
