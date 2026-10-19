<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Slug;

use App\Shared\Application\SlugGeneratorInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

final readonly class AsciiSlugGenerator implements SlugGeneratorInterface
{
    private AsciiSlugger $slugger;

    public function __construct()
    {
        $this->slugger = new AsciiSlugger('ru');
    }

    #[\Override]
    public function generate(string $text): string
    {
        return $this->slugger->slug(str_replace(['ь', 'ъ', 'Ь', 'Ъ'], '', $text))->lower()->toString();
    }
}
