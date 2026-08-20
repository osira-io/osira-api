<?php

declare(strict_types=1);

namespace App\Service\Agent;

final class AgentConfigVersionHasher
{
    /** @param array<string, mixed> $payload */
    public function hash(array $payload): string
    {
        $canonical = $this->canonicalize($payload);
        \assert(\is_array($canonical));

        return hash('sha256', json_encode($canonical, \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map($this->canonicalize(...), $value);
        }

        ksort($value);
        foreach ($value as $key => $nested) {
            $value[$key] = $this->canonicalize($nested);
        }

        return $value;
    }
}
