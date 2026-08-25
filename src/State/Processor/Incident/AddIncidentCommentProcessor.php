<?php

declare(strict_types=1);

namespace App\State\Processor\Incident;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Incident\AddIncidentCommentInput;
use App\Dto\Incident\IncidentActivityOutput;
use App\Entity\User\User;
use App\Service\Incident\IncidentActivityOutputFactory;
use App\Service\Incident\IncidentInteractionManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** @implements ProcessorInterface<AddIncidentCommentInput, IncidentActivityOutput> */
final readonly class AddIncidentCommentProcessor implements ProcessorInterface
{
    public function __construct(private Security $security, private IncidentInteractionManager $manager, private IncidentActivityOutputFactory $outputFactory)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): IncidentActivityOutput
    {
        $actor = $this->security->getUser();
        if (!$actor instanceof User) {
            throw new AccessDeniedHttpException('User authentication is required.');
        }
        $id = $uriVariables['id'] ?? '';

        return $this->outputFactory->create($this->manager->comment(\is_string($id) ? $id : '', $actor, $data->message));
    }
}
