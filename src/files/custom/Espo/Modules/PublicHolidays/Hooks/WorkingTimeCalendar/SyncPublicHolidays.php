<?php

namespace Espo\Modules\PublicHolidays\Hooks\WorkingTimeCalendar;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Utils\Log;
use Espo\Entities\WorkingTimeCalendar;
use Espo\Modules\PublicHolidays\Tools\CalendarSyncService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
use Throwable;

/**
 * Syncs public holidays immediately when the region or the exclusions of a calendar change.
 *
 * @implements AfterSave<WorkingTimeCalendar>
 * @noinspection PhpUnused
 */
class SyncPublicHolidays implements AfterSave
{
    public function __construct(
        private CalendarSyncService $syncService,
        private Log $log,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if (
            !$entity->isAttributeChanged(CalendarSyncService::FIELD_REGION) &&
            !$entity->isAttributeChanged(CalendarSyncService::FIELD_EXCLUDED)
        ) {
            return;
        }

        try {
            $this->syncService->syncCalendar($entity);
        } catch (Throwable $e) {
            // Saving the calendar must never fail because of the sync.
            $this->log->error("Public Holidays: Sync of calendar {$entity->getId()} failed. " .
                $e->getMessage(), ['exception' => $e]);
        }
    }
}
