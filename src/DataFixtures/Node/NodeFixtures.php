<?php

declare(strict_types=1);

namespace App\DataFixtures\Node;

use App\DataFixtures\NodeGroup\NodeGroupFixtures;
use App\Entity\NodeGroup\NodeGroup;
use App\Service\Node\Factory\NodeFactory;
use DH\Auditor\Auditor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** Seeds a realistic, deterministic fleet of nodes spanning every {@see NodeGroupFixtures} group. */
final class NodeFixtures extends Fixture implements DependentFixtureInterface
{
    public const string PROD_WEB_01_REFERENCE = 'node-prod-web-01';

    /**
     * @var list<array{
     *     reference: string,
     *     hostname: string,
     *     displayName: string,
     *     os: string,
     *     architecture: string,
     *     environment: string,
     *     tags: list<string>,
     *     groups: list<string>,
     * }>
     */
    private const array NODES = [
        [
            'reference' => self::PROD_WEB_01_REFERENCE,
            'hostname' => 'prod-web-01', 'displayName' => 'Production Web 01', 'os' => 'linux', 'architecture' => 'x86_64',
            'environment' => 'production', 'tags' => ['web', 'nginx', 'critical'],
            'groups' => [NodeGroupFixtures::PRODUCTION_REFERENCE, NodeGroupFixtures::LINUX_SERVERS_REFERENCE, NodeGroupFixtures::WEB_SERVERS_REFERENCE, NodeGroupFixtures::CRITICAL_INFRASTRUCTURE_REFERENCE],
        ],
        [
            'reference' => 'node-prod-web-02',
            'hostname' => 'prod-web-02', 'displayName' => 'Production Web 02', 'os' => 'linux', 'architecture' => 'x86_64',
            'environment' => 'production', 'tags' => ['web', 'nginx'],
            'groups' => [NodeGroupFixtures::PRODUCTION_REFERENCE, NodeGroupFixtures::LINUX_SERVERS_REFERENCE, NodeGroupFixtures::WEB_SERVERS_REFERENCE],
        ],
        [
            'reference' => 'node-prod-api-01',
            'hostname' => 'prod-api-01', 'displayName' => 'Production API 01', 'os' => 'linux', 'architecture' => 'x86_64',
            'environment' => 'production', 'tags' => ['api', 'critical'],
            'groups' => [NodeGroupFixtures::PRODUCTION_REFERENCE, NodeGroupFixtures::LINUX_SERVERS_REFERENCE, NodeGroupFixtures::CRITICAL_INFRASTRUCTURE_REFERENCE],
        ],
        [
            'reference' => 'node-prod-db-01',
            'hostname' => 'prod-db-01', 'displayName' => 'PostgreSQL Production', 'os' => 'linux', 'architecture' => 'x86_64',
            'environment' => 'production', 'tags' => ['database', 'postgresql', 'critical'],
            'groups' => [NodeGroupFixtures::PRODUCTION_REFERENCE, NodeGroupFixtures::LINUX_SERVERS_REFERENCE, NodeGroupFixtures::DATABASES_REFERENCE, NodeGroupFixtures::CRITICAL_INFRASTRUCTURE_REFERENCE],
        ],
        [
            'reference' => 'node-prod-cache-01',
            'hostname' => 'prod-cache-01', 'displayName' => 'Production Cache 01', 'os' => 'linux', 'architecture' => 'x86_64',
            'environment' => 'production', 'tags' => ['cache', 'redis'],
            'groups' => [NodeGroupFixtures::PRODUCTION_REFERENCE, NodeGroupFixtures::LINUX_SERVERS_REFERENCE],
        ],
        [
            'reference' => 'node-staging-web-01',
            'hostname' => 'staging-web-01', 'displayName' => 'Staging Web 01', 'os' => 'linux', 'architecture' => 'x86_64',
            'environment' => 'staging', 'tags' => ['web', 'nginx'],
            'groups' => [NodeGroupFixtures::STAGING_REFERENCE, NodeGroupFixtures::LINUX_SERVERS_REFERENCE, NodeGroupFixtures::WEB_SERVERS_REFERENCE],
        ],
        [
            'reference' => 'node-staging-api-01',
            'hostname' => 'staging-api-01', 'displayName' => 'Staging API 01', 'os' => 'linux', 'architecture' => 'x86_64',
            'environment' => 'staging', 'tags' => ['api'],
            'groups' => [NodeGroupFixtures::STAGING_REFERENCE, NodeGroupFixtures::LINUX_SERVERS_REFERENCE],
        ],
        [
            'reference' => 'node-staging-db-01',
            'hostname' => 'staging-db-01', 'displayName' => 'Staging Database', 'os' => 'linux', 'architecture' => 'x86_64',
            'environment' => 'staging', 'tags' => ['database', 'postgresql'],
            'groups' => [NodeGroupFixtures::STAGING_REFERENCE, NodeGroupFixtures::LINUX_SERVERS_REFERENCE, NodeGroupFixtures::DATABASES_REFERENCE],
        ],
        [
            'reference' => 'node-win-office-01',
            'hostname' => 'win-office-01', 'displayName' => 'Office Workstation 01', 'os' => 'windows', 'architecture' => 'x86_64',
            'environment' => 'office', 'tags' => ['windows', 'workstation'],
            'groups' => [NodeGroupFixtures::WINDOWS_SERVERS_REFERENCE],
        ],
        [
            'reference' => 'node-win-office-02',
            'hostname' => 'win-office-02', 'displayName' => 'Office Workstation 02', 'os' => 'windows', 'architecture' => 'x86_64',
            'environment' => 'office', 'tags' => ['windows', 'workstation'],
            'groups' => [NodeGroupFixtures::WINDOWS_SERVERS_REFERENCE],
        ],
        [
            'reference' => 'node-homelab-01',
            'hostname' => 'homelab-01', 'displayName' => 'Homelab Server', 'os' => 'linux', 'architecture' => 'x86_64',
            'environment' => 'homelab', 'tags' => ['homelab', 'experimental'],
            'groups' => [NodeGroupFixtures::HOMELAB_REFERENCE, NodeGroupFixtures::LINUX_SERVERS_REFERENCE],
        ],
        [
            'reference' => 'node-homelab-nas-01',
            'hostname' => 'homelab-nas-01', 'displayName' => 'Homelab NAS', 'os' => 'linux', 'architecture' => 'x86_64',
            'environment' => 'homelab', 'tags' => ['storage', 'nas'],
            'groups' => [NodeGroupFixtures::HOMELAB_REFERENCE, NodeGroupFixtures::LINUX_SERVERS_REFERENCE],
        ],
    ];

    public function __construct(
        private readonly Auditor $auditor,
        private readonly NodeFactory $nodeFactory,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->auditor->getConfiguration()->disable();

        $now = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        foreach (self::NODES as $definition) {
            $node = $this->nodeFactory->create($definition['hostname'], $definition['displayName'], $definition['os'], $definition['architecture'], $now, $now);
            $node->updateBusinessProperties($definition['displayName'], $definition['environment'], $definition['tags']);
            $node->replaceGroups(array_map(
                fn (string $reference): NodeGroup => $this->getReference($reference, NodeGroup::class),
                $definition['groups'],
            ));
            $manager->persist($node);
            $this->addReference($definition['reference'], $node);
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
