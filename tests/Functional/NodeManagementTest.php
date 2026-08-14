<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Node;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversNothing]
final class NodeManagementTest extends ApiTestCase
{
    private const string PASSWORD = 'correct horse battery staple';

    protected static ?bool $alwaysBootKernel = true;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $entityManager = $this->entityManager();
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        self::ensureKernelShutdown();
    }

    public function testNodePatchRequiresAuthenticationAndOnlyUpdatesBusinessProperties(): void
    {
        $client = self::createJsonClient();
        $node = $this->createNode('srv-prod-01');

        $client->request('PATCH', '/api/nodes/'.$node->id(), ['json' => ['displayName' => 'Database']]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $token = $this->createUserAndLogin($client);
        $response = $client->request('PATCH', '/api/nodes/'.$node->id(), [
            'auth_bearer' => $token,
            'json' => [
                'displayName' => ' Main PostgreSQL ',
                'environment' => ' Production ',
                'tags' => [' database ', 'critical', 'database'],
            ],
        ]);

        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        self::assertSame('Main PostgreSQL', $body['displayName']);
        self::assertSame('production', $body['environment']);
        self::assertSame(['database', 'critical'], $body['tags']);
        self::assertSame('srv-prod-01', $body['hostname']);
        self::assertSame('linux', $body['os']);
        self::assertSame('x86_64', $body['architecture']);

        $client->request('PATCH', '/api/nodes/'.$node->id(), [
            'auth_bearer' => $token,
            'json' => ['hostname' => 'attacker', 'os' => 'windows', 'architecture' => 'arm64'],
        ]);
        self::assertResponseIsSuccessful();

        $read = $client->request('GET', '/api/nodes/'.$node->id(), ['auth_bearer' => $token])->toArray();
        self::assertSame('srv-prod-01', $read['hostname']);
        self::assertSame('linux', $read['os']);
        self::assertSame('x86_64', $read['architecture']);
        self::assertStringNotContainsString('secretHash', json_encode($read, \JSON_THROW_ON_ERROR));
    }

    public function testTagsRejectEmptyValues(): void
    {
        $client = self::createJsonClient();
        $node = $this->createNode('tagged-host');
        $token = $this->createUserAndLogin($client);

        $client->request('PATCH', '/api/nodes/'.$node->id(), [
            'auth_bearer' => $token,
            'json' => ['tags' => ['valid', '  ']],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testNodeGroupCrudMembershipAndDeletionPreserveNodes(): void
    {
        $client = self::createJsonClient();
        $firstNode = $this->createNode('web-01');
        $secondNode = $this->createNode('web-02');
        $token = $this->createUserAndLogin($client);

        $client->request('GET', '/api/node-groups');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $production = $this->createGroup($client, $token, ' Production ', ' Production servers ');
        $linux = $this->createGroup($client, $token, 'Linux Servers', null);
        $productionId = $production['id'] ?? null;
        $linuxId = $linux['id'] ?? null;
        self::assertIsString($productionId);
        self::assertIsString($linuxId);
        self::assertSame('Production', $production['name']);
        self::assertSame('Production servers', $production['description']);

        $client->request('POST', '/api/node-groups', [
            'auth_bearer' => $token,
            'json' => ['name' => 'Production'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        $updatedResponse = $client->request('PATCH', '/api/node-groups/'.$linuxId, [
            'auth_bearer' => $token,
            'json' => ['name' => 'Linux Infrastructure'],
        ]);
        $updated = self::responseObject($updatedResponse);
        self::assertSame('Linux Infrastructure', $updated['name']);

        $firstResponse = $client->request('PATCH', '/api/nodes/'.$firstNode->id(), [
            'auth_bearer' => $token,
            'json' => ['groups' => [$productionId, $linuxId, $productionId]],
        ]);
        $first = self::responseObject($firstResponse);
        $firstGroups = $first['groups'] ?? null;
        self::assertIsArray($firstGroups);
        self::assertCount(2, $firstGroups);

        $secondResponse = $client->request('PATCH', '/api/nodes/'.$secondNode->id(), [
            'auth_bearer' => $token,
            'json' => ['groups' => [$productionId]],
        ]);
        $second = self::responseObject($secondResponse);
        $secondGroups = $second['groups'] ?? null;
        self::assertIsArray($secondGroups);
        $secondGroup = $secondGroups[0] ?? null;
        self::assertIsArray($secondGroup);
        self::assertSame($productionId, $secondGroup['id'] ?? null);

        $collection = $client->request('GET', '/api/node-groups', ['auth_bearer' => $token])->toArray();
        self::assertCount(2, $collection);

        $client->request('DELETE', '/api/node-groups/'.$productionId, ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $readNodeResponse = $client->request('GET', '/api/nodes/'.$firstNode->id(), ['auth_bearer' => $token]);
        $readNode = self::responseObject($readNodeResponse);
        $readGroups = $readNode['groups'] ?? null;
        self::assertIsArray($readGroups);
        $remainingGroup = $readGroups[0] ?? null;
        self::assertIsArray($remainingGroup);
        self::assertSame($linuxId, $remainingGroup['id'] ?? null);
        self::assertNotNull($this->entityManager()->find(Node::class, $secondNode->id()));
    }

    public function testUnknownGroupIsRejected(): void
    {
        $client = self::createJsonClient();
        $node = $this->createNode('web-03');
        $token = $this->createUserAndLogin($client);

        $client->request('PATCH', '/api/nodes/'.$node->id(), [
            'auth_bearer' => $token,
            'json' => ['groups' => ['01K00000000000000000000000']],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** @return array<string, mixed> */
    private function createGroup(Client $client, string $token, string $name, ?string $description): array
    {
        $response = $client->request('POST', '/api/node-groups', [
            'auth_bearer' => $token,
            'json' => ['name' => $name, 'description' => $description],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return self::responseObject($response);
    }

    private function createNode(string $hostname): Node
    {
        $now = new \DateTimeImmutable();
        $node = new Node($hostname, null, 'linux', 'x86_64', $now, $now);
        $entityManager = $this->entityManager();
        $entityManager->persist($node);
        $entityManager->flush();

        return $node;
    }

    private function createUserAndLogin(Client $client): string
    {
        $now = new \DateTimeImmutable();
        $user = new User('operator@example.com', ['ROLE_USER'], $now);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD), $now);
        $entityManager = $this->entityManager();
        $entityManager->persist($user);
        $entityManager->flush();

        $response = $client->request('POST', '/api/auth/login', [
            'json' => ['email' => 'operator@example.com', 'password' => self::PASSWORD],
        ]);
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
}
