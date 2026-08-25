<?php

declare(strict_types=1);

namespace App\DataFixtures\Monitoring;

use App\DataFixtures\NodeGroup\NodeGroupFixtures;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\NodeGroup\NodeGroup;
use App\Factory\Monitoring\ItemDefinitionFactory;
use App\Factory\Monitoring\MonitoringTemplateFactory;
use App\Repository\NodeGroup\NodeGroupRepository;
use DH\Auditor\Auditor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** Development examples only. Product bootstrap intentionally creates no monitoring catalog. */
final class MonitoringFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly ItemDefinitionFactory $itemDefinitionFactory,
        private readonly MonitoringTemplateFactory $monitoringTemplateFactory,
        private readonly NodeGroupRepository $nodeGroups,
        private readonly Auditor $auditor,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->auditor->getConfiguration()->disable();
        $now = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');

        $linuxCpu = $this->itemDefinitionFactory->create(
            'custom.cpu.usage', 'Example CPU usage', 'Development-only example Linux collector.', '%',
            ItemValueType::FLOAT, 60, 5,
            "awk '/^cpu / {usage=(\$2+\$4)*100/(\$2+\$4+\$5); print usage}' /proc/stat", null, true, $now,
        );
        $serviceStatus = $this->itemDefinitionFactory->create(
            'custom.service.status', 'Example service status', 'Development-only cross-platform collector.', null,
            ItemValueType::BOOLEAN, 30, 5,
            'systemctl is-active --quiet nginx && printf 1 || printf 0',
            "if ((Get-Service nginx).Status -eq 'Running') { 1 } else { 0 }", true, $now,
        );
        $windowsCpu = $this->itemDefinitionFactory->create(
            'custom.windows.cpu.usage', 'Example Windows CPU usage', 'Development-only example Windows collector.', '%',
            ItemValueType::FLOAT, 60, 5, null,
            '(Get-Counter \'\\Processor(_Total)\\% Processor Time\').CounterSamples.CookedValue', true, $now,
        );

        $linuxTemplate = $this->monitoringTemplateFactory->create('Example Linux', 'example-linux', 'Development-only Linux example.', true, $now);
        $linuxTemplate->replaceItemDefinitions([$linuxCpu, $serviceStatus], $now);
        $windowsTemplate = $this->monitoringTemplateFactory->create('Example Windows', 'example-windows', 'Development-only Windows example.', true, $now);
        $windowsTemplate->replaceItemDefinitions([$windowsCpu, $serviceStatus], $now);

        $linuxGroup = $this->nodeGroups->findOneBy(['name' => 'Linux Servers']);
        $windowsGroup = $this->nodeGroups->findOneBy(['name' => 'Windows Servers']);
        \assert($linuxGroup instanceof NodeGroup);
        \assert($windowsGroup instanceof NodeGroup);
        $linuxGroup->replaceMonitoringTemplates([$linuxTemplate], $now);
        $windowsGroup->replaceMonitoringTemplates([$windowsTemplate], $now);

        foreach ([$linuxCpu, $serviceStatus, $windowsCpu, $linuxTemplate, $windowsTemplate] as $entity) {
            $manager->persist($entity);
        }
        $manager->flush();
        $this->auditor->getConfiguration()->enable();
    }

    /** @return array<class-string<\Doctrine\Common\DataFixtures\FixtureInterface>> */
    public function getDependencies(): array
    {
        return [NodeGroupFixtures::class];
    }
}
