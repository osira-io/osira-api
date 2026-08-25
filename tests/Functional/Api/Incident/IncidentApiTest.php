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
use App\Factory\Incident\IncidentFactory;
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
