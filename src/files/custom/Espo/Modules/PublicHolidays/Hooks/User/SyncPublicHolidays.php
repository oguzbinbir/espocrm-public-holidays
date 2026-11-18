<?php

namespace Espo\Modules\PublicHolidays\Hooks\User;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Utils\Log;
use Espo\Entities\User;
use Espo\Entities\WorkingTimeCalendar;
use Espo\Modules\PublicHolidays\Tools\CalendarSyncService;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;
use Throwable;

/**
 * When a user gets a working time calendar or is (re)activated, the calendar is synced
 * immediately, so new employees get their public holidays without waiting for the job.
 *
 * @implements AfterSave<User>
 * @noinspection PhpUnused
 */
class SyncPublicHolidays implements AfterSave
{
    public function __construct(
        private CalendarSyncService $syncService,
        private EntityManager $entityManager,
        private Log $log,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if (
            !$entity->isAttributeChanged('workingTimeCalendarId') &&
            !$entity->isAttributeChanged('isActive') &&
            !$entity->isAttributeChanged('type')
        ) {
            return;
        }

        $calendarId = $entity->get('workingTimeCalendarId');

        if (!$calendarId) {
            return;
        }

        try {
            $calendar = $this->entityManager
                ->getRDBRepositoryByClass(WorkingTimeCalendar::class)
                ->getById($calendarId);

            if (!$calendar || !$calendar->get(CalendarSyncService::FIELD_REGION)) {
                return;
            }

            $this->syncService->syncCalendar($calendar);
        } catch (Throwable $e) {
            $this->log->error("Public Holidays: Sync of calendar $calendarId failed. " .
                $e->getMessage(), ['exception' => $e]);
        }
    }
}
