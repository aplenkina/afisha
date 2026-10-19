<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Shared\Domain\BaseEnumTrait;

enum UserRole: string
{
    use BaseEnumTrait;

    case Editor = 'ROLE_EDITOR';
    case Moderator = 'ROLE_MODERATOR';
    case Admin = 'ROLE_ADMIN';

    public function label(): string
    {
        return match ($this) {
            self::Editor => 'Редактор',
            self::Moderator => 'Модератор',
            self::Admin => 'Администратор',
        };
    }
}
