<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Incident;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Alert\AlertOperator;
use App\Entity\Alert\AlertRule;
use App\Entity\Alert\AlertSeverity;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Node\Node;
use App\Entity\User\User;
use App\Factory\Incident\IncidentActivityFactory;
use App\Factory\Incident\IncidentFactory;
use App\Security\Rbac\PermissionCode;
use App\Security\Rbac\SystemRole;
use App\Tests\Functional\Support\RbacTestTrait;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class IncidentApiTest extends ApiTestCase
{
    use RbacTestTrait;

    private const string PASSWORD = 'correct horse battery staple';
    protected static ?bool $alwaysBootKernel = true;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $em = $this->entityManager();
        $schema = new SchemaTool($em);
        $schema->dropDatabase();
        $schema->createSchema($em->getMetadataFactory()->getAllMetadata());
        $this->initializeRbac();
        self::ensureKernelShutdown();
    }

    public function testReadOnlyEndpointsRequirePermissionAndApplyFilters(): void
    {
        $now = new \DateTimeImmutable('2026-08-25T12:00:00+00:00');
        $node = new Node('incident-node', null, 'linux', 'amd64', $now, $now);
        $item = new ItemDefinition('custom.cpu.usage', 'CPU', null, '%', ItemValueType::FLOAT, 60, 5, 'printf 1', null, true, $now);
        $rule = new AlertRule('CPU high', 'CPU high', 'CPU usage is high', $item, AlertOperator::GT, '90', '80', 300, 3, AlertSeverity::CRITICAL, true, $now);
        $incident = (new IncidentFactory())->create($node, $rule, [], '95', $now);
        $em = $this->entityManager();
        foreach ([$node, $item, $rule, $incident] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $client = self::createClient(defaultOptions: ['headers' => ['accept' => 'application/json', 'content-type' => 'application/json']]);
        $client->request('GET', '/api/incidents');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $viewer = $this->createUser('incident-viewer@example.com', SystemRole::VIEWER);
        $token = $this->login($client, $viewer->getUserIdentifier());
        $collection = $client->request('GET', '/api/incidents?status=firing&severity=critical&node='.$node->id().'&alertRule='.$rule->id().'&date=2026-08-25T11%3A00%3A00%2B00%3A00', ['auth_bearer' => $token])->toArray();
        $items = $collection['items'] ?? null;
        self::assertIsArray($items);
        self::assertCount(1, $items);

        $payload = $client->request('GET', '/api/incidents/'.$incident->id(), ['auth_bearer' => $token])->toArray();
        self::assertSame('firing', $payload['status'] ?? null);
        self::assertSame('critical', $payload['severity'] ?? null);
        self::assertSame((string) $node->id(), $payload['nodeId'] ?? null);

        $empty = $client->request('GET', '/api/incidents?status=resolved', ['auth_bearer' => $token])->toArray();
        self::assertSame([], $empty['items'] ?? null);
    }

    public function testDatabaseRejectsTwoActiveIncidentsWithTheSameLogicalIdentity(): void
    {
        $now = new \DateTimeImmutable('2026-08-25T12:00:00+00:00');
        $node = new Node('concurrent-node', null, 'linux', 'amd64', $now, $now);
        $item = new ItemDefinition('custom.disk.usage', 'Disk', null, '%', ItemValueType::FLOAT, 60, 5, 'printf 1', null, true, $now);
        $rule = new AlertRule('Disk high', 'Disk high', 'Disk usage is high', $item, AlertOperator::GT, '90', '80', 300, 1, AlertSeverity::CRITICAL, true, $now);
        $factory = new IncidentFactory();
        $em = $this->entityManager();
        foreach ([$node, $item, $rule, $factory->create($node, $rule, ['device' => '/data'], '91', $now), $factory->create($node, $rule, ['device' => '/data'], '92', $now)] as $entity) {
            $em->persist($entity);
        }

        $this->expectException(UniqueConstraintViolationException::class);
        $em->flush();
    }

    public function testHumanWorkflowAcknowledgesCommentsAndBuildsAStableTimeline(): void
    {
        [$incident, $viewer, $operator] = $this->workflowSubjects();
        $client = self::createClient(defaultOptions: ['headers' => ['accept' => 'application/json', 'content-type' => 'application/json']]);
        $viewerToken = $this->login($client, $viewer->getUserIdentifier());
        $operatorToken = $this->login($client, $operator->getUserIdentifier());

        $client->request('POST', '/api/incidents/'.$incident->id().'/acknowledge', ['auth_bearer' => $viewerToken, 'json' => []]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $client->request('POST', '/api/incidents/'.$incident->id().'/comments', ['auth_bearer' => $viewerToken, 'json' => ['message' => 'No write access']]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $acknowledged = $client->request('POST', '/api/incidents/'.$incident->id().'/acknowledge', [
            'auth_bearer' => $operatorToken,
            'json' => ['message' => '  I am taking ownership  '],
        ])->toArray();
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('firing', $acknowledged['status'] ?? null);
        $acknowledgedBy = $acknowledged['acknowledgedBy'] ?? null;
        self::assertIsArray($acknowledgedBy);
        self::assertSame((string) $operator->id(), $acknowledgedBy['id'] ?? null);
        self::assertNotNull($acknowledged['acknowledgedAt'] ?? null);

        $client->request('POST', '/api/incidents/'.$incident->id().'/acknowledge', ['auth_bearer' => $operatorToken, 'json' => []]);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        $comment = $client->request('POST', '/api/incidents/'.$incident->id().'/comments', [
            'auth_bearer' => $operatorToken,
            'json' => ['message' => '  Restart in progress  '],
        ])->toArray();
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('Restart in progress', $comment['message'] ?? null);
        $commentActor = $comment['actor'] ?? null;
        self::assertIsArray($commentActor);
        self::assertSame((string) $operator->id(), $commentActor['id'] ?? null);

        $client->request('POST', '/api/incidents/'.$incident->id().'/comments', ['auth_bearer' => $operatorToken, 'json' => ['message' => '   ']]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $timeline = $client->request('GET', '/api/incidents/'.$incident->id().'/activities?itemsPerPage=2&page=1', ['auth_bearer' => $viewerToken])->toArray();
        $metadata = $timeline['metadata'] ?? null;
        $timelineItems = $timeline['items'] ?? null;
        self::assertIsArray($metadata);
        self::assertIsArray($timelineItems);
        self::assertSame(3, $metadata['totalItems'] ?? null);
        self::assertSame(['opened', 'acknowledged'], array_column($timelineItems, 'type'));
        $acknowledgementItem = $timelineItems[1] ?? null;
        self::assertIsArray($acknowledgementItem);
        self::assertSame('I am taking ownership', $acknowledgementItem['message'] ?? null);
        $timelineActor = $acknowledgementItem['actor'] ?? null;
        self::assertIsArray($timelineActor);
        self::assertSame((string) $operator->id(), $timelineActor['id'] ?? null);
        $secondPage = $client->request('GET', '/api/incidents/'.$incident->id().'/activities?itemsPerPage=2&page=2', ['auth_bearer' => $viewerToken])->toArray();
        $secondPageItems = $secondPage['items'] ?? null;
        self::assertIsArray($secondPageItems);
        self::assertSame(['comment'], array_column($secondPageItems, 'type'));
    }

    public function testResolvedIncidentRejectsAcknowledgementButAcceptsCommentsAndKeepsExistingHistory(): void
    {
        [$incident, , $operator] = $this->workflowSubjects();
        $client = self::createClient(defaultOptions: ['headers' => ['accept' => 'application/json', 'content-type' => 'application/json']]);
        $token = $this->login($client, $operator->getUserIdentifier());

        $client->request('POST', '/api/incidents/'.$incident->id().'/acknowledge', ['auth_bearer' => $token, 'json' => []]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->entityManager()->clear();
        $managedIncident = $this->entityManager()->find(\App\Entity\Incident\Incident::class, $incident->id());
        self::assertInstanceOf(\App\Entity\Incident\Incident::class, $managedIncident);
        $managedIncident->resolve('40', new \DateTimeImmutable());
        $this->entityManager()->flush();

        $payload = $client->request('GET', '/api/incidents/'.$incident->id(), ['auth_bearer' => $token])->toArray();
        self::assertSame('resolved', $payload['status'] ?? null);
        $acknowledgedBy = $payload['acknowledgedBy'] ?? null;
        self::assertIsArray($acknowledgedBy);
        self::assertSame((string) $operator->id(), $acknowledgedBy['id'] ?? null);
        $client->request('POST', '/api/incidents/'.$incident->id().'/comments', ['auth_bearer' => $token, 'json' => ['message' => 'Post-resolution note']]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $client->request('POST', '/api/incidents/'.$incident->id().'/acknowledge', ['auth_bearer' => $token, 'json' => []]);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        $timeline = $client->request('GET', '/api/incidents/'.$incident->id().'/activities?itemsPerPage=10', ['auth_bearer' => $token])->toArray();
        $timelineItems = $timeline['items'] ?? [];
        self::assertIsArray($timelineItems);
        $types = array_column($timelineItems, 'type');
        sort($types);
        self::assertSame(['acknowledged', 'comment', 'opened', 'resolved'], $types);
        $timestamps = array_column($timelineItems, 'createdAt');
        $sortedTimestamps = $timestamps;
        sort($sortedTimestamps);
        self::assertSame($sortedTimestamps, $timestamps);
    }

    public function testDatabaseConstraintMakesConcurrentAcknowledgementUnique(): void
    {
        [$incident, , $operator] = $this->workflowSubjects();
        $secondOperator = $this->createUser('workflow-operator-2@example.com', SystemRole::OPERATOR);
        $factory = new IncidentActivityFactory();
        $now = new \DateTimeImmutable();
        $this->entityManager()->persist($factory->acknowledged($incident, $operator, null, $now));
        $this->entityManager()->persist($factory->acknowledged($incident, $secondOperator, 'racing request', $now));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager()->flush();
    }

    /** @return array{\App\Entity\Incident\Incident, User, User} */
    private function workflowSubjects(): array
    {
        $now = new \DateTimeImmutable('2026-08-25T12:00:00+00:00');
        $node = new Node('workflow-node', null, 'linux', 'amd64', $now, $now);
        $item = new ItemDefinition('custom.workflow', 'Workflow', null, null, ItemValueType::FLOAT, 60, 5, 'printf 1', null, true, $now);
        $rule = new AlertRule('Workflow alert', 'Workflow alert', 'Workflow alert', $item, AlertOperator::GT, '90', '80', 300, 1, AlertSeverity::WARNING, true, $now);
        $incident = (new IncidentFactory())->create($node, $rule, [], '95', $now);
        $em = $this->entityManager();
        foreach ([$node, $item, $rule, $incident] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $viewer = $this->createUser('workflow-viewer@example.com', SystemRole::VIEWER);
        $operator = $this->createUser('workflow-operator@example.com', SystemRole::OPERATOR);
        self::assertContains(PermissionCode::INCIDENTS_READ, array_keys(PermissionCode::catalog()));

        return [$incident, $viewer, $operator];
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
        $token = $response->toArray()['token'] ?? null;
        self::assertIsString($token);

        return $token;
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
