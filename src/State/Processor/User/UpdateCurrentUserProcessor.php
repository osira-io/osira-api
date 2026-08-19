<?php

declare(strict_types=1);

namespace App\State\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\User\CurrentUserOutput;
use App\Dto\User\UpdateCurrentUserInput;
use App\Entity\User\User;
use App\Service\User\CurrentUserManager;
use App\Service\User\CurrentUserOutputFactory;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** @implements ProcessorInterface<UpdateCurrentUserInput, CurrentUserOutput> */
final readonly class UpdateCurrentUserProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private CurrentUserManager $manager,
        private CurrentUserOutputFactory $outputFactory,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CurrentUserOutput
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Authentication is required.');
        }

        return $this->outputFactory->create($this->manager->updateLocale($user, $data->getLocale()));
    }
}
