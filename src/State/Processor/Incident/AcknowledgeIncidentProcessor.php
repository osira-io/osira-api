<?php

declare(strict_types=1);

namespace App\State\Processor\Incident;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Incident\AcknowledgeIncidentInput;
use App\Dto\Incident\IncidentOutput;
use App\Entity\User\User;
use App\Service\Incident\IncidentInteractionManager;
use App\Service\Incident\IncidentOutputFactory;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** @implements ProcessorInterface<AcknowledgeIncidentInput, IncidentOutput> */
final readonly class AcknowledgeIncidentProcessor implements ProcessorInterface
{
    public function __construct(private Security $security, private IncidentInteractionManager $manager, private IncidentOutputFactory $outputFactory)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): IncidentOutput
    {
        $actor = $this->security->getUser();
        if (!$actor instanceof User) {
            throw new AccessDeniedHttpException('User authentication is required.');
        }
        $id = $uriVariables['id'] ?? '';

        return $this->outputFactory->create($this->manager->acknowledge(\is_string($id) ? $id : '', $actor, $data->message));
    }
}
