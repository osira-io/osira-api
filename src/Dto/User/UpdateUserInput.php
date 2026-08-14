<?php

declare(strict_types=1);

namespace App\Dto\User;

use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateUserInput
{
    private bool $emailProvided = false;
    private ?string $email = null;
    private bool $passwordProvided = false;
    private ?string $password = null;
    private bool $roleIdsProvided = false;
    /** @var list<string> */
    private array $roleIds = [];

    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->emailProvided = true;
        $this->email = $email;
    }

    #[Ignore]
    public function isEmailProvided(): bool
    {
        return $this->emailProvided;
    }

    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(min: 12, max: 4096)]
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): void
    {
        $this->passwordProvided = true;
        $this->password = $password;
    }

    #[Ignore]
    public function isPasswordProvided(): bool
    {
        return $this->passwordProvided;
    }

    /** @return list<string> */
    #[Assert\All([new Assert\Ulid()])]
    public function getRoleIds(): array
    {
        return $this->roleIds;
    }

    /** @param list<string> $roleIds */
    public function setRoleIds(array $roleIds): void
    {
        $this->roleIdsProvided = true;
        $this->roleIds = $roleIds;
    }

    #[Ignore]
    public function areRoleIdsProvided(): bool
    {
        return $this->roleIdsProvided;
    }
}
