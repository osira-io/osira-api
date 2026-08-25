<?php

declare(strict_types=1);

namespace App\Factory\Monitoring;

use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Service\Shared\Exception\ResourceValidationException;

final class ItemDefinitionFactory
{
    public function create(
        string $key,
        string $name,
        ?string $description,
        ?string $unit,
        ItemValueType $valueType,
        int $intervalSeconds,
        ?int $timeoutSeconds,
        ?string $linuxCommand,
        ?string $windowsCommand,
        bool $isEnabled,
        \DateTimeImmutable $createdAt,
    ): ItemDefinition {
        $linuxCommand = $this->normalizeCommand($linuxCommand);
        $windowsCommand = $this->normalizeCommand($windowsCommand);
        $this->assertAtLeastOneCommand($linuxCommand, $windowsCommand);

        return new ItemDefinition(
            $this->normalizeKey($key),
            $this->normalizeName($name),
            $this->normalizeNullable($description),
            $this->normalizeNullable($unit),
            $valueType,
            $this->normalizePositive($intervalSeconds, 'The interval must be greater than zero.'),
            $this->normalizeNullablePositive($timeoutSeconds, 'The timeout must be greater than zero when provided.'),
            $linuxCommand,
            $windowsCommand,
            $isEnabled,
            $createdAt,
        );
    }

    public function normalizeCommand(?string $command): ?string
    {
        if (null === $command) {
            return null;
        }
        if (str_contains($command, "\0")) {
            throw new ResourceValidationException('Collection commands cannot contain NUL bytes.');
        }
        if (mb_strlen($command) > 20000) {
            throw new ResourceValidationException('Collection commands cannot exceed 20000 characters.');
        }
        if ('' === trim($command)) {
            throw new ResourceValidationException('A collection command cannot be empty when provided.');
        }

        return $command;
    }

    public function assertAtLeastOneCommand(?string $linuxCommand, ?string $windowsCommand): void
    {
        if (null === $linuxCommand && null === $windowsCommand) {
            throw new ResourceValidationException('At least one Linux or Windows collection command must be provided.');
        }
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
