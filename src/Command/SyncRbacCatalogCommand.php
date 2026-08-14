<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Rbac\RbacCatalogSynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'osira:rbac:sync', description: 'Synchronize Osira permissions and system roles.')]
final class SyncRbacCatalogCommand extends Command
{
    public function __construct(private readonly RbacCatalogSynchronizer $synchronizer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->synchronizer->synchronize();
        $output->writeln('<info>RBAC catalog synchronized.</info>');

        return Command::SUCCESS;
    }
}
