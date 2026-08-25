<?php

declare(strict_types=1);

namespace App\DataFixtures\Maintenance;

use App\DataFixtures\Node\NodeFixtures;
use App\DataFixtures\NodeGroup\NodeGroupFixtures;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Factory\Maintenance\MaintenanceWindowFactory;
use DH\Auditor\Auditor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** Seeds deterministic example maintenance windows for development only. */
final class MaintenanceWindowFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly Auditor $auditor,
        private readonly MaintenanceWindowFactory $factory,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->auditor->getConfiguration()->disable();

        $now = new \DateTimeImmutable('2026-08-25T12:00:00+00:00');

        $production = $this->factory->create(
            'Example production upgrade',
            'Development example window inherited through the Production node group.',
            new \DateTimeImmutable('2026-08-25T10:00:00+00:00'),
            new \DateTimeImmutable('2026-08-25T18:00:00+00:00'),
            true,
            $now,
        );
        $production->replaceNodeGroups([$this->getReference(NodeGroupFixtures::PRODUCTION_REFERENCE, NodeGroup::class)], $now);
        $manager->persist($production);

        $nodeSpecific = $this->factory->create(
            'Example web node patch',
            'Development example window assigned directly to one node.',
            new \DateTimeImmutable('2026-09-01T01:00:00+00:00'),
            new \DateTimeImmutable('2026-09-01T03:00:00+00:00'),
            true,
            $now,
        );
        $nodeSpecific->replaceNodes([$this->getReference(NodeFixtures::PROD_WEB_01_REFERENCE, Node::class)], $now);
        $manager->persist($nodeSpecific);

        $manager->flush();

        $this->auditor->getConfiguration()->enable();
    }

    /** @return array<class-string<\Doctrine\Common\DataFixtures\FixtureInterface>> */
    public function getDependencies(): array
    {
        return [NodeFixtures::class, NodeGroupFixtures::class];
    }
}
