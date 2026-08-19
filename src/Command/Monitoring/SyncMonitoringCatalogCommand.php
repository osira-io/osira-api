<?php

declare(strict_types=1);

namespace App\Command\Monitoring;

use App\Service\Monitoring\MonitoringCatalogSynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'osira:monitoring:sync', description: 'Synchronize Osira monitoring templates and item definitions.')]
final class SyncMonitoringCatalogCommand extends Command
{
    public function __construct(private readonly MonitoringCatalogSynchronizer $synchronizer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->synchronizer->synchronize();
        $output->writeln('<info>Monitoring catalog synchronized.</info>');

        return Command::SUCCESS;
    }
}
