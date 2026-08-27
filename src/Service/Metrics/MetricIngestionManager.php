<?php

declare(strict_types=1);

namespace App\Service\Metrics;

use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Node\Node;
use App\Service\Alert\AlertEvaluationManager;
use App\Service\Monitoring\EffectiveNodeMonitoringResolver;
use App\Service\Shared\Exception\ResourceValidationException;
use Psr\Clock\ClockInterface;

final readonly class MetricIngestionManager
{
    public function __construct(
        private EffectiveNodeMonitoringResolver $monitoringResolver,
        private VictoriaMetricsClientInterface $victoriaMetrics,
        private AlertEvaluationManager $alertEvaluationManager,
        private ClockInterface $clock,
    ) {
    }

    /** @param list<array{itemKey: string, value: mixed, collectedAt?: string|null}> $samples */
    public function ingest(Node $node, array $samples): int
    {
        $effectiveItems = $this->monitoringResolver->resolve($node)->items;
        $itemsByKey = [];
        foreach ($effectiveItems as $item) {
            $itemsByKey[$item->key()] = $item;
        }

        $now = $this->clock->now();
        $normalized = [];
        /** @var array<string, array{item: ItemDefinition, evaluatedAt: \DateTimeImmutable}> $evaluations */
        $evaluations = [];
        foreach ($samples as $index => $sample) {
            $itemKey = $sample['itemKey'];
            $item = $itemsByKey[$itemKey] ?? null;
            if (!$item instanceof ItemDefinition) {
                throw new ResourceValidationException(\sprintf('Sample %d references an ItemDefinition that is not effective for this Node.', $index));
            }
            $collectedAt = $this->collectedAt($sample, $now, $index);
            $normalized[] = new VictoriaMetricsWriteSample(
                (string) $node->id(),
                $item->key(),
                self::normalizeValue($sample['value'], $item->valueType(), $index),
                $collectedAt,
            );
            $id = (string) $item->id();
            if (!isset($evaluations[$id]) || $evaluations[$id]['evaluatedAt'] < $collectedAt) {
                $evaluations[$id] = ['item' => $item, 'evaluatedAt' => $collectedAt];
            }
        }

        $this->victoriaMetrics->importSamples($normalized);
        $this->alertEvaluationManager->evaluateItems($node, $evaluations);

        return \count($normalized);
    }

    /** @param array{itemKey: string, value: mixed, collectedAt?: string|null} $sample */
    private function collectedAt(array $sample, \DateTimeImmutable $now, int $index): \DateTimeImmutable
    {
        $rawCollectedAt = $sample['collectedAt'] ?? null;
        if (null === $rawCollectedAt) {
            return $now;
        }
        try {
            $collectedAt = new \DateTimeImmutable($rawCollectedAt);
        } catch (\Throwable $exception) {
            throw new ResourceValidationException(\sprintf('Sample %d has an invalid collectedAt.', $index), previous: $exception);
        }
        if ($collectedAt > $now->modify('+5 minutes')) {
            throw new ResourceValidationException(\sprintf('Sample %d collectedAt is too far in the future.', $index));
        }

        return $collectedAt;
    }

    private static function normalizeValue(mixed $value, ItemValueType $type, int $index): string
    {
        return match ($type) {
            ItemValueType::FLOAT => self::floatValue($value, $index),
            ItemValueType::INTEGER => \is_int($value)
                ? (string) $value
                : throw new ResourceValidationException(\sprintf('Sample %d value must be an integer.', $index)),
            ItemValueType::BOOLEAN => \is_bool($value)
                ? ($value ? '1' : '0')
                : throw new ResourceValidationException(\sprintf('Sample %d value must be a boolean.', $index)),
            ItemValueType::STRING => throw new ResourceValidationException(\sprintf('Sample %d targets a string ItemDefinition, which is not metric-compatible.', $index)),
        };
    }

    private static function floatValue(mixed $value, int $index): string
    {
        if ((!\is_float($value) && !\is_int($value)) || !is_finite((float) $value)) {
            throw new ResourceValidationException(\sprintf('Sample %d value must be a finite number.', $index));
        }

        return (string) $value;
    }
}
