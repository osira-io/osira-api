<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command\User;

use App\Entity\Rbac\Role;
use App\Entity\User\User;
use App\Repository\User\UserRepository;
use App\Security\Rbac\SystemRole;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\ApplicationTester;

final class CreateAdminCommandTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($metadata);
    }

    public function testCommandCreatesAdministratorAndRefusesDuplicateEmail(): void
    {
        $passwordFile = tempnam(sys_get_temp_dir(), 'osira-admin-password-');
        self::assertIsString($passwordFile);
        file_put_contents($passwordFile, "correct horse battery staple\n", \LOCK_EX);

        try {
            $tester = $this->applicationTester();
            $status = $tester->run([
                'command' => 'osira:user:create-admin',
                '--email' => 'Admin@Example.com',
                '--password-file' => $passwordFile,
            ], ['interactive' => false]);

            self::assertSame(Command::SUCCESS, $status);
            self::assertStringContainsString('admin@example.com', $tester->getDisplay());
            self::assertStringNotContainsString('correct horse battery staple', $tester->getDisplay());

            $repository = self::getContainer()->get(UserRepository::class);
            $user = $repository->findOneByEmail('admin@example.com');
            self::assertInstanceOf(User::class, $user);
            self::assertContains('ROLE_USER', $user->getRoles());
            self::assertSame([SystemRole::SUPER_ADMIN], array_map(static fn (Role $role): string => $role->slug(), $user->businessRoles()->toArray()));
            self::assertNotSame('correct horse battery staple', $user->getPassword());
            $audit = self::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchAssociative(
                'SELECT blame_id, blame_user, diffs FROM audit_users WHERE object_id = ? AND type = ?',
                [(string) $user->id(), 'insert'],
            );
            self::assertIsArray($audit);
            self::assertSame('osira:user:create-admin', $audit['blame_id'] ?? null);
            self::assertSame('osira:user:create-admin', $audit['blame_user'] ?? null);
            $auditDiffs = $audit['diffs'] ?? null;
            self::assertIsString($auditDiffs);
            self::assertStringNotContainsString('password', strtolower($auditDiffs));

            $duplicateTester = $this->applicationTester();
            $duplicateStatus = $duplicateTester->run([
                'command' => 'osira:user:create-admin',
                '--email' => 'admin@example.com',
                '--password-file' => $passwordFile,
            ], ['interactive' => false]);
            self::assertSame(Command::FAILURE, $duplicateStatus);
            self::assertStringContainsString('already exists', $duplicateTester->getDisplay());
        } finally {
            unlink($passwordFile);
        }
    }

    private function applicationTester(): ApplicationTester
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $application = new Application($kernel);
        $application->setAutoExit(false);

        return new ApplicationTester($application);
    }
}
