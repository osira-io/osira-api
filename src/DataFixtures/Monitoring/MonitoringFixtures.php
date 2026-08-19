<?php

declare(strict_types=1);

namespace App\DataFixtures\Monitoring;

use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Repository\Monitoring\MonitoringTemplateRepository;
use App\Repository\Node\NodeRepository;
use App\Repository\NodeGroup\NodeGroupRepository;
use App\Service\Monitoring\MonitoringCatalogSynchronizer;
use DH\Auditor\Auditor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class MonitoringFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly MonitoringCatalogSynchronizer $synchronizer,
        private readonly MonitoringTemplateRepository $monitoringTemplates,
        private readonly NodeRepository $nodes,
        private readonly NodeGroupRepository $nodeGroups,
        private readonly Auditor $auditor,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->auditor->getConfiguration()->disable();

        $this->synchronizer->synchronize();

        $now = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $linuxBase = $this->templateBySlug('linux-base');
        $windowsBase = $this->templateBySlug('windows-base');
        $dockerBase = $this->templateBySlug('docker-base');

        $linuxServers = $this->groupByName('Linux Servers');
        $linuxServers->replaceMonitoringTemplates([$linuxBase], $now);

        $windowsServers = $this->groupByName('Windows Servers');
        $windowsServers->replaceMonitoringTemplates([$windowsBase], $now);

        $webServers = $this->groupByName('Web Servers');
        $webServers->replaceMonitoringTemplates([$dockerBase], $now);

        $prodCache = $this->nodeByHostname('prod-cache-01');
        $prodCache->replaceMonitoringTemplates([$dockerBase]);

        $manager->flush();

        $this->auditor->getConfiguration()->enable();
    }

    /** @return array<class-string<\Doctrine\Common\DataFixtures\FixtureInterface>> */
    public function getDependencies(): array
    {
        return [
            \App\DataFixtures\NodeGroup\NodeGroupFixtures::class,
            \App\DataFixtures\Node\NodeFixtures::class,
        ];
    }

    private function templateBySlug(string $slug): MonitoringTemplate
    {
        $monitoringTemplate = $this->monitoringTemplates->findOneBy(['slug' => $slug]);
        \assert($monitoringTemplate instanceof MonitoringTemplate);

        return $monitoringTemplate;
    }

    private function groupByName(string $name): NodeGroup
    {
        $nodeGroup = $this->nodeGroups->findOneBy(['name' => $name]);
        \assert($nodeGroup instanceof NodeGroup);

        return $nodeGroup;
    }

    private function nodeByHostname(string $hostname): Node
    {
        $node = $this->nodes->findOneBy(['hostname' => $hostname]);
        \assert($node instanceof Node);

        return $node;
    }
}
