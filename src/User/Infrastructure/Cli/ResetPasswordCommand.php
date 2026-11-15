<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Cli;

use Aljerom\SymfonyBoilerplate\CQRS\CommandBus;
use App\User\Application\Message\Command\ResetUserPasswordCommand;
use App\User\Domain\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:reset-pass', description: 'Reset a user password')]
final class ResetPasswordCommand extends Command
{
    public function __construct(
        private CommandBus $commandBus,
        private UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email of the user');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $user = $this->userRepository->findByEmail(strtolower(trim((string) $input->getArgument('email'))));
        if ($user === null) {
            $io->error('User not found');

            return self::FAILURE;
        }

        $newPassword = $io->askQuestion($this->passwordQuestion('New password'));
        if ($newPassword === null) {
            $io->error('A new password is required in interactive mode');

            return self::FAILURE;
        }

        $confirmation = $io->askQuestion($this->passwordQuestion('Repeat new password'));
        if ($newPassword !== $confirmation) {
            $io->error('Passwords do not match');

            return self::FAILURE;
        }

        $user = $this->commandBus->dispatch(new ResetUserPasswordCommand((string) $user->id(), $newPassword));

        $io->success(sprintf('Password reset for %s', $user->email()));

        return self::SUCCESS;
    }

    private function passwordQuestion(string $label): Question
    {
        $question = new Question($label . ': ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);
        $question->setValidator(static function (mixed $value): string {
            $password = (string) $value;
            $length = mb_strlen($password);
            if ($length < 6 || $length > 100) {
                throw new \InvalidArgumentException('The password must be between 6 and 100 characters.');
            }

            return $password;
        });

        return $question;
    }
}
