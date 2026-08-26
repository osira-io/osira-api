<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Notification;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\User\User;
use App\Security\Rbac\SystemRole;
use App\Tests\Functional\Support\RbacTestTrait;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class NotificationApiTest extends ApiTestCase
{
    use RbacTestTrait;

    private const string PASSWORD = 'correct horse battery staple';
    private const string WEBHOOK_SECRET = 'a-test-webhook-secret-value';
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

    public function testCrudNeverExposesOrAuditsWebhookSecret(): void
    {
        $client = self::jsonClient();
        $token = $this->login($client, $this->createUser('notification-admin@example.com', SystemRole::ADMIN)->getUserIdentifier());
        $response = $client->request('POST', '/api/notification-channels', ['auth_bearer' => $token, 'json' => [
            'name' => 'Incident webhook', 'type' => 'webhook', 'webhookUrl' => 'https://hooks.example.test/incidents', 'webhookSecret' => self::WEBHOOK_SECRET,
        ]]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $created = $response->toArray();
        $channelId = $created['id'] ?? null;
        self::assertIsString($channelId);
        self::assertTrue($created['hasWebhookSecret'] ?? false);
        self::assertArrayNotHasKey('webhookSecret', $created);
        self::assertStringNotContainsString(self::WEBHOOK_SECRET, $response->getContent(false));

        $rule = $client->request('POST', '/api/notification-rules', ['auth_bearer' => $token, 'json' => [
            'name' => 'Critical incidents', 'severities' => ['critical'], 'channelIds' => [$channelId],
        ]])->toArray();
        $ruleId = $rule['id'] ?? null;
        self::assertIsString($ruleId);
        self::assertSame(['critical'], $rule['severities'] ?? null);
        $ruleChannels = $rule['channels'] ?? null;
        self::assertIsArray($ruleChannels);
        self::assertCount(1, $ruleChannels);

        $updated = $client->request('PATCH', '/api/notification-channels/'.$channelId, ['auth_bearer' => $token, 'json' => ['name' => 'Primary incident webhook']])->toArray();
        self::assertSame('Primary incident webhook', $updated['name'] ?? null);
        self::assertTrue($updated['hasWebhookSecret'] ?? false);

        $updatedRule = $client->request('PATCH', '/api/notification-rules/'.$ruleId, ['auth_bearer' => $token, 'json' => ['isEnabled' => false]])->toArray();
        self::assertFalse($updatedRule['isEnabled'] ?? true);

        $readChannel = $client->request('GET', '/api/notification-channels/'.$channelId, ['auth_bearer' => $token])->toArray();
        self::assertSame($channelId, $readChannel['id'] ?? null);
        $readRule = $client->request('GET', '/api/notification-rules/'.$ruleId, ['auth_bearer' => $token])->toArray();
        self::assertSame($ruleId, $readRule['id'] ?? null);

        $client->request('DELETE', '/api/notification-rules/'.$ruleId, ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $client->request('DELETE', '/api/notification-channels/'.$channelId, ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $connection = self::getContainer()->get(Connection::class);
        $channelAudit = $connection->fetchAllAssociative('SELECT type, diffs FROM audit_notification_channels WHERE object_id = ? ORDER BY id ASC', [$channelId]);
        self::assertContains('insert', array_column($channelAudit, 'type'));
        self::assertContains('update', array_column($channelAudit, 'type'));
        self::assertContains('remove', array_column($channelAudit, 'type'));
        $encodedAudit = json_encode($channelAudit, \JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString(self::WEBHOOK_SECRET, $encodedAudit);
        self::assertStringNotContainsString('webhookSecretCiphertext', $encodedAudit);

        $ruleAudit = $connection->fetchAllAssociative('SELECT type FROM audit_notification_rules WHERE object_id = ? ORDER BY id ASC', [$ruleId]);
        self::assertContains('insert', array_column($ruleAudit, 'type'));
        self::assertContains('update', array_column($ruleAudit, 'type'));
        self::assertContains('remove', array_column($ruleAudit, 'type'));
    }

    public function testReadPermissionsAreAvailableToViewerButWritesAreDenied(): void
    {
        $client = self::jsonClient();
        $token = $this->login($client, $this->createUser('notification-viewer@example.com', SystemRole::VIEWER)->getUserIdentifier());

        $client->request('GET', '/api/notification-channels', ['auth_bearer' => $token]);
        self::assertResponseIsSuccessful();
        $client->request('GET', '/api/notification-rules', ['auth_bearer' => $token]);
        self::assertResponseIsSuccessful();
        $client->request('POST', '/api/notification-channels', ['auth_bearer' => $token, 'json' => ['name' => 'Denied', 'type' => 'email', 'emailRecipients' => ['ops@example.com']]]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function createUser(string $email, string $role): User
    {
        $now = new \DateTimeImmutable();
        $user = new User($email, ['ROLE_USER'], $now);
        $this->assignSystemRole($user, $role);
        $user->setPasswordHash(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD), $now);
        $this->entityManager()->persist($user);
        $this->entityManager()->flush();

        return $user;
    }

    private function login(Client $client, string $email): string
    {
        $payload = $client->request('POST', '/api/auth/login', ['json' => ['email' => $email, 'password' => self::PASSWORD]])->toArray();
        $token = $payload['token'] ?? null;
        self::assertIsString($token);

        return $token;
    }

    private static function jsonClient(): Client
    {
        return self::createClient(defaultOptions: ['headers' => ['accept' => 'application/json', 'content-type' => 'application/json']]);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
