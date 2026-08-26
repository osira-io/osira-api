<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Monitoring;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Node\Node;
use App\Entity\Rbac\Role;
use App\Entity\User\User;
use App\Repository\Node\NodeRepository;
use App\Repository\Rbac\PermissionRepository;
use App\Security\Rbac\PermissionCode;
use App\Security\Rbac\SystemRole;
use App\Service\Monitoring\EffectiveNodeMonitoringResolver;
use App\Tests\Functional\Support\RbacTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class MonitoringApiTest extends ApiTestCase
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

    public function testEmptyCatalogCustomItemCrudAndGroupOnlyAssignments(): void
    {
        $client = self::createJsonClient();
        $superAdmin = $this->createUser('root@example.com', SystemRole::SUPER_ADMIN);
        $token = $this->login($client, $superAdmin->getUserIdentifier());

        $client->request('GET', '/api/monitoring-templates');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        self::assertSame([], $client->request('GET', '/api/monitoring-templates', ['auth_bearer' => $token])->toArray()['items'] ?? null);
        self::assertSame([], $client->request('GET', '/api/item-definitions', ['auth_bearer' => $token])->toArray()['items'] ?? null);

        $client->request('POST', '/api/item-definitions', [
            'auth_bearer' => $token,
            'json' => [
                'key' => 'custom.check.latency',
                'name' => 'Custom latency',
                'unit' => 'ms',
                'valueType' => 'duration',
                'intervalSeconds' => 30,
                'linuxCommand' => 'printf 1',
            ],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $item = $client->request('POST', '/api/item-definitions', [
            'auth_bearer' => $token,
            'json' => [
                'key' => 'custom.check.latency',
                'name' => 'Custom latency',
                'description' => 'Latency of a custom TCP probe.',
                'unit' => 'ms',
                'valueType' => 'float',
                'intervalSeconds' => 30,
                'timeoutSeconds' => 5,
                'linuxCommand' => "printf '12.5'",
                'isEnabled' => true,
            ],
        ])->toArray();
        $itemId = $item['id'] ?? null;
        self::assertIsString($itemId);

        $template = $client->request('POST', '/api/monitoring-templates', [
            'auth_bearer' => $token,
            'json' => [
                'name' => 'Custom TCP Template',
                'description' => 'Checks a custom endpoint.',
                'itemDefinitionIds' => [$itemId],
                'isEnabled' => true,
            ],
        ])->toArray();
        $templateId = $template['id'] ?? null;
        self::assertIsString($templateId);
        self::assertSame('custom-tcp-template', $template['slug'] ?? null);

        $readTemplate = $client->request('GET', '/api/monitoring-templates/'.$templateId, ['auth_bearer' => $token])->toArray();
        self::assertSame($templateId, $readTemplate['id'] ?? null);
        $readTemplateItems = $readTemplate['itemDefinitions'] ?? null;
        self::assertIsArray($readTemplateItems);
        self::assertCount(1, $readTemplateItems);
        $readTemplateFirstItem = reset($readTemplateItems);
        self::assertIsArray($readTemplateFirstItem);
        self::assertSame($itemId, $readTemplateFirstItem['id'] ?? null);

        $readItem = $client->request('GET', '/api/item-definitions/'.$itemId, ['auth_bearer' => $token])->toArray();
        self::assertSame($itemId, $readItem['id'] ?? null);
        self::assertSame('custom.check.latency', $readItem['key'] ?? null);

        $node = new Node('srv-monitor-01', null, 'linux', 'x86_64', new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->entityManager()->persist($node);
        $this->entityManager()->flush();

        $group = $client->request('POST', '/api/node-groups', [
            'auth_bearer' => $token,
            'json' => ['name' => 'Monitored Linux', 'monitoringTemplateIds' => [$templateId]],
        ])->toArray();
        $groupId = $group['id'] ?? null;
        self::assertIsString($groupId);
        $readGroup = $client->request('GET', '/api/node-groups/'.$groupId, ['auth_bearer' => $token])->toArray();
        self::assertSame($groupId, $readGroup['id'] ?? null);

        $nodeResponse = $client->request('PATCH', '/api/nodes/'.$node->id(), [
            'auth_bearer' => $token,
            'json' => ['groups' => [$groupId]],
        ])->toArray();
        self::assertArrayNotHasKey('monitoringTemplates', $nodeResponse);

        $updatedGroup = $client->request('PATCH', '/api/node-groups/'.$groupId, [
            'auth_bearer' => $token,
            'json' => ['monitoringTemplateIds' => [$templateId]],
        ])->toArray();
        $updatedGroupMonitoringTemplates = $updatedGroup['monitoringTemplates'] ?? null;
        self::assertIsArray($updatedGroupMonitoringTemplates);
        self::assertCount(1, $updatedGroupMonitoringTemplates);

        $storedNode = self::getContainer()->get(NodeRepository::class)->find($node->id());
        self::assertInstanceOf(Node::class, $storedNode);
        $resolved = (new EffectiveNodeMonitoringResolver())->resolve($storedNode);
        self::assertSame(['custom-tcp-template'], array_map(
            static fn ($monitoringTemplate): string => $monitoringTemplate->slug(),
            $resolved->templates,
        ));
        self::assertContains('custom.check.latency', array_map(
            static fn ($itemDefinition): string => $itemDefinition->key(),
            $resolved->items,
        ));

        $client->request('PATCH', '/api/item-definitions/'.$itemId, [
            'auth_bearer' => $token,
            'json' => ['isEnabled' => false],
        ]);
        self::assertResponseIsSuccessful();

        $storedNodeAfterDisable = self::getContainer()->get(NodeRepository::class)->find($node->id());
        self::assertInstanceOf(Node::class, $storedNodeAfterDisable);
        $resolvedAfterDisable = (new EffectiveNodeMonitoringResolver())->resolve($storedNodeAfterDisable);
        self::assertNotContains('custom.check.latency', array_map(
            static fn ($itemDefinition): string => $itemDefinition->key(),
            $resolvedAfterDisable->items,
        ));
    }

    public function testMonitoringEndpointsUseDedicatedPermissions(): void
    {
        $client = self::createJsonClient();

        $viewerToken = $this->login($client, $this->createUser('viewer@example.com', SystemRole::VIEWER)->getUserIdentifier());
        $client->request('GET', '/api/monitoring-templates', ['auth_bearer' => $viewerToken]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $operatorToken = $this->login($client, $this->createUser('operator@example.com', SystemRole::OPERATOR)->getUserIdentifier());
        $client->request('GET', '/api/item-definitions', ['auth_bearer' => $operatorToken]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $adminToken = $this->login($client, $this->createUser('admin@example.com', SystemRole::ADMIN)->getUserIdentifier());
        $client->request('GET', '/api/monitoring-templates', ['auth_bearer' => $adminToken]);
        self::assertResponseIsSuccessful();
    }

    public function testItemsRequireAtLeastOneValidOsCommandAndSupportBothPlatforms(): void
    {
        $client = self::createJsonClient();
        $token = $this->login($client, $this->createUser('commands@example.com', SystemRole::ADMIN)->getUserIdentifier());

        $client->request('POST', '/api/item-definitions', [
            'auth_bearer' => $token,
            'json' => ['key' => 'custom.none', 'name' => 'None', 'valueType' => 'integer', 'intervalSeconds' => 30, 'timeoutSeconds' => 5],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        foreach ([
            ['key' => 'custom.linux', 'linuxCommand' => 'printf 1'],
            ['key' => 'custom.windows', 'windowsCommand' => 'Write-Output 1'],
            ['key' => 'custom.cross', 'linuxCommand' => 'printf 1', 'windowsCommand' => 'Write-Output 1'],
        ] as $definition) {
            $client->request('POST', '/api/item-definitions', [
                'auth_bearer' => $token,
                'json' => ['name' => $definition['key'], 'valueType' => 'integer', 'intervalSeconds' => 30, 'timeoutSeconds' => 5, ...$definition],
            ]);
            self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        }

        self::assertSame(3, $this->entityManager()->getRepository(\App\Entity\Monitoring\ItemDefinition::class)->count([]));
    }

    public function testManagingCommandsRequiresTheDedicatedPermissionInAdditionToCreate(): void
    {
        $client = self::createJsonClient();
        $operator = $this->createUser('limited@example.com');
        $this->assignPermissions($operator, [PermissionCode::ITEM_DEFINITIONS_CREATE]);
        $token = $this->login($client, $operator->getUserIdentifier());

        $client->request('POST', '/api/item-definitions', [
            'auth_bearer' => $token,
            'json' => [
                'key' => 'custom.denied', 'name' => 'Denied', 'valueType' => 'integer',
                'intervalSeconds' => 30, 'timeoutSeconds' => 5, 'linuxCommand' => 'printf 1',
            ],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
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

    /** @param list<string> $permissions */
    private function assignPermissions(User $user, array $permissions): void
    {
        $repository = self::getContainer()->get(PermissionRepository::class);
        $role = new Role('Limited item creator', 'limited-item-creator', null, false, new \DateTimeImmutable());
        $entities = [];
        foreach ($permissions as $code) {
            $permission = $repository->findOneBy(['code' => $code]);
            self::assertNotNull($permission);
            $entities[] = $permission;
        }
        $role->replacePermissions($entities, new \DateTimeImmutable());
        $user->replaceBusinessRoles([$role], new \DateTimeImmutable());
        $this->entityManager()->persist($role);
        $this->entityManager()->flush();
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

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
