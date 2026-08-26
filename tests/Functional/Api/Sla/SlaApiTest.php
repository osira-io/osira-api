<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Sla;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Alert\AlertOperator;
use App\Entity\Alert\AlertRule;
use App\Entity\Alert\AlertRuleImpactType;
use App\Entity\Alert\AlertSeverity;
use App\Entity\Maintenance\MaintenanceWindow;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Entity\User\User;
use App\Factory\Incident\IncidentFactory;
use App\Repository\Node\NodeRepository;
use App\Repository\Sla\SlaRepository;
use App\Security\Rbac\SystemRole;
use App\Tests\Functional\Support\RbacTestTrait;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Ulid;

final class SlaApiTest extends ApiTestCase
{
    use RbacTestTrait;

    private const string PASSWORD = 'correct horse battery staple';

    protected static ?bool $alwaysBootKernel = true;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $entityManager = $this->entityManager();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $this->initializeRbac();
        self::ensureKernelShutdown();
    }

    public function testCrudReportScopeAggregationAndAudit(): void
    {
        $client = self::createJsonClient();
        $adminToken = $this->login($client, $this->createUser('sla-admin@example.com', SystemRole::ADMIN)->getUserIdentifier());
        [$group, $firstNode, $secondNode] = $this->createReportScope();
        self::assertSame(2, $this->nodeGroupNodesCount());

        $created = $client->request('POST', '/api/slas', [
            'auth_bearer' => $adminToken,
            'json' => [
                'name' => 'Production SLA',
                'description' => 'Availability objective',
                'targetPercentage' => 99.9,
                'periodType' => 'rolling_30_days',
                'excludeMaintenance' => true,
                'nodeIds' => [(string) $firstNode->id()],
                'nodeGroupIds' => [(string) $group->id()],
            ],
        ])->toArray();

        $slaId = $created['id'] ?? null;
        self::assertIsString($slaId);
        self::assertSame(99.9, $created['targetPercentage'] ?? null);
        self::assertCount(1, self::arrayValue($created, 'nodes'));
        self::assertCount(1, self::arrayValue($created, 'nodeGroups'));
        $managedSla = self::getContainer()->get(SlaRepository::class)->find(new Ulid($slaId));
        self::assertNotNull($managedSla);
        self::assertCount(2, self::getContainer()->get(NodeRepository::class)->findForGroups(array_values($managedSla->nodeGroups()->toArray())));

        $report = $client->request('GET', '/api/slas/'.$slaId.'/report?from=2026-08-26T10%3A00%3A00%2B00%3A00&to=2026-08-26T11%3A00%3A00%2B00%3A00', [
            'auth_bearer' => $adminToken,
        ])->toArray();

        self::assertSame(7200, $report['totalPeriodSeconds'] ?? null, 'The directly-scoped node must be deduplicated from its NodeGroup.');
        self::assertSame(600, $report['excludedMaintenanceSeconds'] ?? null);
        self::assertSame(6600, $report['eligibleSeconds'] ?? null);
        self::assertSame(2400, $report['downtimeSeconds'] ?? null);
        self::assertSame(4200, $report['uptimeSeconds'] ?? null);
        self::assertSame(63.63636, $report['availabilityPercentage'] ?? null);
        self::assertFalse($report['compliant'] ?? true);
        self::assertSame('non_compliant', $report['status'] ?? null);
        $nodeReports = self::arrayValue($report, 'nodes');
        self::assertCount(2, $nodeReports);
        self::assertSame([(string) $firstNode->id(), (string) $secondNode->id()], array_column($nodeReports, 'id'));
        self::assertSame([83.33333, 40.0], array_column($nodeReports, 'availabilityPercentage'));
        self::assertSame(['non_compliant', 'non_compliant'], array_column($nodeReports, 'status'));

        $client->request('GET', '/api/slas/'.$slaId.'/report?from=2026-08-26T11%3A00%3A00%2B00%3A00&to=2026-08-26T10%3A00%3A00%2B00%3A00', [
            'auth_bearer' => $adminToken,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $updated = $client->request('PATCH', '/api/slas/'.$slaId, [
            'auth_bearer' => $adminToken,
            'json' => ['targetPercentage' => 50.0, 'description' => null, 'excludeMaintenance' => false, 'nodeIds' => []],
        ])->toArray();
        self::assertSame(50.0, $updated['targetPercentage'] ?? null);
        self::assertNull($updated['description'] ?? null);
        self::assertCount(0, self::arrayValue($updated, 'nodes'));

        $reportWithMaintenance = $client->request('GET', '/api/slas/'.$slaId.'/report?from=2026-08-26T10%3A00%3A00%2B00%3A00&to=2026-08-26T11%3A00%3A00%2B00%3A00', [
            'auth_bearer' => $adminToken,
        ])->toArray();
        self::assertSame(0, $reportWithMaintenance['excludedMaintenanceSeconds'] ?? null);
        self::assertSame(3000, $reportWithMaintenance['downtimeSeconds'] ?? null);
        self::assertSame(58.33333, $reportWithMaintenance['availabilityPercentage'] ?? null);
        self::assertTrue($reportWithMaintenance['compliant'] ?? false);
        self::assertSame('compliant', $reportWithMaintenance['status'] ?? null);

        $collection = $client->request('GET', '/api/slas', ['auth_bearer' => $adminToken])->toArray();
        self::assertCount(1, self::arrayValue($collection, 'items'));

        $client->request('DELETE', '/api/slas/'.$slaId, ['auth_bearer' => $adminToken]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame(0, self::getContainer()->get(SlaRepository::class)->count([]));

        $auditRows = self::getContainer()->get(Connection::class)->fetchAllAssociative('SELECT type, diffs FROM audit_slas WHERE object_id = ? ORDER BY id ASC', [$slaId]);
        self::assertGreaterThanOrEqual(3, \count($auditRows));
        self::assertContains('insert', array_column($auditRows, 'type'));
        self::assertContains('update', array_column($auditRows, 'type'));
        self::assertContains('remove', array_column($auditRows, 'type'));
        $encodedAuditRows = json_encode($auditRows, \JSON_THROW_ON_ERROR);
        self::assertStringContainsString('targetPercentage', $encodedAuditRows);
        self::assertStringContainsString('nodes', $encodedAuditRows);
        self::assertStringContainsString('nodeGroups', $encodedAuditRows);
    }

    public function testSlaDedicatedPermissionsAndScopeValidation(): void
    {
        $client = self::createJsonClient();
        $viewerToken = $this->login($client, $this->createUser('sla-viewer@example.com', SystemRole::VIEWER)->getUserIdentifier());
        $operatorToken = $this->login($client, $this->createUser('sla-operator@example.com', SystemRole::OPERATOR)->getUserIdentifier());
        $now = new \DateTimeImmutable('2026-08-26T10:00:00+00:00');
        $node = new Node('sla-rbac-node', null, 'linux', 'amd64', $now, $now);
        $this->entityManager()->persist($node);
        $this->entityManager()->flush();

        $client->request('GET', '/api/slas', ['auth_bearer' => $viewerToken]);
        self::assertResponseIsSuccessful();
        $client->request('POST', '/api/slas', [
            'auth_bearer' => $viewerToken,
            'json' => ['name' => 'Denied', 'targetPercentage' => 99.9, 'nodeIds' => [(string) $node->id()]],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $client->request('POST', '/api/slas', [
            'auth_bearer' => $operatorToken,
            'json' => ['name' => 'Missing scope', 'targetPercentage' => 99.9],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $created = $client->request('POST', '/api/slas', [
            'auth_bearer' => $operatorToken,
            'json' => ['name' => 'Operator SLA', 'targetPercentage' => 99.95, 'nodeIds' => [(string) $node->id()]],
        ])->toArray();
        $slaId = $created['id'] ?? null;
        self::assertIsString($slaId);
        $client->request('PATCH', '/api/slas/'.$slaId, ['auth_bearer' => $operatorToken, 'json' => ['isEnabled' => false]]);
        self::assertResponseIsSuccessful();
        $client->request('DELETE', '/api/slas/'.$slaId, ['auth_bearer' => $operatorToken]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testOnlyAvailabilityImpactIncidentsCountTowardDowntime(): void
    {
        $client = self::createJsonClient();
        $adminToken = $this->login($client, $this->createUser('sla-impact-admin@example.com', SystemRole::ADMIN)->getUserIdentifier());
        $from = new \DateTimeImmutable('2026-08-26T10:00:00+00:00');
        $node = new Node('impact-node', null, 'linux', 'amd64', $from, $from);
        $item = new ItemDefinition('custom.sla.impact.probe', 'Impact probe', null, null, ItemValueType::FLOAT, 60, 5, 'printf 1', null, true, $from);
        $availabilityRule = new AlertRule('Availability rule', 'Service unreachable', $item, AlertOperator::GT, '0', null, 60, 1, AlertSeverity::CRITICAL, AlertRuleImpactType::AVAILABILITY, true, $from);
        $performanceRule = new AlertRule('Performance rule', 'CPU high', $item, AlertOperator::GT, '0', null, 60, 1, AlertSeverity::WARNING, AlertRuleImpactType::PERFORMANCE, true, $from);
        $informationalRule = new AlertRule('Informational rule', 'Backup age', $item, AlertOperator::GT, '0', null, 60, 1, AlertSeverity::INFO, AlertRuleImpactType::INFORMATIONAL, true, $from);

        $factory = new IncidentFactory();
        $availabilityIncident = $factory->create($node, $availabilityRule, ['probe' => 'availability'], '1', $from);
        $availabilityIncident->resolve('0', new \DateTimeImmutable('2026-08-26T10:20:00+00:00'));
        $performanceIncident = $factory->create($node, $performanceRule, ['probe' => 'performance'], '1', new \DateTimeImmutable('2026-08-26T10:10:00+00:00'));
        $performanceIncident->resolve('0', new \DateTimeImmutable('2026-08-26T10:40:00+00:00'));
        $informationalIncident = $factory->create($node, $informationalRule, ['probe' => 'informational'], '1', new \DateTimeImmutable('2026-08-26T10:30:00+00:00'));
        $informationalIncident->resolve('0', new \DateTimeImmutable('2026-08-26T10:50:00+00:00'));

        $entityManager = $this->entityManager();
        foreach ([$node, $item, $availabilityRule, $performanceRule, $informationalRule, $availabilityIncident, $performanceIncident, $informationalIncident] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        $created = $client->request('POST', '/api/slas', [
            'auth_bearer' => $adminToken,
            'json' => ['name' => 'Impact SLA', 'targetPercentage' => 99.9, 'nodeIds' => [(string) $node->id()]],
        ])->toArray();
        $slaId = $created['id'] ?? null;
        self::assertIsString($slaId);

        $report = $client->request('GET', '/api/slas/'.$slaId.'/report?from=2026-08-26T10%3A00%3A00%2B00%3A00&to=2026-08-26T11%3A00%3A00%2B00%3A00', [
            'auth_bearer' => $adminToken,
        ])->toArray();

        self::assertSame(1200, $report['downtimeSeconds'] ?? null, 'Only the availability-impact incident (10:00-10:20) must count as downtime.');
        self::assertSame(2400, $report['uptimeSeconds'] ?? null);
        self::assertSame(66.66667, $report['availabilityPercentage'] ?? null);
        self::assertSame('non_compliant', $report['status'] ?? null);
    }

    public function testNodeScopedToEmptyGroupYieldsNoDataReport(): void
    {
        $client = self::createJsonClient();
        $adminToken = $this->login($client, $this->createUser('sla-nodata-admin@example.com', SystemRole::ADMIN)->getUserIdentifier());
        $from = new \DateTimeImmutable('2026-08-26T10:00:00+00:00');
        $emptyGroup = new NodeGroup('Empty group', null, $from);
        $this->entityManager()->persist($emptyGroup);
        $this->entityManager()->flush();

        $created = $client->request('POST', '/api/slas', [
            'auth_bearer' => $adminToken,
            'json' => ['name' => 'Empty scope SLA', 'targetPercentage' => 99.9, 'nodeGroupIds' => [(string) $emptyGroup->id()]],
        ])->toArray();
        $slaId = $created['id'] ?? null;
        self::assertIsString($slaId);

        $report = $client->request('GET', '/api/slas/'.$slaId.'/report?from=2026-08-26T10%3A00%3A00%2B00%3A00&to=2026-08-26T11%3A00%3A00%2B00%3A00', [
            'auth_bearer' => $adminToken,
        ])->toArray();

        self::assertSame(0, $report['eligibleSeconds'] ?? null);
        self::assertSame(0, $report['downtimeSeconds'] ?? null);
        self::assertSame(0, $report['uptimeSeconds'] ?? null);
        self::assertNull($report['availabilityPercentage'] ?? null, 'availabilityPercentage must be absent or null, never defaulted to a numeric value.');
        self::assertNull($report['compliant'] ?? null, 'compliant must be absent or null, never defaulted to true.');
        self::assertSame('no_data', $report['status'] ?? null);
        self::assertSame([], $report['nodes'] ?? null);
    }

    public function testNodesFullyCoveredByMaintenanceAreExcludedFromWeightedAggregateAndReportedAsNoData(): void
    {
        $client = self::createJsonClient();
        $adminToken = $this->login($client, $this->createUser('sla-mixed-admin@example.com', SystemRole::ADMIN)->getUserIdentifier());
        $from = new \DateTimeImmutable('2026-08-26T10:00:00+00:00');
        $dataNode = new Node('mixed-data-node', null, 'linux', 'amd64', $from, $from);
        $noDataNode = new Node('mixed-nodata-node', null, 'linux', 'amd64', $from, $from);
        $item = new ItemDefinition('custom.sla.mixed.probe', 'Mixed probe', null, null, ItemValueType::FLOAT, 60, 5, 'printf 1', null, true, $from);
        $rule = new AlertRule('Mixed availability rule', 'Service unreachable', $item, AlertOperator::GT, '0', null, 60, 1, AlertSeverity::CRITICAL, AlertRuleImpactType::AVAILABILITY, true, $from);
        $factory = new IncidentFactory();
        $incident = $factory->create($dataNode, $rule, ['probe' => 'data'], '1', $from);
        $incident->resolve('0', new \DateTimeImmutable('2026-08-26T10:30:00+00:00'));
        $fullWindowMaintenance = new MaintenanceWindow('No-data window', null, $from, new \DateTimeImmutable('2026-08-26T11:00:00+00:00'), true, $from);
        $fullWindowMaintenance->replaceNodes([$noDataNode], $from);

        $entityManager = $this->entityManager();
        foreach ([$dataNode, $noDataNode, $item, $rule, $incident, $fullWindowMaintenance] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        $created = $client->request('POST', '/api/slas', [
            'auth_bearer' => $adminToken,
            'json' => [
                'name' => 'Mixed no-data SLA',
                'targetPercentage' => 50.0,
                'excludeMaintenance' => true,
                'nodeIds' => [(string) $dataNode->id(), (string) $noDataNode->id()],
            ],
        ])->toArray();
        $slaId = $created['id'] ?? null;
        self::assertIsString($slaId);

        $report = $client->request('GET', '/api/slas/'.$slaId.'/report?from=2026-08-26T10%3A00%3A00%2B00%3A00&to=2026-08-26T11%3A00%3A00%2B00%3A00', [
            'auth_bearer' => $adminToken,
        ])->toArray();

        self::assertSame(3600, $report['eligibleSeconds'] ?? null, 'Only the data-bearing node contributes eligible seconds.');
        self::assertSame(1800, $report['downtimeSeconds'] ?? null);
        self::assertSame(50.0, $report['availabilityPercentage'] ?? null, 'The no_data node must not be counted as artificially 100% available, nor dilute the weighted average.');
        self::assertTrue($report['compliant'] ?? false);
        self::assertSame('compliant', $report['status'] ?? null);
        $nodeReports = self::arrayValue($report, 'nodes');
        self::assertCount(2, $nodeReports);
        $byId = [];
        foreach ($nodeReports as $nodeReport) {
            self::assertIsArray($nodeReport);
            self::assertIsString($nodeReport['id']);
            $byId[$nodeReport['id']] = $nodeReport;
        }
        self::assertSame('no_data', $byId[(string) $noDataNode->id()]['status'] ?? null);
        self::assertNull($byId[(string) $noDataNode->id()]['availabilityPercentage'] ?? null);
        self::assertSame('compliant', $byId[(string) $dataNode->id()]['status'] ?? null);

        $updated = $client->request('PATCH', '/api/slas/'.$slaId, [
            'auth_bearer' => $adminToken,
            'json' => ['nodeIds' => [(string) $noDataNode->id()]],
        ])->toArray();
        self::assertCount(1, self::arrayValue($updated, 'nodes'));

        $allNoDataReport = $client->request('GET', '/api/slas/'.$slaId.'/report?from=2026-08-26T10%3A00%3A00%2B00%3A00&to=2026-08-26T11%3A00%3A00%2B00%3A00', [
            'auth_bearer' => $adminToken,
        ])->toArray();

        self::assertSame(0, $allNoDataReport['eligibleSeconds'] ?? null);
        self::assertNull($allNoDataReport['availabilityPercentage'] ?? null);
        self::assertSame('no_data', $allNoDataReport['status'] ?? null);
        $allNoDataNodeReports = self::arrayValue($allNoDataReport, 'nodes');
        self::assertCount(1, $allNoDataNodeReports);
        $onlyNodeReport = reset($allNoDataNodeReports);
        self::assertIsArray($onlyNodeReport);
        self::assertSame('no_data', $onlyNodeReport['status'] ?? null);
    }

    /** @return array{NodeGroup, Node, Node} */
    private function createReportScope(): array
    {
        $from = new \DateTimeImmutable('2026-08-26T10:00:00+00:00');
        $group = new NodeGroup('Production', null, $from);
        $firstNode = new Node('prod-01', null, 'linux', 'amd64', $from, $from);
        $secondNode = new Node('prod-02', null, 'linux', 'amd64', $from, $from);
        $firstNode->replaceGroups([$group]);
        $secondNode->replaceGroups([$group]);
        $item = new ItemDefinition('custom.sla.probe', 'SLA probe', null, null, ItemValueType::FLOAT, 60, 5, 'printf 1', null, true, $from);
        $rule = new AlertRule('SLA alert', 'SLA probe unavailable', $item, AlertOperator::GT, '0', null, 60, 1, AlertSeverity::CRITICAL, AlertRuleImpactType::AVAILABILITY, true, $from);
        $factory = new IncidentFactory();
        $resolvedIncident = $factory->create($firstNode, $rule, ['probe' => 'one'], '1', $from);
        $resolvedIncident->resolve('0', new \DateTimeImmutable('2026-08-26T10:10:00+00:00'));
        $firingIncident = $factory->create($secondNode, $rule, ['probe' => 'two'], '1', new \DateTimeImmutable('2026-08-26T10:20:00+00:00'));
        $maintenance = new MaintenanceWindow('prod-02 maintenance', null, new \DateTimeImmutable('2026-08-26T10:30:00+00:00'), new \DateTimeImmutable('2026-08-26T10:40:00+00:00'), true, $from);
        $maintenance->replaceNodes([$secondNode], $from);

        $entityManager = $this->entityManager();
        foreach ([$group, $firstNode, $secondNode, $item, $rule, $resolvedIncident, $firingIncident, $maintenance] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        return [$group, $firstNode, $secondNode];
    }

    private function createUser(string $email, string $role): User
    {
        $now = new \DateTimeImmutable();
        $user = new User($email, ['ROLE_USER'], $now);
        $this->assignSystemRole($user, $role);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD), $now);
        $this->entityManager()->persist($user);
        $this->entityManager()->flush();

        return $user;
    }

    private function login(Client $client, string $email): string
    {
        $response = $client->request('POST', '/api/auth/login', ['json' => ['email' => $email, 'password' => self::PASSWORD]]);
        self::assertResponseIsSuccessful();
        $token = $response->toArray()['token'] ?? null;
        self::assertIsString($token);

        return $token;
    }

    private static function createJsonClient(): Client
    {
        return self::createClient(defaultOptions: ['headers' => ['accept' => 'application/json', 'content-type' => 'application/json']]);
    }

    /** @param array<mixed> $payload
     * @return array<int|string, mixed>
     */
    private static function arrayValue(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;
        self::assertIsArray($value);

        return $value;
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function nodeGroupNodesCount(): int
    {
        $count = self::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM node_group_nodes');
        if (!\is_int($count) && !\is_string($count)) {
            throw new \LogicException('Unexpected node_group_nodes count result.');
        }

        return (int) $count;
    }
}
