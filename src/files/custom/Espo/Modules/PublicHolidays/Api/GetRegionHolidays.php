<?php

namespace Espo\Modules\PublicHolidays\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\WorkingTimeCalendar;
use Espo\Modules\PublicHolidays\Tools\CalendarSyncService;

/**
 * GET /PublicHolidays/regionHolidays?region=DE-BW
 *
 * Returns the holidays of a region (used for the exclusion field).
 *
 * @noinspection PhpUnused
 */
class GetRegionHolidays implements Action
{
    public function __construct(
        private Acl $acl,
        private CalendarSyncService $syncService,
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->acl->checkScope(WorkingTimeCalendar::ENTITY_TYPE)) {
            throw new Forbidden();
        }

        $region = $request->getQueryParam('region');

        if (!$region || !preg_match('/^[A-Z0-9-]{2,20}$/', $region)) {
            throw new BadRequest("Bad region.");
        }

        $list = [];

        foreach ($this->syncService->getAvailableHolidays($region) as $key => $name) {
            $list[] = ['key' => $key, 'name' => $name];
        }

        return ResponseComposer::json($list);
    }
}
