<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto\Monitoring;

use App\Dto\Monitoring\UpdateItemDefinitionInput;
use App\Dto\Monitoring\UpdateMonitoringTemplateInput;
use PHPUnit\Framework\TestCase;

final class UpdateMonitoringInputsTest extends TestCase
{
    public function testUpdateItemDefinitionInputTracksProvidedFields(): void
    {
        $input = new UpdateItemDefinitionInput();

        self::assertFalse($input->isKeyProvided());
        self::assertFalse($input->isNameProvided());
        self::assertFalse($input->isDescriptionProvided());
        self::assertFalse($input->isUnitProvided());
        self::assertFalse($input->isValueTypeProvided());
        self::assertFalse($input->isIntervalSecondsProvided());
        self::assertFalse($input->isTimeoutSecondsProvided());
        self::assertFalse($input->isLinuxCommandProvided());
        self::assertFalse($input->isWindowsCommandProvided());
        self::assertFalse($input->isIsEnabledProvided());

        $input->setKey('custom.key');
        $input->setName('Custom name');
        $input->setDescription('Custom description');
        $input->setUnit('ms');
        $input->setValueType('float');
        $input->setIntervalSeconds(30);
        $input->setTimeoutSeconds(5);
        $input->setLinuxCommand('printf 1');
        $input->setWindowsCommand('Write-Output 1');
        $input->setIsEnabled(true);

        self::assertTrue($input->isKeyProvided());
        self::assertTrue($input->isNameProvided());
        self::assertTrue($input->isDescriptionProvided());
        self::assertTrue($input->isUnitProvided());
        self::assertTrue($input->isValueTypeProvided());
        self::assertTrue($input->isIntervalSecondsProvided());
        self::assertTrue($input->isTimeoutSecondsProvided());
        self::assertTrue($input->isLinuxCommandProvided());
        self::assertTrue($input->isWindowsCommandProvided());
        self::assertTrue($input->isIsEnabledProvided());
        self::assertSame('custom.key', $input->getKey());
        self::assertSame('Custom name', $input->getName());
        self::assertSame('Custom description', $input->getDescription());
        self::assertSame('ms', $input->getUnit());
        self::assertSame('float', $input->getValueType());
        self::assertSame(30, $input->getIntervalSeconds());
        self::assertSame(5, $input->getTimeoutSeconds());
        self::assertSame('printf 1', $input->getLinuxCommand());
        self::assertSame('Write-Output 1', $input->getWindowsCommand());
        self::assertTrue($input->getIsEnabled());
    }

    public function testUpdateMonitoringTemplateInputTracksProvidedFields(): void
    {
        $input = new UpdateMonitoringTemplateInput();

        self::assertFalse($input->isNameProvided());
        self::assertFalse($input->isSlugProvided());
        self::assertFalse($input->isDescriptionProvided());
        self::assertFalse($input->areItemDefinitionIdsProvided());
        self::assertFalse($input->isIsEnabledProvided());
        self::assertSame([], $input->getItemDefinitionIds());

        $input->setName('Linux Base');
        $input->setSlug('linux-base');
        $input->setDescription('Core template');
        $input->setItemDefinitionIds(['01K2Z6QQN7Z7M0DYXJ5F0M0JVE']);
        $input->setIsEnabled(false);

        self::assertTrue($input->isNameProvided());
        self::assertTrue($input->isSlugProvided());
        self::assertTrue($input->isDescriptionProvided());
        self::assertTrue($input->areItemDefinitionIdsProvided());
        self::assertTrue($input->isIsEnabledProvided());
        self::assertSame('Linux Base', $input->getName());
        self::assertSame('linux-base', $input->getSlug());
        self::assertSame('Core template', $input->getDescription());
        self::assertSame(['01K2Z6QQN7Z7M0DYXJ5F0M0JVE'], $input->getItemDefinitionIds());
        self::assertFalse($input->getIsEnabled());
    }
}
