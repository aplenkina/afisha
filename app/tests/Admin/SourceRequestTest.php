<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Admin\Request\SourceRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class SourceRequestTest extends TestCase
{
    public function testLimitsMatchDatabaseColumns(): void
    {
        self::assertSame(0, $this->violations(new SourceRequest(
            cityId: '5',
            slug: str_repeat('s', 128),
            name: str_repeat('n', 128),
            sourceUrl: 'https://example.by/'.str_repeat('u', 493),
        )));

        self::assertSame(3, $this->violations(new SourceRequest(
            cityId: '5',
            slug: str_repeat('s', 129),
            name: str_repeat('n', 129),
            sourceUrl: 'https://example.by/'.str_repeat('u', 494),
        )));
    }

    public function testRequiredFields(): void
    {
        self::assertSame(3, $this->violations(new SourceRequest()));
    }

    public function testConfigMustBeValidJson(): void
    {
        $valid = new SourceRequest(cityId: '5', name: 'n', sourceUrl: 'https://a.by', config: '{"pages": ["/"]}');
        $broken = new SourceRequest(cityId: '5', name: 'n', sourceUrl: 'https://a.by', config: '{"pages": [');
        $empty = new SourceRequest(cityId: '5', name: 'n', sourceUrl: 'https://a.by', config: null);

        self::assertSame(0, $this->violations($valid));
        self::assertSame(1, $this->violations($broken));
        self::assertSame(0, $this->violations($empty), 'настройки необязательны');
    }

    private function violations(SourceRequest $request): int
    {
        return count(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($request));
    }
}
