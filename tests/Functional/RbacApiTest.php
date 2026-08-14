<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Node;
use App\Entity\Role;
use App\Entity\User;
use App\Repository\RoleRepository;
use App\Security\PermissionCode;
use App\Security\SystemRole;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RbacApiTest extends ApiTestCase
{
    use RbacTestTrait;

    private const string PASSWORD = 'correct horse battery staple';

    protected static ?bool $alwaysBootKernel = true;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $entityManager = $this->entityManager();
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($metadata);
        $this->initializeRbac();
        self::ensureKernelShutdown();
    }

    public function testRoleAndUserCrudUsesStandardRestAndNeverExposesPassword(): void
    {
        $client = self::createJsonClient();
        $superAdmin = $this->createUser('root@example.com', SystemRole::SUPER_ADMIN);
        $token = $this->login($client, $superAdmin->getUserIdentifier());

        $permissions = $client->request('GET', '/api/permissions?itemsPerPage=100', ['auth_bearer' => $token])->toArray();
        $permissionItems = $permissions['items'] ?? null;
        $permissionMetadata = $permissions['metadata'] ?? null;
        self::assertIsArray($permissionItems);
        self::assertIsArray($permissionMetadata);
        self::assertCount(\count(PermissionCode::catalog()), $permissionItems);
        self::assertSame(\count(PermissionCode::catalog()), $permissionMetadata['totalItems'] ?? null);

        $roleResponse = $client->request('POST', '/api/roles', [
            'auth_bearer' => $token,
            'json' => [
                'name' => 'Auditor Team',
                'permissionCodes' => [PermissionCode::NODES_READ],
            ],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $role = $roleResponse->toArray();
        self::assertSame('auditor-team', $role['slug']);
        self::assertFalse($role['isSystem']);

        $client->request('POST', '/api/roles', [
            'auth_bearer' => $token,
            'json' => ['name' => 'Invalid role', 'permissionCodes' => ['unknown.permission']],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $roleId = $role['id'];
        self::assertIsString($roleId);
        $updatedRole = $client->request('PATCH', '/api/roles/'.$roleId, [
            'auth_bearer' => $token,
            'json' => ['description' => 'Read-only audit access', 'permissionCodes' => [PermissionCode::NODES_READ, PermissionCode::NODE_GROUPS_READ]],
        ])->toArray();
        self::assertSame('Read-only audit access', $updatedRole['description']);
        $updatedPermissions = $updatedRole['permissions'] ?? null;
        self::assertIsArray($updatedPermissions);
        self::assertCount(2, $updatedPermissions);

        $userResponse = $client->request('POST', '/api/users', [
            'auth_bearer' => $token,
            'json' => ['email' => 'Auditor@Example.com', 'password' => self::PASSWORD, 'roleIds' => [$roleId]],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $user = $userResponse->toArray();
        self::assertSame('auditor@example.com', $user['email']);
        self::assertStringNotContainsString('password', $userResponse->getContent(false));
        self::assertStringNotContainsString(self::PASSWORD, $userResponse->getContent(false));
        $stored = $this->entityManager()->getRepository(User::class)->findOneBy(['email' => 'auditor@example.com']);
        self::assertInstanceOf(User::class, $stored);
        self::assertNotSame(self::PASSWORD, $stored->getPassword());

        $userId = $user['id'];
        self::assertIsString($userId);
        $client->request('PATCH', '/api/users/'.$userId, [
            'auth_bearer' => $token,
            'json' => ['email' => 'audit@example.com', 'password' => 'a different secure password'],
        ]);
        self::assertResponseIsSuccessful();

        $users = $client->request('GET', '/api/users?itemsPerPage=1&page=2', ['auth_bearer' => $token])->toArray();
        $userItems = $users['items'] ?? null;
        $userMetadata = $users['metadata'] ?? null;
        self::assertIsArray($userItems);
        self::assertIsArray($userMetadata);
        self::assertSame(2, $userMetadata['totalItems'] ?? null);
        self::assertCount(1, $userItems);

        $client->request('DELETE', '/api/users/'.$userId, ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $client->request('DELETE', '/api/roles/'.$roleId, ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $client->request('POST', '/api/permissions', ['auth_bearer' => $token, 'json' => []]);
        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }

    public function testPermissionMappingsEnforceViewerOperatorAndUnprivilegedAccess(): void
    {
        $client = self::createJsonClient();
        $node = new Node('rbac-node', null, 'linux', 'x86_64', new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->entityManager()->persist($node);
        $this->entityManager()->flush();

        $viewerToken = $this->login($client, $this->createUser('viewer@example.com', SystemRole::VIEWER)->getUserIdentifier());
        $client->request('GET', '/api/nodes', ['auth_bearer' => $viewerToken]);
        self::assertResponseIsSuccessful();
        $client->request('PATCH', '/api/nodes/'.$node->id(), ['auth_bearer' => $viewerToken, 'json' => ['displayName' => 'Denied']]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $client->request('GET', '/api/node-groups', ['auth_bearer' => $viewerToken]);
        self::assertResponseIsSuccessful();
        $client->request('POST', '/api/node-groups', ['auth_bearer' => $viewerToken, 'json' => ['name' => 'Denied']]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $operatorToken = $this->login($client, $this->createUser('operator@example.com', SystemRole::OPERATOR)->getUserIdentifier());
        $client->request('PATCH', '/api/nodes/'.$node->id(), ['auth_bearer' => $operatorToken, 'json' => ['displayName' => 'Allowed']]);
        self::assertResponseIsSuccessful();
        $client->request('GET', '/api/users', ['auth_bearer' => $operatorToken]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $adminToken = $this->login($client, $this->createUser('admin@example.com', SystemRole::ADMIN)->getUserIdentifier());
        $client->request('POST', '/api/enrollment-tokens', ['auth_bearer' => $adminToken, 'json' => []]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $noRoleToken = $this->login($client, $this->createUser('none@example.com')->getUserIdentifier());
        $client->request('GET', '/api/nodes', ['auth_bearer' => $noRoleToken]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testSystemRolesAndLastSuperAdminAreProtected(): void
    {
        $client = self::createJsonClient();
        $superAdmin = $this->createUser('root@example.com', SystemRole::SUPER_ADMIN);
        $token = $this->login($client, $superAdmin->getUserIdentifier());
        $superAdminRole = self::getContainer()->get(RoleRepository::class)->findOneBy(['slug' => SystemRole::SUPER_ADMIN]);
        self::assertInstanceOf(Role::class, $superAdminRole);

        $client->request('DELETE', '/api/roles/'.$superAdminRole->id(), ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $client->request('PATCH', '/api/roles/'.$superAdminRole->id(), ['auth_bearer' => $token, 'json' => ['permissionCodes' => []]]);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $client->request('PATCH', '/api/users/'.$superAdmin->id(), ['auth_bearer' => $token, 'json' => ['roleIds' => []]]);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $client->request('DELETE', '/api/users/'.$superAdmin->id(), ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
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
        $token = $response->toArray()['token'];
        self::assertIsString($token);

        return $token;
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
