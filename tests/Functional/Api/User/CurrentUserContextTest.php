<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\User;

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
use Symfony\Contracts\HttpClient\ResponseInterface;

final class CurrentUserContextTest extends ApiTestCase
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

    public function testCurrentUserEndpointsRequireAuthentication(): void
    {
        $client = self::createJsonClient();
        $client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $client->request('PATCH', '/api/me', ['json' => ['locale' => 'en']]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testContextContainsStableRolesAndDeduplicatedEffectivePermissions(): void
    {
        $client = self::createJsonClient();
        $user = $this->createUser('operator@example.com', [SystemRole::OPERATOR, SystemRole::VIEWER]);
        $response = $client->request('GET', '/api/me', ['auth_bearer' => $this->login($client, $user)]);
        self::assertResponseIsSuccessful();
        $context = self::responseObject($response);

        self::assertSame((string) $user->id(), $context['id']);
        self::assertSame('operator@example.com', $context['email']);
        self::assertSame('en', $context['locale']);
        self::assertSame(['operator', 'viewer'], array_column(self::arrayValue($context, 'roles'), 'slug'));

        $permissions = self::stringList($context, 'permissions');
        $expected = [
            PermissionCode::NODES_READ,
            PermissionCode::NODES_UPDATE,
            PermissionCode::NODE_GROUPS_READ,
            PermissionCode::NODE_GROUPS_CREATE,
            PermissionCode::NODE_GROUPS_UPDATE,
            PermissionCode::NODE_GROUPS_DELETE,
        ];
        sort($expected, \SORT_STRING);
        self::assertSame($expected, $permissions);
        self::assertSame($permissions, array_values(array_unique($permissions)));

        $body = $response->getContent(false);
        self::assertStringNotContainsString('password', $body);
        self::assertStringNotContainsString($user->getPassword(), $body);
        self::assertStringNotContainsString('secretHash', $body);
        self::assertStringNotContainsString('tokenHash', $body);
    }

    public function testSuperAdminContextContainsEveryKnownPermission(): void
    {
        $client = self::createJsonClient();
        $user = $this->createUser('root@example.com', [SystemRole::SUPER_ADMIN]);
        $context = self::responseObject($client->request('GET', '/api/me', ['auth_bearer' => $this->login($client, $user)]));
        $permissions = self::stringList($context, 'permissions');
        $expected = array_keys(PermissionCode::catalog());
        sort($expected, \SORT_STRING);

        self::assertSame($expected, $permissions);
    }

    public function testCurrentUserCanOnlyUpdateToASupportedLocale(): void
    {
        $client = self::createJsonClient();
        $user = $this->createUser('viewer@example.com', [SystemRole::VIEWER]);
        $token = $this->login($client, $user);

        $updated = $client->request('PATCH', '/api/me', ['auth_bearer' => $token, 'json' => ['locale' => ' EN ']])->toArray();
        self::assertSame('en', $updated['locale']);
        self::assertSame('viewer@example.com', $updated['email']);

        $invalidLocale = $client->request('PATCH', '/api/me', ['auth_bearer' => $token, 'json' => ['locale' => 'fr']]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertStringContainsString('not supported', $invalidLocale->getContent(false));

        foreach ([
            ['email' => 'attacker@example.com'],
            ['roles' => []],
            ['permissions' => [PermissionCode::USERS_CREATE]],
        ] as $forbiddenField) {
            $client->request('PATCH', '/api/me', ['auth_bearer' => $token, 'json' => ['locale' => 'en', ...$forbiddenField]]);
            self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        }

        $stored = $this->entityManager()->find(User::class, $user->id());
        self::assertInstanceOf(User::class, $stored);
        self::assertSame('viewer@example.com', $stored->getUserIdentifier());
        self::assertSame(['viewer'], array_map(static fn (Role $role): string => $role->slug(), $stored->businessRoles()->toArray()));
    }

    /** @param list<string> $roleSlugs */
    private function createUser(string $email, array $roleSlugs): User
    {
        $now = new \DateTimeImmutable();
        $roles = [];
        $repository = self::getContainer()->get(RoleRepository::class);
        foreach ($roleSlugs as $slug) {
            $role = $repository->findOneBy(['slug' => $slug]);
            self::assertInstanceOf(Role::class, $role);
            $roles[] = $role;
        }

        $user = new User($email, ['ROLE_USER'], $now);
        $user->replaceBusinessRoles($roles, $now);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD), $now);
        $this->entityManager()->persist($user);
        $this->entityManager()->flush();

        return $user;
    }

    private function login(Client $client, User $user): string
    {
        $response = $client->request('POST', '/api/auth/login', [
            'json' => ['email' => $user->getUserIdentifier(), 'password' => self::PASSWORD],
        ]);
        self::assertResponseIsSuccessful();
        $token = $response->toArray()['token'];
        self::assertIsString($token);

        return $token;
    }

    /** @param array<string, mixed> $object
     * @return list<array<string, mixed>>
     */
    private static function arrayValue(array $object, string $key): array
    {
        $value = $object[$key] ?? null;
        self::assertIsArray($value);

        $items = [];
        foreach ($value as $item) {
            self::assertIsArray($item);
            $objectItem = [];
            foreach ($item as $itemKey => $itemValue) {
                self::assertIsString($itemKey);
                $objectItem[$itemKey] = $itemValue;
            }
            $items[] = $objectItem;
        }

        return $items;
    }

    /** @param array<string, mixed> $object
     * @return list<string>
     */
    private static function stringList(array $object, string $key): array
    {
        $value = $object[$key] ?? null;
        self::assertIsArray($value);
        $items = [];
        foreach ($value as $item) {
            self::assertIsString($item);
            $items[] = $item;
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private static function responseObject(ResponseInterface $response): array
    {
        $decoded = json_decode($response->getContent(false), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        $object = [];
        foreach ($decoded as $key => $value) {
            self::assertIsString($key);
            $object[$key] = $value;
        }

        return $object;
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
