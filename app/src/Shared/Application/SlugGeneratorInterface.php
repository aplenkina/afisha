<?php

declare(strict_types=1);

namespace App\Shared\Application;

interface SlugGeneratorInterface
{
    public function generate(string $text): string;
}
