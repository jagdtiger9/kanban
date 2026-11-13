<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Cli;

use Aljerom\SymfonyBoilerplate\CQRS\CommandBus;
use App\User\Application\Message\Command\PromoteUserToGlobalAdminCommand;
use App\User\Domain\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:promote-admin', description: 'Promote a user to global admin')]
final class PromoteAdminCommand extends Command
{
    public function __construct(
        private CommandBus $commandBus,
        private UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email of the user to promote');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $user = $this->userRepository->findByEmail(strtolower(trim((string) $input->getArgument('email'))));
        if ($user === null) {
            $io->error('User not found');

            return self::FAILURE;
        }

        $user = $this->commandBus->dispatch(new PromoteUserToGlobalAdminCommand((string) $user->id()));

        $io->success(sprintf('%s is now a global admin (%s)', $user->email(), implode(', ', $user->roles())));

        return self::SUCCESS;
    }
}
