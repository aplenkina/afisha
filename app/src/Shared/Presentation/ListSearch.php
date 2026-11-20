<?php

declare(strict_types=1);

namespace App\Shared\Presentation;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final class ListSearch
{
    public const int MAX_QUERY_LENGTH = 200;
    private const string DELIMITER = "\x1F";

    public readonly string $query;
    public private(set) ?string $error = null;
    public private(set) bool $timedOut = false;
    private ?string $expression = null;
    private ?int $deadline = null;

    public function __construct(string $query, public readonly bool $regex, private readonly int $budgetMs = 75)
    {
        $query = $regex ? $query : trim($query);
        $this->query = mb_substr($query, 0, self::MAX_QUERY_LENGTH);

        if (mb_strlen($query) > self::MAX_QUERY_LENGTH) {
            $this->error = 'Введите не больше '.self::MAX_QUERY_LENGTH.' символов.';
        } elseif ($regex && '' !== $query) {
            $expression = self::DELIMITER.'(*NO_JIT)(*LIMIT_MATCH=10000)(*LIMIT_DEPTH=256)'.$query.self::DELIMITER.'u';
            if (str_contains($query, self::DELIMITER) || false === @preg_match($expression, '')) {
                $this->error = 'Некорректное регулярное выражение. Проверьте синтаксис.';
            } else {
                $this->expression = $expression;
            }
        }
    }

    public function shouldStop(): bool
    {
        if (null !== $this->error) {
            return true;
        }

        $now = hrtime(true);
        $this->deadline ??= $now + $this->budgetMs * 1_000_000;

        return $this->timedOut = $now >= $this->deadline;
    }

    public function parameters(): array
    {
        return array_filter(['q' => $this->query, 'regex' => $this->regex ? '1' : ''], static fn (string $value): bool => '' !== $value);
    }

    public function filter(iterable $items, \Closure $fields): array
    {
        $result = [];
        foreach ($items as $item) {
            if ('' === $this->query && null === $this->error) {
                $result[] = $item;
                continue;
            }
            if ($this->shouldStop()) {
                break;
            }
            if ($this->matches($fields($item))) {
                $result[] = $item;
            }
        }

        return $result;
    }

    public function matches(array $fields): bool
    {
        foreach ($fields as $field) {
            if (!is_scalar($field) || '' === (string) $field) {
                continue;
            }
            $field = (string) $field;
            if (null === $this->expression) {
                if (false !== mb_stripos($field, $this->query)) {
                    return true;
                }
                continue;
            }

            $matched = preg_match($this->expression, $field);
            if (false === $matched) {
                $this->error = 'Регулярное выражение слишком сложное. Упростите запрос.';

                return false;
            }
            if (1 === $matched) {
                return true;
            }
        }

        return false;
    }
}
