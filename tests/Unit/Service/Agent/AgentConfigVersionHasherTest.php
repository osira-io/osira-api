<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Agent;

use App\Service\Agent\AgentConfigVersionHasher;
use PHPUnit\Framework\TestCase;

final class AgentConfigVersionHasherTest extends TestCase
{
    public function testHashIsDeterministicAcrossAssociativeKeyOrder(): void
    {
        $hasher = new AgentConfigVersionHasher();

        $left = $hasher->hash([
            'node' => ['id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'hostname' => 'srv-01'],
            'agent' => ['version' => '0.1.0', 'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW'],
            'items' => [[
                'valueType' => 'float',
                'key' => 'system.cpu.usage',
                'unit' => '%',
                'timeoutSeconds' => 5,
                'intervalSeconds' => 10,
                'parameters' => ['b' => 2, 'a' => 1],
            ]],
        ]);
        $right = $hasher->hash([
            'items' => [[
                'parameters' => ['a' => 1, 'b' => 2],
                'intervalSeconds' => 10,
                'timeoutSeconds' => 5,
                'unit' => '%',
                'key' => 'system.cpu.usage',
                'valueType' => 'float',
            ]],
            'agent' => ['id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW', 'version' => '0.1.0'],
            'node' => ['hostname' => 'srv-01', 'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV'],
        ]);

        self::assertSame($left, $right);
    }
}
