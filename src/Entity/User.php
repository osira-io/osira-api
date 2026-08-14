<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_users_email', columns: ['email'])]
final class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private readonly Ulid $id;

    #[ORM\Column(length: 180)]
    /** @var non-empty-string */
    private readonly string $email;

    #[ORM\Column]
    private string $password;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @param list<string> $roles */
    public function __construct(string $email, array $roles, \DateTimeImmutable $now)
    {
        $this->id = new Ulid();
        $this->email = self::normalizeEmail($email);
        $this->password = '';
        $this->roles = array_values(array_unique([...$roles, 'ROLE_USER']));
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    /** @return non-empty-string */
    public static function normalizeEmail(string $email): string
    {
        $normalizedEmail = mb_strtolower(trim($email));
        if ('' === $normalizedEmail) {
            throw new \InvalidArgumentException('The email address cannot be empty.');
        }

        return $normalizedEmail;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setPasswordHash(string $passwordHash, \DateTimeImmutable $updatedAt): void
    {
        $this->password = $passwordHash;
        $this->updatedAt = $updatedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
