<?php

declare(strict_types=1);

namespace App\Identity\Domain;

final class UserCredentials
{
    public const int EMAIL_MAX_LENGTH = 180;
    public const int PASSWORD_MIN_LENGTH = 8;
    public const int PASSWORD_MAX_BYTES = 4096;

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function requireEmail(string $email): string
    {
        $email = self::normalizeEmail($email);
        if (mb_strlen($email) > self::EMAIL_MAX_LENGTH || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidUserCredentials('Укажите корректный адрес электронной почты.');
        }

        return $email;
    }

    public static function requirePassword(string $password): void
    {
        if (mb_strlen($password) < self::PASSWORD_MIN_LENGTH || '' === trim($password)) {
            throw new InvalidUserCredentials('Пароль должен быть не короче '.self::PASSWORD_MIN_LENGTH.' символов.');
        }
        if (strlen($password) > self::PASSWORD_MAX_BYTES || str_contains($password, "\0")) {
            throw new InvalidUserCredentials('Пароль слишком длинный или содержит недопустимые символы.');
        }
    }
}
