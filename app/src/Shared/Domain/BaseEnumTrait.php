<?php

declare(strict_types=1);

namespace App\Shared\Domain;

trait BaseEnumTrait
{
    /**
     * @return list<string|int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @throws EnumTypeMismatched
     */
    public static function create(mixed $value): self
    {
        $type = self::tryFrom($value);

        if (null === $type) {
            throw new EnumTypeMismatched(self::values());
        }

        return $type;
    }
}
