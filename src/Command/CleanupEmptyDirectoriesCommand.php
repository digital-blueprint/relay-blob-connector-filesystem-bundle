<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Command;

use Dbp\Relay\BlobConnectorFilesystemBundle\Service\FilesystemService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'dbp:relay:blob-connector-filesystem:cleanup-empty-directories',
    description: 'Recursively removes empty directories below the configured storage root.',
)]
class CleanupEmptyDirectoriesCommand extends Command
{
    public function __construct(
        private readonly FilesystemService $filesystemService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report how many empty directories would be removed without deleting them.');
        $this->setHelp('The storage root and symbolic links are preserved. Run without concurrent writes to avoid upload failures.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        try {
            $removed = $this->filesystemService->removeEmptyDirectories($dryRun);
        } catch (\RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf($dryRun ? 'Would remove %d empty directories.' : 'Removed %d empty directories.', $removed));

        return Command::SUCCESS;
    }
}
