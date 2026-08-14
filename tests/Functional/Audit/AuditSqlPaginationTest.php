<?php

declare(strict_types=1);

namespace App\Tests\Functional\Audit;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Rbac\Infrastructure\Security\SystemRole;
use App\Tests\Functional\Support\RbacTestTrait;
use App\User\Domain\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Characterization tests for `GET /api/audits` locking the current contract (global
 * ordering across audit tables, deterministic tie-break, SQL-driven pagination, count,
 * and filters) before the multi-table PHP merge/sort is replaced by a PostgreSQL
 * UNION ALL. These tests seed the `audit_*` tables directly with fully-controlled
 * timestamps/ids so the expected order is exact and independent of wall-clock timing.
 */
final class AuditSqlPaginationTest extends ApiTestCase
{
    use RbacTestTrait;

    private const string PASSWORD = 'correct horse battery staple';

    /**
     * Real per-table identity sequences already advance by 1+ before a test seeds its rows
     * (e.g. creating the admin user triggers a genuine `audit_users` insert). Offsetting every
     * synthetic id keeps the readable 1, 2, 3... numbering in test bodies while avoiding a
     * primary key collision with that organic row; relative ordering is unaffected by a
     * uniform shift.
     */
    private const int ID_OFFSET = 1000;

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

    public function testGlobalOrderIsInterleavedAcrossTablesNewestFirst(): void
    {
        $client = self::createJsonClient();
        $token = $this->login($client, $this->createAdmin('interleaved@example.com'));

        // 10:00 Node(1) -> 10:01 User(1) -> 10:02 Role(1) -> 10:03 Node(2) -> 10:04 User(2) -> 10:05 Role(2)
        $this->seedAuditRow('audit_nodes', 1, 'insert', 'node-a', '2024-01-01 10:00:00');
        $this->seedAuditRow('audit_users', 1, 'insert', 'user-a', '2024-01-01 10:01:00');
        $this->seedAuditRow('audit_roles', 1, 'insert', 'role-a', '2024-01-01 10:02:00');
        $this->seedAuditRow('audit_nodes', 2, 'update', 'node-a', '2024-01-01 10:03:00');
        $this->seedAuditRow('audit_users', 2, 'update', 'user-a', '2024-01-01 10:04:00');
        $this->seedAuditRow('audit_roles', 2, 'update', 'role-a', '2024-01-01 10:05:00');

        $payload = $this->fetchAudits($client, $token, 'itemsPerPage=100&'.self::dateWindow('2024-01-01T00:00:00+00:00', '2024-01-01T23:59:59+00:00'));
        $entities = array_column($payload['items'], 'entity');

        self::assertSame(['Role', 'User', 'Node', 'Role', 'User', 'Node'], $entities);
        self::assertSame(6, $payload['metadata']['totalItems']);
    }

    public function testSameTimestampTieBreaksByIdDescendingThenEntityAscending(): void
    {
        $client = self::createJsonClient();
        $token = $this->login($client, $this->createAdmin('tiebreak@example.com'));

        // Same timestamp, same per-table id (1) on two different tables:
        // tie-break falls through to entity name ascending -> Node before User.
        $this->seedAuditRow('audit_nodes', 1, 'insert', 'tie-a', '2024-02-01 09:00:00');
        $this->seedAuditRow('audit_users', 1, 'insert', 'tie-b', '2024-02-01 09:00:00');

        // Same timestamp, different ids on two different tables:
        // tie-break by id DESC must win over entity name ordering (Agent id=20 before Node id=10).
        $this->seedAuditRow('audit_agents', 20, 'insert', 'tie-c', '2024-02-01 09:01:00');
        $this->seedAuditRow('audit_nodes', 10, 'insert', 'tie-d', '2024-02-01 09:01:00');

        $payload = $this->fetchAudits($client, $token, 'itemsPerPage=100&'.self::dateWindow('2024-02-01T00:00:00+00:00', '2024-02-01T23:59:59+00:00'));
        $rows = array_map(
            static fn (array $item): array => ['entity' => $item['entity'], 'entityId' => $item['entityId']],
            $payload['items'],
        );

        self::assertSame([
            ['entity' => 'Agent', 'entityId' => 'tie-c'],
            ['entity' => 'Node', 'entityId' => 'tie-d'],
            ['entity' => 'Node', 'entityId' => 'tie-a'],
            ['entity' => 'User', 'entityId' => 'tie-b'],
        ], $rows);
    }

