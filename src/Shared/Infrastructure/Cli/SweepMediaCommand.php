<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Cli;

use App\Board\Domain\Repository\AttachmentRepository;
use App\Shared\Application\Media\MediaSweeper;
use App\User\Domain\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:media:sweep', description: 'Delete unreferenced media files and report missing ones')]
final class SweepMediaCommand extends Command
{
    private const array MANAGED_PREFIXES = ['attachments', 'avatars'];

    public function __construct(
        private MediaSweeper $sweeper,
        private AttachmentRepository $attachments,
        private UserRepository $users,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report without deleting files')
            ->addOption(
                'older-than',
                null,
                InputOption::VALUE_REQUIRED,
                'Only delete unreferenced files older than this relative duration',
                '24 hours',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $olderThan = trim((string) $input->getOption('older-than'));

        try {
            $gracePeriod = \DateInterval::createFromDateString($olderThan);
        } catch (\DateMalformedIntervalStringException) {
            $gracePeriod = false;
        }

        if ($gracePeriod === false || $gracePeriod->invert === 1) {
            $io->error(sprintf(
                'Invalid --older-than duration "%s"; use a relative duration such as "24 hours".',
                $olderThan,
            ));

            return self::FAILURE;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $report = $this->sweeper->sweep(
            [...$this->attachments->allStorageKeys(), ...$this->users->allAvatarStorageKeys()],
            self::MANAGED_PREFIXES,
            new \DateTimeImmutable(),
            $gracePeriod,
            $dryRun,
        );

        $io->text(sprintf(
            'Scanned %d stored file(s) against %d referenced key(s).',
            $report->storedFiles,
            $report->referencedKeys,
        ));

        if ($report->unreferenced !== []) {
            $io->section('Unreferenced files');
            $io->listing($report->unreferenced);
        }

        if ($report->missing !== []) {
            $io->section('Missing referenced files');
            $io->listing($report->missing);
        }

        $io->success($dryRun
            ? sprintf(
                'Dry run: %d unreferenced file(s), %d retained by the %s grace period, none deleted.',
                count($report->unreferenced),
                count($report->retained),
                $olderThan,
            )
            : sprintf(
                'Deleted %d unreferenced file(s); retained %d by the %s grace period.',
                count($report->deleted),
                count($report->retained),
                $olderThan,
            ));

        if ($report->missing !== []) {
            $io->warning(sprintf('%d referenced file(s) are missing from storage.', count($report->missing)));
        }

        return self::SUCCESS;
    }
}
