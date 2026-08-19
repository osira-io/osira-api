<?php

declare(strict_types=1);

namespace App\Service\Monitoring;

use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Repository\Monitoring\ItemDefinitionRepository;
use App\Repository\Monitoring\MonitoringTemplateRepository;
use App\Factory\Monitoring\ItemDefinitionFactory;
use App\Factory\Monitoring\MonitoringTemplateFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class MonitoringCatalogSynchronizer
{
    /** @var array<string, array{name: string, description: ?string, category: ?string, unit: ?string, valueType: ItemValueType, intervalSeconds: int, timeoutSeconds: ?int}> */
    private const array ITEM_CATALOG = [
        'system.cpu.usage' => ['name' => 'CPU usage', 'description' => 'Percentage of CPU utilization.', 'category' => 'System', 'unit' => '%', 'valueType' => ItemValueType::FLOAT, 'intervalSeconds' => 60, 'timeoutSeconds' => null],
        'system.memory.usage' => ['name' => 'Memory usage', 'description' => 'Percentage of used system memory.', 'category' => 'System', 'unit' => '%', 'valueType' => ItemValueType::FLOAT, 'intervalSeconds' => 60, 'timeoutSeconds' => null],
        'system.load.1' => ['name' => 'Load average 1m', 'description' => 'One-minute system load average.', 'category' => 'System', 'unit' => 'load', 'valueType' => ItemValueType::FLOAT, 'intervalSeconds' => 60, 'timeoutSeconds' => null],
        'system.load.5' => ['name' => 'Load average 5m', 'description' => 'Five-minute system load average.', 'category' => 'System', 'unit' => 'load', 'valueType' => ItemValueType::FLOAT, 'intervalSeconds' => 60, 'timeoutSeconds' => null],
        'system.load.15' => ['name' => 'Load average 15m', 'description' => 'Fifteen-minute system load average.', 'category' => 'System', 'unit' => 'load', 'valueType' => ItemValueType::FLOAT, 'intervalSeconds' => 60, 'timeoutSeconds' => null],
        'system.disk.usage' => ['name' => 'Disk usage', 'description' => 'Percentage of used disk space.', 'category' => 'System', 'unit' => '%', 'valueType' => ItemValueType::FLOAT, 'intervalSeconds' => 300, 'timeoutSeconds' => null],
        'system.network.rx' => ['name' => 'Network receive throughput', 'description' => 'Bytes received per second.', 'category' => 'System', 'unit' => 'bytes_per_second', 'valueType' => ItemValueType::INTEGER, 'intervalSeconds' => 60, 'timeoutSeconds' => null],
        'system.network.tx' => ['name' => 'Network transmit throughput', 'description' => 'Bytes sent per second.', 'category' => 'System', 'unit' => 'bytes_per_second', 'valueType' => ItemValueType::INTEGER, 'intervalSeconds' => 60, 'timeoutSeconds' => null],
        'system.uptime' => ['name' => 'System uptime', 'description' => 'Seconds since the machine booted.', 'category' => 'System', 'unit' => 'seconds', 'valueType' => ItemValueType::INTEGER, 'intervalSeconds' => 60, 'timeoutSeconds' => null],
        'container.cpu.usage' => ['name' => 'Container CPU usage', 'description' => 'Percentage of container CPU utilization.', 'category' => 'Containers', 'unit' => '%', 'valueType' => ItemValueType::FLOAT, 'intervalSeconds' => 60, 'timeoutSeconds' => null],
        'container.memory.usage' => ['name' => 'Container memory usage', 'description' => 'Memory used by the container.', 'category' => 'Containers', 'unit' => 'bytes', 'valueType' => ItemValueType::INTEGER, 'intervalSeconds' => 60, 'timeoutSeconds' => null],
        'container.network.rx' => ['name' => 'Container network receive throughput', 'description' => 'Bytes received per second by the container.', 'category' => 'Containers', 'unit' => 'bytes_per_second', 'valueType' => ItemValueType::INTEGER, 'intervalSeconds' => 60, 'timeoutSeconds' => null],
        'container.network.tx' => ['name' => 'Container network transmit throughput', 'description' => 'Bytes sent per second by the container.', 'category' => 'Containers', 'unit' => 'bytes_per_second', 'valueType' => ItemValueType::INTEGER, 'intervalSeconds' => 60, 'timeoutSeconds' => null],
        'container.restart.count' => ['name' => 'Container restart count', 'description' => 'Total number of container restarts.', 'category' => 'Containers', 'unit' => 'count', 'valueType' => ItemValueType::INTEGER, 'intervalSeconds' => 60, 'timeoutSeconds' => null],
        'container.state' => ['name' => 'Container state', 'description' => 'Current container lifecycle state.', 'category' => 'Containers', 'unit' => null, 'valueType' => ItemValueType::STRING, 'intervalSeconds' => 60, 'timeoutSeconds' => null],
    ];

    /** @var array<string, array{name: string, description: ?string, itemKeys: list<string>}> */
    private const array TEMPLATE_CATALOG = [
        'linux-base' => ['name' => 'Linux Base', 'description' => 'Baseline Linux host monitoring.', 'itemKeys' => ['system.cpu.usage', 'system.memory.usage', 'system.load.1', 'system.load.5', 'system.load.15', 'system.disk.usage', 'system.network.rx', 'system.network.tx', 'system.uptime']],
        'windows-base' => ['name' => 'Windows Base', 'description' => 'Baseline Windows host monitoring.', 'itemKeys' => ['system.cpu.usage', 'system.memory.usage', 'system.disk.usage', 'system.network.rx', 'system.network.tx', 'system.uptime']],
        'docker-base' => ['name' => 'Docker Base', 'description' => 'Baseline Docker container monitoring.', 'itemKeys' => ['container.cpu.usage', 'container.memory.usage', 'container.network.rx', 'container.network.tx', 'container.restart.count', 'container.state']],
    ];

    public function __construct(
        private ItemDefinitionRepository $itemDefinitions,
        private MonitoringTemplateRepository $monitoringTemplates,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private ItemDefinitionFactory $itemDefinitionFactory,
        private MonitoringTemplateFactory $monitoringTemplateFactory,
    ) {
    }

    public function synchronize(): void
    {
        $now = $this->clock->now();
        $itemsByKey = [];

        foreach (self::ITEM_CATALOG as $key => $definition) {
            $itemDefinition = $this->itemDefinitions->findOneBy(['key' => $key]);
            if (!$itemDefinition instanceof ItemDefinition) {
                $itemDefinition = $this->itemDefinitionFactory->create(
                    $key,
                    $definition['name'],
                    $definition['description'],
                    $definition['category'],
                    $definition['unit'],
                    $definition['valueType'],
                    $definition['intervalSeconds'],
                    $definition['timeoutSeconds'],
                    true,
                    true,
                    $now,
                );
                $this->entityManager->persist($itemDefinition);
            } else {
                $itemDefinition->synchronize(
                    $definition['name'],
                    $definition['description'],
                    $definition['category'],
                    $definition['unit'],
                    $definition['valueType'],
                    $definition['intervalSeconds'],
                    $definition['timeoutSeconds'],
                    true,
                    $now,
                );
            }

            $itemsByKey[$key] = $itemDefinition;
        }

        foreach (self::TEMPLATE_CATALOG as $slug => $definition) {
            $monitoringTemplate = $this->monitoringTemplates->findOneBy(['slug' => $slug]);
            if (!$monitoringTemplate instanceof MonitoringTemplate) {
                $monitoringTemplate = $this->monitoringTemplateFactory->create($definition['name'], $slug, $definition['description'], true, true, $now);
                $this->entityManager->persist($monitoringTemplate);
            } else {
                $monitoringTemplate->synchronize($definition['name'], $definition['description'], true, $now);
            }

            $monitoringTemplate->replaceItemDefinitions(array_map(
                static fn (string $key): ItemDefinition => $itemsByKey[$key],
                $definition['itemKeys'],
            ), $now);
        }

        $this->entityManager->flush();
    }
}
