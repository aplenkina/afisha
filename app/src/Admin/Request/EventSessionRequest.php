<?php

declare(strict_types=1);

namespace App\Admin\Request;

use App\Admin\Request\Validation\OptionalDatabaseId;
use App\Catalog\Domain\SessionStatus;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class EventSessionRequest
{
    private const string DATE_FORMAT = 'Y-m-d\TH:i';

    public function __construct(
        #[OptionalDatabaseId]
        public ?string $id = null,
        #[Assert\Length(max: 16)]
        public ?string $startsAt = null,
        #[Assert\Length(max: 16)]
        public ?string $endsAt = null,
        #[OptionalDatabaseId]
        public ?string $venueId = null,
        #[Assert\Url]
        #[Assert\Length(max: 512)]
        public ?string $ticketUrl = null,
        #[Assert\Choice(callback: [self::class, 'statusValues'])]
        public ?string $status = null,
    ) {
    }

    /** @return list<string> */
    public static function statusValues(): array
    {
        return ['', ...array_column(SessionStatus::cases(), 'value')];
    }

    #[Assert\Callback]
    public function validateTimes(ExecutionContextInterface $context): void
    {
        if ($this->isEmptyRow()) {
            return;
        }

        $starts = $this->startTime();
        $ends = $this->endTime();
        if (null === $starts) {
            $context->buildViolation('Укажите корректные дату и время начала.')->atPath('startsAt')->addViolation();
        }
        if ('' !== ($this->endsAt ?? '') && null === $ends) {
            $context->buildViolation('Укажите корректные дату и время окончания.')->atPath('endsAt')->addViolation();
        } elseif (null !== $starts && null !== $ends && $ends < $starts) {
            $context->buildViolation('Окончание сеанса не может быть раньше начала.')->atPath('endsAt')->addViolation();
        }
    }

    public function startTime(): ?\DateTimeImmutable
    {
        return self::parseDate($this->startsAt);
    }

    public function endTime(): ?\DateTimeImmutable
    {
        return self::parseDate($this->endsAt);
    }

    private function isEmptyRow(): bool
    {
        foreach ([$this->id, $this->startsAt, $this->endsAt, $this->venueId, $this->ticketUrl] as $value) {
            if ('' !== ($value ?? '')) {
                return false;
            }
        }

        return \in_array($this->status, [null, '', SessionStatus::Scheduled->value], true);
    }

    private static function parseDate(?string $value): ?\DateTimeImmutable
    {
        if (null === $value || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}$/D', $value) || str_starts_with($value, '0000-')) {
            return null;
        }

        $time = \DateTimeImmutable::createFromFormat('!'.self::DATE_FORMAT, $value, new \DateTimeZone('Europe/Minsk'));

        return false !== $time && $time->format(self::DATE_FORMAT) === $value ? $time : null;
    }
}
