<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'osira:user:create-admin', description: 'Create the first Osira administrator.')]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Administrator email address')
            ->addOption('password-file', null, InputOption::VALUE_REQUIRED, 'Read the password from a file, or from stdin with -');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $this->readEmail($input, $output);
        if (null === $email) {
            return Command::INVALID;
        }

        if (null !== $this->userRepository->findOneByEmail($email)) {
            $output->writeln('<error>A user with this email already exists.</error>');

            return Command::FAILURE;
        }

        $password = $this->readPassword($input, $output);
        if (null === $password) {
            return Command::INVALID;
        }

        $now = $this->clock->now();
        $user = new User($email, ['ROLE_ADMIN'], $now);
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password), $now);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $output->writeln(\sprintf('<info>Administrator %s created successfully.</info>', $user->getUserIdentifier()));

        return Command::SUCCESS;
    }

    private function readEmail(InputInterface $input, OutputInterface $output): ?string
    {
        $emailOption = $input->getOption('email');
        $email = \is_string($emailOption) ? $emailOption : null;
        if (null === $email && $input->isInteractive()) {
            $email = $this->questionHelper()->ask($input, $output, new Question('Administrator email: '));
        }

        if (!\is_string($email) || 0 !== \count($this->validator->validate($email, [new Assert\NotBlank(), new Assert\Email()]))) {
            $output->writeln('<error>A valid email address is required.</error>');

            return null;
        }

        return User::normalizeEmail($email);
    }

    private function readPassword(InputInterface $input, OutputInterface $output): ?string
    {
        $passwordFile = $input->getOption('password-file');
        $password = null;
        if (\is_string($passwordFile)) {
            $contents = @file_get_contents('-' === $passwordFile ? 'php://stdin' : $passwordFile);
            $password = false === $contents ? null : rtrim($contents, "\r\n");
        } elseif ($input->isInteractive()) {
            $question = new Question('Password: ');
            $question->setHidden(true);
            $password = $this->questionHelper()->ask($input, $output, $question);
        }

        if (!\is_string($password) || mb_strlen($password) < 12) {
            $output->writeln('<error>A password of at least 12 characters is required.</error>');

            return null;
        }

        return $password;
    }

    private function questionHelper(): QuestionHelper
    {
        $helper = $this->getHelper('question');
        if (!$helper instanceof QuestionHelper) {
            throw new \LogicException('The Symfony question helper is unavailable.');
        }

        return $helper;
    }
}
