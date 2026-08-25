<?php

declare(strict_types=1);

namespace App\Tests\Integration\DataFixtures;

use App\DataFixtures\Rbac\RbacFixtures;
use App\DataFixtures\User\UserFixtures;
use App\Entity\Agent\Agent;
use App\Entity\Maintenance\MaintenanceWindow;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Entity\Rbac\Role;
use App\Entity\User\User;
use App\Repository\Node\NodeRepository;
use App\Repository\NodeGroup\NodeGroupRepository;
use App\Repository\Rbac\RoleRepository;
use App\Repository\User\UserRepository;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Loads the development fixtures against a real database and asserts on the resulting
 * data, not on DoctrineFixturesBundle itself. This is the only place the fixture dataset's
 * shape is verified, so functional tests stay independent of it (per CONTRIBUTING.md).
 */
final class DevFixturesTest extends KernelTestCase
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

        $this->loadFixtures();
    }

    public function testAllFourSystemRolesAndTheCustomRoleExist(): void
    {
        $roles = $this->entityManager->getRepository(Role::class)->findAll();
        $slugs = array_map(static fn (Role $role): string => $role->slug(), $roles);
        sort($slugs);

        self::assertSame(['admin', 'noc-operator', 'operator', 'super-admin', 'viewer'], $slugs);
    }

    public function testCustomRoleHasOnlyItsIntendedPermissions(): void
    {
        $role = self::getContainer()->get(RoleRepository::class)->findOneBy(['slug' => RbacFixtures::NOC_OPERATOR_ROLE_SLUG]);
        self::assertInstanceOf(Role::class, $role);

        $codes = array_map(static fn ($permission): string => $permission->code(), $role->permissions()->toArray());
        sort($codes);

        self::assertSame(['audit_logs.read', 'metrics.read', 'node_groups.read', 'nodes.read', 'nodes.update'], $codes);
    }

    public function testExpectedDevUsersExistWithHashedPasswords(): void
    {
        $repository = self::getContainer()->get(UserRepository::class);

        foreach (['admin@osira.local', 'admin2@osira.local', 'operator@osira.local', 'viewer@osira.local', 'noc@osira.local'] as $email) {
            $user = $repository->findOneByEmail($email);
            self::assertInstanceOf(User::class, $user, \sprintf('Expected a dev user for "%s".', $email));
            self::assertNotSame(UserFixtures::DEV_PASSWORD, $user->getPassword());
            self::assertStringStartsWith('$2y$', $user->getPassword());
            self::assertSame('en', $user->locale());
        }
    }

    public function testEachDevUserIsAssignedItsExpectedRole(): void
    {
        $repository = self::getContainer()->get(UserRepository::class);

        /** @var array<string, string> $expected */
        $expected = [
            'admin@osira.local' => 'super-admin',
            'admin2@osira.local' => 'admin',
            'operator@osira.local' => 'operator',
            'viewer@osira.local' => 'viewer',
            'noc@osira.local' => RbacFixtures::NOC_OPERATOR_ROLE_SLUG,
        ];

        foreach ($expected as $email => $slug) {
            $user = $repository->findOneByEmail($email);
            self::assertInstanceOf(User::class, $user);
            $roleSlugs = array_map(static fn (Role $role): string => $role->slug(), $user->businessRoles()->toArray());
            self::assertSame([$slug], $roleSlugs);
        }
    }

    public function testExpectedNodeGroupsExist(): void
    {
        $groups = self::getContainer()->get(NodeGroupRepository::class)->findAll();
        $names = array_map(static fn (NodeGroup $group): string => $group->name(), $groups);
        sort($names);

        self::assertSame([
            'Critical Infrastructure', 'Databases', 'Homelab', 'Linux Servers',
            'Production', 'Staging', 'Web Servers', 'Windows Servers',
        ], $names);
    }

    public function testTwelveNodesExistWithCoherentEnvironmentsAndTags(): void
    {
        $nodes = self::getContainer()->get(NodeRepository::class)->findAll();
        self::assertCount(12, $nodes);

        foreach ($nodes as $node) {
            self::assertContains($node->os(), ['linux', 'windows']);
            self::assertContains($node->environment(), ['production', 'staging', 'office', 'homelab']);
            self::assertNotEmpty($node->tags());
            self::assertNotEmpty($node->groups());
        }
    }

    public function testProductionWebNodeBelongsToItsExpectedGroups(): void
    {
        $node = self::getContainer()->get(NodeRepository::class)->findOneBy(['hostname' => 'prod-web-01']);
        self::assertInstanceOf(Node::class, $node);

        $groupNames = array_map(static fn (NodeGroup $group): string => $group->name(), $node->groups()->toArray());
        sort($groupNames);

        self::assertSame(['Critical Infrastructure', 'Linux Servers', 'Production', 'Web Servers'], $groupNames);
        self::assertSame(['web', 'nginx', 'critical'], $node->tags());
        self::assertSame('production', $node->environment());
    }

    public function testWindowsNodesBelongOnlyToTheWindowsServersGroup(): void
    {
        $node = self::getContainer()->get(NodeRepository::class)->findOneBy(['hostname' => 'win-office-01']);
        self::assertInstanceOf(Node::class, $node);

        $groupNames = array_map(static fn (NodeGroup $group): string => $group->name(), $node->groups()->toArray());

        self::assertSame(['Windows Servers'], $groupNames);
        self::assertSame('windows', $node->os());
    }

    public function testAgentsExistOnlyForLinuxNodesAndCarryNoCredential(): void
    {
        // Eagerly joins the node so the assertion never triggers a lazy-proxy re-fetch of an
        // already-hydrated Node, which trips Doctrine's readonly-id hydration guard.
        $result = $this->entityManager->createQueryBuilder()
            ->select('agent', 'node')
            ->from(Agent::class, 'agent')
            ->join('agent.node', 'node')
            ->getQuery()
            ->getResult();
        \assert(\is_array($result));
        self::assertCount(10, $result);

        foreach ($result as $agent) {
            self::assertInstanceOf(Agent::class, $agent);
            self::assertSame('linux', $agent->node()->os());
            self::assertSame('0.1.0-dev', $agent->version());
        }

        self::assertSame(0, $this->countRows('agent_credentials'), 'Fixtures must never create a usable AgentCredential.');
    }

    public function testMonitoringFixturesAreClearlyScopedDevelopmentExamples(): void
    {
        $templates = $this->entityManager->getRepository(MonitoringTemplate::class)->findAll();
        self::assertCount(2, $templates);
        self::assertSame(3, $this->entityManager->getRepository(ItemDefinition::class)->count([]));

        $linuxBase = $this->entityManager->getRepository(MonitoringTemplate::class)->findOneBy(['slug' => 'example-linux']);
        self::assertInstanceOf(MonitoringTemplate::class, $linuxBase);
        self::assertCount(2, $linuxBase->itemDefinitions());

        $linuxServers = self::getContainer()->get(NodeGroupRepository::class)->findOneBy(['name' => 'Linux Servers']);
        self::assertInstanceOf(NodeGroup::class, $linuxServers);
        self::assertSame(['example-linux'], array_map(static fn (MonitoringTemplate $template): string => $template->slug(), $linuxServers->monitoringTemplates()->toArray()));
    }

    public function testMaintenanceFixturesAreClearlyScopedDevelopmentExamples(): void
    {
        $windows = $this->entityManager->getRepository(MaintenanceWindow::class)->findAll();
        self::assertCount(2, $windows);

        $names = array_map(static fn (MaintenanceWindow $window): string => $window->name(), $windows);
        sort($names);
        self::assertSame(['Example production upgrade', 'Example web node patch'], $names);
    }

    public function testNoRawSecretIsPresentInTheSeededData(): void
    {
        self::assertSame(0, $this->countRows('enrollment_tokens'), 'Fixtures must not create enrollment tokens.');

        $connection = $this->entityManager->getConnection();
        $nodeGroupPayload = json_encode($connection->fetchAllAssociative('SELECT name, description FROM node_groups'), \JSON_THROW_ON_ERROR);
        $nodePayload = json_encode($connection->fetchAllAssociative('SELECT hostname, tags FROM nodes'), \JSON_THROW_ON_ERROR);
        foreach ([$nodeGroupPayload, $nodePayload] as $payload) {
            self::assertStringNotContainsString(UserFixtures::DEV_PASSWORD, $payload);
            self::assertStringNotContainsString('secret', strtolower($payload));
        }
    }

    public function testReloadingFixturesIsIdempotent(): void
    {
        $before = $this->rowCountsByTable();

        $this->entityManager->clear();
        $this->loadFixtures();

        self::assertSame($before, $this->rowCountsByTable());
    }

    /** @return array<string, int> */
    private function rowCountsByTable(): array
    {
        return [
            'users' => $this->countRows('users'),
            'roles' => $this->countRows('roles'),
            'nodes' => $this->countRows('nodes'),
            'node_groups' => $this->countRows('node_groups'),
            'monitoring_templates' => $this->countRows('monitoring_templates'),
            'item_definitions' => $this->countRows('item_definitions'),
            'maintenance_windows' => $this->countRows('maintenance_windows'),
            'agents' => $this->countRows('agents'),
        ];
    }

    private function countRows(string $table): int
    {
        $value = $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM '.$table);
        \assert(\is_int($value) || \is_string($value));

        return (int) $value;
    }

    private function loadFixtures(): void
    {
        $loader = self::getContainer()->get('doctrine.fixtures.loader');
        $executor = new ORMExecutor($this->entityManager, new ORMPurger($this->entityManager));
        $executor->execute($loader->getFixtures());
    }
}
