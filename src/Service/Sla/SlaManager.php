<?php

declare(strict_types=1);

namespace App\Service\Sla;

use App\Dto\Sla\CreateSlaInput;
use App\Dto\Sla\UpdateSlaInput;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Entity\Sla\Sla;
use App\Factory\Sla\SlaFactory;
use App\Repository\Node\NodeRepository;
use App\Repository\NodeGroup\NodeGroupRepository;
use App\Repository\Sla\SlaRepository;
use App\Service\Shared\Exception\ResourceNotFoundException;
use App\Service\Shared\Exception\ResourceValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Ulid;

final readonly class SlaManager
{
    public function __construct(private SlaRepository $repository, private NodeRepository $nodes, private NodeGroupRepository $groups, private EntityManagerInterface $entityManager, private ClockInterface $clock, private SlaFactory $factory)
    {
    }

    public function create(CreateSlaInput $input): Sla
    {
        $now = $this->clock->now();
        $sla = $this->factory->create($input->name, $input->description, $input->targetPercentage, $input->periodType, $input->excludeMaintenance, $input->isEnabled, $now);
        $sla->replaceNodes($this->resolveNodes($input->nodeIds), $now);
        $sla->replaceNodeGroups($this->resolveGroups($input->nodeGroupIds), $now);
        $this->assertScope($sla);
        $this->entityManager->persist($sla);
        $this->entityManager->flush();

        return $sla;
    }

    public function update(string $id, UpdateSlaInput $input): Sla
    {
        $sla = $this->find($id);
        $now = $this->clock->now();
        $sla->update(
            $input->isNameProvided() ? $this->factory->normalizeName((string) $input->getName()) : $sla->name(),
            $input->isDescriptionProvided() ? $this->factory->normalizeDescription($input->getDescription()) : $sla->description(),
            $input->isTargetPercentageProvided() ? $this->factory->normalizeTarget((float) $input->getTargetPercentage()) : $sla->targetPercentage(),
            $input->isPeriodTypeProvided() ? $this->factory->periodType((string) $input->getPeriodType()) : $sla->periodType(),
            $input->isExcludeMaintenanceProvided() ? (bool) $input->getExcludeMaintenance() : $sla->excludeMaintenance(),
            $input->isIsEnabledProvided() ? (bool) $input->getIsEnabled() : $sla->isEnabled(),
            $now,
        );
        if ($input->areNodeIdsProvided()) {
            $sla->replaceNodes($this->resolveNodes($input->getNodeIds()), $now);
        }
        if ($input->areNodeGroupIdsProvided()) {
            $sla->replaceNodeGroups($this->resolveGroups($input->getNodeGroupIds()), $now);
        }
        $this->assertScope($sla);
        $this->entityManager->flush();

        return $sla;
    }

    public function delete(string $id): void
    {
        $this->entityManager->remove($this->find($id));
        $this->entityManager->flush();
    }

    public function find(string $id): Sla
    {
        if (!Ulid::isValid($id)) {
            throw new ResourceNotFoundException('SLA not found.');
        }
        $sla = $this->repository->find(new Ulid($id));
        if (!$sla instanceof Sla) {
            throw new ResourceNotFoundException('SLA not found.');
        }

        return $sla;
    }

    /**
     * @param list<string> $ids
     *
     * @return list<Node>
     */
    private function resolveNodes(array $ids): array
    {
        $result = [];
        foreach (array_values(array_unique($ids)) as $id) {
            $node = Ulid::isValid($id) ? $this->nodes->find(new Ulid($id)) : null;
            if (!$node instanceof Node) {
                throw new ResourceValidationException(\sprintf('Node "%s" does not exist.', $id));
            }
            $result[] = $node;
        }

        return $result;
    }

    /**
     * @param list<string> $ids
     *
     * @return list<NodeGroup>
     */
    private function resolveGroups(array $ids): array
    {
        $result = [];
        foreach (array_values(array_unique($ids)) as $id) {
            $group = Ulid::isValid($id) ? $this->groups->find(new Ulid($id)) : null;
            if (!$group instanceof NodeGroup) {
                throw new ResourceValidationException(\sprintf('Node group "%s" does not exist.', $id));
            }
            $result[] = $group;
        }

        return $result;
    }

    private function assertScope(Sla $sla): void
    {
        if (0 === $sla->nodes()->count() && 0 === $sla->nodeGroups()->count()) {
            throw new ResourceValidationException('At least one node or node group scope must be provided.');
        }
    }
}
