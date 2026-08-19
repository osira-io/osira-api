<?php

declare(strict_types=1);

namespace App\Tests\Unit\State\Provider\Monitoring;

use ApiPlatform\Metadata\Get;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Service\Monitoring\ItemDefinitionOutputFactory;
use App\Service\Monitoring\MonitoringTemplateOutputFactory;
use App\State\Provider\Monitoring\ItemDefinitionProvider;
use App\State\Provider\Monitoring\MonitoringTemplateProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

final class MonitoringProvidersTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
    }

    public function testItemDefinitionProviderReturnsNullForInvalidOrMissingIds(): void
    {
        $provider = new ItemDefinitionProvider(
            $this->entityManager->getRepository(ItemDefinition::class),
            new ItemDefinitionOutputFactory(),
        );

        self::assertNull($provider->provide(new Get(), ['id' => 'invalid']));
        self::assertNull($provider->provide(new Get(), ['id' => (string) new Ulid()]));
    }

    public function testItemDefinitionProviderBuildsOutputWhenEntityExists(): void
    {
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');
        $item = new ItemDefinition('system.cpu.usage', 'CPU usage', null, null, null, ItemValueType::FLOAT, 60, null, false, true, $now);
        $this->entityManager->persist($item);
        $this->entityManager->flush();

        $provider = new ItemDefinitionProvider(
            $this->entityManager->getRepository(ItemDefinition::class),
            new ItemDefinitionOutputFactory(),
        );
        $output = $provider->provide(new Get(), ['id' => (string) $item->id()]);

        self::assertNotNull($output);
        self::assertSame('system.cpu.usage', $output->key);
    }

    public function testMonitoringTemplateProviderReturnsNullForInvalidOrMissingIds(): void
    {
        $provider = new MonitoringTemplateProvider(
            $this->entityManager->getRepository(MonitoringTemplate::class),
            new MonitoringTemplateOutputFactory(new ItemDefinitionOutputFactory()),
        );

        self::assertNull($provider->provide(new Get(), ['id' => null]));
        self::assertNull($provider->provide(new Get(), ['id' => (string) new Ulid()]));
    }

    public function testMonitoringTemplateProviderBuildsOutputWhenEntityExists(): void
    {
        $now = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');
        $template = new MonitoringTemplate('Linux Base', 'linux-base', null, false, true, $now);
        $this->entityManager->persist($template);
        $this->entityManager->flush();

        $provider = new MonitoringTemplateProvider(
            $this->entityManager->getRepository(MonitoringTemplate::class),
            new MonitoringTemplateOutputFactory(new ItemDefinitionOutputFactory()),
        );
        $output = $provider->provide(new Get(), ['id' => (string) $template->id()]);

        self::assertNotNull($output);
        self::assertSame('linux-base', $output->slug);
    }
}
