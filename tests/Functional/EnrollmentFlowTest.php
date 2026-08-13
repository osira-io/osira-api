<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\EnrollmentToken;
use App\Entity\User;
use App\Security\TokenGenerator;
use App\Security\TokenHasher;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[CoversNothing]
final class EnrollmentFlowTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $entityManager = $this->entityManager();
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        self::ensureKernelShutdown();
    }

    public function testAdministratorCanCreateOneTimeEnrollmentToken(): void
    {
        $client = self::createJsonClient();
        $response = $client->request('POST', '/api/enrollment-tokens', [
            'auth_bearer' => $this->createUserAndLogin($client, ['ROLE_ADMIN']),
            'json' => [],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $payload = $response->toArray();
        self::assertArrayHasKey('id', $payload);
        self::assertArrayHasKey('token', $payload);
        self::assertArrayHasKey('expiresAt', $payload);
        self::assertIsString($payload['token']);
        self::assertStringStartsWith(TokenGenerator::ENROLLMENT_PREFIX, $payload['token']);

        $storedHash = $this->entityManager()->getConnection()->fetchOne('SELECT token_hash FROM enrollment_token');
        self::assertIsString($storedHash);
        self::assertNotSame($payload['token'], $storedHash);
        self::assertStringNotContainsString($payload['token'], $storedHash);

        self::assertIsString($payload['id']);
        $client->request('GET', '/api/enrollment_tokens/'.$payload['id']);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAgentCanEnrollSuccessfully(): void
    {
        $client = self::createJsonClient();
        $rawEnrollmentToken = $this->persistEnrollmentToken(new \DateTimeImmutable(), new \DateTimeImmutable('+15 minutes'));

        $response = $this->enroll($client, $rawEnrollmentToken);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $payload = $response->toArray();
        self::assertArrayHasKey('nodeId', $payload);
        self::assertArrayHasKey('agentId', $payload);
        self::assertArrayHasKey('agentToken', $payload);
        self::assertIsString($payload['agentToken']);
        self::assertStringStartsWith(TokenGenerator::AGENT_PREFIX, $payload['agentToken']);

        $connection = $this->entityManager()->getConnection();
        self::assertSame(1, $this->countRows('node'));
        self::assertSame(1, $this->countRows('agent'));
        self::assertSame(1, $this->countRows('agent_credential'));
        self::assertNotFalse($connection->fetchOne('SELECT used_at FROM enrollment_token'));

        $storedSecretHash = $connection->fetchOne('SELECT secret_hash FROM agent_credential');
        self::assertIsString($storedSecretHash);
        self::assertNotSame($payload['agentToken'], $storedSecretHash);
        self::assertStringNotContainsString($payload['agentToken'], $storedSecretHash);
    }

    public function testInvalidEnrollmentTokenIsRejected(): void
    {
        $client = self::createJsonClient();

        $this->enroll($client, TokenGenerator::ENROLLMENT_PREFIX.'invalid-token');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(0, $this->countRows('node'));
    }

    public function testExpiredEnrollmentTokenIsRejected(): void
    {
        $client = self::createJsonClient();
        $rawToken = $this->persistEnrollmentToken(
            new \DateTimeImmutable('-2 hours'),
            new \DateTimeImmutable('-1 hour'),
        );

        $this->enroll($client, $rawToken);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(0, $this->countRows('node'));
    }

    public function testUsedEnrollmentTokenIsRejected(): void
    {
        $client = self::createJsonClient();
        $rawToken = $this->persistEnrollmentToken(
            new \DateTimeImmutable('-1 minute'),
            new \DateTimeImmutable('+10 minutes'),
            new \DateTimeImmutable(),
        );

        $this->enroll($client, $rawToken);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame(0, $this->countRows('node'));
    }

    public function testNodeEndpointsNeverExposeCredentials(): void
    {
        $client = self::createJsonClient();
        $rawEnrollmentToken = $this->persistEnrollmentToken(new \DateTimeImmutable(), new \DateTimeImmutable('+15 minutes'));
        $enrollmentResponse = $this->enroll($client, $rawEnrollmentToken);
        $enrollmentPayload = $enrollmentResponse->toArray();
        self::assertIsString($enrollmentPayload['nodeId']);
        self::assertIsString($enrollmentPayload['agentToken']);

        $jwt = $this->createUserAndLogin($client, ['ROLE_USER']);
        $collectionResponse = $client->request('GET', '/api/nodes', ['auth_bearer' => $jwt]);
        self::assertResponseIsSuccessful();
        $collectionBody = $collectionResponse->getContent(false);
        self::assertStringContainsString('srv-prod-01', $collectionBody);
        self::assertStringNotContainsString($enrollmentPayload['agentToken'], $collectionBody);
        self::assertStringNotContainsString('secretHash', $collectionBody);
        self::assertStringNotContainsString('tokenHash', $collectionBody);
        self::assertStringNotContainsString('credential', $collectionBody);

        $itemResponse = $client->request('GET', '/api/nodes/'.$enrollmentPayload['nodeId'], ['auth_bearer' => $jwt]);
        self::assertResponseIsSuccessful();
        $itemBody = $itemResponse->getContent(false);
        self::assertStringContainsString('srv-prod-01', $itemBody);
        self::assertStringNotContainsString($enrollmentPayload['agentToken'], $itemBody);
        self::assertStringNotContainsString('secretHash', $itemBody);
        self::assertStringNotContainsString('tokenHash', $itemBody);
        self::assertStringNotContainsString('credential', $itemBody);
    }

    /** @param list<string> $roles */
    private function createUserAndLogin(Client $client, array $roles): string
    {
        $user = new User('admin@example.com', $roles, new \DateTimeImmutable());
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPasswordHash($passwordHasher->hashPassword($user, 'correct horse battery staple'), new \DateTimeImmutable());
        $entityManager = $this->entityManager();
        $entityManager->persist($user);
        $entityManager->flush();

        $response = $client->request('POST', '/api/auth/login', [
            'json' => ['email' => 'admin@example.com', 'password' => 'correct horse battery staple'],
        ]);
        self::assertResponseIsSuccessful();

        $payload = $response->toArray();
        self::assertIsString($payload['token']);

        return $payload['token'];
    }

    private static function createJsonClient(): Client
    {
        return self::createClient(defaultOptions: [
            'headers' => [
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ],
        ]);
    }

    private function enroll(Client $client, string $enrollmentToken): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $client->request('POST', '/api/agents/enroll', [
            'json' => [
                'enrollmentToken' => $enrollmentToken,
                'hostname' => 'srv-prod-01',
                'os' => 'linux',
                'architecture' => 'x86_64',
                'agentVersion' => '0.1.0',
            ],
        ]);
    }

    private function persistEnrollmentToken(
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $expiresAt,
        ?\DateTimeImmutable $usedAt = null,
    ): string {
        $tokenGenerator = self::getContainer()->get(TokenGenerator::class);
        $tokenHasher = self::getContainer()->get(TokenHasher::class);

        $rawToken = $tokenGenerator->generateEnrollmentToken();
        $token = new EnrollmentToken($tokenHasher->hash($rawToken), $createdAt, $expiresAt);
        if (null !== $usedAt) {
            $token->markUsed($usedAt);
        }

        $entityManager = $this->entityManager();
        $entityManager->persist($token);
        $entityManager->flush();

        return $rawToken;
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function countRows(string $table): int
    {
        $count = $this->entityManager()->getConnection()->fetchOne('SELECT COUNT(*) FROM '.$table);
        self::assertIsNumeric($count);

        return (int) $count;
    }
}
