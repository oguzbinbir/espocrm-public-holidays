<?php

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\ORM\EntityManager;

/**
 * Called when the extension is installed or upgraded.
 */
class AfterInstall
{
    private const JOB_NAME = 'SyncPublicHolidays';

    public function run(Container $container): void
    {
        $em = $container->getByClass(EntityManager::class);
        $config = $container->getByClass(Config::class);
        $configWriter = $container->getByClass(InjectableFactory::class)->create(ConfigWriter::class);

        $this->setConfigDefaults($config, $configWriter);
        $this->createScheduledJob($em);
    }

    private function setConfigDefaults(Config $config, ConfigWriter $configWriter): void
    {
        $changed = false;

        if ($config->get('phLeadYears') === null) {
            $configWriter->set('phLeadYears', 1);
            $changed = true;
        }

        if ($config->get('phOnlyActiveUsers') === null) {
            $configWriter->set('phOnlyActiveUsers', true);
            $changed = true;
        }

        if ($changed) {
            $configWriter->save();
        }
    }

    private function createScheduledJob(EntityManager $em): void
    {
        $existing = $em->getRDBRepository('ScheduledJob')
            ->where(['job' => self::JOB_NAME])
            ->findOne();

        if ($existing) {
            return;
        }

        $job = $em->getNewEntity('ScheduledJob');

        $job->setMultiple([
            'name' => 'Sync Public Holidays',
            'job' => self::JOB_NAME,
            'status' => 'Active',
            // Every Monday at 03:15.
            'scheduling' => '15 3 * * 1',
        ]);

        $em->saveEntity($job);
    }
}
