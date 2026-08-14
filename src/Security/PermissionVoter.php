<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, mixed> */
final class PermissionVoter extends Voter
{
    public function __construct(private readonly PermissionChecker $checker)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \array_key_exists($attribute, PermissionCode::catalog());
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $this->checker->isGranted($user, $attribute);
    }
}
