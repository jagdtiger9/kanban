<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Cli;

use Aljerom\SymfonyBoilerplate\CQRS\CommandBus;
use App\User\Application\Message\Command\DemoteUserFromGlobalAdminCommand;
use App\User\Domain\Exception\UserNotFoundException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:demote-admin', description: 'Demote a global admin')]
final class DemoteAdminCommand extends Command
{
    public function __construct(
        private CommandBus $commandBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email of the user to demote');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $user = $this->commandBus->dispatch(
                new DemoteUserFromGlobalAdminCommand((string) $input->getArgument('email')),
            );
        } catch (UserNotFoundException) {
            $io->error('User not found');

            return self::FAILURE;
        }

        $io->success(sprintf('%s is no longer a global admin (%s)', $user->email(), implode(', ', $user->roles())));

        return self::SUCCESS;
    }
}
