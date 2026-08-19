<?php

declare(strict_types=1);

namespace App\Factory\User;

use App\Entity\User\User;
use App\Service\Shared\Exception\ResourceValidationException;

final class UserFactory
{
    /**
     * @param list<string> $technicalRoles
     */
    public function create(string $email, \DateTimeImmutable $createdAt, array $technicalRoles = ['ROLE_USER']): User
    {
        return new User($this->normalizeEmail($email), $technicalRoles, $createdAt);
    }

    public function normalizeEmail(string $email): string
    {
        try {
            return User::normalizeEmail($email);
        } catch (\InvalidArgumentException $exception) {
            throw new ResourceValidationException($exception->getMessage(), previous: $exception);
        }
    }
}
