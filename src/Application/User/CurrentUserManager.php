<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Entity\User;
use App\Internationalization\SupportedLocale;
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
