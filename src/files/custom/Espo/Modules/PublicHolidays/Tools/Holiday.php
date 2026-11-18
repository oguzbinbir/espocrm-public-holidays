<?php

namespace Espo\Modules\PublicHolidays\Tools;

/**
 * A single public holiday of a region on a specific date.
 */
readonly class Holiday
{
    /**
     * @param string $region A region code, e.g. `DE-BW`.
     * @param string $key A holiday key independent of the year, e.g. `corpusChristi`.
     * @param string $name A translated name, e.g. `Fronleichnam`.
     * @param string $date A date in `Y-m-d` format.
     */
    public function __construct(
        public string $region,
        public string $key,
        public string $name,
        public string $date,
    ) {}

    /**
     * A unique identifier stored in the working time range (`phHolidayKey`).
     * Example: `DE-BW:2027-05-27:corpusChristi`.
     */
    public function getUniqueKey(): string
    {
        return $this->region . ':' . $this->date . ':' . $this->key;
    }
}
