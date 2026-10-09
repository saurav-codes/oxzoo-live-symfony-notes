<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Writes var/build.json at build time (composer post-install-cmd) for /_zoo/health. */
#[AsCommand(name: 'app:build-info', description: 'Record the build time for /_zoo/health')]
final class BuildInfoCommand extends Command
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $this->projectDir.'/var/build.json';
        @mkdir(\dirname($file), 0755, true);
        file_put_contents($file, json_encode(['built_at' => gmdate('Y-m-d\TH:i:s\Z')]));
        $output->writeln('wrote '.$file);

        return Command::SUCCESS;
    }
}
