<?php

declare(strict_types=1);

namespace App\User\Application\Service;

use App\Shared\Application\Internationalization\SupportedLocale;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class CurrentUserManager
{
    public function __construct(private EntityManagerInterface $entityManager, private ClockInterface $clock)
    {
    }

    public function updateLocale(User $user, string $locale): User
    {
        if (!SupportedLocale::isSupported($locale)) {
            throw new UnprocessableEntityHttpException(\sprintf('The locale "%s" is not supported.', $locale));
        }

        $user->updateLocale($locale, $this->clock->now());
        $this->entityManager->flush();

        return $user;
    }
}
