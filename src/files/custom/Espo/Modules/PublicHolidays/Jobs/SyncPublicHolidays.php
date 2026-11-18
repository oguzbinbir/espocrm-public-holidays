<?php

namespace Espo\Modules\PublicHolidays\Jobs;

use Espo\Core\Job\JobDataLess;
use Espo\Core\Utils\Log;
use Espo\Modules\PublicHolidays\Tools\CalendarSyncService;

/**
 * Scheduled job: keeps public holidays of all working time calendars up to date.
 *
 * @noinspection PhpUnused
 */
class SyncPublicHolidays implements JobDataLess
{
    public function __construct(
        private CalendarSyncService $syncService,
        private Log $log,
    ) {}

    public function run(): void
    {
        $result = $this->syncService->syncAll();

        $this->log->info('Public Holidays: Sync finished.', $result->toArray());
    }
}
