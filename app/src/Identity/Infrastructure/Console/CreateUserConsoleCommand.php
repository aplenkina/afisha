<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Console;

use App\Identity\Application\CreateUser\CreateUserCommand;
use App\Identity\Domain\InvalidUserCredentials;
use App\Identity\Domain\UserCredentials;
use App\Identity\Domain\UserRole;
use App\Shared\Domain\DomainError;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:user:create', description: 'Создать пользователя админки')]
final class CreateUserConsoleCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Почта')
            ->addArgument('role', InputArgument::OPTIONAL, 'editor, moderator или admin', 'admin')
            ->addArgument('password', InputArgument::OPTIONAL, 'Пароль (если не указан, будет запрошен)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $role = UserRole::tryFrom('ROLE_'.strtoupper((string) $input->getArgument('role')));
        if (null === $role) {
            $io->error('Роль должна быть editor, moderator или admin');

            return Command::INVALID;
        }

        $password = $input->getArgument('password');
        if (null === $password) {
            $question = (new Question('Пароль: '))->setHidden(true)->setHiddenFallback(false);
            $password = $io->askQuestion($question);
        }

        if (!is_string($password)) {
            $io->error('Пароль должен быть не короче '.UserCredentials::PASSWORD_MIN_LENGTH.' символов');

            return Command::INVALID;
        }

        try {
            $this->messageBus->dispatch(new CreateUserCommand((string) $input->getArgument('email'), $password, $role));
        } catch (HandlerFailedException $exception) {
            $previous = $exception->getPrevious();
            if ($previous instanceof DomainError) {
                $io->error($previous->getMessage());

                return $previous instanceof InvalidUserCredentials ? Command::INVALID : Command::FAILURE;
            }

            throw $exception;
        }

        $io->success('Пользователь создан');

        return Command::SUCCESS;
    }
}
