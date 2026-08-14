<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use DH\AuditorBundle\Controller\ViewerController;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** Prevents the disabled third-party HTML viewer from registering public routes. */
final readonly class DisableAuditorViewerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition(ViewerController::class)) {
            $container->getDefinition(ViewerController::class)->clearTag('routing.controller');
        }
    }
}
