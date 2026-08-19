<?php

declare(strict_types=1);

namespace App\DataFixtures\NodeGroup;

use App\Entity\NodeGroup\NodeGroup;
use DH\Auditor\Auditor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/** Seeds a realistic, deterministic set of node groups for development. */
final class NodeGroupFixtures extends Fixture
{
    public const string PRODUCTION_REFERENCE = 'node-group-production';
    public const string STAGING_REFERENCE = 'node-group-staging';
    public const string LINUX_SERVERS_REFERENCE = 'node-group-linux-servers';
    public const string WINDOWS_SERVERS_REFERENCE = 'node-group-windows-servers';
    public const string DATABASES_REFERENCE = 'node-group-databases';
    public const string WEB_SERVERS_REFERENCE = 'node-group-web-servers';
    public const string CRITICAL_INFRASTRUCTURE_REFERENCE = 'node-group-critical-infrastructure';
    public const string HOMELAB_REFERENCE = 'node-group-homelab';

    /** @var list<array{reference: string, name: string, description: string}> */
    private const array GROUPS = [
        ['reference' => self::PRODUCTION_REFERENCE, 'name' => 'Production', 'description' => 'Nodes serving live production traffic.'],
        ['reference' => self::STAGING_REFERENCE, 'name' => 'Staging', 'description' => 'Pre-production nodes used to validate releases.'],
        ['reference' => self::LINUX_SERVERS_REFERENCE, 'name' => 'Linux Servers', 'description' => 'Nodes running a Linux operating system.'],
        ['reference' => self::WINDOWS_SERVERS_REFERENCE, 'name' => 'Windows Servers', 'description' => 'Nodes running a Windows operating system.'],
        ['reference' => self::DATABASES_REFERENCE, 'name' => 'Databases', 'description' => 'Nodes hosting database engines.'],
        ['reference' => self::WEB_SERVERS_REFERENCE, 'name' => 'Web Servers', 'description' => 'Nodes serving HTTP traffic.'],
        ['reference' => self::CRITICAL_INFRASTRUCTURE_REFERENCE, 'name' => 'Critical Infrastructure', 'description' => 'Nodes whose failure has a severe operational impact.'],
        ['reference' => self::HOMELAB_REFERENCE, 'name' => 'Homelab', 'description' => 'Personal and experimental nodes outside the managed fleet.'],
    ];

    public function __construct(private readonly Auditor $auditor)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $this->auditor->getConfiguration()->disable();

        $now = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        foreach (self::GROUPS as $definition) {
            $group = new NodeGroup($definition['name'], $definition['description'], $now);
            $manager->persist($group);
            $this->addReference($definition['reference'], $group);
        }
        $manager->flush();

        $this->auditor->getConfiguration()->enable();
    }
}
