<?php

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\ORM\EntityManager;

/**
 * Called when the extension is uninstalled.
 *
 * Public holiday exceptions that were created by the extension are kept (they are regular records).
 */
class AfterUninstall
{
    private const JOB_NAME = 'SyncPublicHolidays';

    public function run(Container $container): void
    {
        $em = $container->getByClass(EntityManager::class);

        $jobs = $em->getRDBRepository('ScheduledJob')
            ->where(['job' => self::JOB_NAME])
            ->find();

        foreach ($jobs as $job) {
            $em->removeEntity($job);
        }

        $configWriter = $container->getByClass(InjectableFactory::class)->create(ConfigWriter::class);

        $configWriter->remove('phLeadYears');
        $configWriter->remove('phOnlyActiveUsers');
        $configWriter->save();
    }
}
