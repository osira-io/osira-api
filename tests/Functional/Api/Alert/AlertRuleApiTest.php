<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Alert;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Entity\User\User;
use App\Repository\Alert\AlertRuleRepository;
use App\Security\Rbac\SystemRole;
use App\Tests\Functional\Support\RbacTestTrait;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AlertRuleApiTest extends ApiTestCase
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

    public function testCreateReadCollectionPatchDeleteAndAudit(): void
    {
        $client = self::createJsonClient();
        $adminToken = $this->login($client, $this->createUser('alert-admin@example.com', SystemRole::ADMIN)->getUserIdentifier());
        [$item, $template, $group, $node] = $this->createScope();

        $created = $client->request('POST', '/api/alert-rules', [
            'auth_bearer' => $adminToken,
            'json' => [
                'name' => 'Nginx unavailable',
                'itemDefinitionId' => (string) $item->id(),
                'operator' => 'neq',
                'expectedValue' => '1',
                'severity' => 'critical',
                'impactType' => 'availability',
                'evaluationWindowSeconds' => 60,
                'requiredOccurrences' => 2,
                'monitoringTemplateIds' => [(string) $template->id()],
                'nodeGroupIds' => [(string) $group->id()],
                'nodeIds' => [(string) $node->id()],
            ],
        ])->toArray();

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $ruleId = $created['id'] ?? null;
        self::assertIsString($ruleId);
        self::assertSame('Nginx unavailable', $created['name'] ?? null);
        self::assertNull($created['description'] ?? null);
        $createdItemDefinition = $created['itemDefinition'] ?? null;
        self::assertIsArray($createdItemDefinition);
        self::assertSame((string) $item->id(), $createdItemDefinition['id'] ?? null);
        self::assertSame('neq', $created['operator'] ?? null);
        self::assertSame('1', $created['expectedValue'] ?? null);
        self::assertSame('critical', $created['severity'] ?? null);
        self::assertSame('availability', $created['impactType'] ?? null);
        self::assertTrue($created['isEnabled'] ?? false);
        self::assertSame([(string) $template->id()], $created['monitoringTemplateIds'] ?? null);
        self::assertSame([(string) $group->id()], $created['nodeGroupIds'] ?? null);
        self::assertSame([(string) $node->id()], $created['nodeIds'] ?? null);

        $item2 = $client->request('GET', '/api/alert-rules/'.$ruleId, ['auth_bearer' => $adminToken])->toArray();
        self::assertSame($ruleId, $item2['id'] ?? null);

        $collection = $client->request('GET', '/api/alert-rules', ['auth_bearer' => $adminToken])->toArray();
        self::assertCount(1, self::arrayValue($collection, 'items'));

        $updated = $client->request('PATCH', '/api/alert-rules/'.$ruleId, [
            'auth_bearer' => $adminToken,
            'json' => ['description' => 'Fires when nginx is down', 'requiredOccurrences' => 3, 'isEnabled' => false],
        ])->toArray();
        self::assertSame('Fires when nginx is down', $updated['description'] ?? null);
        self::assertSame(3, $updated['requiredOccurrences'] ?? null);
        self::assertFalse($updated['isEnabled'] ?? true);
        self::assertSame('availability', $updated['impactType'] ?? null);

        $client->request('DELETE', '/api/alert-rules/'.$ruleId, ['auth_bearer' => $adminToken]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame(0, self::getContainer()->get(AlertRuleRepository::class)->count([]));

        $auditRows = self::getContainer()->get(Connection::class)->fetchAllAssociative('SELECT type, diffs FROM audit_alert_rules WHERE object_id = ? ORDER BY id ASC', [$ruleId]);
        self::assertGreaterThanOrEqual(3, \count($auditRows));
        self::assertContains('insert', array_column($auditRows, 'type'));
        self::assertContains('update', array_column($auditRows, 'type'));
        self::assertContains('remove', array_column($auditRows, 'type'));
        $encodedAuditRows = json_encode($auditRows, \JSON_THROW_ON_ERROR);
        self::assertStringContainsString('impactType', $encodedAuditRows);
        self::assertStringContainsString('severity', $encodedAuditRows);
    }

    public function testUnauthenticatedRequestsAreRejected(): void
    {
        $client = self::createJsonClient();
        $client->request('GET', '/api/alert-rules');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testRbacPermissions(): void
    {
        $client = self::createJsonClient();
        $viewerToken = $this->login($client, $this->createUser('alert-viewer@example.com', SystemRole::VIEWER)->getUserIdentifier());
        $operatorToken = $this->login($client, $this->createUser('alert-operator@example.com', SystemRole::OPERATOR)->getUserIdentifier());
        [$item] = $this->createScope();

        $client->request('GET', '/api/alert-rules', ['auth_bearer' => $viewerToken]);
        self::assertResponseIsSuccessful();
        $client->request('POST', '/api/alert-rules', [
            'auth_bearer' => $viewerToken,
            'json' => $this->basePayload($item),
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $created = $client->request('POST', '/api/alert-rules', [
            'auth_bearer' => $operatorToken,
            'json' => $this->basePayload($item),
        ])->toArray();
        $ruleId = $created['id'] ?? null;
        self::assertIsString($ruleId);
        $client->request('PATCH', '/api/alert-rules/'.$ruleId, ['auth_bearer' => $operatorToken, 'json' => ['isEnabled' => false]]);
        self::assertResponseIsSuccessful();
        $client->request('DELETE', '/api/alert-rules/'.$ruleId, ['auth_bearer' => $operatorToken]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testImpactTypeIsMandatoryOnCreate(): void
    {
        $client = self::createJsonClient();
        $adminToken = $this->login($client, $this->createUser('alert-impact-admin@example.com', SystemRole::ADMIN)->getUserIdentifier());
        [$item] = $this->createScope();

        $payload = $this->basePayload($item);
        unset($payload['impactType']);
        $client->request('POST', '/api/alert-rules', ['auth_bearer' => $adminToken, 'json' => $payload]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPatchWithoutImpactTypePreservesCurrentValue(): void
    {
        $client = self::createJsonClient();
        $adminToken = $this->login($client, $this->createUser('alert-preserve-admin@example.com', SystemRole::ADMIN)->getUserIdentifier());
        [$item] = $this->createScope();

        $payload = $this->basePayload($item);
        $payload['impactType'] = 'performance';
        $created = $client->request('POST', '/api/alert-rules', ['auth_bearer' => $adminToken, 'json' => $payload])->toArray();
        $ruleId = $created['id'] ?? null;
        self::assertIsString($ruleId);

        $updated = $client->request('PATCH', '/api/alert-rules/'.$ruleId, ['auth_bearer' => $adminToken, 'json' => ['name' => 'Renamed rule']])->toArray();
        self::assertSame('performance', $updated['impactType'] ?? null);
        self::assertSame('Renamed rule', $updated['name'] ?? null);
    }

    public function testOperatorIncompatibleWithBooleanItemIsRejected(): void
    {
        $client = self::createJsonClient();
        $adminToken = $this->login($client, $this->createUser('alert-op-admin@example.com', SystemRole::ADMIN)->getUserIdentifier());
        $now = new \DateTimeImmutable('2026-08-26T10:00:00+00:00');
        $booleanItem = new ItemDefinition('custom.alert.boolean', 'Boolean probe', null, null, ItemValueType::BOOLEAN, 60, 5, 'printf 1', 'exit 0', true, $now);
        $this->entityManager()->persist($booleanItem);
        $this->entityManager()->flush();

        $payload = $this->basePayload($booleanItem);
        $payload['operator'] = 'gt';
        $payload['expectedValue'] = '1';
        $client->request('POST', '/api/alert-rules', ['auth_bearer' => $adminToken, 'json' => $payload]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testMalformedExpectedValueIsRejected(): void
    {
        $client = self::createJsonClient();
        $adminToken = $this->login($client, $this->createUser('alert-expected-admin@example.com', SystemRole::ADMIN)->getUserIdentifier());
        [$item] = $this->createScope();

        $payload = $this->basePayload($item);
        $payload['expectedValue'] = 'not-a-number';
        $client->request('POST', '/api/alert-rules', ['auth_bearer' => $adminToken, 'json' => $payload]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testMalformedRecoveryThresholdIsRejected(): void
    {
        $client = self::createJsonClient();
        $adminToken = $this->login($client, $this->createUser('alert-recovery-admin@example.com', SystemRole::ADMIN)->getUserIdentifier());
        [$item] = $this->createScope();

        $payload = $this->basePayload($item);
        $payload['recoveryThreshold'] = 'not-a-number';
        $client->request('POST', '/api/alert-rules', ['auth_bearer' => $adminToken, 'json' => $payload]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testInvalidRequiredOccurrencesAndWindowAreRejected(): void
    {
        $client = self::createJsonClient();
        $adminToken = $this->login($client, $this->createUser('alert-window-admin@example.com', SystemRole::ADMIN)->getUserIdentifier());
        [$item] = $this->createScope();

        $payload = $this->basePayload($item);
        $payload['requiredOccurrences'] = 0;
        $client->request('POST', '/api/alert-rules', ['auth_bearer' => $adminToken, 'json' => $payload]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $payload = $this->basePayload($item);
        $payload['evaluationWindowSeconds'] = 0;
        $client->request('POST', '/api/alert-rules', ['auth_bearer' => $adminToken, 'json' => $payload]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUnknownItemDefinitionIsRejected(): void
    {
        $client = self::createJsonClient();
        $adminToken = $this->login($client, $this->createUser('alert-unknown-item-admin@example.com', SystemRole::ADMIN)->getUserIdentifier());
        [$item] = $this->createScope();

        $payload = $this->basePayload($item);
        $payload['itemDefinitionId'] = (string) new \Symfony\Component\Uid\Ulid();
        $client->request('POST', '/api/alert-rules', ['auth_bearer' => $adminToken, 'json' => $payload]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testStringItemDefinitionIsRejected(): void
    {
        $client = self::createJsonClient();
        $adminToken = $this->login($client, $this->createUser('alert-string-admin@example.com', SystemRole::ADMIN)->getUserIdentifier());
        $now = new \DateTimeImmutable('2026-08-26T10:00:00+00:00');
        $stringItem = new ItemDefinition('custom.alert.string', 'String probe', null, null, ItemValueType::STRING, 60, 5, 'echo ok', 'echo ok', true, $now);
        $this->entityManager()->persist($stringItem);
        $this->entityManager()->flush();

        $payload = $this->basePayload($stringItem);
        $payload['operator'] = 'eq';
        $payload['expectedValue'] = 'ok';
        $client->request('POST', '/api/alert-rules', ['auth_bearer' => $adminToken, 'json' => $payload]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testTemplateAssignmentValidation(): void
    {
        $client = self::createJsonClient();
        $adminToken = $this->login($client, $this->createUser('alert-template-admin@example.com', SystemRole::ADMIN)->getUserIdentifier());
        [$item, $template] = $this->createScope();
        $now = new \DateTimeImmutable('2026-08-26T10:00:00+00:00');
        $otherItem = new ItemDefinition('custom.alert.other', 'Other probe', null, null, ItemValueType::FLOAT, 60, 5, 'printf 1', 'exit 0', true, $now);
        $this->entityManager()->persist($otherItem);
        $this->entityManager()->flush();

        $valid = $this->basePayload($item);
        $valid['monitoringTemplateIds'] = [(string) $template->id()];
        $client->request('POST', '/api/alert-rules', ['auth_bearer' => $adminToken, 'json' => $valid]);
        self::assertResponseIsSuccessful();

        $invalid = $this->basePayload($otherItem);
        $invalid['monitoringTemplateIds'] = [(string) $template->id()];
        $client->request('POST', '/api/alert-rules', ['auth_bearer' => $adminToken, 'json' => $invalid]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testNodeGroupAssignmentValidation(): void
    {
        $client = self::createJsonClient();
        $adminToken = $this->login($client, $this->createUser('alert-group-admin@example.com', SystemRole::ADMIN)->getUserIdentifier());
        [$item, , $group] = $this->createScope();
        $now = new \DateTimeImmutable('2026-08-26T10:00:00+00:00');
        $otherItem = new ItemDefinition('custom.alert.othergroup', 'Other group probe', null, null, ItemValueType::FLOAT, 60, 5, 'printf 1', 'exit 0', true, $now);
        $this->entityManager()->persist($otherItem);
        $this->entityManager()->flush();

        $valid = $this->basePayload($item);
        $valid['nodeGroupIds'] = [(string) $group->id()];
        $client->request('POST', '/api/alert-rules', ['auth_bearer' => $adminToken, 'json' => $valid]);
        self::assertResponseIsSuccessful();

        $invalid = $this->basePayload($otherItem);
        $invalid['nodeGroupIds'] = [(string) $group->id()];
        $client->request('POST', '/api/alert-rules', ['auth_bearer' => $adminToken, 'json' => $invalid]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testNodeAssignmentValidationIncludingOsIncompatibility(): void
    {
        $client = self::createJsonClient();
        $adminToken = $this->login($client, $this->createUser('alert-node-admin@example.com', SystemRole::ADMIN)->getUserIdentifier());
        [$item, , $group, $node] = $this->createScope();
        $now = new \DateTimeImmutable('2026-08-26T10:00:00+00:00');

        $valid = $this->basePayload($item);
        $valid['nodeIds'] = [(string) $node->id()];
        $client->request('POST', '/api/alert-rules', ['auth_bearer' => $adminToken, 'json' => $valid]);
        self::assertResponseIsSuccessful();

        $strayNode = new Node('stray-node', null, 'linux', 'amd64', $now, $now);
        $this->entityManager()->persist($strayNode);
        $this->entityManager()->flush();
        $noEffectiveItem = $this->basePayload($item);
        $noEffectiveItem['nodeIds'] = [(string) $strayNode->id()];
        $client->request('POST', '/api/alert-rules', ['auth_bearer' => $adminToken, 'json' => $noEffectiveItem]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $windowsOnlyItem = new ItemDefinition('custom.alert.windowsonly', 'Windows-only probe', null, null, ItemValueType::FLOAT, 60, 5, null, 'Get-Process | Measure-Object', true, $now);
        $windowsTemplate = new MonitoringTemplate('Windows-only template', 'windows-only-template', null, true, $now);
        $windowsTemplate->replaceItemDefinitions([$windowsOnlyItem], $now);
        $windowsGroup = new NodeGroup('Windows-only group', null, $now);
        $windowsGroup->replaceMonitoringTemplates([$windowsTemplate], $now);
        $linuxNodeInWindowsGroup = new Node('linux-in-windows-group', null, 'linux', 'amd64', $now, $now);
        $linuxNodeInWindowsGroup->replaceGroups([$windowsGroup]);
        foreach ([$windowsOnlyItem, $windowsTemplate, $windowsGroup, $linuxNodeInWindowsGroup] as $entity) {
            $this->entityManager()->persist($entity);
        }
        $this->entityManager()->flush();

        $osIncompatible = $this->basePayload($windowsOnlyItem);
        $osIncompatible['nodeIds'] = [(string) $linuxNodeInWindowsGroup->id()];
        $client->request('POST', '/api/alert-rules', ['auth_bearer' => $adminToken, 'json' => $osIncompatible]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testMultiScopeAssignmentIsValid(): void
    {
        $client = self::createJsonClient();
        $adminToken = $this->login($client, $this->createUser('alert-multi-admin@example.com', SystemRole::ADMIN)->getUserIdentifier());
        [$item, $template, $group, $node] = $this->createScope();

        $payload = $this->basePayload($item);
        $payload['monitoringTemplateIds'] = [(string) $template->id()];
        $payload['nodeGroupIds'] = [(string) $group->id()];
        $payload['nodeIds'] = [(string) $node->id()];
        $created = $client->request('POST', '/api/alert-rules', ['auth_bearer' => $adminToken, 'json' => $payload])->toArray();

        self::assertSame([(string) $template->id()], $created['monitoringTemplateIds'] ?? null);
        self::assertSame([(string) $group->id()], $created['nodeGroupIds'] ?? null);
        self::assertSame([(string) $node->id()], $created['nodeIds'] ?? null);
    }

    public function testImpactTypeChangeInfluencesSlaAvailability(): void
    {
        $client = self::createJsonClient();
        $adminToken = $this->login($client, $this->createUser('alert-sla-admin@example.com', SystemRole::ADMIN)->getUserIdentifier());
        [$item, , , $node] = $this->createScope();

        $payload = $this->basePayload($item);
        $payload['impactType'] = 'performance';
        $created = $client->request('POST', '/api/alert-rules', ['auth_bearer' => $adminToken, 'json' => $payload])->toArray();
        $ruleId = $created['id'] ?? null;
        self::assertIsString($ruleId);

        $rule = self::getContainer()->get(AlertRuleRepository::class)->find(new \Symfony\Component\Uid\Ulid($ruleId));
        self::assertNotNull($rule);
        $managedNode = self::getContainer()->get(\App\Repository\Node\NodeRepository::class)->find($node->id());
        self::assertNotNull($managedNode);
        $from = new \DateTimeImmutable('2026-08-26T10:00:00+00:00');
        $incident = self::getContainer()->get(\App\Factory\Incident\IncidentFactory::class)->create($managedNode, $rule, ['probe' => 'sla'], '0', $from);
        $this->entityManager()->persist($incident);
        $this->entityManager()->flush();

        $slaCreated = $client->request('POST', '/api/slas', [
            'auth_bearer' => $adminToken,
            'json' => ['name' => 'Impact toggle SLA', 'targetPercentage' => 99.9, 'nodeIds' => [(string) $node->id()]],
        ])->toArray();
        $slaId = $slaCreated['id'] ?? null;
        self::assertIsString($slaId);

        $reportUrl = '/api/slas/'.$slaId.'/report?from=2026-08-26T10%3A00%3A00%2B00%3A00&to=2026-08-26T11%3A00%3A00%2B00%3A00';
        $performanceReport = $client->request('GET', $reportUrl, ['auth_bearer' => $adminToken])->toArray();
        self::assertSame(0, $performanceReport['downtimeSeconds'] ?? null, 'A performance-impact incident must not count as downtime.');

        $client->request('PATCH', '/api/alert-rules/'.$ruleId, ['auth_bearer' => $adminToken, 'json' => ['impactType' => 'availability']]);
        self::assertResponseIsSuccessful();

        $availabilityReport = $client->request('GET', $reportUrl, ['auth_bearer' => $adminToken])->toArray();
        self::assertGreaterThan(0, $availabilityReport['downtimeSeconds'] ?? 0, 'After reclassifying to availability, the same Incident must now count as downtime.');
    }

    /** @return array{ItemDefinition, MonitoringTemplate, NodeGroup, Node} */
    private function createScope(): array
    {
        $now = new \DateTimeImmutable('2026-08-26T10:00:00+00:00');
        $item = new ItemDefinition('custom.alert.cpu', 'CPU usage', null, '%', ItemValueType::FLOAT, 60, 5, 'printf 1', 'exit 0', true, $now);
        $template = new MonitoringTemplate('Alert template', 'alert-template', null, true, $now);
        $template->replaceItemDefinitions([$item], $now);
        $group = new NodeGroup('Alert group', null, $now);
        $group->replaceMonitoringTemplates([$template], $now);
        $node = new Node('alert-node', null, 'linux', 'amd64', $now, $now);
        $node->replaceGroups([$group]);

        $entityManager = $this->entityManager();
        foreach ([$item, $template, $group, $node] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        return [$item, $template, $group, $node];
    }

    /** @return array<string, mixed> */
    private function basePayload(ItemDefinition $item): array
    {
        return [
            'name' => 'Test rule '.$item->key(),
            'itemDefinitionId' => (string) $item->id(),
            'operator' => 'gt',
            'expectedValue' => '90',
            'severity' => 'warning',
            'impactType' => 'availability',
            'evaluationWindowSeconds' => 60,
            'requiredOccurrences' => 1,
        ];
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
}
