<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Audit;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Rbac\Role;
use App\Entity\User\User;
use App\Repository\Rbac\RoleRepository;
use App\Security\Rbac\PermissionCode;
use App\Security\Rbac\SystemRole;
use App\Tests\Functional\Support\RbacTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuditLogTest extends ApiTestCase
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

    public function testUserCrudIsAuditedWithActorFiltersPaginationAndNoSecrets(): void
    {
        $client = self::createJsonClient();
        $root = $this->createUser('root@example.com', SystemRole::SUPER_ADMIN);
        $token = $this->login($client, $root->getUserIdentifier());
        $viewerRole = self::getContainer()->get(RoleRepository::class)->findOneBy(['slug' => SystemRole::VIEWER]);
        self::assertInstanceOf(Role::class, $viewerRole);

        $created = $client->request('POST', '/api/users', [
            'auth_bearer' => $token,
            'json' => ['email' => 'audited@example.com', 'password' => 'initial password for audit', 'roleIds' => [(string) $viewerRole->id()]],
        ])->toArray();
        $userId = $created['id'] ?? null;
        self::assertIsString($userId);

        $client->request('PATCH', '/api/users/'.$userId, [
            'auth_bearer' => $token,
            'json' => ['email' => 'updated@example.com', 'password' => 'replacement password for audit'],
        ]);
        self::assertResponseIsSuccessful();
        $client->request('DELETE', '/api/users/'.$userId, ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $response = $client->request('GET', '/api/audits?entity=User&entityId='.$userId.'&actor='.$root->id().'&itemsPerPage=1&page=2&dateFrom=2000-01-01T00%3A00%3A00%2B00%3A00&dateTo=2100-01-01T00%3A00%3A00%2B00%3A00', ['auth_bearer' => $token]);
        self::assertResponseIsSuccessful();
        $payload = $response->toArray();
        $items = $payload['items'] ?? null;
        $metadata = $payload['metadata'] ?? null;
        self::assertIsArray($items);
        self::assertIsArray($metadata);
        self::assertCount(1, $items);
        self::assertGreaterThanOrEqual(3, $metadata['totalItems'] ?? 0);
        self::assertSame(2, $metadata['currentPage'] ?? null);
        $audit = $items[0] ?? null;
        self::assertIsArray($audit);
        $actor = $audit['actor'] ?? null;
        self::assertIsArray($actor);
        self::assertSame('User', $audit['entity'] ?? null);
        self::assertSame($userId, $audit['entityId'] ?? null);
        self::assertSame((string) $root->id(), $actor['id'] ?? null);
        self::assertSame('root@example.com', $actor['email'] ?? null);
        self::assertSame('127.0.0.1', $audit['ip'] ?? null);
        self::assertSame('api', $audit['securityContext'] ?? null);
        self::assertStringNotContainsString('password', strtolower($response->getContent(false)));
        self::assertStringNotContainsString('initial password for audit', $response->getContent(false));
        self::assertStringNotContainsString('replacement password for audit', $response->getContent(false));

        $updates = $client->request('GET', '/api/audits?entity=User&entityId='.$userId.'&action=update', ['auth_bearer' => $token])->toArray();
        $updateItems = $updates['items'] ?? null;
        self::assertIsArray($updateItems);
        self::assertNotEmpty($updateItems);
        foreach ($updateItems as $updateAudit) {
            self::assertIsArray($updateAudit);
            self::assertSame('update', $updateAudit['action'] ?? null);
        }
    }

    public function testOnlySuperAdminAndAdminCanReadAudits(): void
    {
        $client = self::createJsonClient();
        $client->request('GET', '/api/audits');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        foreach ([SystemRole::SUPER_ADMIN, SystemRole::ADMIN] as $index => $role) {
            $user = $this->createUser($role.$index.'@example.com', $role);
            $client->request('GET', '/api/audits', ['auth_bearer' => $this->login($client, $user->getUserIdentifier())]);
            self::assertResponseIsSuccessful();
        }

        foreach ([SystemRole::OPERATOR, SystemRole::VIEWER] as $index => $role) {
            $user = $this->createUser($role.$index.'@example.com', $role);
            $client->request('GET', '/api/audits', ['auth_bearer' => $this->login($client, $user->getUserIdentifier())]);
            self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        }
    }

    public function testRoleNodeNodeGroupAndEnrollmentBusinessChangesAreAudited(): void
    {
        $client = self::createJsonClient();
        $root = $this->createUser('business-audit@example.com', SystemRole::SUPER_ADMIN);
        $token = $this->login($client, $root->getUserIdentifier());

        $role = $client->request('POST', '/api/roles', [
            'auth_bearer' => $token,
            'json' => ['name' => 'Audit operators', 'permissionCodes' => [PermissionCode::NODES_READ]],
        ])->toArray();
        $roleId = $role['id'] ?? null;
        self::assertIsString($roleId);
        $client->request('PATCH', '/api/roles/'.$roleId, [
            'auth_bearer' => $token,
            'json' => ['name' => 'Audited operators', 'permissionCodes' => [PermissionCode::NODES_READ, PermissionCode::NODES_UPDATE]],
        ]);
        self::assertResponseIsSuccessful();
        $roleUpdates = $this->auditItems($client, $token, 'Role', $roleId, 'update');
        self::assertNotEmpty($roleUpdates);
        self::assertStringContainsString('Audited operators', json_encode($roleUpdates, \JSON_THROW_ON_ERROR));
        self::assertNotEmpty($this->auditItems($client, $token, 'Role', $roleId, 'associate'));

        $node = new \App\Entity\Node\Node('audit-node', null, 'linux', 'x86_64', new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->entityManager()->persist($node);
        $this->entityManager()->flush();
        $client->request('PATCH', '/api/nodes/'.$node->id(), [
            'auth_bearer' => $token,
            'json' => ['displayName' => 'Production database', 'environment' => 'production', 'tags' => ['database', 'critical']],
        ]);
        self::assertResponseIsSuccessful();
        $nodeUpdates = $this->auditItems($client, $token, 'Node', (string) $node->id(), 'update');
        self::assertNotEmpty($nodeUpdates);
        $nodeAudit = $nodeUpdates[0];
        self::assertIsArray($nodeAudit);
        $nodeChanges = $nodeAudit['changes'] ?? null;
        self::assertIsArray($nodeChanges);
        self::assertArrayHasKey('displayName', $nodeChanges);
        self::assertArrayHasKey('environment', $nodeChanges);
        self::assertArrayHasKey('tags', $nodeChanges);

        $group = $client->request('POST', '/api/node-groups', [
            'auth_bearer' => $token,
            'json' => ['name' => 'Production', 'description' => 'Critical systems'],
        ])->toArray();
        $groupId = $group['id'] ?? null;
        self::assertIsString($groupId);
        $client->request('PATCH', '/api/node-groups/'.$groupId, ['auth_bearer' => $token, 'json' => ['name' => 'Production core']]);
        self::assertResponseIsSuccessful();
        $client->request('DELETE', '/api/node-groups/'.$groupId, ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        foreach (['insert', 'update', 'remove'] as $action) {
            self::assertNotEmpty($this->auditItems($client, $token, 'NodeGroup', $groupId, $action));
        }

        $itemDefinition = $client->request('POST', '/api/item-definitions', [
            'auth_bearer' => $token,
            'json' => [
                'key' => 'audit.custom.item',
                'name' => 'Audited custom item',
                'valueType' => 'float',
                'intervalSeconds' => 60,
            ],
        ])->toArray();
        $itemDefinitionId = $itemDefinition['id'] ?? null;
        self::assertIsString($itemDefinitionId);
        $client->request('PATCH', '/api/item-definitions/'.$itemDefinitionId, [
            'auth_bearer' => $token,
            'json' => ['description' => 'Updated by audit test'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertNotEmpty($this->auditItems($client, $token, 'ItemDefinition', $itemDefinitionId, 'insert'));
        self::assertNotEmpty($this->auditItems($client, $token, 'ItemDefinition', $itemDefinitionId, 'update'));

        $monitoringTemplate = $client->request('POST', '/api/monitoring-templates', [
            'auth_bearer' => $token,
            'json' => [
                'name' => 'Audit template',
                'itemDefinitionIds' => [$itemDefinitionId],
            ],
        ])->toArray();
        $monitoringTemplateId = $monitoringTemplate['id'] ?? null;
        self::assertIsString($monitoringTemplateId);
        $client->request('PATCH', '/api/monitoring-templates/'.$monitoringTemplateId, [
            'auth_bearer' => $token,
            'json' => ['description' => 'Patched for audit coverage'],
        ]);
        self::assertResponseIsSuccessful();
        $client->request('DELETE', '/api/monitoring-templates/'.$monitoringTemplateId, ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertNotEmpty($this->auditItems($client, $token, 'MonitoringTemplate', $monitoringTemplateId, 'insert'));
        self::assertNotEmpty($this->auditItems($client, $token, 'MonitoringTemplate', $monitoringTemplateId, 'update'));
        self::assertNotEmpty($this->auditItems($client, $token, 'MonitoringTemplate', $monitoringTemplateId, 'remove'));

        $client->request('DELETE', '/api/item-definitions/'.$itemDefinitionId, ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertNotEmpty($this->auditItems($client, $token, 'ItemDefinition', $itemDefinitionId, 'remove'));

        $enrollment = $client->request('POST', '/api/enrollment-tokens', ['auth_bearer' => $token, 'json' => []])->toArray();
        $enrollmentId = $enrollment['id'] ?? null;
        $rawToken = $enrollment['token'] ?? null;
        self::assertIsString($enrollmentId);
        self::assertIsString($rawToken);
        $enrollmentAudits = $this->auditItems($client, $token, 'EnrollmentToken', $enrollmentId, 'insert');
        self::assertNotEmpty($enrollmentAudits);
        $encodedEnrollmentAudits = json_encode($enrollmentAudits, \JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('tokenHash', $encodedEnrollmentAudits);
        self::assertStringNotContainsString($rawToken, $encodedEnrollmentAudits);

        $schemaManager = $this->entityManager()->getConnection()->createSchemaManager();
        self::assertTrue($schemaManager->tablesExist(['audit_agent_credentials']));
        foreach (['audit_users', 'audit_enrollment_tokens', 'audit_agent_credentials'] as $table) {
            $storedDiffs = $this->entityManager()->getConnection()->fetchFirstColumn('SELECT diffs FROM '.$table);
            $encodedDiffs = json_encode($storedDiffs, \JSON_THROW_ON_ERROR);
            self::assertStringNotContainsString('password', strtolower($encodedDiffs));
            self::assertStringNotContainsString('tokenhash', strtolower($encodedDiffs));
            self::assertStringNotContainsString('secrethash', strtolower($encodedDiffs));
            self::assertStringNotContainsString($rawToken, $encodedDiffs);
        }
    }

    public function testAuditContractAndQueryValidationAreDocumented(): void
    {
        $client = self::createClient();
        $client->request('GET', '/audit');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $document = $client->request('GET', '/api/docs.jsonopenapi', ['headers' => ['accept' => 'application/vnd.openapi+json']])->toArray(false);
        $paths = $document['paths'] ?? null;
        self::assertIsArray($paths);
        self::assertArrayHasKey('/api/audits', $paths);
        $auditPath = $paths['/api/audits'];
        self::assertIsArray($auditPath);
        $operation = $auditPath['get'];
        self::assertIsArray($operation);
        self::assertSame('Lists control-plane audit events with filters and pagination.', $operation['summary'] ?? null);
        $operationParameters = $operation['parameters'] ?? null;
        self::assertIsArray($operationParameters);
        $parameters = array_column($operationParameters, 'name');
        self::assertSame(['page', 'itemsPerPage', 'entity', 'entityId', 'action', 'actor', 'dateFrom', 'dateTo'], $parameters);

        $admin = $this->createUser('validation@example.com', SystemRole::ADMIN);
        $token = $this->login($client, $admin->getUserIdentifier());
        $client->request('GET', '/api/audits?unknown=true', ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $client->request('GET', '/api/audits?dateFrom=not-a-date', ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    private function createUser(string $email, string $roleSlug): User
    {
        $now = new \DateTimeImmutable();
        $user = new User($email, ['ROLE_USER'], $now);
        $this->assignSystemRole($user, $roleSlug);
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
        $token = $response->toArray()['token'];
        self::assertIsString($token);

        return $token;
    }

    /** @return list<mixed> */
    private function auditItems(Client $client, string $token, string $entity, string $entityId, string $action): array
    {
        $payload = $client->request('GET', \sprintf('/api/audits?entity=%s&entityId=%s&action=%s&itemsPerPage=100', $entity, $entityId, $action), ['auth_bearer' => $token])->toArray();
        $items = $payload['items'] ?? null;
        self::assertIsArray($items);

        return array_values($items);
    }

    private static function createJsonClient(): Client
    {
        return self::createClient(defaultOptions: ['headers' => ['accept' => 'application/json', 'content-type' => 'application/json']]);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
