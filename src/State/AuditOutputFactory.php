<?php

declare(strict_types=1);

namespace App\State;

use App\ApiResource\AuditActorOutput;
use App\ApiResource\AuditOutput;
use App\Audit\AuditRecord;

final readonly class AuditOutputFactory
{
    private const array SENSITIVE_FIELD_PARTS = ['password', 'token', 'secret', 'credential'];

    public function create(AuditRecord $record): AuditOutput
    {
        $entry = $record->entry;
        $createdAt = $entry->createdAt;
        if (!$createdAt instanceof \DateTimeImmutable || null === $entry->id) {
            throw new \LogicException('Incomplete audit entry returned by the reader.');
        }

        $actor = null;
        if (null !== $entry->userId || null !== $entry->username) {
            $actor = new AuditActorOutput(null === $entry->userId ? null : (string) $entry->userId, $entry->username);
        }

        return new AuditOutput(
            $record->entity.':'.$entry->id,
            $record->entity,
            $entry->objectId,
            $entry->type,
            $actor,
            $entry->ip,
            $entry->userFirewall,
            $createdAt,
            self::removeSensitiveFields($entry->getDiffs()),
        );
    }

    /** @param array<mixed, mixed> $values
     * @return array<string, mixed>
     */
    private static function removeSensitiveFields(array $values): array
    {
        $safeValues = [];
        foreach ($values as $field => $value) {
            if (!\is_string($field)) {
                continue;
            }
            $normalizedField = strtolower($field);
            if (array_any(self::SENSITIVE_FIELD_PARTS, static fn (string $part): bool => str_contains($normalizedField, $part))) {
                continue;
            }
            if (\is_array($value)) {
                $value = self::removeSensitiveFields($value);
            }
            $safeValues[$field] = $value;
        }

        return $safeValues;
    }
}
