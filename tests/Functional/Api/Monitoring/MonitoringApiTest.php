<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Monitoring;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Node\Node;
use App\Entity\User\User;
use App\Repository\Node\NodeRepository;
use App\Security\Rbac\SystemRole;
use App\Service\Monitoring\EffectiveNodeMonitoringResolver;
use App\Service\Monitoring\MonitoringCatalogSynchronizer;
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
        self::getContainer()->get(MonitoringCatalogSynchronizer::class)->synchronize();
        self::ensureKernelShutdown();
    }

    public function testCrudAssignmentsAndSystemProtectionForMonitoringCatalog(): void
    {
        $client = self::createJsonClient();
        $superAdmin = $this->createUser('root@example.com', SystemRole::SUPER_ADMIN);
        $token = $this->login($client, $superAdmin->getUserIdentifier());

        $client->request('GET', '/api/monitoring-templates');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $systemTemplates = $client->request('GET', '/api/monitoring-templates?itemsPerPage=100', ['auth_bearer' => $token])->toArray();
        $systemItems = $client->request('GET', '/api/item-definitions?itemsPerPage=100', ['auth_bearer' => $token])->toArray();
        $systemTemplateItems = $systemTemplates['items'] ?? null;
        $systemItemItems = $systemItems['items'] ?? null;
        self::assertIsArray($systemTemplateItems);
        self::assertIsArray($systemItemItems);

        self::assertCount(3, $systemTemplateItems);
        self::assertCount(15, $systemItemItems);

        $client->request('POST', '/api/item-definitions', [
            'auth_bearer' => $token,
            'json' => [
                'key' => 'custom.check.latency',
                'name' => 'Custom latency',
                'category' => 'Custom',
                'unit' => 'ms',
                'valueType' => 'duration',
                'intervalSeconds' => 30,
            ],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $item = $client->request('POST', '/api/item-definitions', [
            'auth_bearer' => $token,
            'json' => [
                'key' => 'custom.check.latency',
                'name' => 'Custom latency',
                'description' => 'Latency of a custom TCP probe.',
                'category' => 'Custom',
                'unit' => 'ms',
                'valueType' => 'float',
                'intervalSeconds' => 30,
                'timeoutSeconds' => 5,
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

        $node = new Node('srv-monitor-01', null, 'linux', 'x86_64', new \DateTimeImmutable(), new \DateTimeImmutable());
        $this->entityManager()->persist($node);
        $this->entityManager()->flush();

        $group = $client->request('POST', '/api/node-groups', [
            'auth_bearer' => $token,
            'json' => ['name' => 'Monitored Linux', 'monitoringTemplateIds' => [$templateId]],
        ])->toArray();
        $groupId = $group['id'] ?? null;
        self::assertIsString($groupId);

        $linuxBaseId = $this->templateIdBySlug($client, $token, 'linux-base');
        $dockerBaseId = $this->templateIdBySlug($client, $token, 'docker-base');

        $nodeResponse = $client->request('PATCH', '/api/nodes/'.$node->id(), [
            'auth_bearer' => $token,
            'json' => [
                'groups' => [$groupId],
                'monitoringTemplateIds' => [$linuxBaseId, $dockerBaseId],
            ],
        ])->toArray();
        $nodeMonitoringTemplates = $nodeResponse['monitoringTemplates'] ?? null;
        self::assertIsArray($nodeMonitoringTemplates);
        self::assertCount(2, $nodeMonitoringTemplates);

        $updatedGroup = $client->request('PATCH', '/api/node-groups/'.$groupId, [
            'auth_bearer' => $token,
            'json' => ['monitoringTemplateIds' => [$templateId, $dockerBaseId]],
        ])->toArray();
        $updatedGroupMonitoringTemplates = $updatedGroup['monitoringTemplates'] ?? null;
        self::assertIsArray($updatedGroupMonitoringTemplates);
        self::assertCount(2, $updatedGroupMonitoringTemplates);

        $storedNode = self::getContainer()->get(NodeRepository::class)->find($node->id());
        self::assertInstanceOf(Node::class, $storedNode);
        $resolved = (new EffectiveNodeMonitoringResolver())->resolve($storedNode);
        self::assertSame(['custom-tcp-template', 'docker-base', 'linux-base'], array_map(
            static fn ($monitoringTemplate): string => $monitoringTemplate->slug(),
            $resolved->templates,
        ));
        self::assertContains('custom.check.latency', array_map(
            static fn ($itemDefinition): string => $itemDefinition->key(),
            $resolved->items,
        ));

        $client->request('PATCH', '/api/monitoring-templates/'.$linuxBaseId, [
            'auth_bearer' => $token,
            'json' => ['description' => 'Denied'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        $client->request('DELETE', '/api/item-definitions/'.$this->itemIdByKey($client, $token, 'system.cpu.usage'), ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

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

    private function templateIdBySlug(Client $client, string $token, string $slug): string
    {
        $payload = $client->request('GET', '/api/monitoring-templates?itemsPerPage=100', ['auth_bearer' => $token])->toArray();
        $items = $payload['items'] ?? null;
        self::assertIsArray($items);
        foreach ($items as $template) {
            self::assertIsArray($template);
            if (($template['slug'] ?? null) === $slug) {
                $id = $template['id'] ?? null;
                self::assertIsString($id);

                return $id;
            }
        }

        self::fail(\sprintf('Monitoring template "%s" not found in API payload.', $slug));
    }

    private function itemIdByKey(Client $client, string $token, string $key): string
    {
        $payload = $client->request('GET', '/api/item-definitions?itemsPerPage=100', ['auth_bearer' => $token])->toArray();
        $items = $payload['items'] ?? null;
        self::assertIsArray($items);
        foreach ($items as $item) {
            self::assertIsArray($item);
            if (($item['key'] ?? null) === $key) {
                $id = $item['id'] ?? null;
                self::assertIsString($id);

                return $id;
            }
        }

        self::fail(\sprintf('Item definition "%s" not found in API payload.', $key));
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
