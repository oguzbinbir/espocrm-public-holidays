<?php

namespace Espo\Modules\PublicHolidays\Tools;

class SyncResult
{
    public function __construct(
        public int $calendarsProcessed = 0,
        public int $calendarsSkipped = 0,
        public int $rangesCreated = 0,
        public int $linked = 0,
        public int $unlinked = 0,
    ) {}

    public function add(SyncResult $other): void
    {
        $this->calendarsProcessed += $other->calendarsProcessed;
        $this->calendarsSkipped += $other->calendarsSkipped;
        $this->rangesCreated += $other->rangesCreated;
        $this->linked += $other->linked;
        $this->unlinked += $other->unlinked;
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'calendarsProcessed' => $this->calendarsProcessed,
            'calendarsSkipped' => $this->calendarsSkipped,
            'rangesCreated' => $this->rangesCreated,
            'linked' => $this->linked,
            'unlinked' => $this->unlinked,
        ];
    }
}
