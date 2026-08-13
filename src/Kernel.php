<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * @return list<string> the allowed values for APP_ENV
     */
    final protected function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
