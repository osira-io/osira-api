<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Role;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\SystemRole;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

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
            $tester = $this->commandTester();
            $status = $tester->execute([
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

            $duplicateTester = $this->commandTester();
            $duplicateStatus = $duplicateTester->execute([
                '--email' => 'admin@example.com',
                '--password-file' => $passwordFile,
            ], ['interactive' => false]);
            self::assertSame(Command::FAILURE, $duplicateStatus);
            self::assertStringContainsString('already exists', $duplicateTester->getDisplay());
        } finally {
            unlink($passwordFile);
        }
    }

    private function commandTester(): CommandTester
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $application = new Application($kernel);

        return new CommandTester($application->find('osira:user:create-admin'));
    }
}
