<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Maintenance;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Node\Node;
use App\Entity\User\User;
use App\Repository\Maintenance\MaintenanceWindowRepository;
use App\Security\Rbac\SystemRole;
use App\Tests\Functional\Support\RbacTestTrait;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class MaintenanceWindowApiTest extends ApiTestCase
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

    public function testCrudValidationAndNodeGroupTargets(): void
    {
        $client = self::createJsonClient();
        $admin = $this->createUser('maint-admin@example.com', SystemRole::ADMIN);
        $token = $this->login($client, $admin->getUserIdentifier());
        $now = new \DateTimeImmutable('2026-08-25T12:00:00+00:00');
        $node = new Node('maintenance-node', null, 'linux', 'amd64', $now, $now);
        $this->entityManager()->persist($node);
        $this->entityManager()->flush();

        $group = $client->request('POST', '/api/node-groups', [
            'auth_bearer' => $token,
            'json' => ['name' => 'Maintenance targets'],
        ])->toArray();
        $groupId = $group['id'] ?? null;
        self::assertIsString($groupId);

        $client->request('POST', '/api/maintenance-windows', [
            'auth_bearer' => $token,
            'json' => [
                'name' => 'Invalid interval',
                'startsAt' => '2026-08-25T13:00:00+00:00',
                'endsAt' => '2026-08-25T13:00:00+00:00',
                'nodeIds' => [(string) $node->id()],
            ],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $client->request('POST', '/api/maintenance-windows', [
            'auth_bearer' => $token,
            'json' => [
                'name' => 'Invalid target',
                'startsAt' => '2026-08-25T12:00:00+00:00',
                'endsAt' => '2026-08-25T13:00:00+00:00',
                'nodeIds' => ['01K3H2V4WA0000000000000000'],
            ],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $created = $client->request('POST', '/api/maintenance-windows', [
            'auth_bearer' => $token,
            'json' => [
                'name' => 'Production patch',
                'description' => 'Kernel patching window',
                'startsAt' => '2026-08-25T12:00:00+02:00',
                'endsAt' => '2026-08-25T14:00:00+02:00',
                'isEnabled' => true,
                'nodeIds' => [(string) $node->id()],
                'nodeGroupIds' => [$groupId],
            ],
        ])->toArray();

        $windowId = $created['id'] ?? null;
        self::assertIsString($windowId);
        self::assertSame('Production patch', $created['name'] ?? null);
        self::assertSame('2026-08-25T10:00:00+00:00', $created['startsAt'] ?? null);
        self::assertCount(1, self::arrayValue($created, 'nodes'));
        self::assertCount(1, self::arrayValue($created, 'nodeGroups'));

        $read = $client->request('GET', '/api/maintenance-windows/'.$windowId, ['auth_bearer' => $token])->toArray();
        self::assertSame($windowId, $read['id'] ?? null);

        $updated = $client->request('PATCH', '/api/maintenance-windows/'.$windowId, [
            'auth_bearer' => $token,
            'json' => ['description' => null, 'isEnabled' => false, 'nodeGroupIds' => [$groupId], 'nodeIds' => []],
        ])->toArray();
        self::assertNull($updated['description'] ?? null);
        self::assertFalse($updated['isEnabled'] ?? true);
        self::assertCount(0, self::arrayValue($updated, 'nodes'));
        self::assertCount(1, self::arrayValue($updated, 'nodeGroups'));

        $collection = $client->request('GET', '/api/maintenance-windows', ['auth_bearer' => $token])->toArray();
        self::assertCount(1, self::arrayValue($collection, 'items'));

        $client->request('DELETE', '/api/maintenance-windows/'.$windowId, ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame(0, self::getContainer()->get(MaintenanceWindowRepository::class)->count([]));
        $auditRows = self::getContainer()->get(Connection::class)->fetchAllAssociative('SELECT type, diffs FROM audit_maintenance_windows WHERE object_id = ? ORDER BY id ASC', [$windowId]);
        self::assertGreaterThanOrEqual(3, \count($auditRows));
        self::assertContains('insert', array_column($auditRows, 'type'));
        self::assertContains('update', array_column($auditRows, 'type'));
        self::assertContains('remove', array_column($auditRows, 'type'));
        self::assertStringContainsString('startsAt', json_encode($auditRows, \JSON_THROW_ON_ERROR));
    }

    public function testMaintenanceWindowsUseDedicatedPermissions(): void
    {
        $client = self::createJsonClient();

        $viewerToken = $this->login($client, $this->createUser('maint-viewer@example.com', SystemRole::VIEWER)->getUserIdentifier());
        $client->request('GET', '/api/maintenance-windows', ['auth_bearer' => $viewerToken]);
        self::assertResponseIsSuccessful();
        $client->request('POST', '/api/maintenance-windows', [
            'auth_bearer' => $viewerToken,
            'json' => ['name' => 'Denied', 'startsAt' => '2026-08-25T12:00:00+00:00', 'endsAt' => '2026-08-25T13:00:00+00:00'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $operatorToken = $this->login($client, $this->createUser('maint-operator@example.com', SystemRole::OPERATOR)->getUserIdentifier());
        $client->request('GET', '/api/maintenance-windows', ['auth_bearer' => $operatorToken]);
        self::assertResponseIsSuccessful();
    }

    private function createUser(string $email, ?string $roleSlug = null): User
    {
        $now = new \DateTimeImmutable();
        $user = new User($email, ['ROLE_USER'], $now);
        if (null !== $roleSlug) {
            $this->assignSystemRole($user, $roleSlug);
        }
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
