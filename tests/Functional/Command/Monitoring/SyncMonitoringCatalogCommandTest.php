<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command\Monitoring;

use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\MonitoringTemplate;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SyncMonitoringCatalogCommandTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    public function testProductBootstrapHasNoCatalogAndNoSynchronizationCommand(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $application = new Application($kernel);

        self::assertFalse($application->has('osira:monitoring:sync'));
        self::assertSame(0, $entityManager->getRepository(MonitoringTemplate::class)->count([]));
        self::assertSame(0, $entityManager->getRepository(ItemDefinition::class)->count([]));
        self::assertSame(0, $entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM alert_rules'));
    }
}
