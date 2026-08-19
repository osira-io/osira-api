<?php

declare(strict_types=1);

namespace App\Dto\NodeGroup;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateNodeGroupInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    public string $name = '';

    #[Assert\Length(max: 2000)]
    public ?string $description = null;

    /** @var list<string> */
    #[Assert\Count(max: 100)]
    #[Assert\All([new Assert\Type('string'), new Assert\Ulid()])]
    public array $monitoringTemplateIds = [];
}
