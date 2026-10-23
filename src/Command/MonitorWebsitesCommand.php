<?php

namespace App\Command;

use App\Services\WebsiteMonitorRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:monitor:websites', description: 'Check all monitored websites and send configured alerts')]
class MonitorWebsitesCommand extends Command
{
    public function __construct(private readonly WebsiteMonitorRunner $runner)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->runner->run();
        $output->writeln(sprintf('Checked %d monitored website(s).', $count));
        return Command::SUCCESS;
    }
}
