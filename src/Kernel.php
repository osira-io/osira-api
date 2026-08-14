<?php

declare(strict_types=1);

namespace App;

use App\DependencyInjection\DisableAuditorViewerCompilerPass;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new DisableAuditorViewerCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
    }

    /**
     * @return list<string> the allowed values for APP_ENV
     */
    final protected function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
