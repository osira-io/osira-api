<?php

declare(strict_types=1);

namespace App\Factory\Monitoring;

use App\Entity\Monitoring\MonitoringTemplate;
use App\Service\Shared\Exception\ResourceValidationException;

final class MonitoringTemplateFactory
{
    public function create(
        string $name,
        ?string $slug,
        ?string $description,
        bool $isEnabled,
        \DateTimeImmutable $createdAt,
    ): MonitoringTemplate {
        $normalizedName = $this->normalizeName($name);

        return new MonitoringTemplate(
            $normalizedName,
            $this->normalizeSlug($slug ?? $normalizedName),
            $this->normalizeDescription($description),
            $isEnabled,
            $createdAt,
        );
    }

    public function normalizeName(string $name): string
    {
        $name = trim($name);
        if ('' === $name) {
            throw new ResourceValidationException('A monitoring template name cannot be empty.');
        }

        return $name;
    }

    public function normalizeSlug(string $slug): string
    {
        $slug = mb_strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        if ('' === $slug) {
            throw new ResourceValidationException('A monitoring template slug cannot be empty.');
        }

        return $slug;
    }

    public function normalizeDescription(?string $description): ?string
    {
        if (null === $description) {
            return null;
        }

        $description = trim($description);

        return '' === $description ? null : $description;
    }
}
