<?php

declare(strict_types=1);

namespace App\User\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\User\Domain\Entity\User;
use App\User\Presentation\Api\Factory\CurrentUserOutputFactory;
use App\User\Presentation\Api\Resource\CurrentUserOutput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** @implements ProviderInterface<CurrentUserOutput> */
final readonly class CurrentUserProvider implements ProviderInterface
{
    public function __construct(private Security $security, private CurrentUserOutputFactory $outputFactory)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CurrentUserOutput
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Authentication is required.');
        }

        return $this->outputFactory->create($user);
    }
}
