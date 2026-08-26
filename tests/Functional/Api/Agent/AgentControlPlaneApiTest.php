<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Agent;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Agent\Agent;
use App\Entity\Agent\AgentCredential;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Entity\Rbac\Role;
use App\Entity\User\User;
use App\Repository\Rbac\PermissionRepository;
use App\Security\Auth\TokenGenerator;
use App\Security\Auth\TokenHasher;
use App\Security\Rbac\PermissionCode;
use App\Security\Rbac\SystemRole;
use App\Tests\Functional\Support\RbacTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AgentControlPlaneApiTest extends ApiTestCase
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

    public function testAgentConfigReturnsEffectiveDeterministicConfiguration(): void
    {
        $client = self::createJsonClient();
        $enrollment = $this->enrollAgent($client);
        $node = $this->nodeById($enrollment['nodeId']);
        $this->attachEffectiveMonitoringScenario($node);

        $response = $client->request('GET', '/api/agent/config', [
            'auth_bearer' => $enrollment['agentToken'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json; charset=utf-8');

        $payload = $response->toArray();
        self::assertIsString($payload['version'] ?? null);
        $nodePayload = $payload['node'] ?? null;
        $agentPayload = $payload['agent'] ?? null;
        $itemsPayload = $payload['items'] ?? null;
        self::assertIsArray($nodePayload);
        self::assertIsArray($agentPayload);
        self::assertIsArray($itemsPayload);

        self::assertSame($response->getHeaders(false)['etag'][0] ?? null, '"'.$payload['version'].'"');
        self::assertSame((string) $node->id(), $nodePayload['id'] ?? null);
        self::assertSame('srv-agent-01', $nodePayload['hostname'] ?? null);
        self::assertSame($enrollment['agentId'], $agentPayload['id'] ?? null);
        self::assertSame('0.1.0', $agentPayload['version'] ?? null);
        self::assertSame(['custom.latency'], array_column($itemsPayload, 'key'));
        self::assertIsArray($itemsPayload[0]);
        self::assertSame(['shell' => 'bash', 'command' => 'printf 12.5'], $itemsPayload[0]['execution'] ?? null);
        self::assertArrayNotHasKey('windowsCommand', $itemsPayload[0]);
        self::assertNotContains('custom.disabled.metric', array_column($itemsPayload, 'key'));
        self::assertStringNotContainsString('secretHash', json_encode($payload, \JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('tokenHash', json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    public function testAgentConfigSupportsConditionalRequests(): void
    {
        $client = self::createJsonClient();
        $enrollment = $this->enrollAgent($client);
        $node = $this->nodeById($enrollment['nodeId']);
        $this->attachEffectiveMonitoringScenario($node);

        $first = $client->request('GET', '/api/agent/config', [
            'auth_bearer' => $enrollment['agentToken'],
        ]);
        self::assertResponseIsSuccessful();
        $etag = $first->getHeaders(false)['etag'][0] ?? null;
        self::assertIsString($etag);

        $client->request('GET', '/api/agent/config', [
            'auth_bearer' => $enrollment['agentToken'],
            'headers' => ['If-None-Match' => $etag],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_MODIFIED);
    }

    public function testAgentWithoutGroupReceivesNoItemsAndWindowsReceivesOnlyPowerShell(): void
    {
        $client = self::createJsonClient();
        $emptyEnrollment = $this->enrollAgent($client);
        $empty = $client->request('GET', '/api/agent/config', ['auth_bearer' => $emptyEnrollment['agentToken']])->toArray();
        self::assertSame([], $empty['items'] ?? null);

        $windowsEnrollment = $this->enrollAgent($client, 'windows');
        $windowsNode = $this->nodeById($windowsEnrollment['nodeId']);
        $this->attachEffectiveMonitoringScenario($windowsNode);
        $windows = $client->request('GET', '/api/agent/config', ['auth_bearer' => $windowsEnrollment['agentToken']])->toArray();
        $items = $windows['items'] ?? null;
        self::assertIsArray($items);
        self::assertCount(1, $items);
        self::assertIsArray($items[0]);
        self::assertSame(['shell' => 'powershell', 'command' => 'Write-Output 12.5'], $items[0]['execution'] ?? null);
        self::assertArrayNotHasKey('linuxCommand', $items[0]);
    }

    public function testConfigEtagChangesOnlyWhenTheEffectiveLinuxCommandChanges(): void
    {
        $client = self::createJsonClient();
        $enrollment = $this->enrollAgent($client);
        $node = $this->nodeById($enrollment['nodeId']);
        $item = $this->attachEffectiveMonitoringScenario($node);

        $initial = $client->request('GET', '/api/agent/config', ['auth_bearer' => $enrollment['agentToken']])->getHeaders(false)['etag'][0] ?? null;
        self::assertIsString($initial);
        $now = new \DateTimeImmutable('2026-08-20T11:00:00+00:00');
        $item = $this->entityManager()->find(ItemDefinition::class, $item->id());
        self::assertInstanceOf(ItemDefinition::class, $item);
        $item->update($item->key(), $item->name(), $item->description(), $item->unit(), $item->valueType(), 15, 3, 'printf 12.5', 'Write-Output 99', true, $now);
        $this->entityManager()->flush();
        $unchanged = $client->request('GET', '/api/agent/config', ['auth_bearer' => $enrollment['agentToken']])->getHeaders(false)['etag'][0] ?? null;
        self::assertSame($initial, $unchanged);

        $item = $this->entityManager()->find(ItemDefinition::class, $item->id());
        self::assertInstanceOf(ItemDefinition::class, $item);
        $item->update($item->key(), $item->name(), $item->description(), $item->unit(), $item->valueType(), 15, 3, 'printf 42', 'Write-Output 99', true, $now->modify('+1 second'));
        $this->entityManager()->flush();
        $changed = $client->request('GET', '/api/agent/config', ['auth_bearer' => $enrollment['agentToken']])->getHeaders(false)['etag'][0] ?? null;
        self::assertNotSame($initial, $changed);
    }

    public function testAgentConfigRejectsMissingInvalidRevokedAndUserTokens(): void
    {
        $client = self::createJsonClient();
        $enrollment = $this->enrollAgent($client);

        $client->request('GET', '/api/agent/config');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $client->request('GET', '/api/agent/config', [
            'auth_bearer' => TokenGenerator::AGENT_PREFIX.'invalid',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $jwt = $this->login($client, $this->createUser('viewer@example.com', SystemRole::VIEWER)->getUserIdentifier());
        $client->request('GET', '/api/agent/config', ['auth_bearer' => $jwt]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $credential = $this->activeCredentialForAgent($enrollment['agentId']);
        $credential->revoke(new \DateTimeImmutable('2026-08-20T12:00:00+00:00'));
        $this->entityManager()->flush();

        $client->request('GET', '/api/agent/config', [
            'auth_bearer' => $enrollment['agentToken'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testRotateReturnsNewSecretAndInvalidatesOldCredential(): void
    {
        $client = self::createJsonClient();
        $enrollment = $this->enrollAgent($client);

        $response = $client->request('POST', '/api/agent/credentials/rotate', [
            'auth_bearer' => $enrollment['agentToken'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $payload = $response->toArray();
        self::assertIsString($payload['agentToken'] ?? null);
        self::assertNotSame($enrollment['agentToken'], $payload['agentToken']);
        self::assertStringStartsWith(TokenGenerator::AGENT_PREFIX, $payload['agentToken']);

        $credentialRows = $this->entityManager()->getConnection()->fetchAllAssociative('SELECT secret_hash, revoked_at FROM agent_credentials');
        self::assertCount(2, $credentialRows);
        self::assertCount(1, array_filter($credentialRows, static fn (array $row): bool => null !== ($row['revoked_at'] ?? null)));
        self::assertCount(1, array_filter($credentialRows, static fn (array $row): bool => null === ($row['revoked_at'] ?? null)));
        foreach ($credentialRows as $credentialRow) {
            self::assertNotSame($payload['agentToken'], $credentialRow['secret_hash'] ?? null);
        }

        $client->request('GET', '/api/agent/config', [
            'auth_bearer' => $enrollment['agentToken'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $client->request('GET', '/api/agent/config', [
            'auth_bearer' => $payload['agentToken'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testAdminRevocationRequiresPermissionAndTakesEffectImmediately(): void
    {
        $client = self::createJsonClient();
        $enrollment = $this->enrollAgent($client);
        $credential = $this->activeCredentialForAgent($enrollment['agentId']);

        $viewerToken = $this->login($client, $this->createUser('viewer@example.com', SystemRole::VIEWER)->getUserIdentifier());
        $client->request('POST', '/api/agent-credentials/'.$credential->id().'/revoke', [
            'auth_bearer' => $viewerToken,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $operator = $this->createUser('operator@example.com');
        $this->assignPermissions($operator, [PermissionCode::AGENT_CREDENTIALS_REVOKE]);
        $operatorToken = $this->login($client, $operator->getUserIdentifier());

        $client->request('POST', '/api/agent-credentials/'.$credential->id().'/revoke', [
            'auth_bearer' => $operatorToken,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $client->request('GET', '/api/agent/config', [
            'auth_bearer' => $enrollment['agentToken'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /** @return array{nodeId: string, agentId: string, agentToken: string} */
    private function enrollAgent(Client $client, string $os = 'linux'): array
    {
        $rawEnrollmentToken = $this->persistEnrollmentToken();
        $response = $client->request('POST', '/api/agents/enroll', [
            'json' => [
                'enrollmentToken' => $rawEnrollmentToken,
                'hostname' => 'srv-agent-'.('linux' === $os ? '01' : $os),
                'os' => $os,
                'architecture' => 'x86_64',
                'agentVersion' => '0.1.0',
            ],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $payload = $response->toArray();
        self::assertIsString($payload['nodeId'] ?? null);
        self::assertIsString($payload['agentId'] ?? null);
        self::assertIsString($payload['agentToken'] ?? null);

        return $payload;
    }

    private function attachEffectiveMonitoringScenario(Node $node): ItemDefinition
    {
        $entityManager = $this->entityManager();
        $customItem = new ItemDefinition('custom.latency', 'Latency', null, 'ms', ItemValueType::FLOAT, 15, 3, 'printf 12.5', 'Write-Output 12.5', true, new \DateTimeImmutable('2026-08-20T10:00:00+00:00'));
        $disabledItem = new ItemDefinition('custom.disabled.metric', 'Disabled metric', null, null, ItemValueType::FLOAT, 30, null, 'printf 0', null, false, new \DateTimeImmutable('2026-08-20T10:00:00+00:00'));
        $customTemplate = new MonitoringTemplate('Custom Template', 'custom-template', null, true, new \DateTimeImmutable('2026-08-20T10:01:00+00:00'));
        $disabledTemplate = new MonitoringTemplate('Disabled Template', 'disabled-template', null, false, new \DateTimeImmutable('2026-08-20T10:01:00+00:00'));
        $customTemplate->replaceItemDefinitions([$customItem, $disabledItem], new \DateTimeImmutable('2026-08-20T10:02:00+00:00'));
        $disabledTemplate->replaceItemDefinitions([$customItem], new \DateTimeImmutable('2026-08-20T10:02:00+00:00'));

        $group = new NodeGroup('Inherited Group', null, new \DateTimeImmutable('2026-08-20T10:03:00+00:00'));
        $group->replaceMonitoringTemplates([$customTemplate, $disabledTemplate], new \DateTimeImmutable('2026-08-20T10:04:00+00:00'));
        $node->replaceGroups([$group]);

        $entityManager->persist($customItem);
        $entityManager->persist($disabledItem);
        $entityManager->persist($customTemplate);
        $entityManager->persist($disabledTemplate);
        $entityManager->persist($group);
        $entityManager->flush();

        return $customItem;
    }

    private function persistEnrollmentToken(): string
    {
        $tokenGenerator = self::getContainer()->get(TokenGenerator::class);
        $tokenHasher = self::getContainer()->get(TokenHasher::class);
        $createdAt = new \DateTimeImmutable('-5 minutes');
        $rawToken = $tokenGenerator->generateEnrollmentToken();
        $token = new \App\Entity\Enrollment\EnrollmentToken(
            $tokenHasher->hash($rawToken),
            $createdAt,
            $createdAt->modify('+15 minutes'),
        );

        $this->entityManager()->persist($token);
        $this->entityManager()->flush();

        return $rawToken;
    }

    private function nodeById(string $id): Node
    {
        $node = $this->entityManager()->find(Node::class, $id);
        self::assertInstanceOf(Node::class, $node);

        return $node;
    }

    private function activeCredentialForAgent(string $agentId): AgentCredential
    {
        $agent = $this->entityManager()->find(Agent::class, $agentId);
        self::assertInstanceOf(Agent::class, $agent);

        $credential = $this->entityManager()->getRepository(AgentCredential::class)->findOneBy(['agent' => $agent], ['createdAt' => 'DESC']);
        self::assertInstanceOf(AgentCredential::class, $credential);

        return $credential;
    }

    private function createUser(string $email, ?string $systemRole = null): User
    {
        $now = new \DateTimeImmutable('2026-08-20T11:00:00+00:00');
        $user = new User($email, ['ROLE_USER'], $now);
        if (null !== $systemRole) {
            $this->assignSystemRole($user, $systemRole);
        }

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD), $now);
        $this->entityManager()->persist($user);
        $this->entityManager()->flush();

        return $user;
    }

    /** @param list<string> $permissions */
    private function assignPermissions(User $user, array $permissions): void
    {
        $permissionRepository = self::getContainer()->get(PermissionRepository::class);
        $role = new Role('Agent credential operator', 'agent-credential-operator', null, false, new \DateTimeImmutable('2026-08-20T11:05:00+00:00'));
        $entities = [];
        foreach ($permissions as $code) {
            $permission = $permissionRepository->findOneBy(['code' => $code]);
            self::assertNotNull($permission);
            $entities[] = $permission;
        }

        $role->replacePermissions($entities, new \DateTimeImmutable('2026-08-20T11:06:00+00:00'));
        $user->replaceBusinessRoles([$role], new \DateTimeImmutable('2026-08-20T11:07:00+00:00'));
        $this->entityManager()->persist($role);
        $this->entityManager()->flush();
    }

    private function login(Client $client, string $email): string
    {
        $response = $client->request('POST', '/api/auth/login', [
            'json' => ['email' => $email, 'password' => self::PASSWORD],
        ]);
        self::assertResponseIsSuccessful();

        $payload = $response->toArray();
        self::assertIsString($payload['token'] ?? null);

        return $payload['token'];
    }

    private static function createJsonClient(): Client
    {
        return self::createClient(defaultOptions: [
            'headers' => [
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ],
        ]);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
