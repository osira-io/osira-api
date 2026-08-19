<?php

declare(strict_types=1);

namespace App\Service\Monitoring\Factory;

use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Service\Shared\Exception\ResourceValidationException;

final class ItemDefinitionFactory
{
    public function create(
        string $key,
        string $name,
        ?string $description,
        ?string $category,
        ?string $unit,
        ItemValueType $valueType,
        int $intervalSeconds,
        ?int $timeoutSeconds,
        bool $isSystem,
        bool $isEnabled,
        \DateTimeImmutable $createdAt,
    ): ItemDefinition {
        return new ItemDefinition(
            $this->normalizeKey($key),
            $this->normalizeName($name),
            $this->normalizeNullable($description),
            $this->normalizeNullable($category),
            $this->normalizeNullable($unit),
            $valueType,
            $this->normalizePositive($intervalSeconds, 'The interval must be greater than zero.'),
            $this->normalizeNullablePositive($timeoutSeconds, 'The timeout must be greater than zero when provided.'),
            $isSystem,
            $isEnabled,
            $createdAt,
        );
    }

    public function normalizeKey(string $key): string
    {
        $key = mb_strtolower(trim($key));
        if ('' === $key) {
            throw new ResourceValidationException('An item definition key cannot be empty.');
        }

        if (1 !== preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $key)) {
            throw new ResourceValidationException('The item definition key contains invalid characters.');
        }

        return $key;
    }

    public function normalizeName(string $name): string
    {
        $name = trim($name);
        if ('' === $name) {
            throw new ResourceValidationException('An item definition name cannot be empty.');
        }

        return $name;
    }

    public function normalizeNullable(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }

    public function normalizePositive(int $value, string $message): int
    {
        if ($value <= 0) {
            throw new ResourceValidationException($message);
        }

        return $value;
    }

    public function normalizeNullablePositive(?int $value, string $message): ?int
    {
        if (null === $value) {
            return null;
        }

        if ($value <= 0) {
            throw new ResourceValidationException($message);
        }

        return $value;
    }
}
