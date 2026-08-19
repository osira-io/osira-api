<?php

declare(strict_types=1);

namespace App\Dto\Monitoring;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateMonitoringTemplateInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    public string $name = '';

    #[Assert\Length(max: 128)]
    public ?string $slug = null;

    #[Assert\Length(max: 2000)]
    public ?string $description = null;

    /** @var list<string> */
    #[Assert\Count(max: 200)]
    #[Assert\All([new Assert\Type('string'), new Assert\Ulid()])]
    public array $itemDefinitionIds = [];

    public bool $isEnabled = true;
}
