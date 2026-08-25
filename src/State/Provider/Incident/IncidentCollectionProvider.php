<?php

declare(strict_types=1);

namespace App\State\Provider\Incident;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Incident\IncidentCollectionOutput;
use App\Entity\Incident\Incident;
use App\Repository\Incident\IncidentRepository;
use App\Service\Incident\IncidentOutputFactory;
use App\Service\Shared\PaginationMetadataFactory;
use App\Service\Shared\PaginationParameters;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/** @implements ProviderInterface<IncidentCollectionOutput> */
final readonly class IncidentCollectionProvider implements ProviderInterface
{
    public function __construct(
        private IncidentRepository $repository,
        private PaginatorInterface $paginator,
        private IncidentOutputFactory $outputFactory,
        private PaginationMetadataFactory $metadataFactory,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): IncidentCollectionOutput
    {
        $request = $this->requestStack->getCurrentRequest();
        \assert(null !== $request);
        $filters = [];
        foreach (['status', 'severity', 'node', 'alertRule'] as $name) {
            $value = $request->query->get($name);
            if (\is_string($value)) {
                $filters[$name] = $value;
            }
        }
        $date = $request->query->get('date');
        if (\is_string($date)) {
            $filters['date'] = new \DateTimeImmutable($date);
        }

        $parameters = PaginationParameters::fromOperation($operation);
        $pagination = $this->paginator->paginate($this->repository->createFilteredQueryBuilder($filters), $parameters->page, $parameters->itemsPerPage);
        $items = [];
        foreach ($pagination->getItems() as $incident) {
            if (!$incident instanceof Incident) {
                throw new \LogicException('Unexpected incident pagination result.');
            }
            $items[] = $this->outputFactory->create($incident);
        }

        return new IncidentCollectionOutput($items, $this->metadataFactory->create($pagination));
    }
}
