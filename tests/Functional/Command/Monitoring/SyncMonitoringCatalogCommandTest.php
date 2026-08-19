<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command\Monitoring;

use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\MonitoringTemplate;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\ApplicationTester;

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

    public function testCommandSynchronizesSystemTemplatesAndItemsIdempotently(): void
    {
        $tester = $this->applicationTester();

        $firstStatus = $tester->run(['command' => 'osira:monitoring:sync'], ['interactive' => false]);
        self::assertSame(Command::SUCCESS, $firstStatus);
        self::assertStringContainsString('Monitoring catalog synchronized.', $tester->getDisplay());

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertSame(3, $entityManager->getRepository(MonitoringTemplate::class)->count([]));
        self::assertSame(15, $entityManager->getRepository(ItemDefinition::class)->count([]));

        $secondStatus = $this->applicationTester()->run(['command' => 'osira:monitoring:sync'], ['interactive' => false]);
        self::assertSame(Command::SUCCESS, $secondStatus);
        self::assertSame(3, $entityManager->getRepository(MonitoringTemplate::class)->count([]));
        self::assertSame(15, $entityManager->getRepository(ItemDefinition::class)->count([]));

        $linuxBase = $entityManager->getRepository(MonitoringTemplate::class)->findOneBy(['slug' => 'linux-base']);
        self::assertInstanceOf(MonitoringTemplate::class, $linuxBase);
        self::assertTrue($linuxBase->isSystem());
        self::assertTrue($linuxBase->isEnabled());
        self::assertCount(9, $linuxBase->itemDefinitions());
    }

    private function applicationTester(): ApplicationTester
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $application = new Application($kernel);
        $application->setAutoExit(false);

        return new ApplicationTester($application);
    }
}
