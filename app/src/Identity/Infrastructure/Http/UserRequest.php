<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http;

use App\Identity\Domain\InvalidUserCredentials;
use App\Identity\Domain\UserCredentials;
use App\Identity\Domain\UserRole;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class UserRequest
{
    public function __construct(
        #[Assert\Length(max: 10)]
        #[Assert\Regex('/^[1-9][0-9]*$/D')]
        #[Assert\LessThanOrEqual(2_147_483_647)]
        public ?string $id = null,
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: UserCredentials::EMAIL_MAX_LENGTH)]
        public string $email = '',
        #[Assert\NotBlank]
        #[Assert\Choice(callback: [UserRequest::class, 'roleValues'])]
        public string $role = '',
        #[Assert\Choice(choices: ['1'])]
        public ?string $active = null,
        public ?string $password = null,
    ) {
    }

    /** @return list<string> */
    public static function roleValues(): array
    {
        return array_column(UserRole::cases(), 'value');
    }

    #[Assert\Callback]
    public function validatePassword(ExecutionContextInterface $context): void
    {
        $password = $this->password ?? '';
        if ('' === $password && null !== $this->userId()) {
            return;
        }

        try {
            UserCredentials::requirePassword($password);
        } catch (InvalidUserCredentials $error) {
            $context->buildViolation($error->getMessage())->atPath('password')->addViolation();
        }
    }

    public function userId(): ?int
    {
        return null === $this->id || '' === $this->id ? null : (int) $this->id;
    }
}
