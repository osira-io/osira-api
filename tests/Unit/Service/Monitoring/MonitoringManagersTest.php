<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Monitoring;

use App\Dto\Monitoring\CreateItemDefinitionInput;
use App\Dto\Monitoring\CreateMonitoringTemplateInput;
use App\Dto\Monitoring\UpdateItemDefinitionInput;
use App\Dto\Monitoring\UpdateMonitoringTemplateInput;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Service\Monitoring\ItemDefinitionManager;
use App\Service\Monitoring\MonitoringTemplateManager;
use App\Service\Shared\Exception\ResourceConflictException;
use App\Service\Shared\Exception\ResourceNotFoundException;
use App\Service\Shared\Exception\ResourceValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

final class MonitoringManagersTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ItemDefinitionManager $itemDefinitionManager;
    private MonitoringTemplateManager $monitoringTemplateManager;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        $this->itemDefinitionManager = self::getContainer()->get(ItemDefinitionManager::class);
        $this->monitoringTemplateManager = self::getContainer()->get(MonitoringTemplateManager::class);
    }

    public function testItemDefinitionManagerCreateNormalizesValues(): void
    {
        $input = new CreateItemDefinitionInput();
        $input->key = '  CUSTOM.CHECK.LATENCY  ';
        $input->name = '  Custom latency  ';
        $input->description = '  Probe latency  ';
        $input->unit = '  ms  ';
        $input->valueType = 'float';
        $input->intervalSeconds = 30;
        $input->timeoutSeconds = 5;
        $input->linuxCommand = "printf '12.5'";
        $input->isEnabled = true;

        $item = $this->itemDefinitionManager->create($input);

        self::assertSame('custom.check.latency', $item->key());
        self::assertSame('Custom latency', $item->name());
        self::assertSame('Probe latency', $item->description());
        self::assertSame('ms', $item->unit());
        self::assertSame("printf '12.5'", $item->linuxCommand());
        self::assertSame(1, $this->entityManager->getRepository(ItemDefinition::class)->count([]));
    }

    public function testItemDefinitionManagerDeleteRejectsInvalidId(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->itemDefinitionManager->delete('invalid');
    }

    public function testItemDefinitionManagerUpdateRejectsDuplicateKeys(): void
    {
        $this->itemDefinitionManager->create($this->createItemDefinitionInput('system.cpu.usage', 'CPU usage'));
        $other = $this->itemDefinitionManager->create($this->createItemDefinitionInput('custom.check.latency', 'Latency'));
        $update = new UpdateItemDefinitionInput();
        $update->setKey('SYSTEM.CPU.USAGE');

        $this->expectException(ResourceConflictException::class);
        $this->itemDefinitionManager->update((string) $other->id(), $update);
    }

    public function testItemDefinitionManagerCreateRejectsInvalidInput(): void
    {
        $this->expectException(ResourceValidationException::class);
        $this->itemDefinitionManager->create($this->createItemDefinitionInput('invalid key', 'Invalid'));
    }

    public function testMonitoringTemplateManagerCreateNormalizesSlugAndDeduplicatesItems(): void
    {
        $item = $this->itemDefinitionManager->create($this->createItemDefinitionInput('system.cpu.usage', 'CPU usage'));

        $input = new CreateMonitoringTemplateInput();
        $input->name = '  Linux Base  ';
        $input->slug = null;
        $input->description = '  Core metrics  ';
        $input->itemDefinitionIds = [(string) $item->id(), (string) $item->id()];
        $input->isEnabled = true;

        $template = $this->monitoringTemplateManager->create($input);

        self::assertSame('Linux Base', $template->name());
        self::assertSame('linux-base', $template->slug());
        self::assertSame('Core metrics', $template->description());
        self::assertCount(1, $template->itemDefinitions());
        self::assertSame(1, $this->entityManager->getRepository(MonitoringTemplate::class)->count([]));
    }

    public function testMonitoringTemplateManagerDeleteRejectsInvalidId(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->monitoringTemplateManager->delete('invalid');
    }

    public function testMonitoringTemplateManagerUpdateRejectsMissingItemDefinitions(): void
    {
        $template = $this->monitoringTemplateManager->create($this->createMonitoringTemplateInput('Linux Base'));

        $input = new UpdateMonitoringTemplateInput();
        $input->setName('Linux Core');
        $input->setSlug('linux-core');
        $input->setItemDefinitionIds([(string) new Ulid()]);

        $this->expectException(ResourceValidationException::class);
        $this->monitoringTemplateManager->update((string) $template->id(), $input);
    }

    private function createItemDefinitionInput(string $key, string $name): CreateItemDefinitionInput
    {
        $input = new CreateItemDefinitionInput();
        $input->key = $key;
        $input->name = $name;
        $input->description = null;
        $input->unit = null;
        $input->valueType = 'float';
        $input->intervalSeconds = 30;
        $input->timeoutSeconds = null;
        $input->linuxCommand = 'printf 1';
        $input->isEnabled = true;

        return $input;
    }

    private function createMonitoringTemplateInput(string $name): CreateMonitoringTemplateInput
    {
        $input = new CreateMonitoringTemplateInput();
        $input->name = $name;
        $input->slug = null;
        $input->description = null;
        $input->itemDefinitionIds = [];
        $input->isEnabled = true;

        return $input;
    }
}
