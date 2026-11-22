<?php

declare(strict_types=1);

namespace App\Call\Infrastructure\Cli;

use App\Call\Application\Service\CallCheckStatus;
use App\Call\Application\Service\CallDiagnostics;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The server-side part of the calls check (the /calls-check page shows the same plus the
 * browser tests). Exits non-zero when a check fails, so a deploy script can run it.
 */
#[AsCommand(name: 'app:calls:check', description: 'Check what calls depend on: Centrifugo, the "call" namespace, STUN/TURN')]
final class CallsCheckCommand extends Command
{
    public function __construct(private CallDiagnostics $diagnostics)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Calls check');

        $failed = false;
        foreach ($this->diagnostics->run() as $check) {
            $tag = match ($check->status) {
                CallCheckStatus::Ok => '<info>OK</info>  ',
                CallCheckStatus::Warn => '<comment>WARN</comment>',
                CallCheckStatus::Fail => '<error>FAIL</error>',
            };
            $failed = $failed || $check->status === CallCheckStatus::Fail;
            $io->writeln(sprintf('%s %s', $tag, $check->label));
            $io->writeln('     ' . str_replace("\n", "\n     ", $check->detail));
            if ($check->hint !== null) {
                $io->writeln('     <fg=gray>' . $check->hint . '</>');
            }
        }
        $io->newLine();
        $io->writeln('Browser-side tests (WebSocket, own call channel, STUN/TURN from outside): open /calls-check.');

        if ($failed) {
            $io->error('Some checks failed. See docs/calls-server.md.');

            return self::FAILURE;
        }
        $io->success('All server-side checks passed.');

        return self::SUCCESS;
    }
}
