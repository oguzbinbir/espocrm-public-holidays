<?php

namespace Espo\Modules\PublicHolidays\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Entities\WorkingTimeCalendar;
use Espo\Modules\PublicHolidays\Tools\CalendarSyncService;
use Espo\ORM\EntityManager;

/**
 * POST /PublicHolidays/calendar/:id/sync
 *
 * @noinspection PhpUnused
 */
class PostSyncCalendar implements Action
{
    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private CalendarSyncService $syncService,
    ) {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id');

        if (!$id) {
            throw new BadRequest();
        }

        $calendar = $this->entityManager
            ->getRDBRepositoryByClass(WorkingTimeCalendar::class)
            ->getById($id);

        if (!$calendar) {
            throw new NotFound();
        }

        if (!$this->acl->checkEntityEdit($calendar)) {
            throw new Forbidden();
        }

        $result = $this->syncService->syncCalendar($calendar);

        return ResponseComposer::json($result->toArray());
    }
}
