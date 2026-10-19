<?php

namespace App\Admin\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class SourceRequest
{
    public function __construct(
        public ?string $id = null,
        #[Assert\NotBlank]
        public string $cityId = '',
        #[Assert\Length(max: 128)]
        public ?string $slug = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 128)]
        public string $name = '',
        #[Assert\NotBlank]
        #[Assert\Length(max: 512)]
        public string $sourceUrl = '',
        #[Assert\Json(message: 'Настройки сборщика должны быть корректным JSON')]
        #[Assert\Length(max: 8000)]
        public ?string $config = null,
    ) {
    }
}
