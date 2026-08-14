<?php

declare(strict_types=1);

namespace App\Dto\Node;

use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateNodeInput
{
    private bool $displayNameProvided = false;
    private ?string $displayName = null;
    private bool $environmentProvided = false;
    private ?string $environment = null;
    private bool $tagsProvided = false;
    /** @var list<string> */
    private array $tags = [];
    private bool $groupsProvided = false;
    /** @var list<string> */
    private array $groups = [];

    #[Assert\Length(max: 255)]
    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): void
    {
        $this->displayNameProvided = true;
        $this->displayName = $displayName;
    }

    #[Ignore]
    public function isDisplayNameProvided(): bool
    {
        return $this->displayNameProvided;
    }

    #[Assert\Length(max: 64)]
    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    public function setEnvironment(?string $environment): void
    {
        $this->environmentProvided = true;
        $this->environment = $environment;
    }

    #[Ignore]
    public function isEnvironmentProvided(): bool
    {
        return $this->environmentProvided;
    }

    /** @return list<string> */
    #[Assert\Count(max: 50)]
    #[Assert\All([new Assert\Type('string'), new Assert\Length(max: 64)])]
    public function getTags(): array
    {
        return $this->tags;
    }

    /** @param list<string> $tags */
    public function setTags(array $tags): void
    {
        $this->tagsProvided = true;
        $this->tags = $tags;
    }

    #[Ignore]
    public function areTagsProvided(): bool
    {
        return $this->tagsProvided;
    }

    /** @return list<string> */
    #[Assert\Count(max: 100)]
    #[Assert\All([new Assert\Type('string'), new Assert\Ulid()])]
    public function getGroups(): array
    {
        return $this->groups;
    }

    /** @param list<string> $groups */
    public function setGroups(array $groups): void
    {
        $this->groupsProvided = true;
        $this->groups = $groups;
    }

    #[Ignore]
    public function areGroupsProvided(): bool
    {
        return $this->groupsProvided;
    }
}
