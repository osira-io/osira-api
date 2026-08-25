<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Metrics;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Entity\User\User;
use App\Security\Rbac\SystemRole;
use App\Service\Metrics\VictoriaMetricsClientProxy;
use App\Service\Metrics\VictoriaMetricsInvalidResponseException;
use App\Service\Metrics\VictoriaMetricsUnavailableException;
use App\Tests\Functional\Support\FakeVictoriaMetricsClient;
use App\Tests\Functional\Support\RbacTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class MetricsApiTest extends ApiTestCase
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

    protected function tearDown(): void
    {
        self::getContainer()->get(VictoriaMetricsClientProxy::class)->reset();

        self::ensureKernelShutdown();

        parent::tearDown();
    }

    public function testInstantQueryRangeQueryAndNodeMetricsUseStableContracts(): void
    {
        $client = self::createJsonClient();
        $viewer = $this->createUser('viewer@example.com', SystemRole::VIEWER);
        $token = $this->login($client, $viewer->getUserIdentifier());
        $node = $this->createNodeWithTemplate('srv-metrics-01');

        $fakeClient = new FakeVictoriaMetricsClient();
        $fakeClient->instantResult = [
            FakeVictoriaMetricsClient::sample([
                '__name__' => 'osira_item_value',
                'node_id' => (string) $node->id(),
                'item_key' => 'custom.cpu.usage',
                'device' => 'cpu0',
            ], '42.5'),
        ];
        $fakeClient->rangeResult = [
            FakeVictoriaMetricsClient::series([
                '__name__' => 'osira_item_value',
                'node_id' => (string) $node->id(),
                'item_key' => 'custom.disk.usage',
                'device' => 'nvme0n1p1',
            ], [
                ['2026-08-19T12:00:00+00:00', '77.1'],
                ['2026-08-19T12:01:00+00:00', '77.4'],
            ]),
        ];
        $client->request('GET', '/api/metrics/query');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->setVictoriaMetricsClient($fakeClient);
        $instant = $client->request('GET', '/api/metrics/query?nodeId='.$node->id().'&itemKey=custom.cpu.usage', [
            'auth_bearer' => $token,
        ])->toArray();
        self::assertSame('custom.cpu.usage', $instant['itemKey'] ?? null);
        self::assertSame((string) $node->id(), $instant['nodeId'] ?? null);
        self::assertSame([['metricKey' => 'custom.cpu.usage', 'labels' => ['device' => 'cpu0'], 'timestamp' => '2026-08-19T12:00:00+00:00', 'value' => '42.5']], $instant['samples'] ?? null);
        self::assertSame(\sprintf('osira_item_value{node_id="%s",item_key="custom.cpu.usage"}', $node->id()), $fakeClient->queries[0] ?? null);

        $this->setVictoriaMetricsClient($fakeClient);
        $range = $client->request('GET', '/api/metrics/query-range?nodeId='.$node->id().'&itemKey=custom.disk.usage&from=2026-08-19T12%3A00%3A00%2B00%3A00&to=2026-08-19T12%3A02%3A00%2B00%3A00&stepSeconds=60&device=nvme0n1p1', [
            'auth_bearer' => $token,
        ])->toArray();
        self::assertSame('custom.disk.usage', $range['itemKey'] ?? null);
        self::assertSame((string) $node->id(), $range['nodeId'] ?? null);
        self::assertSame([[
            'metricKey' => 'custom.disk.usage',
            'labels' => ['device' => 'nvme0n1p1'],
            'points' => [
                ['timestamp' => '2026-08-19T12:00:00+00:00', 'value' => '77.1'],
                ['timestamp' => '2026-08-19T12:01:00+00:00', 'value' => '77.4'],
            ],
        ]], $range['series'] ?? null);

        $this->setVictoriaMetricsClient($fakeClient);
        $nodeMetrics = $client->request('GET', '/api/nodes/'.$node->id().'/metrics', [
            'auth_bearer' => $token,
        ])->toArray();
        self::assertSame((string) $node->id(), $nodeMetrics['nodeId'] ?? null);
        self::assertNotEmpty($nodeMetrics['samples'] ?? []);
    }

    public function testMetricsEndpointsEnforceDedicatedPermissionsAndStrictValidation(): void
    {
        $client = self::createJsonClient();
        $node = $this->createNodeWithTemplate('srv-metrics-02');

        $client->request('GET', '/api/metrics/query?nodeId='.$node->id().'&itemKey=custom.cpu.usage');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $noRoleToken = $this->login($client, $this->createUser('none@example.com')->getUserIdentifier());
        $client->request('GET', '/api/metrics/query?nodeId='.$node->id().'&itemKey=custom.cpu.usage', ['auth_bearer' => $noRoleToken]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $viewerToken = $this->login($client, $this->createUser('viewer2@example.com', SystemRole::VIEWER)->getUserIdentifier());
        $client->request('GET', '/api/metrics/query?nodeId='.$node->id().'&itemKey=custom.cpu.usage&query=up', ['auth_bearer' => $viewerToken]);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $client->request('GET', '/api/metrics/query-range?nodeId='.$node->id().'&itemKey=custom.cpu.usage&from=2026-08-19T12%3A02%3A00%2B00%3A00&to=2026-08-19T12%3A00%3A00%2B00%3A00&stepSeconds=0', ['auth_bearer' => $viewerToken]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUnknownDisabledAndUnavailableMetricsAreHandledCleanly(): void
    {
        $client = self::createJsonClient();
        $viewerToken = $this->login($client, $this->createUser('viewer3@example.com', SystemRole::VIEWER)->getUserIdentifier());
        $node = $this->createNodeWithTemplate('srv-metrics-03');

        $client->request('GET', '/api/metrics/query?nodeId='.$node->id().'&itemKey=unknown.metric', ['auth_bearer' => $viewerToken]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $disabledItem = new ItemDefinition('custom.disabled.metric', 'Disabled', null, null, ItemValueType::FLOAT, 60, null, 'printf 0', null, false, new \DateTimeImmutable());
        $template = new MonitoringTemplate('Disabled Template', 'disabled-template', null, true, new \DateTimeImmutable());
        $template->replaceItemDefinitions([$disabledItem], new \DateTimeImmutable());
        $entityManager = $this->entityManager();
        $entityManager->persist($disabledItem);
        $entityManager->persist($template);
        $entityManager->flush();
        $managedNode = $entityManager->find(Node::class, $node->id());
        self::assertInstanceOf(Node::class, $managedNode);
        $group = new NodeGroup('Disabled metrics', null, new \DateTimeImmutable());
        $group->replaceMonitoringTemplates([$template], new \DateTimeImmutable());
        $managedNode->replaceGroups([...$managedNode->groups()->toArray(), $group]);
        $entityManager->persist($group);
        $entityManager->flush();

        $client->request('GET', '/api/metrics/query?nodeId='.$managedNode->id().'&itemKey=custom.disabled.metric', ['auth_bearer' => $viewerToken]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $unavailable = new FakeVictoriaMetricsClient();
        $unavailable->instantException = new VictoriaMetricsUnavailableException('down');
        $this->setVictoriaMetricsClient($unavailable);
        $client->request('GET', '/api/metrics/query?nodeId='.$node->id().'&itemKey=custom.cpu.usage', ['auth_bearer' => $viewerToken]);
        self::assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE);

        $invalid = new FakeVictoriaMetricsClient();
        $invalid->instantException = new VictoriaMetricsInvalidResponseException('bad');
        $this->setVictoriaMetricsClient($invalid);
        $client->request('GET', '/api/metrics/query?nodeId='.$node->id().'&itemKey=custom.cpu.usage', ['auth_bearer' => $viewerToken]);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_GATEWAY);
    }

    private function createNodeWithTemplate(string $hostname): Node
    {
        $now = new \DateTimeImmutable();
        $node = new Node($hostname, null, 'linux', 'x86_64', $now, $now);
        $cpu = new ItemDefinition('custom.cpu.usage', 'CPU', null, '%', ItemValueType::FLOAT, 60, 5, 'printf 42.5', null, true, $now);
        $disk = new ItemDefinition('custom.disk.usage', 'Disk', null, '%', ItemValueType::FLOAT, 60, 5, 'printf 77.1', null, true, $now);
        $template = new MonitoringTemplate('Metrics '.$hostname, 'metrics-'.$hostname, null, true, $now);
        $template->replaceItemDefinitions([$cpu, $disk], $now);
        $group = new NodeGroup('Metrics '.$hostname, null, $now);
        $group->replaceMonitoringTemplates([$template], $now);
        $node->replaceGroups([$group]);
        foreach ([$cpu, $disk, $template, $group, $node] as $entity) {
            $this->entityManager()->persist($entity);
        }
        $this->entityManager()->flush();

        return $node;
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

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function setVictoriaMetricsClient(FakeVictoriaMetricsClient $client): void
    {
        self::getContainer()->get(VictoriaMetricsClientProxy::class)->useClient($client);
    }
}
