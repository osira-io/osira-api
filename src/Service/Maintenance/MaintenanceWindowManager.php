<?php

declare(strict_types=1);

namespace App\Service\Maintenance;

use App\Dto\Maintenance\CreateMaintenanceWindowInput;
use App\Dto\Maintenance\UpdateMaintenanceWindowInput;
use App\Entity\Maintenance\MaintenanceWindow;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Factory\Maintenance\MaintenanceWindowFactory;
use App\Repository\Maintenance\MaintenanceWindowRepository;
use App\Repository\Node\NodeRepository;
use App\Repository\NodeGroup\NodeGroupRepository;
use App\Service\Shared\Exception\ResourceNotFoundException;
use App\Service\Shared\Exception\ResourceValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Ulid;

final readonly class MaintenanceWindowManager
{
    public function __construct(
        private MaintenanceWindowRepository $repository,
        private NodeRepository $nodeRepository,
        private NodeGroupRepository $nodeGroupRepository,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private MaintenanceWindowFactory $factory,
    ) {
    }

    public function create(CreateMaintenanceWindowInput $input): MaintenanceWindow
    {
        $startsAt = $this->factory->parseDateTime($input->startsAt, 'startsAt');
        $endsAt = $this->factory->parseDateTime($input->endsAt, 'endsAt');
        $this->factory->assertInterval($startsAt, $endsAt);
        $now = $this->clock->now();
        $window = $this->factory->create($input->name, $input->description, $startsAt, $endsAt, $input->isEnabled, $now);
        $window->replaceNodes($this->resolveNodes($input->nodeIds), $now);
        $window->replaceNodeGroups($this->resolveNodeGroups($input->nodeGroupIds), $now);
        $this->assertHasTarget($window);
        $this->entityManager->persist($window);
        $this->entityManager->flush();

        return $window;
    }

    public function update(string $id, UpdateMaintenanceWindowInput $input): MaintenanceWindow
    {
        $window = $this->find($id);
        $startsAt = $input->isStartsAtProvided()
            ? $this->factory->parseDateTime((string) $input->getStartsAt(), 'startsAt')
            : $window->startsAt();
        $endsAt = $input->isEndsAtProvided()
            ? $this->factory->parseDateTime((string) $input->getEndsAt(), 'endsAt')
            : $window->endsAt();
        $this->factory->assertInterval($startsAt, $endsAt);
        $now = $this->clock->now();
        $window->update(
            $input->isNameProvided() ? $this->factory->normalizeName((string) $input->getName()) : $window->name(),
            $input->isDescriptionProvided() ? $this->factory->normalizeDescription($input->getDescription()) : $window->description(),
            $startsAt,
            $endsAt,
            $input->isIsEnabledProvided() ? (bool) $input->getIsEnabled() : $window->isEnabled(),
            $now,
        );
        if ($input->areNodeIdsProvided()) {
            $window->replaceNodes($this->resolveNodes($input->getNodeIds()), $now);
        }
        if ($input->areNodeGroupIdsProvided()) {
            $window->replaceNodeGroups($this->resolveNodeGroups($input->getNodeGroupIds()), $now);
        }
        $this->assertHasTarget($window);
        $this->entityManager->flush();

        return $window;
    }

    public function delete(string $id): void
    {
        $this->entityManager->remove($this->find($id));
        $this->entityManager->flush();
    }

    private function find(string $id): MaintenanceWindow
    {
        if (!Ulid::isValid($id)) {
            throw new ResourceNotFoundException('Maintenance window not found.');
        }
        $window = $this->repository->find(new Ulid($id));
        if (!$window instanceof MaintenanceWindow) {
            throw new ResourceNotFoundException('Maintenance window not found.');
        }

        return $window;
    }

    /** @param list<string> $ids
     * @return list<Node>
     */
    private function resolveNodes(array $ids): array
    {
        $nodes = [];
        foreach (array_values(array_unique($ids)) as $id) {
            if (!Ulid::isValid($id)) {
                throw new ResourceValidationException(\sprintf('Node "%s" does not exist.', $id));
            }
            $node = $this->nodeRepository->find(new Ulid($id));
            if (!$node instanceof Node) {
                throw new ResourceValidationException(\sprintf('Node "%s" does not exist.', $id));
            }
            $nodes[] = $node;
        }

        return $nodes;
    }

    /** @param list<string> $ids
     * @return list<NodeGroup>
     */
    private function resolveNodeGroups(array $ids): array
    {
        $groups = [];
        foreach (array_values(array_unique($ids)) as $id) {
            if (!Ulid::isValid($id)) {
                throw new ResourceValidationException(\sprintf('Node group "%s" does not exist.', $id));
            }
            $group = $this->nodeGroupRepository->find(new Ulid($id));
            if (!$group instanceof NodeGroup) {
                throw new ResourceValidationException(\sprintf('Node group "%s" does not exist.', $id));
            }
            $groups[] = $group;
        }

        return $groups;
    }

    private function assertHasTarget(MaintenanceWindow $window): void
    {
        if (0 === $window->nodes()->count() && 0 === $window->nodeGroups()->count()) {
            throw new ResourceValidationException('At least one node or node group target must be provided.');
        }
    }
}
