<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[CoversNothing]
final class AuthenticationTest extends ApiTestCase
{
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

    public function testLoginSucceedsAndNeverReturnsPasswordHash(): void
    {
        $client = self::createJsonClient();
        $user = $this->createUser(['ROLE_ADMIN']);

        $response = $this->login($client, 'admin@example.com', self::PASSWORD);

        self::assertResponseIsSuccessful();
        $body = $response->getContent(false);
        self::assertStringContainsString('token', $body);
        self::assertStringNotContainsString($user->getPassword(), $body);
        self::assertStringNotContainsString('password', $body);
    }

    public function testWrongPasswordAndUnknownUserAreRejectedIdentically(): void
    {
        $client = self::createJsonClient();
        $this->createUser(['ROLE_USER']);

        $wrongPassword = $this->login($client, 'admin@example.com', 'incorrect password');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $unknownUser = $this->login($client, 'unknown@example.com', 'incorrect password');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame($wrongPassword->getContent(false), $unknownUser->getContent(false));
    }

    public function testEnrollmentTokenCreationRequiresAdministratorRole(): void
    {
        $client = self::createJsonClient();

        $client->request('POST', '/api/enrollment-tokens', ['json' => []]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->createUser(['ROLE_USER']);
        $userToken = $this->loginToken($client);
        $client->request('POST', '/api/enrollment-tokens', ['auth_bearer' => $userToken, 'json' => []]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->resetUsers();
        $this->createUser(['ROLE_ADMIN']);
        $adminToken = $this->loginToken($client);
        $response = $client->request('POST', '/api/enrollment-tokens', ['auth_bearer' => $adminToken, 'json' => []]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $enrollmentToken = $response->toArray()['token'];
        self::assertIsString($enrollmentToken);
        self::assertStringStartsWith('osi_enroll_', $enrollmentToken);
    }

    public function testNodesRequireAuthentication(): void
    {
        $client = self::createJsonClient();

        $client->request('GET', '/api/nodes');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->createUser(['ROLE_USER']);
        $response = $client->request('GET', '/api/nodes', ['auth_bearer' => $this->loginToken($client)]);
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('password', $response->getContent(false));
        self::assertStringNotContainsString('tokenHash', $response->getContent(false));
        self::assertStringNotContainsString('secretHash', $response->getContent(false));
    }

    private const string PASSWORD = 'correct horse battery staple';

    /** @param list<string> $roles */
    private function createUser(array $roles): User
    {
        $now = new \DateTimeImmutable();
        $user = new User('admin@example.com', $roles, $now);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD), $now);
        $entityManager = $this->entityManager();
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function login(Client $client, string $email, string $password): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $client->request('POST', '/api/auth/login', ['json' => ['email' => $email, 'password' => $password]]);
    }

    private function loginToken(Client $client): string
    {
        $response = $this->login($client, 'admin@example.com', self::PASSWORD);
        self::assertResponseIsSuccessful();
        $token = $response->toArray()['token'];
        self::assertIsString($token);

        return $token;
    }

    private function resetUsers(): void
    {
        $this->entityManager()->createQuery('DELETE FROM App\Entity\User user')->execute();
        $this->entityManager()->clear();
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
