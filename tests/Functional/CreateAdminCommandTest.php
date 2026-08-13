<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversNothing]
final class CreateAdminCommandTest extends KernelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
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
            self::assertContains('ROLE_ADMIN', $user->getRoles());
            self::assertContains('ROLE_USER', $user->getRoles());
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
