<?php

declare(strict_types=1);

namespace App\User\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\User\Application\Service\CurrentUserManager;
use App\User\Domain\Entity\User;
use App\User\Presentation\Api\Dto\UpdateCurrentUserInput;
use App\User\Presentation\Api\Factory\CurrentUserOutputFactory;
use App\User\Presentation\Api\Resource\CurrentUserOutput;
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
