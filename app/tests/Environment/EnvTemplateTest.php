<?php

declare(strict_types=1);

namespace App\Tests\Environment;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;

final class EnvTemplateTest extends TestCase
{
    private string $secretsDir;

    protected function setUp(): void
    {
        $this->secretsDir = sys_get_temp_dir().'/env-template-'.bin2hex(random_bytes(4));
        mkdir($this->secretsDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->secretsDir.'/*') ?: []);
        rmdir($this->secretsDir);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function values(): iterable
    {
        yield 'простое значение' => ['plain'];
        yield 'кавычки, доллар и обратный слэш' => ['p@ss"w\'o$rd\\x'];
        yield 'ссылка на переменную' => ['$HOME and ${PATH}'];
        yield 'решётка и пробел' => ['#not a comment'];
    }

    #[DataProvider('values')]
    public function testEnvLocalContainsSecretsExactly(string $value): void
    {
        $env = $this->render('.env.template', $value);

        self::assertSame($value, $env['APP_SECRET']);
        self::assertSame($value, $env['DATABASE_USER']);
        self::assertSame($value, $env['DATABASE_PASSWORD']);
    }

    #[DataProvider('values')]
    public function testEnvTestLocalContainsOnlyDatabaseCredentials(string $value): void
    {
        $env = $this->render('.env.test.template', $value);

        self::assertSame(['DATABASE_USER', 'DATABASE_PASSWORD'], array_keys($env));
        self::assertSame($value, $env['DATABASE_PASSWORD']);
    }

    public function testTrailingNewlineInSecretFileIsIgnored(): void
    {
        $env = $this->render('.env.template', "secret\n");

        self::assertSame('secret', $env['APP_SECRET']);
    }

    /**
     * @return array<string, string>
     */
    private function render(string $template, string $value): array
    {
        foreach (['APP_SECRET', 'POSTGRES_USER', 'POSTGRES_PASSWORD'] as $name) {
            file_put_contents($this->secretsDir.'/'.$name, $value);
        }

        $process = proc_open(
            [PHP_BINARY, '-f', dirname(__DIR__, 2).'/'.$template],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['SECRETS_DIR' => $this->secretsDir],
        );
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        $code = proc_close($process);

        self::assertSame(0, $code, $errors);
        self::assertSame('', $errors);

        return (new Dotenv())->parse($output);
    }
}
