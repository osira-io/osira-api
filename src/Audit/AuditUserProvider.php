<?php

declare(strict_types=1);

namespace App\Audit;

use App\Entity\User as OsiraUser;
use DH\Auditor\User\User;
use DH\Auditor\User\UserInterface;
use DH\Auditor\User\UserProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class AuditUserProvider implements UserProviderInterface
{
    public function __construct(private TokenStorageInterface $tokenStorage)
    {
    }

    public function __invoke(): ?UserInterface
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof OsiraUser) {
            return null;
        }

        return new User((string) $user->id(), $user->getUserIdentifier());
    }
}