    public function testPaginationAppliesGloballyAcrossPagesAndReportsAnEmptyPageBeyondTheEnd(): void
    {
        $client = self::createJsonClient();
        $token = $this->login($client, $this->createAdmin('pages@example.com'));

        for ($i = 1; $i <= 5; ++$i) {
            $this->seedAuditRow('audit_nodes', $i, 'insert', 'node-'.$i, \sprintf('2024-03-01 10:%02d:00', $i));
        }

        $window = self::dateWindow('2024-03-01T00:00:00+00:00', '2024-03-01T23:59:59+00:00');
        $page1 = $this->fetchAudits($client, $token, 'itemsPerPage=2&page=1&'.$window);
        $page2 = $this->fetchAudits($client, $token, 'itemsPerPage=2&page=2&'.$window);
        $page3 = $this->fetchAudits($client, $token, 'itemsPerPage=2&page=3&'.$window);
        $page4 = $this->fetchAudits($client, $token, 'itemsPerPage=2&page=4&'.$window);

        self::assertSame(['node-5', 'node-4'], array_column($page1['items'], 'entityId'));
        self::assertSame(['node-3', 'node-2'], array_column($page2['items'], 'entityId'));
        self::assertSame(['node-1'], array_column($page3['items'], 'entityId'));
        self::assertSame([], $page4['items']);

        foreach ([$page1, $page2, $page3, $page4] as $page) {
            self::assertSame(5, $page['metadata']['totalItems']);
        }
        self::assertTrue($page1['metadata']['hasNextPage']);
        self::assertFalse($page1['metadata']['hasPreviousPage']);
        self::assertTrue($page3['metadata']['hasPreviousPage']);
        self::assertFalse($page3['metadata']['hasNextPage']);
    }

    public function testEntityFilterRestrictsResultsToTheChosenTable(): void
    {
        $client = self::createJsonClient();
        $token = $this->login($client, $this->createAdmin('entityfilter@example.com'));

        $this->seedAuditRow('audit_nodes', 1, 'insert', 'n1', '2024-04-01 10:00:00');
        $this->seedAuditRow('audit_users', 1, 'insert', 'u1', '2024-04-01 10:01:00');
        $this->seedAuditRow('audit_roles', 1, 'insert', 'r1', '2024-04-01 10:02:00');

        $payload = $this->fetchAudits($client, $token, 'entity=Node&itemsPerPage=100');

        self::assertSame(1, $payload['metadata']['totalItems']);
        self::assertSame(['Node'], array_column($payload['items'], 'entity'));
    }

    public function testEntityIdFilterMatchesExactObjectId(): void
    {
        $client = self::createJsonClient();
        $token = $this->login($client, $this->createAdmin('entityid@example.com'));

        $this->seedAuditRow('audit_nodes', 1, 'insert', 'node-x', '2024-05-01 10:00:00');
        $this->seedAuditRow('audit_nodes', 2, 'update', 'node-x', '2024-05-01 10:01:00');
        $this->seedAuditRow('audit_nodes', 3, 'insert', 'node-y', '2024-05-01 10:02:00');

        $payload = $this->fetchAudits($client, $token, 'entityId=node-x&itemsPerPage=100');

        self::assertSame(2, $payload['metadata']['totalItems']);
        foreach ($payload['items'] as $item) {
            self::assertSame('node-x', $item['entityId']);
        }
    }

    public function testActionFilterMatchesExactTransactionType(): void
    {
        $client = self::createJsonClient();
        $token = $this->login($client, $this->createAdmin('action@example.com'));

        $this->seedAuditRow('audit_nodes', 1, 'insert', 'n1', '2024-06-01 10:00:00');
        $this->seedAuditRow('audit_nodes', 2, 'update', 'n1', '2024-06-01 10:01:00');
        $this->seedAuditRow('audit_nodes', 3, 'remove', 'n1', '2024-06-01 10:02:00');

        $payload = $this->fetchAudits($client, $token, 'action=update&itemsPerPage=100');

        self::assertSame(1, $payload['metadata']['totalItems']);
        self::assertSame('update', $payload['items'][0]['action']);
    }

    public function testActorFilterMatchesExactBlameId(): void
    {
        $client = self::createJsonClient();
        $token = $this->login($client, $this->createAdmin('actor@example.com'));

        $this->seedAuditRow('audit_nodes', 1, 'insert', 'n1', '2024-07-01 10:00:00', blameId: 'actor-a');
        $this->seedAuditRow('audit_nodes', 2, 'insert', 'n2', '2024-07-01 10:01:00', blameId: 'actor-b');

        $payload = $this->fetchAudits($client, $token, 'actor=actor-a&itemsPerPage=100');

        self::assertSame(1, $payload['metadata']['totalItems']);
        $actor = $payload['items'][0]['actor'];
        self::assertIsArray($actor);
        self::assertSame('actor-a', $actor['id']);
    }

