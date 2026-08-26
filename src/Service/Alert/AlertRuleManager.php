<?php

declare(strict_types=1);

namespace App\Service\Alert;

use App\Dto\Alert\CreateAlertRuleInput;
use App\Dto\Alert\UpdateAlertRuleInput;
use App\Entity\Alert\AlertOperator;
use App\Entity\Alert\AlertRule;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Factory\Alert\AlertRuleFactory;
use App\Repository\Alert\AlertRuleRepository;
use App\Repository\Incident\IncidentRepository;
use App\Repository\Monitoring\ItemDefinitionRepository;
use App\Repository\Monitoring\MonitoringTemplateRepository;
use App\Repository\Node\NodeRepository;
use App\Repository\NodeGroup\NodeGroupRepository;
use App\Service\Shared\Exception\ResourceConflictException;
use App\Service\Shared\Exception\ResourceNotFoundException;
use App\Service\Shared\Exception\ResourceValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Ulid;

final readonly class AlertRuleManager
{
    public function __construct(
        private AlertRuleRepository $repository,
        private ItemDefinitionRepository $itemDefinitions,
        private MonitoringTemplateRepository $monitoringTemplates,
        private NodeGroupRepository $nodeGroups,
        private NodeRepository $nodes,
        private IncidentRepository $incidents,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private AlertRuleFactory $factory,
    ) {
    }

    public function create(CreateAlertRuleInput $input): AlertRule
    {
        $itemDefinition = $this->resolveItemDefinition($input->itemDefinitionId);
        $operator = $this->factory->operator($input->operator);
        $severity = $this->factory->severity($input->severity);
        $impactType = $this->factory->impactType($input->impactType);
        $this->assertCollectableMetric($itemDefinition);
        $this->assertComparison($itemDefinition, $operator, $input->expectedValue, $input->recoveryThreshold);

        $now = $this->clock->now();
        $alertRule = $this->factory->create($input->name, $input->description, $itemDefinition, $operator, $input->expectedValue, $input->recoveryThreshold, $input->evaluationWindowSeconds, $input->requiredOccurrences, $severity, $impactType, $input->isEnabled, $now);
        $this->applyAssignments($alertRule, $input->monitoringTemplateIds, $input->nodeGroupIds, $input->nodeIds, $now);

        $this->entityManager->persist($alertRule);
        $this->entityManager->flush();

        return $alertRule;
    }

    public function update(string $id, UpdateAlertRuleInput $input): AlertRule
    {
        $alertRule = $this->find($id);
        $itemDefinition = $alertRule->itemDefinition();

        $name = $input->isNameProvided() ? $this->factory->normalizeName((string) $input->getName()) : $alertRule->name();
        $description = $input->isDescriptionProvided() ? $this->factory->normalizeDescription($input->getDescription()) : $alertRule->description();
        $operator = $input->isOperatorProvided() ? $this->factory->operator((string) $input->getOperator()) : $alertRule->operator();
        $expectedValue = $input->isExpectedValueProvided() ? (string) $input->getExpectedValue() : $alertRule->expectedValue();
        $recoveryThreshold = $input->isRecoveryThresholdProvided() ? $input->getRecoveryThreshold() : $alertRule->recoveryThreshold();
        $severity = $input->isSeverityProvided() ? $this->factory->severity((string) $input->getSeverity()) : $alertRule->severity();
        $impactType = $input->isImpactTypeProvided() ? $this->factory->impactType((string) $input->getImpactType()) : $alertRule->impactType();
        $evaluationWindowSeconds = $input->isEvaluationWindowSecondsProvided() ? (int) $input->getEvaluationWindowSeconds() : $alertRule->evaluationWindowSeconds();
        $requiredOccurrences = $input->isRequiredOccurrencesProvided() ? (int) $input->getRequiredOccurrences() : $alertRule->requiredOccurrences();
        $isEnabled = $input->isIsEnabledProvided() ? (bool) $input->getIsEnabled() : $alertRule->isEnabled();

        if ($input->isOperatorProvided() || $input->isExpectedValueProvided() || $input->isRecoveryThresholdProvided()) {
            $this->assertComparison($itemDefinition, $operator, $expectedValue, $recoveryThreshold);
        }

        $now = $this->clock->now();
        $alertRule->update($name, $description, $operator, $expectedValue, $recoveryThreshold, $evaluationWindowSeconds, $requiredOccurrences, $severity, $impactType, $isEnabled, $now);

        try {
            if ($input->areMonitoringTemplateIdsProvided()) {
                $alertRule->replaceTemplates($this->resolveTemplates($input->getMonitoringTemplateIds()), $now);
            }
            if ($input->areNodeGroupIdsProvided()) {
                $alertRule->replaceNodeGroups($this->resolveGroups($input->getNodeGroupIds()), $now);
            }
            if ($input->areNodeIdsProvided()) {
                $alertRule->replaceNodes($this->resolveNodes($input->getNodeIds()), $now);
            }
        } catch (\InvalidArgumentException $exception) {
            throw new ResourceValidationException($exception->getMessage());
        }

        $this->entityManager->flush();

        return $alertRule;
    }

    public function delete(string $id): void
    {
        $alertRule = $this->find($id);
        if ($this->incidents->count(['alertRule' => $alertRule->id()]) > 0) {
            throw new ResourceConflictException('This alert rule is referenced by existing incidents and cannot be deleted.');
        }
        $this->entityManager->remove($alertRule);
        $this->entityManager->flush();
    }

    public function find(string $id): AlertRule
    {
        if (!Ulid::isValid($id)) {
            throw new ResourceNotFoundException('Alert rule not found.');
        }
        $alertRule = $this->repository->findWithItemDefinition(new Ulid($id));
        if (!$alertRule instanceof AlertRule) {
            throw new ResourceNotFoundException('Alert rule not found.');
        }

        return $alertRule;
    }

    /** @param list<string> $monitoringTemplateIds
     * @param list<string> $nodeGroupIds
     * @param list<string> $nodeIds
     */
    private function applyAssignments(AlertRule $alertRule, array $monitoringTemplateIds, array $nodeGroupIds, array $nodeIds, \DateTimeImmutable $now): void
    {
        try {
            $alertRule->replaceTemplates($this->resolveTemplates($monitoringTemplateIds), $now);
            $alertRule->replaceNodeGroups($this->resolveGroups($nodeGroupIds), $now);
            $alertRule->replaceNodes($this->resolveNodes($nodeIds), $now);
        } catch (\InvalidArgumentException $exception) {
            throw new ResourceValidationException($exception->getMessage());
        }
    }

    private function resolveItemDefinition(string $id): ItemDefinition
    {
        if (!Ulid::isValid($id)) {
            throw new ResourceValidationException(\sprintf('Item definition "%s" does not exist.', $id));
        }
        $itemDefinition = $this->itemDefinitions->find(new Ulid($id));
        if (!$itemDefinition instanceof ItemDefinition) {
            throw new ResourceValidationException(\sprintf('Item definition "%s" does not exist.', $id));
        }

        return $itemDefinition;
    }

    private function assertCollectableMetric(ItemDefinition $itemDefinition): void
    {
        if (ItemValueType::STRING === $itemDefinition->valueType()) {
            throw new ResourceValidationException('An alert rule cannot target a string item: string items are not collected as metrics in V1 and can never produce an effective alert.');
        }
    }

    private function assertComparison(ItemDefinition $itemDefinition, AlertOperator $operator, string $expectedValue, ?string $recoveryThreshold): void
    {
        try {
            AlertRuleEvaluator::validateOperator($itemDefinition->valueType(), $operator);
            AlertRuleEvaluator::assertValidValueFormat($itemDefinition->valueType(), $expectedValue);
            if (null !== $recoveryThreshold) {
                AlertRuleEvaluator::assertValidValueFormat($itemDefinition->valueType(), $recoveryThreshold);
            }
        } catch (InvalidAlertComparison $exception) {
            throw new ResourceValidationException($exception->getMessage());
        }
    }

    /** @param list<string> $ids
     * @return list<MonitoringTemplate>
     */
    private function resolveTemplates(array $ids): array
    {
        $result = [];
        foreach (array_values(array_unique($ids)) as $id) {
            $template = Ulid::isValid($id) ? $this->monitoringTemplates->find(new Ulid($id)) : null;
            if (!$template instanceof MonitoringTemplate) {
                throw new ResourceValidationException(\sprintf('Monitoring template "%s" does not exist.', $id));
            }
            $result[] = $template;
        }

        return $result;
    }

    /** @param list<string> $ids
     * @return list<NodeGroup>
     */
    private function resolveGroups(array $ids): array
    {
        $result = [];
        foreach (array_values(array_unique($ids)) as $id) {
            $group = Ulid::isValid($id) ? $this->nodeGroups->find(new Ulid($id)) : null;
            if (!$group instanceof NodeGroup) {
                throw new ResourceValidationException(\sprintf('Node group "%s" does not exist.', $id));
            }
            $result[] = $group;
        }

        return $result;
    }

    /** @param list<string> $ids
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
}
