<?php

declare(strict_types=1);

namespace App\Telegram\Infrastructure\Console;

use App\Shared\Domain\DomainError;
use App\Telegram\Application\PreviewDigest;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:digest:preview', description: 'Показать дайджест событий без отправки в Telegram')]
final class PreviewDigestCommand extends Command
{
    use HandleTrait;

    public function __construct(MessageBusInterface $messageBus, private readonly DigestPresenter $presenter)
    {
        parent::__construct();
        $this->messageBus = $messageBus;
    }

    protected function configure(): void
    {
        $this->addArgument('city', InputArgument::REQUIRED, 'Алиас города')
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Горизонт: 7, 14 или 30 дней', '7')
            ->addOption('category', null, InputOption::VALUE_REQUIRED, 'Алиас категории')
            ->addOption('free', null, InputOption::VALUE_NONE, 'Только бесплатные события');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = filter_var($input->getOption('days'), FILTER_VALIDATE_INT);
        try {
            $digest = $this->handle(new PreviewDigest(
                $input->getArgument('city'),
                $days ?: 0,
                $input->getOption('free'),
                $input->getOption('category'),
            ));
        } catch (HandlerFailedException $failure) {
            $error = $failure->getPrevious();
            if (!$error instanceof DomainError && !$error instanceof \InvalidArgumentException) {
                throw $failure;
            }
            $io->error($error->getMessage());

            return Command::INVALID;
        }
        $messages = $this->presenter->format($digest);
        if ([] === $messages) {
            $io->note('Подходящих сеансов нет.');
        }
        foreach ($messages as $index => $message) {
            $io->section('Сообщение '.($index + 1));
            $output->writeln($message, OutputInterface::OUTPUT_RAW);
        }

        return Command::SUCCESS;
    }
}