    public function testDateFromAndDateToBoundsAreInclusive(): void
    {
        $client = self::createJsonClient();
        $token = $this->login($client, $this->createAdmin('dates@example.com'));

        $this->seedAuditRow('audit_nodes', 1, 'insert', 'n1', '2024-08-01 09:59:59');
        $this->seedAuditRow('audit_nodes', 2, 'insert', 'n2', '2024-08-01 10:00:00');
        $this->seedAuditRow('audit_nodes', 3, 'insert', 'n3', '2024-08-01 11:00:00');
        $this->seedAuditRow('audit_nodes', 4, 'insert', 'n4', '2024-08-01 11:00:01');

        $payload = $this->fetchAudits(
            $client,
            $token,
            'dateFrom='.rawurlencode('2024-08-01T10:00:00+00:00').'&dateTo='.rawurlencode('2024-08-01T11:00:00+00:00').'&itemsPerPage=100',
        );

        self::assertSame(['n3', 'n2'], array_column($payload['items'], 'entityId'));
    }

    public function testCombinedEntityActionAndActorFiltersIntersect(): void
    {
        $client = self::createJsonClient();
        $token = $this->login($client, $this->createAdmin('combined@example.com'));

        $this->seedAuditRow('audit_nodes', 1, 'update', 'match', '2024-09-01 10:00:00', blameId: 'actor-a');
        $this->seedAuditRow('audit_nodes', 2, 'insert', 'match', '2024-09-01 10:01:00', blameId: 'actor-a');
        $this->seedAuditRow('audit_nodes', 3, 'update', 'other', '2024-09-01 10:02:00', blameId: 'actor-b');
        $this->seedAuditRow('audit_nodes', 4, 'update', 'match', '2024-09-01 10:03:00', blameId: 'actor-b');
        $this->seedAuditRow('audit_users', 5, 'update', 'match', '2024-09-01 10:04:00', blameId: 'actor-a');

        $payload = $this->fetchAudits($client, $token, 'entity=Node&action=update&actor=actor-a&itemsPerPage=100');

        self::assertSame(1, $payload['metadata']['totalItems']);
        self::assertSame('match', $payload['items'][0]['entityId']);
        self::assertSame('Node', $payload['items'][0]['entity']);
        self::assertSame('update', $payload['items'][0]['action']);
    }

    /** Scopes a query to a fixed date window so setup noise (RBAC catalog sync, admin creation) never leaks in. */
    private static function dateWindow(string $from, string $to): string
    {
        return 'dateFrom='.rawurlencode($from).'&dateTo='.rawurlencode($to);
    }

    /** @return array{items: list<array<string, mixed>>, metadata: array<string, mixed>} */
    private function fetchAudits(Client $client, string $token, string $query): array
    {
        $response = $client->request('GET', '/api/audits?'.$query, ['auth_bearer' => $token]);
        self::assertResponseIsSuccessful();
        $payload = $response->toArray();
        $items = $payload['items'] ?? null;
        $metadata = $payload['metadata'] ?? null;
        self::assertIsArray($items);
        self::assertIsArray($metadata);

        $typedItems = [];
        foreach (array_values($items) as $item) {
            self::assertIsArray($item);
            $typedItem = [];
            foreach ($item as $itemKey => $itemValue) {
                self::assertIsString($itemKey);
                $typedItem[$itemKey] = $itemValue;
            }
            $typedItems[] = $typedItem;
        }

        $typedMetadata = [];
        foreach ($metadata as $key => $value) {
            self::assertIsString($key);
            $typedMetadata[$key] = $value;
        }

        return ['items' => $typedItems, 'metadata' => $typedMetadata];
    }

    private function seedAuditRow(
        string $table,
        int $id,
        string $type,
        string $objectId,
        string $createdAt,
        ?string $blameId = null,
    ): void {
        $this->connection()->executeStatement(
            'INSERT INTO '.$table.' (id, type, object_id, diffs, blame_id, created_at) VALUES (:row_id, :row_type, :row_object_id, :row_diffs, :row_blame_id, :row_created_at)',
            [
                'row_id' => self::ID_OFFSET + $id,
                'row_type' => $type,
                'row_object_id' => $objectId,
                'row_diffs' => '{}',
                'row_blame_id' => $blameId,
                'row_created_at' => $createdAt,
            ],
        );
    }

    private function createAdmin(string $email): User
    {
        $now = new \DateTimeImmutable();
        $user = new User($email, ['ROLE_USER'], $now);
        $this->assignSystemRole($user, SystemRole::SUPER_ADMIN);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD), $now);
        $this->entityManager()->persist($user);
        $this->entityManager()->flush();

        return $user;
    }

    private function login(Client $client, User $user): string
    {
        $response = $client->request('POST', '/api/auth/login', ['json' => ['email' => $user->getUserIdentifier(), 'password' => self::PASSWORD]]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
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

    private function connection(): Connection
    {
        return $this->entityManager()->getConnection();
    }
}
